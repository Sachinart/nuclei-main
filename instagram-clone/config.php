<?php
/**
 * Instagram Clone - Configuration File
 * Core configuration settings for the application
 */

// Error reporting - set to 0 in production
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('UTC');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'instagram_clone');
define('DB_CHARSET', 'utf8mb4');

// Application Configuration
define('APP_NAME', 'Instagram Clone');
define('APP_URL', 'http://localhost/instagram-clone');
define('APP_VERSION', '1.0.0');

// Security Configuration
define('SESSION_LIFETIME', 86400); // 24 hours in seconds
define('PASSWORD_MIN_LENGTH', 6);
define('SESSION_NAME', 'INSTAGRAM_SESSION');

// File Upload Configuration
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', APP_URL . '/uploads/');
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_VIDEO_TYPES', ['video/mp4', 'video/quicktime', 'video/x-msvideo']);

// Pagination
define('POSTS_PER_PAGE', 10);
define('REELS_PER_PAGE', 20);
define('COMMENTS_PER_PAGE', 20);
define('MESSAGES_PER_PAGE', 50);

// Story Configuration
define('STORY_DURATION', 86400); // 24 hours

// Reel Configuration
define('MAX_REEL_DURATION', 90); // 90 seconds
define('MIN_REEL_DURATION', 3); // 3 seconds

// Algorithm Weights (for feed ranking)
define('WEIGHT_RECENCY', 0.3);
define('WEIGHT_ENGAGEMENT', 0.3);
define('WEIGHT_RELATIONSHIP', 0.25);
define('WEIGHT_INTEREST', 0.15);

// Create necessary directories
$directories = [
    UPLOAD_DIR,
    UPLOAD_DIR . 'posts/',
    UPLOAD_DIR . 'profiles/',
    UPLOAD_DIR . 'stories/',
    UPLOAD_DIR . 'reels/',
    UPLOAD_DIR . 'messages/',
    UPLOAD_DIR . 'thumbnails/'
];

foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}
