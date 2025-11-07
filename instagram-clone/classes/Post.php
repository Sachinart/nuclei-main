<?php
/**
 * Post Class
 * Handles post creation, retrieval, likes, comments, and related functionality
 */

class Post {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Create new post
     */
    public function createPost($user_id, $caption, $media_files, $location = null) {
        try {
            $this->db->beginTransaction();

            // Insert post
            $sql = "INSERT INTO posts (user_id, caption, location, created_at)
                    VALUES (:user_id, :caption, :location, NOW())";

            $this->db->query($sql)
                ->bind(':user_id', $user_id)
                ->bind(':caption', $caption)
                ->bind(':location', $location);

            if (!$this->db->execute()) {
                throw new Exception('Failed to create post');
            }

            $post_id = $this->db->lastInsertId();

            // Insert media files
            foreach ($media_files as $index => $media) {
                $sql_media = "INSERT INTO post_media (post_id, media_type, media_url, thumbnail_url, media_order, width, height, duration)
                              VALUES (:post_id, :media_type, :media_url, :thumbnail_url, :media_order, :width, :height, :duration)";

                $this->db->query($sql_media)
                    ->bind(':post_id', $post_id)
                    ->bind(':media_type', $media['type'])
                    ->bind(':media_url', $media['url'])
                    ->bind(':thumbnail_url', $media['thumbnail'] ?? null)
                    ->bind(':media_order', $index)
                    ->bind(':width', $media['width'] ?? null)
                    ->bind(':height', $media['height'] ?? null)
                    ->bind(':duration', $media['duration'] ?? null)
                    ->execute();
            }

            // Extract and save hashtags
            $this->extractAndSaveHashtags($post_id, $caption);

            // Extract and save mentions
            $this->extractAndSaveMentions($post_id, $caption);

            $this->db->commit();

            return ['success' => true, 'post_id' => $post_id, 'message' => 'Post created successfully'];

        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get post by ID with full details
     */
    public function getPostById($post_id, $current_user_id = null) {
        $sql = "SELECT p.*, u.username, u.full_name, u.profile_picture, u.is_verified,
                (SELECT COUNT(*) FROM post_likes WHERE post_id = p.post_id) as likes_count,
                (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) as comments_count,
                (SELECT COUNT(*) FROM saved_posts WHERE post_id = p.post_id) as saves_count";

        if ($current_user_id) {
            $sql .= ", (SELECT COUNT(*) FROM post_likes WHERE post_id = p.post_id AND user_id = :current_user_id) as user_liked,
                     (SELECT COUNT(*) FROM saved_posts WHERE post_id = p.post_id AND user_id = :current_user_id) as user_saved";
        }

        $sql .= " FROM posts p
                 JOIN users u ON p.user_id = u.user_id
                 WHERE p.post_id = :post_id AND p.is_archived = 0 AND u.is_active = 1
                 LIMIT 1";

        $query = $this->db->query($sql)->bind(':post_id', $post_id);

        if ($current_user_id) {
            $query->bind(':current_user_id', $current_user_id);
        }

        $post = $query->fetch();

        if ($post) {
            $post['media'] = $this->getPostMedia($post_id);
            $post['user_liked'] = isset($post['user_liked']) ? (bool)$post['user_liked'] : false;
            $post['user_saved'] = isset($post['user_saved']) ? (bool)$post['user_saved'] : false;
        }

        return $post;
    }

    /**
     * Get media for a post
     */
    public function getPostMedia($post_id) {
        $sql = "SELECT * FROM post_media WHERE post_id = :post_id ORDER BY media_order ASC";
        $this->db->query($sql)->bind(':post_id', $post_id);
        return $this->db->fetchAll();
    }

    /**
     * Get user's posts
     */
    public function getUserPosts($user_id, $current_user_id = null, $limit = 12, $offset = 0) {
        $sql = "SELECT p.*, u.username, u.profile_picture,
                (SELECT COUNT(*) FROM post_likes WHERE post_id = p.post_id) as likes_count,
                (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) as comments_count,
                (SELECT media_url FROM post_media WHERE post_id = p.post_id ORDER BY media_order ASC LIMIT 1) as thumbnail";

        if ($current_user_id) {
            $sql .= ", (SELECT COUNT(*) FROM post_likes WHERE post_id = p.post_id AND user_id = :current_user_id) as user_liked";
        }

        $sql .= " FROM posts p
                 JOIN users u ON p.user_id = u.user_id
                 WHERE p.user_id = :user_id AND p.is_archived = 0 AND u.is_active = 1
                 ORDER BY p.created_at DESC
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
     * Like a post
     */
    public function likePost($post_id, $user_id) {
        // Check if already liked
        $sql_check = "SELECT like_id FROM post_likes WHERE post_id = :post_id AND user_id = :user_id";
        $this->db->query($sql_check)
            ->bind(':post_id', $post_id)
            ->bind(':user_id', $user_id);

        if ($this->db->fetch()) {
            return ['success' => false, 'message' => 'Already liked'];
        }

        // Insert like
        $sql = "INSERT INTO post_likes (post_id, user_id, created_at) VALUES (:post_id, :user_id, NOW())";
        $this->db->query($sql)
            ->bind(':post_id', $post_id)
            ->bind(':user_id', $user_id);

        if ($this->db->execute()) {
            // Create notification for post owner
            $this->createLikeNotification($post_id, $user_id);

            // Track activity
            $this->trackActivity($user_id, 'post_like', $post_id);

            return ['success' => true, 'message' => 'Post liked'];
        }

        return ['success' => false, 'message' => 'Failed to like post'];
    }

    /**
     * Unlike a post
     */
    public function unlikePost($post_id, $user_id) {
        $sql = "DELETE FROM post_likes WHERE post_id = :post_id AND user_id = :user_id";
        $this->db->query($sql)
            ->bind(':post_id', $post_id)
            ->bind(':user_id', $user_id);

        if ($this->db->execute()) {
            return ['success' => true, 'message' => 'Post unliked'];
        }

        return ['success' => false, 'message' => 'Failed to unlike post'];
    }

    /**
     * Add comment to post
     */
    public function addComment($post_id, $user_id, $comment_text, $parent_comment_id = null) {
        if (empty(trim($comment_text))) {
            return ['success' => false, 'message' => 'Comment cannot be empty'];
        }

        $sql = "INSERT INTO comments (post_id, user_id, parent_comment_id, comment_text, created_at)
                VALUES (:post_id, :user_id, :parent_comment_id, :comment_text, NOW())";

        $this->db->query($sql)
            ->bind(':post_id', $post_id)
            ->bind(':user_id', $user_id)
            ->bind(':parent_comment_id', $parent_comment_id)
            ->bind(':comment_text', $comment_text);

        if ($this->db->execute()) {
            $comment_id = $this->db->lastInsertId();

            // Create notification for post owner
            $this->createCommentNotification($post_id, $user_id, $comment_id);

            return ['success' => true, 'comment_id' => $comment_id, 'message' => 'Comment added'];
        }

        return ['success' => false, 'message' => 'Failed to add comment'];
    }

    /**
     * Get post comments
     */
    public function getPostComments($post_id, $limit = 20, $offset = 0) {
        $sql = "SELECT c.*, u.username, u.full_name, u.profile_picture, u.is_verified,
                (SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.comment_id) as likes_count,
                (SELECT COUNT(*) FROM comments WHERE parent_comment_id = c.comment_id) as replies_count
                FROM comments c
                JOIN users u ON c.user_id = u.user_id
                WHERE c.post_id = :post_id AND c.parent_comment_id IS NULL
                ORDER BY c.created_at DESC
                LIMIT :limit OFFSET :offset";

        $this->db->query($sql)
            ->bind(':post_id', $post_id)
            ->bind(':limit', $limit, PDO::PARAM_INT)
            ->bind(':offset', $offset, PDO::PARAM_INT);

        return $this->db->fetchAll();
    }

    /**
     * Get comment replies
     */
    public function getCommentReplies($parent_comment_id) {
        $sql = "SELECT c.*, u.username, u.full_name, u.profile_picture, u.is_verified,
                (SELECT COUNT(*) FROM comment_likes WHERE comment_id = c.comment_id) as likes_count
                FROM comments c
                JOIN users u ON c.user_id = u.user_id
                WHERE c.parent_comment_id = :parent_comment_id
                ORDER BY c.created_at ASC";

        $this->db->query($sql)->bind(':parent_comment_id', $parent_comment_id);
        return $this->db->fetchAll();
    }

    /**
     * Delete comment
     */
    public function deleteComment($comment_id, $user_id) {
        $sql = "DELETE FROM comments WHERE comment_id = :comment_id AND user_id = :user_id";
        $this->db->query($sql)
            ->bind(':comment_id', $comment_id)
            ->bind(':user_id', $user_id);

        if ($this->db->execute() && $this->db->rowCount() > 0) {
            return ['success' => true, 'message' => 'Comment deleted'];
        }

        return ['success' => false, 'message' => 'Failed to delete comment'];
    }

    /**
     * Save post
     */
    public function savePost($post_id, $user_id, $collection_name = 'All Posts') {
        $sql = "INSERT INTO saved_posts (user_id, post_id, collection_name, created_at)
                VALUES (:user_id, :post_id, :collection_name, NOW())
                ON DUPLICATE KEY UPDATE collection_name = :collection_name";

        $this->db->query($sql)
            ->bind(':user_id', $user_id)
            ->bind(':post_id', $post_id)
            ->bind(':collection_name', $collection_name);

        if ($this->db->execute()) {
            return ['success' => true, 'message' => 'Post saved'];
        }

        return ['success' => false, 'message' => 'Failed to save post'];
    }

    /**
     * Unsave post
     */
    public function unsavePost($post_id, $user_id) {
        $sql = "DELETE FROM saved_posts WHERE post_id = :post_id AND user_id = :user_id";
        $this->db->query($sql)
            ->bind(':post_id', $post_id)
            ->bind(':user_id', $user_id);

        if ($this->db->execute()) {
            return ['success' => true, 'message' => 'Post unsaved'];
        }

        return ['success' => false, 'message' => 'Failed to unsave post'];
    }

    /**
     * Get saved posts
     */
    public function getSavedPosts($user_id, $limit = 12, $offset = 0) {
        $sql = "SELECT p.*, u.username, u.profile_picture,
                (SELECT COUNT(*) FROM post_likes WHERE post_id = p.post_id) as likes_count,
                (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) as comments_count,
                (SELECT media_url FROM post_media WHERE post_id = p.post_id ORDER BY media_order ASC LIMIT 1) as thumbnail,
                sp.collection_name, sp.created_at as saved_at
                FROM saved_posts sp
                JOIN posts p ON sp.post_id = p.post_id
                JOIN users u ON p.user_id = u.user_id
                WHERE sp.user_id = :user_id AND p.is_archived = 0
                ORDER BY sp.created_at DESC
                LIMIT :limit OFFSET :offset";

        $this->db->query($sql)
            ->bind(':user_id', $user_id)
            ->bind(':limit', $limit, PDO::PARAM_INT)
            ->bind(':offset', $offset, PDO::PARAM_INT);

        return $this->db->fetchAll();
    }

    /**
     * Delete post
     */
    public function deletePost($post_id, $user_id) {
        $sql = "DELETE FROM posts WHERE post_id = :post_id AND user_id = :user_id";
        $this->db->query($sql)
            ->bind(':post_id', $post_id)
            ->bind(':user_id', $user_id);

        if ($this->db->execute() && $this->db->rowCount() > 0) {
            return ['success' => true, 'message' => 'Post deleted'];
        }

        return ['success' => false, 'message' => 'Failed to delete post'];
    }

    /**
     * Extract and save hashtags from caption
     */
    private function extractAndSaveHashtags($post_id, $caption) {
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
                    // Link to post
                    $sql_link = "INSERT IGNORE INTO post_hashtags (post_id, hashtag_id)
                                VALUES (:post_id, :hashtag_id)";
                    $this->db->query($sql_link)
                        ->bind(':post_id', $post_id)
                        ->bind(':hashtag_id', $hashtag_data['hashtag_id'])
                        ->execute();
                }
            }
        }
    }

