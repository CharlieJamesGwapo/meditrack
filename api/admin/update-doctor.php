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

if (!$doctor_id) {
    sendJSON(['success' => false, 'message' => 'Doctor ID is required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get existing doctor
    $stmt = $db->prepare("SELECT d.*, u.email FROM doctors d JOIN users u ON d.user_id = u.id WHERE d.id = :did LIMIT 1");
    $stmt->execute([':did' => $doctor_id]);
    $doctor = $stmt->fetch();
    if (!$doctor) {
        sendJSON(['success' => false, 'message' => 'Doctor not found'], 404);
    }

    $db->beginTransaction();

    // Update doctor profile fields
    $fields = ['full_name', 'specialization', 'license_number', 'consultation_fee', 'experience_years', 'bio', 'status'];
    $sets = [];
    $params = [':did' => $doctor_id];

    foreach ($fields as $f) {
        if (isset($input[$f])) {
            $sets[] = "$f = :$f";
            if ($f === 'consultation_fee') {
                $params[":$f"] = floatval($input[$f]);
            } elseif ($f === 'experience_years') {
                $params[":$f"] = intval($input[$f]);
            } else {
                $params[":$f"] = sanitizeInput($input[$f]);
            }
        }
    }

    if (!empty($sets)) {
        $db->prepare("UPDATE doctors SET " . implode(', ', $sets) . " WHERE id = :did")
           ->execute($params);
    }

    // Update user status to match doctor status
    if (isset($input['status'])) {
        $db->prepare("UPDATE users SET status = :status WHERE id = :uid")
           ->execute([':status' => sanitizeInput($input['status']), ':uid' => $doctor['user_id']]);
    }

    // Update email if provided
    if (!empty($input['email']) && $input['email'] !== $doctor['email']) {
        $newEmail = sanitizeInput($input['email']);
        // Check uniqueness
        $stmt = $db->prepare("SELECT id FROM users WHERE email = :email AND id != :uid LIMIT 1");
        $stmt->execute([':email' => html_entity_decode($newEmail), ':uid' => $doctor['user_id']]);
        if ($stmt->rowCount() > 0) {
            $db->rollBack();
            sendJSON(['success' => false, 'message' => 'Email already in use by another user'], 409);
        }
        $db->prepare("UPDATE users SET email = :email WHERE id = :uid")
           ->execute([':email' => html_entity_decode($newEmail), ':uid' => $doctor['user_id']]);
    }

    // Update password if provided
    if (!empty($input['password']) && strlen($input['password']) >= 6) {
        $hash = password_hash($input['password'], PASSWORD_HASH_ALGO, ['cost' => PASSWORD_HASH_COST]);
        $db->prepare("UPDATE users SET password_hash = :hash WHERE id = :uid")
           ->execute([':hash' => $hash, ':uid' => $doctor['user_id']]);
    }

    $db->commit();

    $name = $input['full_name'] ?? $doctor['full_name'];
    logActivity($db, getCurrentUserId(), $_SESSION['username'] ?? '', 'admin', 'UPDATE', 'Doctors', $doctor_id, "Updated doctor: $name");

    sendJSON(['success' => true, 'message' => 'Doctor updated successfully']);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log("update-doctor error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Failed to update doctor'], 500);
}
