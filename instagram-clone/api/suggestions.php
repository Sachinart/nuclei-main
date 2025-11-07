<?php
/**
 * Suggestions API
 * Returns suggested users to follow
 */

require_once '../config.php';
require_once '../classes/Database.php';
require_once '../classes/User.php';
require_once '../classes/Feed.php';

header('Content-Type: application/json');

// Check authentication
$user = new User();
$current_user = $user->getCurrentUser();

if (!$current_user) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$feed = new Feed();
$users = $feed->getSuggestedUsers($current_user['user_id'], 5);

echo json_encode([
    'success' => true,
    'users' => $users
]);
