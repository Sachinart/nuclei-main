<?php
/**
 * Post Actions API
 * Handles like, unlike, save, unsave actions
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
$action = $data['action'] ?? '';
$post_id = $data['post_id'] ?? 0;

if (empty($action) || empty($post_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

$post = new Post();
$user_id = $current_user['user_id'];

switch ($action) {
    case 'like':
        $result = $post->likePost($post_id, $user_id);
        break;

    case 'unlike':
        $result = $post->unlikePost($post_id, $user_id);
        break;

    case 'save':
        $result = $post->savePost($post_id, $user_id);
        break;

    case 'unsave':
        $result = $post->unsavePost($post_id, $user_id);
        break;

    default:
        $result = ['success' => false, 'message' => 'Invalid action'];
}

echo json_encode($result);
