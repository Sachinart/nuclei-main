<?php
/**
 * User Class
 * Handles user authentication, registration, profile management
 */

class User {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Register new user
     */
    public function register($username, $email, $password, $full_name) {
        // Validate input
        if (strlen($username) < 3 || strlen($username) > 30) {
            return ['success' => false, 'message' => 'Username must be 3-30 characters'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email address'];
        }

        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            return ['success' => false, 'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters'];
        }

        // Check if username exists
        if ($this->usernameExists($username)) {
            return ['success' => false, 'message' => 'Username already taken'];
        }

        // Check if email exists
        if ($this->emailExists($email)) {
            return ['success' => false, 'message' => 'Email already registered'];
        }

        // Hash password
        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        // Insert user
        $sql = "INSERT INTO users (username, email, password_hash, full_name, created_at)
                VALUES (:username, :email, :password_hash, :full_name, NOW())";

        $this->db->query($sql)
            ->bind(':username', $username)
            ->bind(':email', $email)
            ->bind(':password_hash', $password_hash)
            ->bind(':full_name', $full_name);

        if ($this->db->execute()) {
            $user_id = $this->db->lastInsertId();
            return ['success' => true, 'user_id' => $user_id, 'message' => 'Registration successful'];
        }

        return ['success' => false, 'message' => 'Registration failed'];
    }

    /**
     * Login user
     */
    public function login($username_or_email, $password) {
        // Get user by username or email
        $sql = "SELECT * FROM users WHERE (username = :identifier OR email = :identifier) AND is_active = 1 LIMIT 1";

        $this->db->query($sql)->bind(':identifier', $username_or_email);
        $user = $this->db->fetch();

        if (!$user) {
            return ['success' => false, 'message' => 'Invalid credentials'];
        }

        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Invalid credentials'];
        }

        // Create session
        $session_token = $this->createSession($user['user_id']);

        // Update last seen
        $this->updateLastSeen($user['user_id']);