    /**
     * Extract and save user mentions from caption
     */
    private function extractAndSaveMentions($post_id, $caption) {
        if (preg_match_all('/@(\w+)/', $caption, $matches)) {
            foreach ($matches[1] as $username) {
                // Get user ID
                $sql_user = "SELECT user_id FROM users WHERE username = :username LIMIT 1";
                $this->db->query($sql_user)->bind(':username', $username);
                $user_data = $this->db->fetch();

                if ($user_data) {
                    // Insert mention
                    $sql_mention = "INSERT IGNORE INTO post_mentions (post_id, user_id)
                                   VALUES (:post_id, :user_id)";
                    $this->db->query($sql_mention)
                        ->bind(':post_id', $post_id)
                        ->bind(':user_id', $user_data['user_id'])
                        ->execute();

                    // Create notification
                    $this->createMentionNotification($post_id, $user_data['user_id']);
                }
            }
        }
    }

    /**
     * Create like notification
     */
    private function createLikeNotification($post_id, $liker_user_id) {
        // Get post owner
        $sql = "SELECT user_id FROM posts WHERE post_id = :post_id";
        $this->db->query($sql)->bind(':post_id', $post_id);
        $post = $this->db->fetch();

        if ($post && $post['user_id'] != $liker_user_id) {
            $sql_notif = "INSERT INTO notifications (user_id, actor_id, notification_type, post_id, created_at)
                         VALUES (:user_id, :actor_id, 'like', :post_id, NOW())";
            $this->db->query($sql_notif)
                ->bind(':user_id', $post['user_id'])
                ->bind(':actor_id', $liker_user_id)
                ->bind(':post_id', $post_id)
                ->execute();
        }
    }

