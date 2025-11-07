<?php
/**
 * Message Class
 * Handles direct messaging and chat functionality
 */

class Message {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Create or get existing conversation between users
     */
    public function getOrCreateConversation($user_id, $other_user_id) {
        // Check if conversation exists
        $sql = "SELECT c.conversation_id
                FROM conversations c
                JOIN conversation_participants cp1 ON c.conversation_id = cp1.conversation_id
                JOIN conversation_participants cp2 ON c.conversation_id = cp2.conversation_id
                WHERE c.is_group = 0
                AND cp1.user_id = :user_id
                AND cp2.user_id = :other_user_id
                AND cp1.left_at IS NULL
                AND cp2.left_at IS NULL
                LIMIT 1";

        $this->db->query($sql)
            ->bind(':user_id', $user_id)
            ->bind(':other_user_id', $other_user_id);

        $existing = $this->db->fetch();

        if ($existing) {
            return ['success' => true, 'conversation_id' => $existing['conversation_id']];
        }

        // Create new conversation
        try {
            $this->db->beginTransaction();

            $sql_conv = "INSERT INTO conversations (is_group, created_at) VALUES (0, NOW())";
            $this->db->query($sql_conv)->execute();
            $conversation_id = $this->db->lastInsertId();

            // Add participants
            $sql_part = "INSERT INTO conversation_participants (conversation_id, user_id, joined_at)
                        VALUES (:conversation_id, :user_id, NOW())";

            $this->db->query($sql_part)
                ->bind(':conversation_id', $conversation_id)
                ->bind(':user_id', $user_id)
                ->execute();

            $this->db->query($sql_part)
                ->bind(':conversation_id', $conversation_id)
                ->bind(':user_id', $other_user_id)
                ->execute();

            $this->db->commit();

            return ['success' => true, 'conversation_id' => $conversation_id];

        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Create group conversation
     */
    public function createGroupConversation($creator_id, $user_ids, $group_name, $group_image = null) {
        try {
            $this->db->beginTransaction();

            $sql = "INSERT INTO conversations (is_group, group_name, group_image, created_by, created_at)
                    VALUES (1, :group_name, :group_image, :creator_id, NOW())";

            $this->db->query($sql)
                ->bind(':group_name', $group_name)
                ->bind(':group_image', $group_image)
                ->bind(':creator_id', $creator_id)
                ->execute();

            $conversation_id = $this->db->lastInsertId();

            // Add creator as admin
            $sql_part = "INSERT INTO conversation_participants (conversation_id, user_id, is_admin, joined_at)
                        VALUES (:conversation_id, :user_id, :is_admin, NOW())";

            $this->db->query($sql_part)
                ->bind(':conversation_id', $conversation_id)
                ->bind(':user_id', $creator_id)
                ->bind(':is_admin', 1)
                ->execute();

            // Add other participants
            foreach ($user_ids as $user_id) {
                if ($user_id != $creator_id) {
                    $this->db->query($sql_part)
                        ->bind(':conversation_id', $conversation_id)
                        ->bind(':user_id', $user_id)
                        ->bind(':is_admin', 0)
                        ->execute();
                }
            }

            $this->db->commit();

            return ['success' => true, 'conversation_id' => $conversation_id];

        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Send message
     */
    public function sendMessage($conversation_id, $sender_id, $message_text = null, $message_type = 'text', $media_url = null, $post_id = null, $reel_id = null, $story_id = null) {
        // Verify sender is participant
        if (!$this->isParticipant($conversation_id, $sender_id)) {
            return ['success' => false, 'message' => 'Not a participant'];
        }

        $sql = "INSERT INTO messages (conversation_id, sender_id, message_type, message_text, media_url, post_id, reel_id, story_id, created_at)
                VALUES (:conversation_id, :sender_id, :message_type, :message_text, :media_url, :post_id, :reel_id, :story_id, NOW())";

        $this->db->query($sql)
            ->bind(':conversation_id', $conversation_id)
            ->bind(':sender_id', $sender_id)
            ->bind(':message_type', $message_type)
            ->bind(':message_text', $message_text)
            ->bind(':media_url', $media_url)
            ->bind(':post_id', $post_id)
            ->bind(':reel_id', $reel_id)
            ->bind(':story_id', $story_id);

        if ($this->db->execute()) {
            $message_id = $this->db->lastInsertId();

            // Update conversation timestamp
            $this->updateConversationTimestamp($conversation_id);

            // Create notifications for other participants
            $this->createMessageNotifications($conversation_id, $sender_id, $message_id);

            return ['success' => true, 'message_id' => $message_id];
        }

        return ['success' => false, 'message' => 'Failed to send message'];
    }

    /**
     * Get conversation messages
     */
    public function getMessages($conversation_id, $user_id, $limit = 50, $offset = 0) {
        // Verify user is participant
        if (!$this->isParticipant($conversation_id, $user_id)) {
            return [];
        }

        $sql = "SELECT m.*, u.username, u.full_name, u.profile_picture,
                (SELECT reaction_type FROM message_reactions WHERE message_id = m.message_id AND user_id = :user_id) as user_reaction
                FROM messages m
                JOIN users u ON m.sender_id = u.user_id
                WHERE m.conversation_id = :conversation_id AND m.is_deleted = 0
                ORDER BY m.created_at DESC
                LIMIT :limit OFFSET :offset";

        $this->db->query($sql)
            ->bind(':conversation_id', $conversation_id)
            ->bind(':user_id', $user_id)
            ->bind(':limit', $limit, PDO::PARAM_INT)
            ->bind(':offset', $offset, PDO::PARAM_INT);

        return array_reverse($this->db->fetchAll()); // Reverse to show oldest first
    }

    /**
     * Get user's conversations
     */
    public function getUserConversations($user_id, $limit = 20) {
        $sql = "SELECT c.*,
                cp.last_read_message_id,
                (SELECT COUNT(*) FROM messages
                 WHERE conversation_id = c.conversation_id
                 AND message_id > COALESCE(cp.last_read_message_id, 0)
                 AND sender_id != :user_id) as unread_count,

                (SELECT m.message_text FROM messages m
                 WHERE m.conversation_id = c.conversation_id AND m.is_deleted = 0
                 ORDER BY m.created_at DESC LIMIT 1) as last_message,

                (SELECT m.created_at FROM messages m
                 WHERE m.conversation_id = c.conversation_id AND m.is_deleted = 0
                 ORDER BY m.created_at DESC LIMIT 1) as last_message_time,

                (SELECT m.sender_id FROM messages m
                 WHERE m.conversation_id = c.conversation_id AND m.is_deleted = 0
                 ORDER BY m.created_at DESC LIMIT 1) as last_sender_id

                FROM conversations c
                JOIN conversation_participants cp ON c.conversation_id = cp.conversation_id
                WHERE cp.user_id = :user_id AND cp.left_at IS NULL
                ORDER BY last_message_time DESC, c.updated_at DESC
                LIMIT :limit";

        $this->db->query($sql)
            ->bind(':user_id', $user_id)
            ->bind(':limit', $limit, PDO::PARAM_INT);

        $conversations = $this->db->fetchAll();

        // Get other participant info for non-group chats
        foreach ($conversations as &$conv) {
            if (!$conv['is_group']) {
                $other_user = $this->getOtherParticipant($conv['conversation_id'], $user_id);
                if ($other_user) {
                    $conv['other_user'] = $other_user;
                }
            } else {
                $conv['participants'] = $this->getParticipants($conv['conversation_id']);
            }
        }

        return $conversations;
    }

    /**
     * Get other participant in one-on-one conversation
     */
    private function getOtherParticipant($conversation_id, $user_id) {
        $sql = "SELECT u.user_id, u.username, u.full_name, u.profile_picture, u.is_verified, u.last_seen
                FROM conversation_participants cp
                JOIN users u ON cp.user_id = u.user_id
                WHERE cp.conversation_id = :conversation_id
                AND cp.user_id != :user_id
                AND cp.left_at IS NULL
                LIMIT 1";

        $this->db->query($sql)
            ->bind(':conversation_id', $conversation_id)
            ->bind(':user_id', $user_id);

        return $this->db->fetch();
    }

    /**
     * Get conversation participants
     */
    public function getParticipants($conversation_id) {
        $sql = "SELECT u.user_id, u.username, u.full_name, u.profile_picture, u.is_verified,
                cp.is_admin, cp.joined_at
                FROM conversation_participants cp
                JOIN users u ON cp.user_id = u.user_id
                WHERE cp.conversation_id = :conversation_id AND cp.left_at IS NULL
                ORDER BY cp.is_admin DESC, cp.joined_at ASC";

        $this->db->query($sql)->bind(':conversation_id', $conversation_id);
        return $this->db->fetchAll();
    }

    /**
     * Mark messages as read
     */
    public function markAsRead($conversation_id, $user_id, $message_id) {
        $sql = "UPDATE conversation_participants
                SET last_read_message_id = :message_id
                WHERE conversation_id = :conversation_id AND user_id = :user_id";

        $this->db->query($sql)
            ->bind(':message_id', $message_id)
            ->bind(':conversation_id', $conversation_id)
            ->bind(':user_id', $user_id);

        if ($this->db->execute()) {
            return ['success' => true, 'message' => 'Marked as read'];
        }

        return ['success' => false, 'message' => 'Failed to mark as read'];
    }

    /**
     * React to message
     */
    public function reactToMessage($message_id, $user_id, $reaction_type) {
        $sql = "INSERT INTO message_reactions (message_id, user_id, reaction_type, created_at)
                VALUES (:message_id, :user_id, :reaction_type, NOW())
                ON DUPLICATE KEY UPDATE reaction_type = :reaction_type, created_at = NOW()";

        $this->db->query($sql)
            ->bind(':message_id', $message_id)
            ->bind(':user_id', $user_id)
            ->bind(':reaction_type', $reaction_type);

        if ($this->db->execute()) {
            return ['success' => true, 'message' => 'Reaction added'];
        }

        return ['success' => false, 'message' => 'Failed to add reaction'];
    }

    /**
     * Remove reaction from message
     */
    public function removeReaction($message_id, $user_id) {
        $sql = "DELETE FROM message_reactions WHERE message_id = :message_id AND user_id = :user_id";

        $this->db->query($sql)
            ->bind(':message_id', $message_id)
            ->bind(':user_id', $user_id);

        if ($this->db->execute()) {
            return ['success' => true, 'message' => 'Reaction removed'];
        }

        return ['success' => false, 'message' => 'Failed to remove reaction'];
    }

    /**
     * Delete message
     */
    public function deleteMessage($message_id, $user_id) {
        // Only sender can delete
        $sql = "UPDATE messages SET is_deleted = 1 WHERE message_id = :message_id AND sender_id = :user_id";

        $this->db->query($sql)
            ->bind(':message_id', $message_id)
            ->bind(':user_id', $user_id);

        if ($this->db->execute() && $this->db->rowCount() > 0) {
            return ['success' => true, 'message' => 'Message deleted'];
        }

        return ['success' => false, 'message' => 'Failed to delete message'];
    }

    /**
     * Leave conversation
     */
    public function leaveConversation($conversation_id, $user_id) {
        $sql = "UPDATE conversation_participants
                SET left_at = NOW()
                WHERE conversation_id = :conversation_id AND user_id = :user_id";

        $this->db->query($sql)
            ->bind(':conversation_id', $conversation_id)
            ->bind(':user_id', $user_id);

        if ($this->db->execute()) {
            return ['success' => true, 'message' => 'Left conversation'];
        }

        return ['success' => false, 'message' => 'Failed to leave conversation'];
    }

    /**
     * Add participant to group
     */
    public function addParticipant($conversation_id, $user_id, $new_user_id) {
        // Check if requester is admin
        if (!$this->isAdmin($conversation_id, $user_id)) {
            return ['success' => false, 'message' => 'Only admins can add participants'];
        }

        $sql = "INSERT INTO conversation_participants (conversation_id, user_id, joined_at)
                VALUES (:conversation_id, :new_user_id, NOW())
                ON DUPLICATE KEY UPDATE left_at = NULL, joined_at = NOW()";

        $this->db->query($sql)
            ->bind(':conversation_id', $conversation_id)
            ->bind(':new_user_id', $new_user_id);

        if ($this->db->execute()) {
            return ['success' => true, 'message' => 'Participant added'];
        }

        return ['success' => false, 'message' => 'Failed to add participant'];
    }

    /**
     * Get unread message count
     */
    public function getUnreadCount($user_id) {
        $sql = "SELECT COUNT(*) as count
                FROM messages m
                JOIN conversation_participants cp ON m.conversation_id = cp.conversation_id
                WHERE cp.user_id = :user_id
                AND m.sender_id != :user_id
                AND m.message_id > COALESCE(cp.last_read_message_id, 0)
                AND m.is_deleted = 0
                AND cp.left_at IS NULL";

        $this->db->query($sql)->bind(':user_id', $user_id);
        $result = $this->db->fetch();
        return $result ? $result['count'] : 0;
    }

    /**
     * Check if user is participant
     */
    private function isParticipant($conversation_id, $user_id) {
        $sql = "SELECT participant_id FROM conversation_participants
                WHERE conversation_id = :conversation_id AND user_id = :user_id AND left_at IS NULL
                LIMIT 1";

        $this->db->query($sql)
            ->bind(':conversation_id', $conversation_id)
            ->bind(':user_id', $user_id);

        return $this->db->fetch() !== false;
    }

    /**
     * Check if user is admin
     */
    private function isAdmin($conversation_id, $user_id) {
        $sql = "SELECT participant_id FROM conversation_participants
                WHERE conversation_id = :conversation_id AND user_id = :user_id AND is_admin = 1 AND left_at IS NULL
                LIMIT 1";

        $this->db->query($sql)
            ->bind(':conversation_id', $conversation_id)
            ->bind(':user_id', $user_id);

        return $this->db->fetch() !== false;
    }

    /**
     * Update conversation timestamp
     */
    private function updateConversationTimestamp($conversation_id) {
        $sql = "UPDATE conversations SET updated_at = NOW() WHERE conversation_id = :conversation_id";
        $this->db->query($sql)->bind(':conversation_id', $conversation_id)->execute();
    }

    /**
     * Create message notifications
     */
    private function createMessageNotifications($conversation_id, $sender_id, $message_id) {
        // Get all participants except sender
        $sql = "SELECT user_id FROM conversation_participants
                WHERE conversation_id = :conversation_id
                AND user_id != :sender_id
                AND left_at IS NULL
                AND notifications_enabled = 1";

        $this->db->query($sql)
            ->bind(':conversation_id', $conversation_id)
            ->bind(':sender_id', $sender_id);

        $participants = $this->db->fetchAll();

        // Create notification for each
        foreach ($participants as $participant) {
            $sql_notif = "INSERT INTO notifications (user_id, actor_id, notification_type, message_id, created_at)
                         VALUES (:user_id, :actor_id, 'message', :message_id, NOW())";

            $this->db->query($sql_notif)
                ->bind(':user_id', $participant['user_id'])
                ->bind(':actor_id', $sender_id)
                ->bind(':message_id', $message_id)
                ->execute();
        }
    }
}
