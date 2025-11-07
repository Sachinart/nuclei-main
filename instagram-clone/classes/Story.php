<?php
/**
 * Story Class
 * Handles 24-hour temporary stories functionality
 */

class Story {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Create new story
     */
    public function createStory($user_id, $media_type, $media_url, $thumbnail_url = null, $duration = 5) {
        $expires_at = date('Y-m-d H:i:s', time() + STORY_DURATION);

        $sql = "INSERT INTO stories (user_id, media_type, media_url, thumbnail_url, duration, expires_at, created_at)
                VALUES (:user_id, :media_type, :media_url, :thumbnail_url, :duration, :expires_at, NOW())";

        $this->db->query($sql)
            ->bind(':user_id', $user_id)
            ->bind(':media_type', $media_type)
            ->bind(':media_url', $media_url)
            ->bind(':thumbnail_url', $thumbnail_url)
            ->bind(':duration', $duration)
            ->bind(':expires_at', $expires_at);

        if ($this->db->execute()) {
            $story_id = $this->db->lastInsertId();
            return ['success' => true, 'story_id' => $story_id, 'message' => 'Story created'];
        }

        return ['success' => false, 'message' => 'Failed to create story'];
    }

    /**
     * Get active stories from followed users
     */
    public function getFollowingStories($user_id) {
        // Delete expired stories first
        $this->deleteExpiredStories();

        $sql = "SELECT u.user_id, u.username, u.full_name, u.profile_picture, u.is_verified,
                (SELECT COUNT(*) FROM stories WHERE user_id = u.user_id AND expires_at > NOW()) as story_count,
                (SELECT COUNT(*) FROM stories s
                 LEFT JOIN story_views sv ON s.story_id = sv.story_id AND sv.user_id = :user_id
                 WHERE s.user_id = u.user_id AND s.expires_at > NOW() AND sv.view_id IS NULL) as unseen_count,
                (SELECT MAX(created_at) FROM stories WHERE user_id = u.user_id AND expires_at > NOW()) as latest_story_time

                FROM users u
                WHERE u.user_id IN (SELECT following_id FROM follows WHERE follower_id = :user_id)
                AND u.is_active = 1
                AND EXISTS (SELECT 1 FROM stories WHERE user_id = u.user_id AND expires_at > NOW())

                ORDER BY unseen_count DESC, latest_story_time DESC";

        $this->db->query($sql)->bind(':user_id', $user_id);
        return $this->db->fetchAll();
    }

    /**
     * Get user's stories
     */
    public function getUserStories($user_id, $viewer_id = null) {
        $sql = "SELECT s.*";

        if ($viewer_id) {
            $sql .= ", (SELECT COUNT(*) FROM story_views WHERE story_id = s.story_id AND user_id = :viewer_id) as viewed";
        }

        $sql .= " FROM stories s
                 WHERE s.user_id = :user_id AND s.expires_at > NOW()
                 ORDER BY s.created_at ASC";

        $query = $this->db->query($sql)->bind(':user_id', $user_id);

        if ($viewer_id) {
            $query->bind(':viewer_id', $viewer_id);
        }

        $stories = $query->fetchAll();

        if ($viewer_id) {
            foreach ($stories as &$story) {
                $story['viewed'] = (bool)$story['viewed'];
            }
        }

        return $stories;
    }

    /**
     * Get story by ID
     */
    public function getStoryById($story_id, $viewer_id = null) {
        $sql = "SELECT s.*, u.username, u.full_name, u.profile_picture, u.is_verified";

        if ($viewer_id) {
            $sql .= ", (SELECT COUNT(*) FROM story_views WHERE story_id = s.story_id AND user_id = :viewer_id) as viewed";
        }

        $sql .= " FROM stories s
                 JOIN users u ON s.user_id = u.user_id
                 WHERE s.story_id = :story_id AND s.expires_at > NOW()
                 LIMIT 1";

        $query = $this->db->query($sql)->bind(':story_id', $story_id);

        if ($viewer_id) {
            $query->bind(':viewer_id', $viewer_id);
        }

        return $query->fetch();
    }

