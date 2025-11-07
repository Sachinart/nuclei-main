<?php
/**
 * Explore Page - Discover new content
 */

require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/User.php';
require_once 'classes/Feed.php';

$user = new User();
$current_user = $user->getCurrentUser();

if (!$current_user) {
    header('Location: login.php');
    exit();
}

$feed = new Feed();
$posts = $feed->getExploreFeed($current_user['user_id'], 24, 0);
$trending = $feed->getTrendingHashtags(10);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explore - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .explore-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 4px;
            max-width: 975px;
            margin: 0 auto;
        }

        .explore-item {
            position: relative;
            aspect-ratio: 1;
            overflow: hidden;
            cursor: pointer;
        }

        .explore-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .explore-item:hover .explore-overlay {
            opacity: 1;
        }

        .explore-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            color: #fff;
            font-weight: 600;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .trending-tags {
            max-width: 975px;
            margin: 24px auto;
            padding: 20px;
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
        }

        .trending-tags h2 {
            margin-bottom: 16px;
        }

        .tag-list {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .tag-item {
            padding: 8px 16px;
            background: var(--bg-color);
            border-radius: 20px;
            font-weight: 600;
            color: var(--text-color);
        }

        .tag-item:hover {
            background: #e0e0e0;
            text-decoration: none;
        }

        @media (max-width: 768px) {
            .explore-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 2px;
            }
        }
    </style>
</head>
<body>
    <!-- Include same navbar as index.php -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <a href="index.php"><h1><?php echo APP_NAME; ?></h1></a>
            </div>
            <div class="nav-search">
                <input type="text" id="search-input" placeholder="Search..." autocomplete="off">
                <div id="search-results" class="search-dropdown"></div>
            </div>
            <div class="nav-icons">
                <a href="index.php" class="nav-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path></svg>
                </a>
                <a href="messages.php" class="nav-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2C6.477 2 2 6.145 2 11.242c0 2.831 1.347 5.346 3.447 7.033V22l3.69-2.028A11.05 11.05 0 0 0 12 20.484c5.523 0 10-4.145 10-9.242S17.523 2 12 2z"></path></svg>
                </a>
                <a href="reels.php" class="nav-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"></rect><line x1="7" y1="2" x2="7" y2="22"></line><line x1="17" y1="2" x2="17" y2="22"></line><line x1="2" y1="12" x2="22" y2="12"></line></svg>
                </a>
                <a href="explore.php" class="nav-icon active">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"></circle><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76" fill="#fff"></polygon></svg>
                </a>
                <a href="profile.php?username=<?php echo $current_user['username']; ?>" class="nav-icon">
                    <img src="<?php echo UPLOAD_URL . $current_user['profile_picture']; ?>" alt="Profile" class="profile-pic-small">
                </a>
            </div>
        </div>
    </nav>

    <div style="margin-top: 84px;">
        <!-- Trending Hashtags -->
        <?php if (!empty($trending)): ?>
        <div class="trending-tags">
            <h2>Trending</h2>
            <div class="tag-list">
                <?php foreach ($trending as $tag): ?>
                <a href="hashtag.php?tag=<?php echo $tag['hashtag_name']; ?>" class="tag-item">
                    #<?php echo htmlspecialchars($tag['hashtag_name']); ?>
                    <span style="color: #8e8e8e; font-weight: normal; margin-left: 4px;">
                        <?php echo $tag['recent_posts']; ?>
                    </span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Explore Grid -->
        <div class="explore-grid">
            <?php foreach ($posts as $post): ?>
            <a href="post.php?id=<?php echo $post['post_id']; ?>" class="explore-item">
                <img src="<?php echo UPLOAD_URL . $post['thumbnail']; ?>" alt="Post">
                <div class="explore-overlay">
                    <span>❤ <?php echo $post['likes_count']; ?></span>
                    <span>💬 <?php echo $post['comments_count']; ?></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
</body>
</html>
