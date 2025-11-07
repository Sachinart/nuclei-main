<?php
/**
 * Follow API
 * Handles follow/unfollow actions
 */

require_once '../config.php';
require_once '../classes/Database.php';
require_once '../classes/User.php';
require_once '../classes/Follow.php';

header('Content-Type: application/json');

// Check authentication
$user = new User();
$current_user = $user->getCurrentUser();

if (!$current_user) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Get request data
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$user_id = $data['user_id'] ?? 0;

if (empty($action) || empty($user_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

$follow = new Follow();
$current_user_id = $current_user['user_id'];

if ($action === 'follow') {
    $result = $follow->followUser($current_user_id, $user_id);
} elseif ($action === 'unfollow') {
    $result = $follow->unfollowUser($current_user_id, $user_id);
} else {
    $result = ['success' => false, 'message' => 'Invalid action'];
}

echo json_encode($result);
