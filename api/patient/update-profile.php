<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('patient')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);

try {
    $database = new Database();
    $db = $database->getConnection();
    $userId = getCurrentUserId();

    $stmt = $db->prepare("SELECT id FROM patients WHERE user_id = :uid LIMIT 1");
    $stmt->execute([':uid' => $userId]);
    $patient = $stmt->fetch();
    if (!$patient) {
        sendJSON(['success' => false, 'message' => 'Patient profile not found'], 404);
    }

    $fields = [
        'full_name', 'date_of_birth', 'gender', 'contact_number',
        'address', 'region', 'city', 'barangay', 'blood_group',
        'allergies', 'emergency_contact_name', 'emergency_contact_number'
    ];

    $params = [':uid' => $userId];
    $sets = [];
    foreach ($fields as $f) {
        if (isset($input[$f])) {
            $sets[] = "$f = :$f";
            $params[":$f"] = sanitizeInput($input[$f]);
        }
    }

    if (empty($sets)) {
        sendJSON(['success' => false, 'message' => 'No fields to update'], 400);
    }

    $db->prepare("UPDATE patients SET " . implode(', ', $sets) . " WHERE user_id = :uid")
       ->execute($params);

    // Update session full_name if provided
    if (!empty($input['full_name'])) {
        $_SESSION['full_name'] = sanitizeInput($input['full_name']);
    }

    logActivity($db, $userId, $_SESSION['username'] ?? '', 'patient', 'UPDATE', 'Patient', $patient['id'], "Patient profile updated");

    sendJSON(['success' => true, 'message' => 'Profile updated successfully']);

} catch (Exception $e) {
    error_log("update-profile error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to update profile'], 500);
}
