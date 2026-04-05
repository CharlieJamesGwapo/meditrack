<?php
require_once __DIR__ . '/../../config/config.php';

if (isLoggedIn()) {
    sendJSON([
        'success'    => true,
        'logged_in'  => true,
        'user'       => [
            'id'         => $_SESSION['user_id'],
            'username'   => $_SESSION['username'],
            'email'      => $_SESSION['email'] ?? '',
            'role'       => $_SESSION['role'],
            'full_name'  => $_SESSION['full_name'] ?? '',
            'profile_id' => $_SESSION['profile_id'] ?? null
        ]
    ]);
} else {
    sendJSON(['success' => true, 'logged_in' => false]);
}
