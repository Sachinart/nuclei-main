<?php
/**
 * Feed Class
 * Implements Instagram-like feed algorithm with personalized content ranking
 */

class Feed {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Get personalized feed for user
     * Uses Instagram-like algorithm considering:
     * - Recency (when posted)
     * - Engagement (likes, comments, saves)
     * - Relationship (how close you are to the poster)
     * - User interests (based on past activity)
     */
    public function getPersonalizedFeed($user_id, $limit = 10, $offset = 0) {
        // Get posts from followed users + some suggested content
        $sql = "SELECT DISTINCT
                    p.post_id,
                    p.user_id,
                    p.caption,
                    p.location,
                    p.created_at,
                    u.username,
                    u.full_name,
                    u.profile_picture,
                    u.is_verified,

                    -- Engagement metrics
                    (SELECT COUNT(*) FROM post_likes WHERE post_id = p.post_id) as likes_count,
                    (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) as comments_count,
                    (SELECT COUNT(*) FROM saved_posts WHERE post_id = p.post_id) as saves_count,

                    -- User interaction
                    (SELECT COUNT(*) FROM post_likes WHERE post_id = p.post_id AND user_id = :user_id) as user_liked,
                    (SELECT COUNT(*) FROM saved_posts WHERE post_id = p.post_id AND user_id = :user_id) as user_saved,

                    -- Relationship score
                    (SELECT COUNT(*) FROM follows WHERE follower_id = :user_id AND following_id = p.user_id) as is_following,
                    (SELECT COUNT(*) FROM post_likes pl
                     JOIN follows f ON pl.user_id = f.follower_id
                     WHERE pl.post_id = p.post_id AND f.following_id = :user_id) as friends_liked_count,

                    -- Recency score (newer = higher)
                    TIMESTAMPDIFF(HOUR, p.created_at, NOW()) as hours_ago,

                    -- Engagement rate
                    ((SELECT COUNT(*) FROM post_likes WHERE post_id = p.post_id) +
                     (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) * 2 +
                     (SELECT COUNT(*) FROM saved_posts WHERE post_id = p.post_id) * 3) as engagement_score,

                    -- User affinity (how often user interacts with this poster)
                    (SELECT COUNT(*) FROM post_likes pl2
                     JOIN posts p2 ON pl2.post_id = p2.post_id
                     WHERE pl2.user_id = :user_id AND p2.user_id = p.user_id) as interaction_history

                FROM posts p
                JOIN users u ON p.user_id = u.user_id

                WHERE p.is_archived = 0
                AND u.is_active = 1
                AND p.user_id != :user_id
                AND p.user_id NOT IN (SELECT blocked_id FROM blocks WHERE blocker_id = :user_id)
                AND p.user_id NOT IN (SELECT blocker_id FROM blocks WHERE blocked_id = :user_id)
                AND (
                    -- Posts from people user follows
                    p.user_id IN (SELECT following_id FROM follows WHERE follower_id = :user_id)
                    OR
                    -- Suggested posts (popular in user's network or interests)
                    p.post_id IN (
                        SELECT DISTINCT ph.post_id FROM post_hashtags ph
                        WHERE ph.hashtag_id IN (
                            SELECT ui.interest_value FROM user_interests ui
                            WHERE ui.user_id = :user_id AND ui.interest_type = 'hashtag'
                            ORDER BY ui.interest_score DESC LIMIT 10
                        )
                    )
                )

                ORDER BY
                    -- Complex ranking algorithm
                    (
                        -- Recency weight (0-1, newer is better)
                        (CASE
                            WHEN TIMESTAMPDIFF(HOUR, p.created_at, NOW()) < 1 THEN 1.0
                            WHEN TIMESTAMPDIFF(HOUR, p.created_at, NOW()) < 6 THEN 0.8
                            WHEN TIMESTAMPDIFF(HOUR, p.created_at, NOW()) < 24 THEN 0.6
                            WHEN TIMESTAMPDIFF(HOUR, p.created_at, NOW()) < 72 THEN 0.4
                            ELSE 0.2
                        END) * " . WEIGHT_RECENCY . "

                        +

                        -- Engagement weight (normalized)
                        (LEAST(
                            ((SELECT COUNT(*) FROM post_likes WHERE post_id = p.post_id) +
                             (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) * 2 +
                             (SELECT COUNT(*) FROM saved_posts WHERE post_id = p.post_id) * 3) / 100,
                            1.0
                        )) * " . WEIGHT_ENGAGEMENT . "

                        +

                        -- Relationship weight
                        (CASE
                            WHEN (SELECT COUNT(*) FROM follows WHERE follower_id = :user_id AND following_id = p.user_id) > 0 THEN 1.0
                            WHEN (SELECT COUNT(*) FROM post_likes pl
                                  JOIN follows f ON pl.user_id = f.follower_id
                                  WHERE pl.post_id = p.post_id AND f.following_id = :user_id) > 0 THEN 0.7
                            ELSE 0.3
                        END) * " . WEIGHT_RELATIONSHIP . "

                        +

                        -- Interest/Affinity weight
                        (LEAST(
                            (SELECT COUNT(*) FROM post_likes pl2
                             JOIN posts p2 ON pl2.post_id = p2.post_id
                             WHERE pl2.user_id = :user_id AND p2.user_id = p.user_id) / 10,
                            1.0
                        )) * " . WEIGHT_INTEREST . "

                    ) DESC,
                    p.created_at DESC

                LIMIT :limit OFFSET :offset";

        $query = $this->db->query($sql);
        $query->bind(':user_id', $user_id);
        $query->bind(':limit', $limit, PDO::PARAM_INT);
        $query->bind(':offset', $offset, PDO::PARAM_INT);

        $posts = $query->fetchAll();

        // Attach media to each post
        foreach ($posts as &$post) {
            $post['media'] = $this->getPostMedia($post['post_id']);
            $post['user_liked'] = (bool)$post['user_liked'];
            $post['user_saved'] = (bool)$post['user_saved'];
            $post['is_following'] = (bool)$post['is_following'];
        }

        return $posts;
    }

    /**
     * Get explore page feed - discover new content
     */
    public function getExploreFeed($user_id, $limit = 24, $offset = 0) {
        $sql = "SELECT
                    p.post_id,
                    p.user_id,
                    p.caption,
                    p.created_at,
                    u.username,
                    u.profile_picture,
                    u.is_verified,
                    (SELECT COUNT(*) FROM post_likes WHERE post_id = p.post_id) as likes_count,
                    (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) as comments_count,
                    (SELECT media_url FROM post_media WHERE post_id = p.post_id ORDER BY media_order ASC LIMIT 1) as thumbnail,

                    -- Popularity score
                    ((SELECT COUNT(*) FROM post_likes WHERE post_id = p.post_id) +
                     (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) * 2 +
                     (SELECT COUNT(*) FROM saved_posts WHERE post_id = p.post_id) * 3) as popularity_score

                FROM posts p
                JOIN users u ON p.user_id = u.user_id

                WHERE p.is_archived = 0
                AND u.is_active = 1
                AND u.is_private = 0
                AND p.user_id != :user_id
                AND p.user_id NOT IN (SELECT blocked_id FROM blocks WHERE blocker_id = :user_id)
                AND p.user_id NOT IN (SELECT blocker_id FROM blocks WHERE blocked_id = :user_id)
                AND p.user_id NOT IN (SELECT following_id FROM follows WHERE follower_id = :user_id)
                AND (
                    -- Posts with hashtags matching user interests
                    p.post_id IN (
                        SELECT ph.post_id FROM post_hashtags ph
                        WHERE ph.hashtag_id IN (
                            SELECT hashtag_id FROM hashtags
                            WHERE hashtag_name IN (
                                SELECT interest_value FROM user_interests
                                WHERE user_id = :user_id AND interest_type = 'hashtag'
                            )
                        )
                    )
                    OR
                    -- Popular posts in general
                    ((SELECT COUNT(*) FROM post_likes WHERE post_id = p.post_id) +
                     (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id)) > 10
                )
                AND p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)

                ORDER BY popularity_score DESC, p.created_at DESC
                LIMIT :limit OFFSET :offset";

        $query = $this->db->query($sql);
        $query->bind(':user_id', $user_id);
        $query->bind(':limit', $limit, PDO::PARAM_INT);
        $query->bind(':offset', $offset, PDO::PARAM_INT);

        return $query->fetchAll();
    }