    /**
     * Create comment notification
     */
    private function createCommentNotification($post_id, $commenter_user_id, $comment_id) {
        // Get post owner
        $sql = "SELECT user_id FROM posts WHERE post_id = :post_id";
        $this->db->query($sql)->bind(':post_id', $post_id);
        $post = $this->db->fetch();

        if ($post && $post['user_id'] != $commenter_user_id) {
            $sql_notif = "INSERT INTO notifications (user_id, actor_id, notification_type, post_id, comment_id, created_at)
                         VALUES (:user_id, :actor_id, 'comment', :post_id, :comment_id, NOW())";
            $this->db->query($sql_notif)
                ->bind(':user_id', $post['user_id'])
                ->bind(':actor_id', $commenter_user_id)
                ->bind(':post_id', $post_id)
                ->bind(':comment_id', $comment_id)
                ->execute();
        }
    }

    /**
     * Create mention notification
     */
    private function createMentionNotification($post_id, $mentioned_user_id) {
        $sql = "SELECT user_id FROM posts WHERE post_id = :post_id";
        $this->db->query($sql)->bind(':post_id', $post_id);
        $post = $this->db->fetch();

        if ($post) {
            $sql_notif = "INSERT INTO notifications (user_id, actor_id, notification_type, post_id, created_at)
                         VALUES (:user_id, :actor_id, 'mention', :post_id, NOW())";
            $this->db->query($sql_notif)
                ->bind(':user_id', $mentioned_user_id)
                ->bind(':actor_id', $post['user_id'])
                ->bind(':post_id', $post_id)
                ->execute();
        }
    }

    /**
     * Track user activity
     */
    private function trackActivity($user_id, $activity_type, $post_id = null) {
        $sql = "INSERT INTO user_activity (user_id, activity_type, post_id, created_at)
                VALUES (:user_id, :activity_type, :post_id, NOW())";

        $this->db->query($sql)
            ->bind(':user_id', $user_id)
            ->bind(':activity_type', $activity_type)
            ->bind(':post_id', $post_id)
            ->execute();
    }
}
