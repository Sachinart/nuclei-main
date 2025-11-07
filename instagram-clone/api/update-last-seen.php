<?php
/**
 * Update Last Seen API
 */

require_once '../config.php';
require_once '../classes/Database.php';
require_once '../classes/User.php';

header('Content-Type: application/json');

$user = new User();
$current_user = $user->getCurrentUser();

if ($current_user) {
    $user->updateLastSeen($current_user['user_id']);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
