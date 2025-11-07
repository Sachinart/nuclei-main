<?php
/**
 * Instagram Clone - Main Entry Point
 */

require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/User.php';
require_once 'classes/Post.php';
require_once 'classes/Feed.php';
require_once 'classes/Follow.php';
require_once 'classes/Story.php';
require_once 'classes/Reel.php';
require_once 'classes/Message.php';
require_once 'classes/Notification.php';
require_once 'classes/FileUpload.php';

// Initialize classes
$user = new User();
$current_user = $user->getCurrentUser();

// Redirect to login if not authenticated
if (!$current_user) {
    header('Location: login.php');
    exit();
}

// Get user statistics
$stats = $user->getUserStats($current_user['user_id']);

// Initialize other classes
$feed = new Feed();
$notification = new Notification();
$message = new Message();

// Get feed posts
$posts = $feed->getPersonalizedFeed($current_user['user_id'], 10, 0);

// Get unread counts
$unread_notifications = $notification->getUnreadCount($current_user['user_id']);
$unread_messages = $message->getUnreadCount($current_user['user_id']);

// Get stories from followed users
$story = new Story();
$stories = $story->getFollowingStories($current_user['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <h1><?php echo APP_NAME; ?></h1>
            </div>
            <div class="nav-search">
                <input type="text" id="search-input" placeholder="Search..." autocomplete="off">
                <div id="search-results" class="search-dropdown"></div>
            </div>
            <div class="nav-icons">
                <a href="index.php" class="nav-icon active">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M9.005 16.545a2.997 2.997 0 0 1 2.997-2.997h0A2.997 2.997 0 0 1 15 16.545V22h7V11.543L12 2 2 11.543V22h7.005z"></path></svg>
                </a>
                <a href="messages.php" class="nav-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2C6.477 2 2 6.145 2 11.242c0 2.831 1.347 5.346 3.447 7.033V22l3.69-2.028A11.05 11.05 0 0 0 12 20.484c5.523 0 10-4.145 10-9.242S17.523 2 12 2z"></path></svg>
                    <?php if ($unread_messages > 0): ?>
                        <span class="badge"><?php echo $unread_messages; ?></span>
                    <?php endif; ?>
                </a>
                <a href="create-post.php" class="nav-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"></rect><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                </a>
                <a href="explore.php" class="nav-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon></svg>
                </a>
                <a href="notifications.php" class="nav-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a7 7 0 0 0-7 7c0 3.866-1.164 6.582-2.525 8.286A1.5 1.5 0 0 0 3.5 20h17a1.5 1.5 0 0 0 1.025-2.714C20.164 15.582 19 12.866 19 9a7 7 0 0 0-7-7z"></path><path d="M9 21a3 3 0 0 0 6 0"></path></svg>
                    <?php if ($unread_notifications > 0): ?>
                        <span class="badge"><?php echo $unread_notifications; ?></span>
                    <?php endif; ?>
                </a>
                <a href="profile.php?username=<?php echo $current_user['username']; ?>" class="nav-icon">
                    <img src="<?php echo UPLOAD_URL . $current_user['profile_picture']; ?>" alt="Profile" class="profile-pic-small">
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-container">
        <!-- Stories -->
        <?php if (!empty($stories)): ?>
        <div class="stories-container">
            <div class="stories-scroll">
                <!-- Current user's story -->
                <div class="story-item" onclick="openStoryUpload()">
                    <div class="story-avatar">
                        <img src="<?php echo UPLOAD_URL . $current_user['profile_picture']; ?>" alt="Your Story">
                    </div>
                    <span class="story-username">Your Story</span>
                </div>

                <!-- Friends' stories -->
                <?php foreach ($stories as $story_user): ?>
                <div class="story-item <?php echo $story_user['unseen_count'] > 0 ? 'unseen' : ''; ?>"
                     onclick="viewStory(<?php echo $story_user['user_id']; ?>)">
                    <div class="story-avatar">
                        <img src="<?php echo UPLOAD_URL . $story_user['profile_picture']; ?>" alt="<?php echo htmlspecialchars($story_user['username']); ?>">
                    </div>
                    <span class="story-username"><?php echo htmlspecialchars($story_user['username']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Feed -->
        <div class="feed-container">
            <?php if (empty($posts)): ?>
            <div class="no-posts">
                <h2>Welcome to <?php echo APP_NAME; ?>!</h2>
                <p>Follow users to see their posts in your feed.</p>
                <a href="explore.php" class="btn-primary">Explore</a>
            </div>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                <div class="post-card" data-post-id="<?php echo $post['post_id']; ?>">
                    <!-- Post Header -->
                    <div class="post-header">
                        <img src="<?php echo UPLOAD_URL . $post['profile_picture']; ?>" alt="<?php echo htmlspecialchars($post['username']); ?>" class="profile-pic">
                        <div class="post-info">
                            <a href="profile.php?username=<?php echo $post['username']; ?>" class="username">
                                <?php echo htmlspecialchars($post['username']); ?>
                                <?php if ($post['is_verified']): ?><span class="verified">✓</span><?php endif; ?>
                            </a>
                            <?php if ($post['location']): ?>
                            <span class="location"><?php echo htmlspecialchars($post['location']); ?></span>
                            <?php endif; ?>
                        </div>
                        <button class="post-options">⋯</button>
                    </div>

                    <!-- Post Media -->
                    <div class="post-media">
                        <?php if (count($post['media']) > 1): ?>
                        <div class="media-slider" data-current="0">
                            <?php foreach ($post['media'] as $index => $media): ?>
                            <div class="media-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                <?php if ($media['media_type'] === 'image'): ?>
                                <img src="<?php echo UPLOAD_URL . $media['media_url']; ?>" alt="Post image">
                                <?php else: ?>
                                <video src="<?php echo UPLOAD_URL . $media['media_url']; ?>" controls></video>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                            <?php if (count($post['media']) > 1): ?>
                            <button class="slider-prev" onclick="slideMedia(<?php echo $post['post_id']; ?>, -1)">‹</button>
                            <button class="slider-next" onclick="slideMedia(<?php echo $post['post_id']; ?>, 1)">›</button>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <?php $media = $post['media'][0]; ?>
                        <?php if ($media['media_type'] === 'image'): ?>
                        <img src="<?php echo UPLOAD_URL . $media['media_url']; ?>" alt="Post image">
                        <?php else: ?>
                        <video src="<?php echo UPLOAD_URL . $media['media_url']; ?>" controls></video>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Post Actions -->
                    <div class="post-actions">
                        <div class="action-buttons">
                            <button class="btn-like <?php echo $post['user_liked'] ? 'liked' : ''; ?>"
                                    onclick="toggleLike(<?php echo $post['post_id']; ?>, this)">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="<?php echo $post['user_liked'] ? '#ed4956' : 'none'; ?>" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                            </button>
                            <button class="btn-comment" onclick="focusComment(<?php echo $post['post_id']; ?>)">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                            </button>
                            <button class="btn-share">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                            </button>
                        </div>
                        <button class="btn-save <?php echo $post['user_saved'] ? 'saved' : ''; ?>"
                                onclick="toggleSave(<?php echo $post['post_id']; ?>, this)">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="<?php echo $post['user_saved'] ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg>
                        </button>
                    </div>

                    <!-- Post Likes -->
                    <div class="post-likes">
                        <span class="likes-count"><?php echo $post['likes_count']; ?></span> likes
                    </div>

                    <!-- Post Caption -->
                    <?php if ($post['caption']): ?>
                    <div class="post-caption">
                        <a href="profile.php?username=<?php echo $post['username']; ?>" class="username">
                            <?php echo htmlspecialchars($post['username']); ?>
                        </a>
                        <span class="caption-text"><?php echo nl2br(htmlspecialchars($post['caption'])); ?></span>
                    </div>
                    <?php endif; ?>

                    <!-- View Comments -->
                    <?php if ($post['comments_count'] > 0): ?>
                    <div class="view-comments">
                        <a href="post.php?id=<?php echo $post['post_id']; ?>">
                            View all <?php echo $post['comments_count']; ?> comments
                        </a>
                    </div>
                    <?php endif; ?>

                    <!-- Post Time -->
                    <div class="post-time">
                        <?php echo timeAgo($post['created_at']); ?>
                    </div>

                    <!-- Add Comment -->
                    <div class="add-comment">
                        <input type="text" placeholder="Add a comment..."
                               onkeypress="handleCommentKeypress(event, <?php echo $post['post_id']; ?>)">
                        <button onclick="postComment(<?php echo $post['post_id']; ?>)">Post</button>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Load More -->
                <div class="load-more">
                    <button onclick="loadMorePosts()">Load More</button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- User Profile -->
            <div class="sidebar-profile">
                <img src="<?php echo UPLOAD_URL . $current_user['profile_picture']; ?>" alt="Profile">
                <div>
                    <a href="profile.php?username=<?php echo $current_user['username']; ?>" class="username">
                        <?php echo htmlspecialchars($current_user['username']); ?>
                    </a>
                    <p class="full-name"><?php echo htmlspecialchars($current_user['full_name']); ?></p>
                </div>
                <a href="logout.php" class="switch-link">Logout</a>
            </div>

            <!-- Suggestions -->
            <div class="suggestions">
                <div class="suggestions-header">
                    <h3>Suggestions For You</h3>
                    <a href="explore.php">See All</a>
                </div>
                <div id="suggestions-list"></div>
            </div>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
</body>
</html>

<?php
/**
 * Helper function to convert timestamp to relative time
 */
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return $diff . ' seconds ago';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' minutes ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' hours ago';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' days ago';
    } else {
        return date('M j, Y', $timestamp);
    }
}
?>
