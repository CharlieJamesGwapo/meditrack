<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$email    = sanitizeInput($input['email'] ?? '');
$password = $input['password'] ?? '';

if (empty($email) || empty($password)) {
    sendJSON(['success' => false, 'message' => 'Email and password are required'], 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT * FROM users WHERE email = :email AND status = 'active' LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        sendJSON(['success' => false, 'message' => 'Invalid email or password'], 401);
    }

    // Get profile_id and full_name
    $profile_id = null;
    $full_name  = $user['username'];

    if ($user['role'] === 'patient') {
        $ps = $db->prepare("SELECT id, full_name FROM patients WHERE user_id = :uid LIMIT 1");
        $ps->execute([':uid' => $user['id']]);
        $profile = $ps->fetch();
        if ($profile) {
            $profile_id = $profile['id'];
            $full_name  = $profile['full_name'];
        }
    } elseif ($user['role'] === 'doctor') {
        $ps = $db->prepare("SELECT id, full_name FROM doctors WHERE user_id = :uid LIMIT 1");
        $ps->execute([':uid' => $user['id']]);
        $profile = $ps->fetch();
        if ($profile) {
            $profile_id = $profile['id'];
            $full_name  = $profile['full_name'];
        }
    }

    // Rotate the session ID on login to defeat session-fixation attacks. An
    // attacker who managed to plant a known PHPSESSID on the user's browser
    // pre-auth (e.g., via XSS on a sibling app or a same-origin subdomain)
    // would otherwise still hold the post-login session.
    session_regenerate_id(true);

    // Set session
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['email']      = $user['email'];
    $_SESSION['username']   = $user['username'];
    $_SESSION['role']       = $user['role'];
    $_SESSION['full_name']  = $full_name;
    $_SESSION['profile_id'] = $profile_id;

    // Update last_login
    $db->prepare("UPDATE users SET last_login = NOW() WHERE id = :id")
       ->execute([':id' => $user['id']]);

    logActivity($db, $user['id'], $user['username'], $user['role'], 'LOGIN', 'Auth', $user['id'], "User logged in");

    sendJSON([
        'success' => true,
        'message' => 'Login successful',
        'user' => [
            'id'         => $user['id'],
            'email'      => $user['email'],
            'username'   => $user['username'],
            'role'       => $user['role'],
            'full_name'  => $full_name,
            'profile_id' => $profile_id
        ]
    ]);

} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    sendJSON(['success' => false, 'message' => 'Login failed. Please try again.'], 500);
}
