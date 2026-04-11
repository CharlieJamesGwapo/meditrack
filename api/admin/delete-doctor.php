<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('admin')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);
$doctor_id = intval($input['doctor_id'] ?? 0);
$action    = sanitizeInput($input['action'] ?? 'deactivate'); // 'deactivate' or 'delete'

if (!$doctor_id) {
    sendJSON(['success' => false, 'message' => 'Doctor ID is required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT d.id, d.full_name, d.user_id FROM doctors d WHERE d.id = :did LIMIT 1");
    $stmt->execute([':did' => $doctor_id]);
    $doctor = $stmt->fetch();
    if (!$doctor) {
        sendJSON(['success' => false, 'message' => 'Doctor not found'], 404);
    }

    if ($action === 'delete') {
        // Check if doctor has any appointments
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM appointments WHERE doctor_id = :did AND status NOT IN ('cancelled','no_show')");
        $stmt->execute([':did' => $doctor_id]);
        $apptCount = (int) $stmt->fetch()['cnt'];

        if ($apptCount > 0) {
            sendJSON(['success' => false, 'message' => "Cannot delete doctor with $apptCount active appointment(s). Deactivate instead."], 400);
        }

        $db->beginTransaction();
        $db->prepare("DELETE FROM doctor_schedules WHERE doctor_id = :did")->execute([':did' => $doctor_id]);
        $db->prepare("DELETE FROM doctors WHERE id = :did")->execute([':did' => $doctor_id]);
        $db->prepare("DELETE FROM users WHERE id = :uid")->execute([':uid' => $doctor['user_id']]);
        $db->commit();

        logActivity($db, getCurrentUserId(), $_SESSION['username'] ?? '', 'admin', 'DELETE', 'Doctors', $doctor_id, "Deleted doctor: {$doctor['full_name']}");
        sendJSON(['success' => true, 'message' => 'Doctor deleted permanently']);

    } else {
        // Deactivate
        $db->prepare("UPDATE doctors SET status = 'inactive' WHERE id = :did")->execute([':did' => $doctor_id]);
        $db->prepare("UPDATE users SET status = 'inactive' WHERE id = :uid")->execute([':uid' => $doctor['user_id']]);

        logActivity($db, getCurrentUserId(), $_SESSION['username'] ?? '', 'admin', 'UPDATE', 'Doctors', $doctor_id, "Deactivated doctor: {$doctor['full_name']}");
        sendJSON(['success' => true, 'message' => 'Doctor deactivated successfully']);
    }

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log("delete-doctor error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to process request'], 500);
}
