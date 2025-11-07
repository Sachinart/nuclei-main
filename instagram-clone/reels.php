<?php
/**
 * Reels Page - Short form video content
 */

require_once 'config.php';
require_once 'classes/Database.php';
require_once 'classes/User.php';
require_once 'classes/Reel.php';

$user = new User();
$current_user = $user->getCurrentUser();

if (!$current_user) {
    header('Location: login.php');
    exit();
}

$reel = new Reel();
$reels = $reel->getReelsFeed($current_user['user_id'], 20, 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reels - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: #000;
            overflow-x: hidden;
        }

        .reels-container {
            max-width: 500px;
            margin: 60px auto 0;
            height: calc(100vh - 60px);
            overflow-y: scroll;
            scroll-snap-type: y mandatory;
            scrollbar-width: none;
        }

        .reels-container::-webkit-scrollbar {
            display: none;
        }

        .reel-item {
            position: relative;
            width: 100%;
            height: calc(100vh - 60px);
            scroll-snap-align: start;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #000;
        }

        .reel-video {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .reel-overlay {
            position: absolute;
            bottom: 80px;
            left: 16px;
            right: 80px;
            color: #fff;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.8);
        }

        .reel-user {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }

        .reel-user img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 2px solid #fff;
        }

        .reel-user .username {
            font-weight: 600;
            color: #fff;
        }

        .reel-caption {
            margin-bottom: 8px;
        }

        .reel-audio {
            font-size: 12px;
            opacity: 0.9;
        }

        .reel-actions {
            position: absolute;
            right: 16px;
            bottom: 80px;
            display: flex;
            flex-direction: column;
            gap: 24px;
            color: #fff;
        }

        .reel-action {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            cursor: pointer;
        }

        .reel-action svg {
            filter: drop-shadow(0 1px 3px rgba(0, 0, 0, 0.8));
        }

        .reel-action.liked svg {
            fill: #ed4956;
            stroke: #ed4956;
        }

        .reel-count {
            font-size: 12px;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .reels-container {
                max-width: 100%;
                margin-top: 0;
                height: 100vh;
            }

            .reel-item {
                height: 100vh;
            }
        }
    </style>
</head>
<body>
    <!-- Simplified navbar for reels -->
    <nav class="navbar" style="background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(10px);">
        <div class="nav-container">
            <div class="nav-logo">
                <a href="index.php" style="color: #fff;"><h1>Reels</h1></a>
            </div>
            <div class="nav-icons">
                <a href="index.php" class="nav-icon" style="color: #fff;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path></svg>
                </a>
            </div>
        </div>
    </nav>

    <div class="reels-container">
        <?php foreach ($reels as $reel_item): ?>
        <div class="reel-item" data-reel-id="<?php echo $reel_item['reel_id']; ?>">
            <video class="reel-video"
                   src="<?php echo UPLOAD_URL . $reel_item['video_url']; ?>"
                   loop
                   playsinline
                   onclick="this.paused ? this.play() : this.pause()"></video>

            <div class="reel-overlay">
                <div class="reel-user">
                    <a href="profile.php?username=<?php echo $reel_item['username']; ?>">
                        <img src="<?php echo UPLOAD_URL . $reel_item['profile_picture']; ?>" alt="<?php echo $reel_item['username']; ?>">
                    </a>
                    <a href="profile.php?username=<?php echo $reel_item['username']; ?>" class="username">
                        <?php echo htmlspecialchars($reel_item['username']); ?>
                        <?php if ($reel_item['is_verified']): ?><span class="verified">✓</span><?php endif; ?>
                    </a>
                    <?php if (!$reel_item['is_following']): ?>
                    <button class="btn-follow" style="color: #fff; font-weight: 600;">Follow</button>
                    <?php endif; ?>
                </div>
                <?php if ($reel_item['caption']): ?>
                <div class="reel-caption"><?php echo nl2br(htmlspecialchars($reel_item['caption'])); ?></div>
                <?php endif; ?>
                <?php if ($reel_item['audio_name']): ?>
                <div class="reel-audio">🎵 <?php echo htmlspecialchars($reel_item['audio_name']); ?></div>
                <?php endif; ?>
            </div>

            <div class="reel-actions">
                <div class="reel-action <?php echo $reel_item['user_liked'] ? 'liked' : ''; ?>"
                     onclick="toggleReelLike(<?php echo $reel_item['reel_id']; ?>, this)">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="<?php echo $reel_item['user_liked'] ? '#ed4956' : 'none'; ?>" stroke="currentColor" stroke-width="2">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                    </svg>
                    <span class="reel-count"><?php echo $reel_item['likes_count']; ?></span>
                </div>

                <div class="reel-action">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                    </svg>
                    <span class="reel-count"><?php echo $reel_item['comments_count']; ?></span>
                </div>

                <div class="reel-action <?php echo $reel_item['user_saved'] ? 'saved' : ''; ?>"
                     onclick="toggleReelSave(<?php echo $reel_item['reel_id']; ?>, this)">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="<?php echo $reel_item['user_saved'] ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2">
                        <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                    </svg>
                </div>

                <div class="reel-action">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="1"></circle>
                        <circle cx="12" cy="5" r="1"></circle>
                        <circle cx="12" cy="19" r="1"></circle>
                    </svg>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
        // Auto-play reels on scroll
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                const video = entry.target.querySelector('video');
                if (entry.isIntersecting) {
                    video.play();
                    recordReelView(entry.target.dataset.reelId);
                } else {
                    video.pause();
                }
            });
        }, { threshold: 0.5 });

        document.querySelectorAll('.reel-item').forEach(item => {
            observer.observe(item);
        });

        function toggleReelLike(reelId, button) {
            const isLiked = button.classList.contains('liked');
            fetch('api/reel-actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: isLiked ? 'unlike' : 'like',
                    reel_id: reelId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    button.classList.toggle('liked');
                    const count = button.querySelector('.reel-count');
                    count.textContent = parseInt(count.textContent) + (isLiked ? -1 : 1);
                }
            });
        }

        function toggleReelSave(reelId, button) {
            const isSaved = button.classList.contains('saved');
            fetch('api/reel-actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: isSaved ? 'unsave' : 'save',
                    reel_id: reelId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    button.classList.toggle('saved');
                }
            });
        }

        function recordReelView(reelId) {
            fetch('api/reel-actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'view',
                    reel_id: reelId
                })
            });
        }
    </script>
</body>
</html>
