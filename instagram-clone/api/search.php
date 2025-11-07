<?php
/**
 * Search API
 * Handles user search
 */

require_once '../config.php';
require_once '../classes/Database.php';
require_once '../classes/User.php';

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
$query = $data['query'] ?? '';

if (empty($query)) {
    echo json_encode(['success' => false, 'message' => 'Missing query']);
    exit();
}

$users = $user->searchUsers($query, 20);

echo json_encode([
    'success' => true,
    'users' => $users
]);
