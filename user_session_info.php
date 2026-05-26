<?php
require_once 'auth_check.php';

header('Content-Type: application/json');

$user_info = get_user_info();
if (empty($user_info['id'])) {
    echo json_encode([ 'success' => false, 'message' => 'Not logged in' ]);
    exit;
}

$response = [
    'success' => true,
    'id' => $user_info['id'],
    'name' => $user_info['name'] ?? '',
    'email' => $user_info['email'] ?? '',
    'role' => get_user_role_label(),
];

echo json_encode($response);
