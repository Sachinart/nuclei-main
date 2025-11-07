<?php
/**
 * Comment API
 * Handles adding comments to posts
 */

require_once '../config.php';
require_once '../classes/Database.php';
require_once '../classes/User.php';
require_once '../classes/Post.php';

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
$post_id = $data['post_id'] ?? 0;
$comment_text = $data['comment_text'] ?? '';
$parent_comment_id = $data['parent_comment_id'] ?? null;

if (empty($post_id) || empty($comment_text)) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

$post = new Post();
$result = $post->addComment($post_id, $current_user['user_id'], $comment_text, $parent_comment_id);

echo json_encode($result);
