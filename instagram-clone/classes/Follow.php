<?php
/**
 * Follow Class
 * Handles follow/unfollow relationships between users
 */

class Follow {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Follow a user
     */
    public function followUser($follower_id, $following_id) {
        if ($follower_id == $following_id) {
            return ['success' => false, 'message' => 'Cannot follow yourself'];
        }

        // Check if already following
        if ($this->isFollowing($follower_id, $following_id)) {
            return ['success' => false, 'message' => 'Already following this user'];
        }

        $sql = "INSERT INTO follows (follower_id, following_id, created_at)
                VALUES (:follower_id, :following_id, NOW())";

        $this->db->query($sql)
            ->bind(':follower_id', $follower_id)
            ->bind(':following_id', $following_id);

        if ($this->db->execute()) {
            // Create notification
            $this->createFollowNotification($follower_id, $following_id);

            return ['success' => true, 'message' => 'User followed successfully'];
        }

        return ['success' => false, 'message' => 'Failed to follow user'];
    }

    /**
     * Unfollow a user
     */
    public function unfollowUser($follower_id, $following_id) {
        $sql = "DELETE FROM follows WHERE follower_id = :follower_id AND following_id = :following_id";

        $this->db->query($sql)
            ->bind(':follower_id', $follower_id)
            ->bind(':following_id', $following_id);

        if ($this->db->execute() && $this->db->rowCount() > 0) {
            return ['success' => true, 'message' => 'User unfollowed successfully'];
        }

        return ['success' => false, 'message' => 'Failed to unfollow user'];
    }

    /**
     * Check if user is following another user
     */
    public function isFollowing($follower_id, $following_id) {
        $sql = "SELECT follow_id FROM follows
                WHERE follower_id = :follower_id AND following_id = :following_id
                LIMIT 1";

        $this->db->query($sql)
            ->bind(':follower_id', $follower_id)
            ->bind(':following_id', $following_id);

        return $this->db->fetch() !== false;
    }

    /**
     * Get followers of a user
     */
    public function getFollowers($user_id, $current_user_id = null, $limit = 20, $offset = 0) {
        $sql = "SELECT u.user_id, u.username, u.full_name, u.profile_picture, u.is_verified,
                f.created_at as followed_at";

        if ($current_user_id) {
            $sql .= ", (SELECT COUNT(*) FROM follows WHERE follower_id = :current_user_id AND following_id = u.user_id) as is_following";
        }

        $sql .= " FROM follows f
                 JOIN users u ON f.follower_id = u.user_id
                 WHERE f.following_id = :user_id AND u.is_active = 1
                 ORDER BY f.created_at DESC
                 LIMIT :limit OFFSET :offset";

        $query = $this->db->query($sql)
            ->bind(':user_id', $user_id)
            ->bind(':limit', $limit, PDO::PARAM_INT)
            ->bind(':offset', $offset, PDO::PARAM_INT);

        if ($current_user_id) {
            $query->bind(':current_user_id', $current_user_id);
        }

        $followers = $query->fetchAll();

        if ($current_user_id) {
            foreach ($followers as &$follower) {
                $follower['is_following'] = (bool)$follower['is_following'];
            }
        }

        return $followers;
    }

    /**
     * Get users that a user is following
     */
    public function getFollowing($user_id, $current_user_id = null, $limit = 20, $offset = 0) {
        $sql = "SELECT u.user_id, u.username, u.full_name, u.profile_picture, u.is_verified,
                f.created_at as followed_at";

        if ($current_user_id) {
            $sql .= ", (SELECT COUNT(*) FROM follows WHERE follower_id = :current_user_id AND following_id = u.user_id) as is_following";
        }

        $sql .= " FROM follows f
                 JOIN users u ON f.following_id = u.user_id
                 WHERE f.follower_id = :user_id AND u.is_active = 1
                 ORDER BY f.created_at DESC
                 LIMIT :limit OFFSET :offset";

        $query = $this->db->query($sql)
            ->bind(':user_id', $user_id)
            ->bind(':limit', $limit, PDO::PARAM_INT)
            ->bind(':offset', $offset, PDO::PARAM_INT);

        if ($current_user_id) {
            $query->bind(':current_user_id', $current_user_id);
        }

        $following = $query->fetchAll();

        if ($current_user_id) {
            foreach ($following as &$user) {
                $user['is_following'] = (bool)$user['is_following'];
            }
        }

        return $following;
    }

    /**
     * Get follower count
     */
    public function getFollowerCount($user_id) {
        $sql = "SELECT COUNT(*) as count FROM follows WHERE following_id = :user_id";
        $this->db->query($sql)->bind(':user_id', $user_id);
        $result = $this->db->fetch();
        return $result ? $result['count'] : 0;
    }

    /**
     * Get following count
     */
    public function getFollowingCount($user_id) {
        $sql = "SELECT COUNT(*) as count FROM follows WHERE follower_id = :user_id";
        $this->db->query($sql)->bind(':user_id', $user_id);
        $result = $this->db->fetch();
        return $result ? $result['count'] : 0;
    }

    /**
     * Get mutual followers
     */
    public function getMutualFollowers($user_id, $other_user_id, $limit = 10) {
        $sql = "SELECT DISTINCT u.user_id, u.username, u.full_name, u.profile_picture, u.is_verified
                FROM users u
                JOIN follows f1 ON u.user_id = f1.follower_id
                JOIN follows f2 ON u.user_id = f2.follower_id
                WHERE f1.following_id = :user_id
                AND f2.following_id = :other_user_id
                AND u.is_active = 1
                LIMIT :limit";

        $this->db->query($sql)
            ->bind(':user_id', $user_id)
            ->bind(':other_user_id', $other_user_id)
            ->bind(':limit', $limit, PDO::PARAM_INT);

        return $this->db->fetchAll();
    }

    /**
     * Remove follower
     */
    public function removeFollower($user_id, $follower_id) {
        $sql = "DELETE FROM follows WHERE follower_id = :follower_id AND following_id = :user_id";

        $this->db->query($sql)
            ->bind(':follower_id', $follower_id)
            ->bind(':user_id', $user_id);

        if ($this->db->execute() && $this->db->rowCount() > 0) {
            return ['success' => true, 'message' => 'Follower removed'];
        }

        return ['success' => false, 'message' => 'Failed to remove follower'];
    }

    /**
     * Create follow notification
     */
    private function createFollowNotification($follower_id, $following_id) {
        $sql = "INSERT INTO notifications (user_id, actor_id, notification_type, created_at)
                VALUES (:user_id, :actor_id, 'follow', NOW())";

        $this->db->query($sql)
            ->bind(':user_id', $following_id)
            ->bind(':actor_id', $follower_id)
            ->execute();
    }
}
