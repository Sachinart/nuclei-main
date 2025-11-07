<?php
/**
 * Notification Class
 * Handles user notifications for various activities
 */

class Notification {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Get user notifications
     */
    public function getUserNotifications($user_id, $limit = 20, $offset = 0) {
        $sql = "SELECT n.*,
                u.username as actor_username,
                u.full_name as actor_name,
                u.profile_picture as actor_picture,
                u.is_verified as actor_verified,

                -- Post info if applicable
                p.caption as post_caption,
                (SELECT media_url FROM post_media WHERE post_id = n.post_id ORDER BY media_order ASC LIMIT 1) as post_thumbnail,

                -- Reel info if applicable
                r.thumbnail_url as reel_thumbnail,

                -- Comment text if applicable
                c.comment_text

                FROM notifications n
                JOIN users u ON n.actor_id = u.user_id
                LEFT JOIN posts p ON n.post_id = p.post_id
                LEFT JOIN reels r ON n.reel_id = r.reel_id
                LEFT JOIN comments c ON n.comment_id = c.comment_id

                WHERE n.user_id = :user_id
                ORDER BY n.created_at DESC
                LIMIT :limit OFFSET :offset";

        $this->db->query($sql)
            ->bind(':user_id', $user_id)
            ->bind(':limit', $limit, PDO::PARAM_INT)
            ->bind(':offset', $offset, PDO::PARAM_INT);

        return $this->db->fetchAll();
    }

    /**
     * Get unread notification count
     */
    public function getUnreadCount($user_id) {
        $sql = "SELECT COUNT(*) as count FROM notifications
                WHERE user_id = :user_id AND is_read = 0";

        $this->db->query($sql)->bind(':user_id', $user_id);
        $result = $this->db->fetch();
        return $result ? $result['count'] : 0;
    }

    /**
     * Mark notification as read
     */
    public function markAsRead($notification_id, $user_id) {
        $sql = "UPDATE notifications SET is_read = 1
                WHERE notification_id = :notification_id AND user_id = :user_id";

        $this->db->query($sql)
            ->bind(':notification_id', $notification_id)
            ->bind(':user_id', $user_id);

        if ($this->db->execute()) {
            return ['success' => true, 'message' => 'Marked as read'];
        }

        return ['success' => false, 'message' => 'Failed to mark as read'];
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead($user_id) {
        $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = :user_id";

        $this->db->query($sql)->bind(':user_id', $user_id);

        if ($this->db->execute()) {
            return ['success' => true, 'message' => 'All marked as read'];
        }

        return ['success' => false, 'message' => 'Failed to mark all as read'];
    }

    /**
     * Delete notification
     */
    public function deleteNotification($notification_id, $user_id) {
        $sql = "DELETE FROM notifications WHERE notification_id = :notification_id AND user_id = :user_id";

        $this->db->query($sql)
            ->bind(':notification_id', $notification_id)
            ->bind(':user_id', $user_id);

        if ($this->db->execute() && $this->db->rowCount() > 0) {
            return ['success' => true, 'message' => 'Notification deleted'];
        }

        return ['success' => false, 'message' => 'Failed to delete notification'];
    }

    /**
     * Clear all notifications
     */
    public function clearAll($user_id) {
        $sql = "DELETE FROM notifications WHERE user_id = :user_id";

        $this->db->query($sql)->bind(':user_id', $user_id);

        if ($this->db->execute()) {
            return ['success' => true, 'message' => 'All notifications cleared'];
        }

        return ['success' => false, 'message' => 'Failed to clear notifications'];
    }
}
