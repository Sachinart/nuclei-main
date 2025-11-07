<?php
/**
 * Reel Class
 * Handles short-form video content (Reels) functionality
 */

class Reel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Create new reel
     */
    public function createReel($user_id, $video_url, $caption, $audio_name = null, $duration = 0, $thumbnail_url = null, $width = null, $height = null) {
        if ($duration < MIN_REEL_DURATION || $duration > MAX_REEL_DURATION) {
            return ['success' => false, 'message' => 'Invalid reel duration'];
        }

        try {
            $this->db->beginTransaction();

            $sql = "INSERT INTO reels (user_id, video_url, thumbnail_url, caption, audio_name, duration, width, height, created_at)
                    VALUES (:user_id, :video_url, :thumbnail_url, :caption, :audio_name, :duration, :width, :height, NOW())";

            $this->db->query($sql)
                ->bind(':user_id', $user_id)
                ->bind(':video_url', $video_url)
                ->bind(':thumbnail_url', $thumbnail_url)
                ->bind(':caption', $caption)
                ->bind(':audio_name', $audio_name)
                ->bind(':duration', $duration)
                ->bind(':width', $width)
                ->bind(':height', $height);

            if (!$this->db->execute()) {
                throw new Exception('Failed to create reel');
            }

            $reel_id = $this->db->lastInsertId();

            // Extract and save hashtags
            $this->extractAndSaveHashtags($reel_id, $caption);

            $this->db->commit();

            return ['success' => true, 'reel_id' => $reel_id, 'message' => 'Reel created successfully'];

        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get reel by ID
     */
    public function getReelById($reel_id, $current_user_id = null) {
        $sql = "SELECT r.*, u.username, u.full_name, u.profile_picture, u.is_verified,
                (SELECT COUNT(*) FROM reel_likes WHERE reel_id = r.reel_id) as likes_count,
                (SELECT COUNT(*) FROM reel_comments WHERE reel_id = r.reel_id) as comments_count";

        if ($current_user_id) {
            $sql .= ", (SELECT COUNT(*) FROM reel_likes WHERE reel_id = r.reel_id AND user_id = :current_user_id) as user_liked,
                     (SELECT COUNT(*) FROM saved_reels WHERE reel_id = r.reel_id AND user_id = :current_user_id) as user_saved";
        }

        $sql .= " FROM reels r
                 JOIN users u ON r.user_id = u.user_id
                 WHERE r.reel_id = :reel_id AND r.is_archived = 0 AND u.is_active = 1
                 LIMIT 1";

        $query = $this->db->query($sql)->bind(':reel_id', $reel_id);

        if ($current_user_id) {
            $query->bind(':current_user_id', $current_user_id);
        }

        $reel = $query->fetch();

        if ($reel && $current_user_id) {
            $reel['user_liked'] = (bool)$reel['user_liked'];
            $reel['user_saved'] = (bool)$reel['user_saved'];
        }

        return $reel;
    }

    /**
     * Get personalized reels feed
     * Uses algorithm similar to posts but optimized for video content
     */
    public function getReelsFeed($user_id, $limit = 20, $offset = 0) {
        $sql = "SELECT r.*, u.username, u.full_name, u.profile_picture, u.is_verified,
                (SELECT COUNT(*) FROM reel_likes WHERE reel_id = r.reel_id) as likes_count,
                (SELECT COUNT(*) FROM reel_comments WHERE reel_id = r.reel_id) as comments_count,
                (SELECT COUNT(*) FROM reel_likes WHERE reel_id = r.reel_id AND user_id = :user_id) as user_liked,
                (SELECT COUNT(*) FROM saved_reels WHERE reel_id = r.reel_id AND user_id = :user_id) as user_saved,

                -- Engagement score
                ((SELECT COUNT(*) FROM reel_likes WHERE reel_id = r.reel_id) +
                 (SELECT COUNT(*) FROM reel_comments WHERE reel_id = r.reel_id) * 2 +
                 r.views_count / 10) as engagement_score,

                -- User affinity
                (SELECT COUNT(*) FROM follows WHERE follower_id = :user_id AND following_id = r.user_id) as is_following

                FROM reels r
                JOIN users u ON r.user_id = u.user_id

                WHERE r.is_archived = 0
                AND u.is_active = 1
                AND r.user_id NOT IN (SELECT blocked_id FROM blocks WHERE blocker_id = :user_id)
                AND r.user_id NOT IN (SELECT blocker_id FROM blocks WHERE blocked_id = :user_id)

                ORDER BY
                    -- Prioritize reels from followed users
                    (SELECT COUNT(*) FROM follows WHERE follower_id = :user_id AND following_id = r.user_id) DESC,

                    -- Then by engagement and recency
                    (
                        -- Recent engagement weight
                        (CASE
                            WHEN r.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1.0
                            WHEN r.created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY) THEN 0.7
                            WHEN r.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 0.5
                            ELSE 0.3
                        END) * 0.4

                        +

                        -- Engagement rate
                        (LEAST(
                            ((SELECT COUNT(*) FROM reel_likes WHERE reel_id = r.reel_id) +
                             (SELECT COUNT(*) FROM reel_comments WHERE reel_id = r.reel_id) * 2 +
                             r.views_count / 10) / 100,
                            1.0
                        )) * 0.6
                    ) DESC,

                    r.created_at DESC

                LIMIT :limit OFFSET :offset";

        $query = $this->db->query($sql);
        $query->bind(':user_id', $user_id);
        $query->bind(':limit', $limit, PDO::PARAM_INT);
        $query->bind(':offset', $offset, PDO::PARAM_INT);

        $reels = $query->fetchAll();

        foreach ($reels as &$reel) {
            $reel['user_liked'] = (bool)$reel['user_liked'];
            $reel['user_saved'] = (bool)$reel['user_saved'];
            $reel['is_following'] = (bool)$reel['is_following'];
        }

        return $reels;
    }

    /**
     * Get user's reels
     */
    public function getUserReels($user_id, $current_user_id = null, $limit = 12, $offset = 0) {
        $sql = "SELECT r.*, u.username, u.profile_picture,
                (SELECT COUNT(*) FROM reel_likes WHERE reel_id = r.reel_id) as likes_count,
                (SELECT COUNT(*) FROM reel_comments WHERE reel_id = r.reel_id) as comments_count";

        if ($current_user_id) {
            $sql .= ", (SELECT COUNT(*) FROM reel_likes WHERE reel_id = r.reel_id AND user_id = :current_user_id) as user_liked";
        }

        $sql .= " FROM reels r
                 JOIN users u ON r.user_id = u.user_id
                 WHERE r.user_id = :user_id AND r.is_archived = 0 AND u.is_active = 1
                 ORDER BY r.created_at DESC
                 LIMIT :limit OFFSET :offset";

        $query = $this->db->query($sql)
            ->bind(':user_id', $user_id)
            ->bind(':limit', $limit, PDO::PARAM_INT)
            ->bind(':offset', $offset, PDO::PARAM_INT);

        if ($current_user_id) {
            $query->bind(':current_user_id', $current_user_id);
        }

        return $query->fetchAll();
    }

    /**
     * Like a reel
     */
    public function likeReel($reel_id, $user_id) {
        // Check if already liked
        $sql_check = "SELECT like_id FROM reel_likes WHERE reel_id = :reel_id AND user_id = :user_id";
        $this->db->query($sql_check)
            ->bind(':reel_id', $reel_id)
            ->bind(':user_id', $user_id);

        if ($this->db->fetch()) {
            return ['success' => false, 'message' => 'Already liked'];
        }

        $sql = "INSERT INTO reel_likes (reel_id, user_id, created_at) VALUES (:reel_id, :user_id, NOW())";
        $this->db->query($sql)
            ->bind(':reel_id', $reel_id)
            ->bind(':user_id', $user_id);

        if ($this->db->execute()) {
            // Create notification
            $this->createLikeNotification($reel_id, $user_id);

            // Track activity
            $this->trackActivity($user_id, 'reel_like', $reel_id);

            return ['success' => true, 'message' => 'Reel liked'];
        }

        return ['success' => false, 'message' => 'Failed to like reel'];
    }

    /**
     * Unlike a reel
     */
    public function unlikeReel($reel_id, $user_id) {
        $sql = "DELETE FROM reel_likes WHERE reel_id = :reel_id AND user_id = :user_id";
        $this->db->query($sql)
            ->bind(':reel_id', $reel_id)
            ->bind(':user_id', $user_id);

        if ($this->db->execute()) {
            return ['success' => true, 'message' => 'Reel unliked'];
        }

        return ['success' => false, 'message' => 'Failed to unlike reel'];
    }

    /**
     * Add comment to reel
     */
    public function addComment($reel_id, $user_id, $comment_text, $parent_comment_id = null) {
        if (empty(trim($comment_text))) {
            return ['success' => false, 'message' => 'Comment cannot be empty'];
        }

        $sql = "INSERT INTO reel_comments (reel_id, user_id, parent_comment_id, comment_text, created_at)
                VALUES (:reel_id, :user_id, :parent_comment_id, :comment_text, NOW())";

        $this->db->query($sql)
            ->bind(':reel_id', $reel_id)
            ->bind(':user_id', $user_id)
            ->bind(':parent_comment_id', $parent_comment_id)
            ->bind(':comment_text', $comment_text);

        if ($this->db->execute()) {
            $comment_id = $this->db->lastInsertId();

            // Create notification
            $this->createCommentNotification($reel_id, $user_id, $comment_id);

            return ['success' => true, 'comment_id' => $comment_id, 'message' => 'Comment added'];
        }

        return ['success' => false, 'message' => 'Failed to add comment'];
    }

    /**
     * Get reel comments
     */
    public function getReelComments($reel_id, $limit = 20, $offset = 0) {
        $sql = "SELECT c.*, u.username, u.full_name, u.profile_picture, u.is_verified,
                (SELECT COUNT(*) FROM reel_comments WHERE parent_comment_id = c.comment_id) as replies_count
                FROM reel_comments c
                JOIN users u ON c.user_id = u.user_id
                WHERE c.reel_id = :reel_id AND c.parent_comment_id IS NULL
                ORDER BY c.created_at DESC
                LIMIT :limit OFFSET :offset";

        $this->db->query($sql)
            ->bind(':reel_id', $reel_id)
            ->bind(':limit', $limit, PDO::PARAM_INT)
            ->bind(':offset', $offset, PDO::PARAM_INT);

        return $this->db->fetchAll();
    }

    /**
     * Record reel view
     */
    public function recordView($reel_id, $user_id = null, $watch_time = 0) {
        // Insert view record
        $sql = "INSERT INTO reel_views (reel_id, user_id, watch_time, viewed_at)
                VALUES (:reel_id, :user_id, :watch_time, NOW())";

        $this->db->query($sql)
            ->bind(':reel_id', $reel_id)
            ->bind(':user_id', $user_id)
            ->bind(':watch_time', $watch_time)
            ->execute();

        // Increment views count
        $sql_update = "UPDATE reels SET views_count = views_count + 1 WHERE reel_id = :reel_id";
        $this->db->query($sql_update)->bind(':reel_id', $reel_id)->execute();

        // Track activity
        if ($user_id) {
            $this->trackActivity($user_id, 'reel_view', $reel_id, $watch_time);
        }

        return ['success' => true, 'message' => 'View recorded'];
    }

    /**
     * Save reel
     */
    public function saveReel($reel_id, $user_id) {
        $sql = "INSERT INTO saved_reels (user_id, reel_id, created_at)
                VALUES (:user_id, :reel_id, NOW())
                ON DUPLICATE KEY UPDATE created_at = NOW()";

        $this->db->query($sql)
            ->bind(':user_id', $user_id)
            ->bind(':reel_id', $reel_id);

        if ($this->db->execute()) {
            return ['success' => true, 'message' => 'Reel saved'];
        }

        return ['success' => false, 'message' => 'Failed to save reel'];
    }

    /**
     * Unsave reel
     */
    public function unsaveReel($reel_id, $user_id) {
        $sql = "DELETE FROM saved_reels WHERE reel_id = :reel_id AND user_id = :user_id";
        $this->db->query($sql)
            ->bind(':reel_id', $reel_id)
            ->bind(':user_id', $user_id);

        if ($this->db->execute()) {
            return ['success' => true, 'message' => 'Reel unsaved'];
        }

        return ['success' => false, 'message' => 'Failed to unsave reel'];
    }

    /**
     * Delete reel
     */
    public function deleteReel($reel_id, $user_id) {
        $sql = "DELETE FROM reels WHERE reel_id = :reel_id AND user_id = :user_id";
        $this->db->query($sql)
            ->bind(':reel_id', $reel_id)
            ->bind(':user_id', $user_id);

        if ($this->db->execute() && $this->db->rowCount() > 0) {
            return ['success' => true, 'message' => 'Reel deleted'];
        }

        return ['success' => false, 'message' => 'Failed to delete reel'];
    }

    /**
     * Extract and save hashtags from caption
     */
    private function extractAndSaveHashtags($reel_id, $caption) {
        if (preg_match_all('/#(\w+)/', $caption, $matches)) {
            foreach ($matches[1] as $hashtag) {
                $hashtag = strtolower($hashtag);

                // Insert or get hashtag
                $sql_hashtag = "INSERT INTO hashtags (hashtag_name, post_count)
                               VALUES (:hashtag_name, 1)
                               ON DUPLICATE KEY UPDATE post_count = post_count + 1";

                $this->db->query($sql_hashtag)->bind(':hashtag_name', $hashtag)->execute();

                // Get hashtag ID
                $sql_get = "SELECT hashtag_id FROM hashtags WHERE hashtag_name = :hashtag_name";
                $this->db->query($sql_get)->bind(':hashtag_name', $hashtag);
                $hashtag_data = $this->db->fetch();

                if ($hashtag_data) {
                    // Link to reel
                    $sql_link = "INSERT IGNORE INTO reel_hashtags (reel_id, hashtag_id)
                                VALUES (:reel_id, :hashtag_id)";
                    $this->db->query($sql_link)
                        ->bind(':reel_id', $reel_id)
                        ->bind(':hashtag_id', $hashtag_data['hashtag_id'])
                        ->execute();
                }
            }
        }
    }

    /**
     * Create like notification
     */
    private function createLikeNotification($reel_id, $liker_user_id) {
        $sql = "SELECT user_id FROM reels WHERE reel_id = :reel_id";
        $this->db->query($sql)->bind(':reel_id', $reel_id);
        $reel = $this->db->fetch();

        if ($reel && $reel['user_id'] != $liker_user_id) {
            $sql_notif = "INSERT INTO notifications (user_id, actor_id, notification_type, reel_id, created_at)
                         VALUES (:user_id, :actor_id, 'reel_like', :reel_id, NOW())";
            $this->db->query($sql_notif)
                ->bind(':user_id', $reel['user_id'])
                ->bind(':actor_id', $liker_user_id)
                ->bind(':reel_id', $reel_id)
                ->execute();
        }
    }

    /**
     * Create comment notification
     */
    private function createCommentNotification($reel_id, $commenter_user_id, $comment_id) {
        $sql = "SELECT user_id FROM reels WHERE reel_id = :reel_id";
        $this->db->query($sql)->bind(':reel_id', $reel_id);
        $reel = $this->db->fetch();

        if ($reel && $reel['user_id'] != $commenter_user_id) {
            $sql_notif = "INSERT INTO notifications (user_id, actor_id, notification_type, reel_id, comment_id, created_at)
                         VALUES (:user_id, :actor_id, 'reel_comment', :reel_id, :comment_id, NOW())";
            $this->db->query($sql_notif)
                ->bind(':user_id', $reel['user_id'])
                ->bind(':actor_id', $commenter_user_id)
                ->bind(':reel_id', $reel_id)
                ->bind(':comment_id', $comment_id)
                ->execute();
        }
    }

    /**
     * Track user activity
     */
    private function trackActivity($user_id, $activity_type, $reel_id, $interaction_time = 0) {
        $sql = "INSERT INTO user_activity (user_id, activity_type, reel_id, interaction_time, created_at)
                VALUES (:user_id, :activity_type, :reel_id, :interaction_time, NOW())";

        $this->db->query($sql)
            ->bind(':user_id', $user_id)
            ->bind(':activity_type', $activity_type)
            ->bind(':reel_id', $reel_id)
            ->bind(':interaction_time', $interaction_time)
            ->execute();
    }
}