    /**
     * Mark story as viewed
     */
    public function viewStory($story_id, $user_id) {
        // Check if story exists and not expired
        $sql_check = "SELECT story_id FROM stories WHERE story_id = :story_id AND expires_at > NOW()";
        $this->db->query($sql_check)->bind(':story_id', $story_id);

        if (!$this->db->fetch()) {
            return ['success' => false, 'message' => 'Story not found or expired'];
        }

        // Insert view (ignore if already viewed)
        $sql = "INSERT IGNORE INTO story_views (story_id, user_id, viewed_at)
                VALUES (:story_id, :user_id, NOW())";

        $this->db->query($sql)
            ->bind(':story_id', $story_id)
            ->bind(':user_id', $user_id);

        if ($this->db->execute()) {
            // Track activity
            $this->trackActivity($user_id, 'story_view', $story_id);

            return ['success' => true, 'message' => 'Story viewed'];
        }

        return ['success' => false, 'message' => 'Failed to record view'];
    }

    /**
     * Get story viewers
     */
    public function getStoryViewers($story_id, $limit = 50, $offset = 0) {
        $sql = "SELECT u.user_id, u.username, u.full_name, u.profile_picture, u.is_verified,
                sv.viewed_at
                FROM story_views sv
                JOIN users u ON sv.user_id = u.user_id
                WHERE sv.story_id = :story_id AND u.is_active = 1
                ORDER BY sv.viewed_at DESC
                LIMIT :limit OFFSET :offset";

        $this->db->query($sql)
            ->bind(':story_id', $story_id)
            ->bind(':limit', $limit, PDO::PARAM_INT)
            ->bind(':offset', $offset, PDO::PARAM_INT);

        return $this->db->fetchAll();
    }

    /**
     * Get viewers count for a story
     */
    public function getViewersCount($story_id) {
        $sql = "SELECT COUNT(*) as count FROM story_views WHERE story_id = :story_id";
        $this->db->query($sql)->bind(':story_id', $story_id);
        $result = $this->db->fetch();
        return $result ? $result['count'] : 0;
    }

    /**
     * Delete story
     */
    public function deleteStory($story_id, $user_id) {
        $sql = "DELETE FROM stories WHERE story_id = :story_id AND user_id = :user_id";

        $this->db->query($sql)
            ->bind(':story_id', $story_id)
            ->bind(':user_id', $user_id);

        if ($this->db->execute() && $this->db->rowCount() > 0) {
            return ['success' => true, 'message' => 'Story deleted'];
        }

        return ['success' => false, 'message' => 'Failed to delete story'];
    }

    /**
     * Delete expired stories (cleanup)
     */
    public function deleteExpiredStories() {
        $sql = "DELETE FROM stories WHERE expires_at <= NOW()";
        $this->db->query($sql)->execute();
    }

    /**
     * Get all active stories (for explore)
     */
    public function getActiveStories($user_id = null, $limit = 20) {
        $this->deleteExpiredStories();

        $sql = "SELECT u.user_id, u.username, u.full_name, u.profile_picture, u.is_verified,
                (SELECT COUNT(*) FROM stories WHERE user_id = u.user_id AND expires_at > NOW()) as story_count,
                (SELECT media_url FROM stories WHERE user_id = u.user_id AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1) as latest_media

                FROM users u
                WHERE u.is_active = 1
                AND EXISTS (SELECT 1 FROM stories WHERE user_id = u.user_id AND expires_at > NOW())";

        if ($user_id) {
            $sql .= " AND u.user_id != :user_id
                     AND u.user_id NOT IN (SELECT blocked_id FROM blocks WHERE blocker_id = :user_id)
                     AND u.user_id NOT IN (SELECT blocker_id FROM blocks WHERE blocked_id = :user_id)";
        }

        $sql .= " ORDER BY RAND()
                 LIMIT :limit";

        $query = $this->db->query($sql)->bind(':limit', $limit, PDO::PARAM_INT);

        if ($user_id) {
            $query->bind(':user_id', $user_id);
        }

        return $query->fetchAll();
    }

    /**
     * Track user activity
     */
    private function trackActivity($user_id, $activity_type, $story_id) {
        $sql = "INSERT INTO user_activity (user_id, activity_type, story_id, created_at)
                VALUES (:user_id, :activity_type, :story_id, NOW())";

        $this->db->query($sql)
            ->bind(':user_id', $user_id)
            ->bind(':activity_type', $activity_type)
            ->bind(':story_id', $story_id)
            ->execute();
    }
}
