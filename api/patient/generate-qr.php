<?php
// CRITICAL: Suppress ALL errors to prevent JSON corruption
error_reporting(0);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Start output buffering FIRST
ob_start();

// Start session
session_start();

// Include files
require_once '../../config/database.php';
require_once '../../utils/QRCodeGenerator.php';

// Clear ANY previous output (including from config.php)
ob_end_clean();
ob_start();

// NOW set headers
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// Define sendJSON function
function sendJSON($data, $statusCode = 200) {
    ob_clean();
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    ob_end_flush();
    exit();
}

// Define logAudit function
function logAudit($db, $userId, $action, $targetTable = null, $targetId = null, $description = null) {
    try {
        $query = "INSERT INTO audit_logs (user_id, action, target_table, target_id, description, ip_address, user_agent) 
                  VALUES (:user_id, :action, :target_table, :target_id, :description, :ip_address, :user_agent)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':user_id' => $userId,
            ':action' => $action,
            ':target_table' => $targetTable,
            ':target_id' => $targetId,
            ':description' => $description,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Audit log error: " . $e->getMessage());
    }
}

// Define constants
if (!defined('SECRET_KEY')) define('SECRET_KEY', 'meditrack_secret_2024');
if (!defined('QR_EXPIRY_HOURS')) define('QR_EXPIRY_HOURS', 24);
if (!defined('QR_SIZE')) define('QR_SIZE', 300);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    sendJSON(['success' => false, 'message' => 'Not authenticated'], 401);
}

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['appointment_id'])) {
    sendJSON(['success' => false, 'message' => 'Appointment ID is required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $userId = $_SESSION['user_id'];
    $appointmentId = $data['appointment_id'];

    // Get patient ID
    $patientQuery = "SELECT id FROM patients WHERE user_id = :user_id";
    $patientStmt = $db->prepare($patientQuery);
    $patientStmt->bindParam(':user_id', $userId);
    $patientStmt->execute();
    $patient = $patientStmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        sendJSON(['success' => false, 'message' => 'Patient profile not found'], 404);
    }

    // Verify appointment belongs to this patient
    $appointmentQuery = "SELECT a.*, d.full_name as doctor_name, d.specialization, d.department 
                         FROM appointments a
                         JOIN doctors d ON a.doctor_id = d.id
                         WHERE a.id = :appointment_id AND a.patient_id = :patient_id";
    $appointmentStmt = $db->prepare($appointmentQuery);
    $appointmentStmt->execute([
        ':appointment_id' => $appointmentId,
        ':patient_id' => $patient['id']
    ]);
    
    $appointment = $appointmentStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) {
        sendJSON(['success' => false, 'message' => 'Appointment not found or does not belong to you'], 404);
    }

    // Check if appointment is scheduled (not cancelled or completed)
    if ($appointment['status'] === 'cancelled') {
        sendJSON(['success' => false, 'message' => 'Cannot generate QR code for cancelled appointment'], 400);
    }

    if ($appointment['status'] === 'completed') {
        sendJSON(['success' => false, 'message' => 'This appointment is already completed'], 400);
    }

    // Check if QR code already exists and is still valid
    $existingQRQuery = "SELECT token_hash, qr_payload, expires_at 
                        FROM qr_tokens 
                        WHERE appointment_id = :appointment_id 
                        AND is_used = 0 
                        AND expires_at > NOW()
                        ORDER BY created_at DESC 
                        LIMIT 1";
    $existingQRStmt = $db->prepare($existingQRQuery);
    $existingQRStmt->execute([':appointment_id' => $appointmentId]);
    
    if ($existingQRStmt->rowCount() > 0) {
        $existingQR = $existingQRStmt->fetch(PDO::FETCH_ASSOC);
        
        // Regenerate QR image from existing token
        $qrData = base64_encode($existingQR['token_hash']);
        
        try {
            $qrCode = new Endroid\QrCode\QrCode($qrData);
            $qrCode->setSize(QR_SIZE);
            $qrCode->setMargin(10);
            $qrCode->setEncoding(new Endroid\QrCode\Encoding\Encoding('UTF-8'));
            $qrCode->setErrorCorrectionLevel(Endroid\QrCode\ErrorCorrectionLevel::High);
            $qrCode->setRoundBlockSizeMode(Endroid\QrCode\RoundBlockSizeMode::Margin);
            
            $writer = new Endroid\QrCode\Writer\PngWriter();
            $result = $writer->write($qrCode);
            $qrImageData = $result->getString();
            $qrImageBase64 = base64_encode($qrImageData);
            
            sendJSON([
                'success' => true,
                'message' => 'QR code retrieved successfully',
                'qr_code' => [
                    'token_hash' => $existingQR['token_hash'],
                    'qr_image' => 'data:image/png;base64,' . $qrImageBase64,
                    'expires_at' => $existingQR['expires_at'],
                    'appointment' => [
                        'appointment_number' => $appointment['appointment_number'],
                        'doctor_name' => $appointment['doctor_name'],
                        'specialization' => $appointment['specialization'],
                        'department' => $appointment['department'],
                        'date' => date('F j, Y', strtotime($appointment['appointment_date'])),
                        'time' => date('g:i A', strtotime($appointment['appointment_time'])),
                        'status' => $appointment['status']
                    ]
                ]
            ]);
        } catch (Exception $e) {
            error_log("QR generation error: " . $e->getMessage());
        }
    }

    // Generate new QR code
    $qrGenerator = new QRCodeGenerator($db);
    $qrResult = $qrGenerator->generateQRCode($appointmentId);

    // Get patient email for notification
    $patientEmailQuery = "SELECT u.email, u.first_name, u.last_name 
                          FROM users u 
                          JOIN patients p ON u.id = p.user_id 
                          WHERE p.id = :patient_id";
    $patientEmailStmt = $db->prepare($patientEmailQuery);
    $patientEmailStmt->execute([':patient_id' => $patient['id']]);
    $patientInfo = $patientEmailStmt->fetch(PDO::FETCH_ASSOC);
    
    // Send email notification
    $emailSent = false;
    if ($patientInfo && !empty($patientInfo['email'])) {
        require_once '../../utils/EmailSender.php';
        $emailSender = new EmailSender();
        
        $patientName = $patientInfo['first_name'] . ' ' . $patientInfo['last_name'];
        $appointmentData = [
            'appointment_number' => $appointment['appointment_number'],
            'doctor_name' => $appointment['doctor_name'],
            'department' => $appointment['department'],
            'date' => date('F j, Y', strtotime($appointment['appointment_date'])),
            'time' => date('g:i A', strtotime($appointment['appointment_time']))
        ];
        
        $emailResult = $emailSender->sendQRCodeEmail(
            $patientInfo['email'],
            $patientName,
            $appointmentData,
            $qrResult['qr_image']
        );
        
        $emailSent = $emailResult['success'];
    }

    // Log audit
    logAudit($db, $userId, 'generate_qr', 'qr_tokens', $appointmentId, 'QR code generated for appointment');

    sendJSON([
        'success' => true,
        'message' => 'QR code generated successfully' . ($emailSent ? ' and sent to your email' : ''),
        'email_sent' => $emailSent,
        'qr_code' => [
            'token_hash' => $qrResult['token_hash'],
            'qr_image' => $qrResult['qr_image'],
            'expires_at' => $qrResult['expires_at'],
            'appointment' => [
                'appointment_number' => $appointment['appointment_number'],
                'doctor_name' => $appointment['doctor_name'],
                'specialization' => $appointment['specialization'],
                'department' => $appointment['department'],
                'date' => date('F j, Y', strtotime($appointment['appointment_date'])),
                'time' => date('g:i A', strtotime($appointment['appointment_time'])),
                'status' => $appointment['status']
            ]
        ]
    ]);

} catch (Exception $e) {
    error_log("QR generation error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
