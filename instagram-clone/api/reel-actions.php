<?php
/**
 * Reel Actions API
 * Handles like, unlike, save, unsave, view actions on reels
 */

require_once '../config.php';
require_once '../classes/Database.php';
require_once '../classes/User.php';
require_once '../classes/Reel.php';

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
$reel_id = $data['reel_id'] ?? 0;

if (empty($action) || empty($reel_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

$reel = new Reel();
$user_id = $current_user['user_id'];

switch ($action) {
    case 'like':
        $result = $reel->likeReel($reel_id, $user_id);
        break;

    case 'unlike':
        $result = $reel->unlikeReel($reel_id, $user_id);
        break;

    case 'save':
        $result = $reel->saveReel($reel_id, $user_id);
        break;

    case 'unsave':
        $result = $reel->unsaveReel($reel_id, $user_id);
        break;

    case 'view':
        $watch_time = $data['watch_time'] ?? 0;
        $result = $reel->recordView($reel_id, $user_id, $watch_time);
        break;

    default:
        $result = ['success' => false, 'message' => 'Invalid action'];
}

echo json_encode($result);