        return [
            'success' => true,
            'user_id' => $user['user_id'],
            'username' => $user['username'],
            'session_token' => $session_token,
            'message' => 'Login successful'
        ];
    }

    /**
     * Create user session
     */
    private function createSession($user_id) {
        $session_token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $sql = "INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at)
                VALUES (:user_id, :session_token, :ip_address, :user_agent, :expires_at)";

        $this->db->query($sql)
            ->bind(':user_id', $user_id)
            ->bind(':session_token', $session_token)
            ->bind(':ip_address', $ip_address)
            ->bind(':user_agent', $user_agent)
            ->bind(':expires_at', $expires_at)
            ->execute();

        // Set session cookie
        $_SESSION['user_id'] = $user_id;
        $_SESSION['session_token'] = $session_token;

        return $session_token;
    }

    /**
     * Validate session
     */
    public function validateSession($session_token) {
        $sql = "SELECT us.*, u.* FROM user_sessions us
                JOIN users u ON us.user_id = u.user_id
                WHERE us.session_token = :session_token
                AND us.expires_at > NOW()
                AND u.is_active = 1
                LIMIT 1";

        $this->db->query($sql)->bind(':session_token', $session_token);
        return $this->db->fetch();
    }

    /**
     * Logout user
     */
    public function logout($session_token) {
        $sql = "DELETE FROM user_sessions WHERE session_token = :session_token";
        $this->db->query($sql)->bind(':session_token', $session_token)->execute();

        session_destroy();
        return ['success' => true, 'message' => 'Logged out successfully'];
    }

    /**
     * Get user by ID
     */
    public function getUserById($user_id) {
        $sql = "SELECT user_id, username, email, full_name, bio, profile_picture,
                website, is_private, is_verified, created_at, last_seen
                FROM users WHERE user_id = :user_id AND is_active = 1 LIMIT 1";

        $this->db->query($sql)->bind(':user_id', $user_id);
        return $this->db->fetch();
    }

    /**
     * Get user by username
     */
    public function getUserByUsername($username) {
        $sql = "SELECT user_id, username, email, full_name, bio, profile_picture,
                website, is_private, is_verified, created_at, last_seen
                FROM users WHERE username = :username AND is_active = 1 LIMIT 1";

        $this->db->query($sql)->bind(':username', $username);
        return $this->db->fetch();
    }

    /**
     * Update user profile
     */
    public function updateProfile($user_id, $data) {
        $allowed_fields = ['full_name', 'bio', 'website', 'gender', 'is_private'];
        $updates = [];
        $params = [':user_id' => $user_id];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowed_fields)) {
                $updates[] = "$key = :$key";
                $params[":$key"] = $value;
            }
        }

        if (empty($updates)) {
            return ['success' => false, 'message' => 'No valid fields to update'];
        }

        $sql = "UPDATE users SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE user_id = :user_id";

        $query = $this->db->query($sql);
        foreach ($params as $param => $value) {
            $query->bind($param, $value);
        }

        if ($query->execute()) {
            return ['success' => true, 'message' => 'Profile updated successfully'];
        }

        return ['success' => false, 'message' => 'Profile update failed'];
    }

    /**
     * Update profile picture
     */
    public function updateProfilePicture($user_id, $file_path) {
        $sql = "UPDATE users SET profile_picture = :file_path, updated_at = NOW() WHERE user_id = :user_id";

        $this->db->query($sql)
            ->bind(':file_path', $file_path)
            ->bind(':user_id', $user_id);

        if ($this->db->execute()) {
            return ['success' => true, 'message' => 'Profile picture updated'];
        }

        return ['success' => false, 'message' => 'Update failed'];
    }

    /**
     * Update last seen
     */
    public function updateLastSeen($user_id) {
        $sql = "UPDATE users SET last_seen = NOW() WHERE user_id = :user_id";
        $this->db->query($sql)->bind(':user_id', $user_id)->execute();
    }

    /**
     * Check if username exists
     */
    private function usernameExists($username) {
        $sql = "SELECT user_id FROM users WHERE username = :username LIMIT 1";
        $this->db->query($sql)->bind(':username', $username);
        return $this->db->fetch() !== false;
    }

    /**
     * Check if email exists
     */
    private function emailExists($email) {
        $sql = "SELECT user_id FROM users WHERE email = :email LIMIT 1";
        $this->db->query($sql)->bind(':email', $email);
        return $this->db->fetch() !== false;
    }

    /**
     * Search users
     */
    public function searchUsers($query, $limit = 20) {
        $search_term = "%$query%";
        $sql = "SELECT user_id, username, full_name, profile_picture, is_verified
                FROM users
                WHERE (username LIKE :query OR full_name LIKE :query)
                AND is_active = 1
                ORDER BY
                    CASE WHEN username LIKE :exact THEN 0 ELSE 1 END,
                    username
                LIMIT :limit";

        $this->db->query($sql)
            ->bind(':query', $search_term)
            ->bind(':exact', $query)
            ->bind(':limit', $limit, PDO::PARAM_INT);

        return $this->db->fetchAll();
    }

    /**
     * Get user statistics
     */
    public function getUserStats($user_id) {
        // Get posts count
        $sql_posts = "SELECT COUNT(*) as count FROM posts WHERE user_id = :user_id AND is_archived = 0";
        $this->db->query($sql_posts)->bind(':user_id', $user_id);
        $posts = $this->db->fetch();

        // Get followers count
        $sql_followers = "SELECT COUNT(*) as count FROM follows WHERE following_id = :user_id";
        $this->db->query($sql_followers)->bind(':user_id', $user_id);
        $followers = $this->db->fetch();

        // Get following count
        $sql_following = "SELECT COUNT(*) as count FROM follows WHERE follower_id = :user_id";
        $this->db->query($sql_following)->bind(':user_id', $user_id);
        $following = $this->db->fetch();

        return [
            'posts' => $posts['count'],
            'followers' => $followers['count'],
            'following' => $following['count']
        ];
    }

    /**
     * Get current authenticated user
     */
    public function getCurrentUser() {
        if (isset($_SESSION['user_id']) && isset($_SESSION['session_token'])) {
            return $this->validateSession($_SESSION['session_token']);
        }
        return false;
    }

    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return $this->getCurrentUser() !== false;
    }
}