    /**
     * Get trending hashtags
     */
    public function getTrendingHashtags($limit = 20) {
        $sql = "SELECT h.*,
                (SELECT COUNT(*) FROM post_hashtags ph
                 JOIN posts p ON ph.post_id = p.post_id
                 WHERE ph.hashtag_id = h.hashtag_id
                 AND p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as recent_posts
                FROM hashtags h
                WHERE h.post_count > 0
                ORDER BY recent_posts DESC, h.post_count DESC
                LIMIT :limit";

        $this->db->query($sql)->bind(':limit', $limit, PDO::PARAM_INT);
        return $this->db->fetchAll();
    }

    /**
     * Get posts by hashtag
     */
    public function getPostsByHashtag($hashtag, $user_id = null, $limit = 20, $offset = 0) {
        $sql = "SELECT p.*, u.username, u.profile_picture, u.is_verified,
                (SELECT COUNT(*) FROM post_likes WHERE post_id = p.post_id) as likes_count,
                (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) as comments_count,
                (SELECT media_url FROM post_media WHERE post_id = p.post_id ORDER BY media_order ASC LIMIT 1) as thumbnail
                FROM posts p
                JOIN users u ON p.user_id = u.user_id
                JOIN post_hashtags ph ON p.post_id = ph.post_id
                JOIN hashtags h ON ph.hashtag_id = h.hashtag_id
                WHERE h.hashtag_name = :hashtag
                AND p.is_archived = 0
                AND u.is_active = 1";

        if ($user_id) {
            $sql .= " AND p.user_id NOT IN (SELECT blocked_id FROM blocks WHERE blocker_id = :user_id)
                     AND p.user_id NOT IN (SELECT blocker_id FROM blocks WHERE blocked_id = :user_id)";
        }

        $sql .= " ORDER BY p.created_at DESC
                 LIMIT :limit OFFSET :offset";

        $query = $this->db->query($sql)
            ->bind(':hashtag', strtolower($hashtag))
            ->bind(':limit', $limit, PDO::PARAM_INT)
            ->bind(':offset', $offset, PDO::PARAM_INT);

        if ($user_id) {
            $query->bind(':user_id', $user_id);
        }

        return $query->fetchAll();
    }

    /**
     * Update user interests based on activity
     */
    public function updateUserInterests($user_id) {
        // Get hashtags from posts user liked
        $sql = "SELECT h.hashtag_name, COUNT(*) as interaction_count
                FROM post_likes pl
                JOIN post_hashtags ph ON pl.post_id = ph.post_id
                JOIN hashtags h ON ph.hashtag_id = h.hashtag_id
                WHERE pl.user_id = :user_id
                AND pl.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY h.hashtag_name
                ORDER BY interaction_count DESC
                LIMIT 20";

        $this->db->query($sql)->bind(':user_id', $user_id);
        $interests = $this->db->fetchAll();

        foreach ($interests as $interest) {
            $score = min(($interest['interaction_count'] * 10), 100);

            $sql_update = "INSERT INTO user_interests (user_id, interest_type, interest_value, interest_score)
                          VALUES (:user_id, 'hashtag', :interest_value, :interest_score)
                          ON DUPLICATE KEY UPDATE interest_score = :interest_score, last_updated = NOW()";

            $this->db->query($sql_update)
                ->bind(':user_id', $user_id)
                ->bind(':interest_value', $interest['hashtag_name'])
                ->bind(':interest_score', $score)
                ->execute();
        }
    }

    /**
     * Get post media
     */
    private function getPostMedia($post_id) {
        $sql = "SELECT * FROM post_media WHERE post_id = :post_id ORDER BY media_order ASC";
        $this->db->query($sql)->bind(':post_id', $post_id);
        return $this->db->fetchAll();
    }

    /**
     * Get suggested users to follow
     */
    public function getSuggestedUsers($user_id, $limit = 10) {
        $sql = "SELECT DISTINCT u.user_id, u.username, u.full_name, u.profile_picture, u.is_verified,
                (SELECT COUNT(*) FROM follows WHERE following_id = u.user_id) as followers_count,
                (SELECT COUNT(*) FROM follows f1
                 JOIN follows f2 ON f1.following_id = f2.follower_id
                 WHERE f1.follower_id = :user_id AND f2.following_id = u.user_id) as mutual_follows

                FROM users u
                WHERE u.user_id != :user_id
                AND u.is_active = 1
                AND u.user_id NOT IN (SELECT following_id FROM follows WHERE follower_id = :user_id)
                AND u.user_id NOT IN (SELECT blocked_id FROM blocks WHERE blocker_id = :user_id)
                AND u.user_id NOT IN (SELECT blocker_id FROM blocks WHERE blocked_id = :user_id)
                AND (
                    -- Users followed by people you follow
                    u.user_id IN (
                        SELECT f2.following_id FROM follows f1
                        JOIN follows f2 ON f1.following_id = f2.follower_id
                        WHERE f1.follower_id = :user_id
                        AND f2.following_id != :user_id
                    )
                    OR
                    -- Popular users in your interest areas
                    u.user_id IN (
                        SELECT p.user_id FROM posts p
                        JOIN post_hashtags ph ON p.post_id = ph.post_id
                        WHERE ph.hashtag_id IN (
                            SELECT hashtag_id FROM hashtags
                            WHERE hashtag_name IN (
                                SELECT interest_value FROM user_interests
                                WHERE user_id = :user_id AND interest_type = 'hashtag'
                            )
                        )
                        GROUP BY p.user_id
                        HAVING COUNT(*) > 3
                    )
                )
                ORDER BY mutual_follows DESC, followers_count DESC
                LIMIT :limit";

        $this->db->query($sql)
            ->bind(':user_id', $user_id)
            ->bind(':limit', $limit, PDO::PARAM_INT);

        return $this->db->fetchAll();
    }
}
