<?php
/**
 * Feed API
 * Returns personalized feed posts
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

$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

$feed = new Feed();
$posts = $feed->getPersonalizedFeed($current_user['user_id'], $limit, $offset);

echo json_encode([
    'success' => true,
    'posts' => $posts
]);
