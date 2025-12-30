<?php
// =======================================
// FLIXSY CORE FUNCTIONS (includes/functions.php)
// Real database queries for all features
// =======================================

require_once __DIR__ . '/db.php';

// =======================================
// FEED & POSTS
// =======================================

/**
 * Get personalized feed for user
 * @param int $userId User ID
 * @param int $limit Number of posts
 * @param int $offset Offset for pagination
 * @return array Posts array
 */
function getFeed($userId, $limit = 20, $offset = 0) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            u.username,
            u.profile_pic,
            u.sector,
            u.is_verified,
            p.likes_count,
            p.comments_count,
            EXISTS(SELECT 1 FROM likes WHERE post_id = p.id AND user_id = ?) as user_liked
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE (
            p.user_id IN (SELECT followee_id FROM follows WHERE follower_id = ?)
            OR p.user_id = ?
        )
        AND p.privacy IN ('public', 'followers')
        AND p.is_archived = 0
        AND u.is_banned = 0
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $stmt->execute([$userId, $userId, $userId, $limit, $offset]);
    return $stmt->fetchAll();
}

/**
 * Get trending posts
 * @param int $limit Number of posts
 * @param int $days Posts from last X days
 * @return array Posts array
 */
function getTrendingPosts($limit = 20, $days = 7) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            u.username,
            u.profile_pic,
            u.is_verified,
            (p.likes_count * 3 + p.comments_count * 5 + p.shares_count * 10 + p.views_count) as trending_score
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
        AND p.privacy = 'public'
        AND p.is_archived = 0
        AND u.is_banned = 0
        ORDER BY trending_score DESC
        LIMIT ?
    ");
    
    $stmt->execute([$days, $limit]);
    return $stmt->fetchAll();
}

/**
 * Get single post by ID
 * @param int $postId Post ID
 * @param int $userId Current user ID (for liked status)
 * @return array|false Post data
 */
function getPost($postId, $userId = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            u.username,
            u.profile_pic,
            u.sector,
            u.is_verified,
            p.likes_count,
            p.comments_count
            " . ($userId ? ", EXISTS(SELECT 1 FROM likes WHERE post_id = p.id AND user_id = ?) as user_liked" : "") . "
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.id = ?
    ");
    
    if ($userId) {
        $stmt->execute([$userId, $postId]);
    } else {
        $stmt->execute([$postId]);
    }
    
    return $stmt->fetch();
}

/**
 * Create new post
 * @param int $userId User ID
 * @param string $caption Post caption
 * @param string $mediaUrl Media URL
 * @param string $mediaType Media type (image/video/audio)
 * @param string $privacy Privacy setting
 * @return int|false Post ID or false
 */
function createPost($userId, $caption, $mediaUrl = null, $mediaType = 'image', $privacy = 'public') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO posts (user_id, caption, media_url, media_type, privacy, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$userId, $caption, $mediaUrl, $mediaType, $privacy]);
        $postId = $pdo->lastInsertId();
        
        // Extract and save hashtags
        extractHashtags($postId, $caption);
        
        // Add XP
        addXP($userId, 'post');
        
        return $postId;
    } catch (PDOException $e) {
        error_log("Create post error: " . $e->getMessage());
        return false;
    }
}

// =======================================
// USER PROFILES
// =======================================

/**
 * Get user profile
 * @param int $userId User ID
 * @param int $currentUserId Current user ID (for follow status)
 * @return array|false User data
 */
function getUserProfile($userId, $currentUserId = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            u.*,
            (SELECT COUNT(*) FROM follows WHERE followee_id = u.id) as followers_count,
            (SELECT COUNT(*) FROM follows WHERE follower_id = u.id) as following_count,
            (SELECT COUNT(*) FROM posts WHERE user_id = u.id AND is_archived = 0) as posts_count
            " . ($currentUserId ? ", EXISTS(SELECT 1 FROM follows WHERE follower_id = ? AND followee_id = u.id) as is_following" : "") . "
        FROM users u
        WHERE u.id = ?
    ");
    
    if ($currentUserId) {
        $stmt->execute([$currentUserId, $userId]);
    } else {
        $stmt->execute([$userId]);
    }
    
    return $stmt->fetch();
}

/**
 * Get user's posts
 * @param int $userId User ID
 * @param int $limit Number of posts
 * @param int $offset Offset
 * @return array Posts
 */
function getUserPosts($userId, $limit = 20, $offset = 0) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT p.*, 
            (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count
        FROM posts p
        WHERE p.user_id = ?
        AND p.is_archived = 0
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $stmt->execute([$userId, $limit, $offset]);
    return $stmt->fetchAll();
}

/**
 * Update user profile
 * @param int $userId User ID
 * @param array $data Profile data
 * @return bool Success
 */
function updateUserProfile($userId, $data) {
    global $pdo;
    
    $allowedFields = ['bio', 'sector', 'profile_pic', 'banner_pic'];
    $updates = [];
    $params = [];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
        }
    }
    
    if (empty($updates)) {
        return false;
    }
    
    $params[] = $userId;
    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
    
    try {
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log("Update profile error: " . $e->getMessage());
        return false;
    }
}

// =======================================
// COMMENTS
// =======================================

/**
 * Get comments for a post
 * @param int $postId Post ID
 * @param int $limit Limit
 * @return array Comments
 */
function getComments($postId, $limit = 50) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            u.username,
            u.profile_pic,
            u.is_verified
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.post_id = ?
        ORDER BY c.created_at ASC
        LIMIT ?
    ");
    
    $stmt->execute([$postId, $limit]);
    return $stmt->fetchAll();
}

/**
 * Add comment
 * @param int $userId User ID
 * @param int $postId Post ID
 * @param string $content Comment content
 * @param int $parentId Parent comment ID (for replies)
 * @return int|false Comment ID
 */
function addComment($userId, $postId, $content, $parentId = null) {
    global $pdo;
    
    try {
        dbBeginTransaction();
        
        // Insert comment
        $stmt = $pdo->prepare("
            INSERT INTO comments (user_id, post_id, parent_id, content, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $postId, $parentId, $content]);
        $commentId = $pdo->lastInsertId();
        
        // Update post comment count
        $pdo->prepare("UPDATE posts SET comments_count = comments_count + 1 WHERE id = ?")
            ->execute([$postId]);
        
        // Add XP
        addXP($userId, 'comment');
        
        // Notify post owner
        $post = getPost($postId);
        if ($post && $post['user_id'] != $userId) {
            createNotification(
                $post['user_id'],
                $userId,
                'comment',
                'commented on your post',
                "/pages/post.php?id=$postId"
            );
        }
        
        dbCommit();
        return $commentId;
        
    } catch (Exception $e) {
        dbRollback();
        error_log("Add comment error: " . $e->getMessage());
        return false;
    }
}

// =======================================
// XP & GAMIFICATION
// =======================================

/**
 * Add XP to user
 * @param int $userId User ID
 * @param string $action Action type
 * @return bool Success
 */
function addXP($userId, $action) {
    global $pdo;
    
    $xpMap = [
        'post' => 10,
        'comment' => 5,
        'like' => 2,
        'follow' => 3,
        'story' => 8,
        'stream' => 15,
        'daily_login' => 5
    ];
    
    $xp = $xpMap[$action] ?? 0;
    if ($xp === 0) return false;
    
    try {
        dbBeginTransaction();
        
        // Log XP
        $pdo->prepare("INSERT INTO xp_log (user_id, action, xp_amount) VALUES (?, ?, ?)")
            ->execute([$userId, $action, $xp]);
        
        // Update user XP
        $pdo->prepare("UPDATE users SET xp = xp + ? WHERE id = ?")
            ->execute([$xp, $userId]);
        
        // Check for level up
        checkLevelUp($userId);
        
        dbCommit();
        return true;
        
    } catch (Exception $e) {
        dbRollback();
        error_log("Add XP error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check and handle level up
 * @param int $userId User ID
 */
function checkLevelUp($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT xp, level FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) return;
    
    // Calculate new level (100 XP per level)
    $newLevel = floor($user['xp'] / 100) + 1;
    
    if ($newLevel > $user['level']) {
        // Update level
        $pdo->prepare("UPDATE users SET level = ? WHERE id = ?")
            ->execute([$newLevel, $userId]);
        
        // Notify user
        createNotification(
            $userId,
            null,
            'system',
            "Congratulations! You've reached level $newLevel! 🎉",
            '/pages/profile.php'
        );
        
        // Award badge for milestone levels
        if ($newLevel % 5 == 0) {
            awardBadge($userId, "Level $newLevel Master", "Reached level $newLevel");
        }
    }
}

/**
 * Award badge to user
 * @param int $userId User ID
 * @param string $name Badge name
 * @param string $description Badge description
 * @return bool Success
 */
function awardBadge($userId, $name, $description = '') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO badges (user_id, name, description, awarded_at)
            VALUES (?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([$userId, $name, $description]);
        
        if ($result && $stmt->rowCount() > 0) {
            createNotification(
                $userId,
                null,
                'badge',
                "You earned the '$name' badge! 🏆",
                '/pages/profile.php'
            );
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Award badge error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get leaderboard
 * @param string $sector Filter by sector
 * @param int $limit Number of users
 * @return array Users
 */
function getLeaderboard($sector = 'all', $limit = 50) {
    global $pdo;
    
    $sql = "
        SELECT 
            id, username, profile_pic, sector, xp, level, is_verified,
            (SELECT COUNT(*) FROM follows WHERE followee_id = users.id) as followers_count
        FROM users
        WHERE is_banned = 0
    ";
    
    $params = [];
    
    if ($sector !== 'all') {
        $sql .= " AND sector = ?";
        $params[] = $sector;
    }
    
    $sql .= " ORDER BY xp DESC, level DESC LIMIT ?";
    $params[] = $limit;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

// =======================================
// NOTIFICATIONS
// =======================================

/**
 * Create notification
 * @param int $userId Recipient user ID
 * @param int $actorId Actor user ID
 * @param string $type Notification type
 * @param string $message Message
 * @param string $link Link URL
 * @return bool Success
 */
function createNotification($userId, $actorId, $type, $message, $link) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, actor_id, type, message, link, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([$userId, $actorId, $type, $message, $link]);
    } catch (PDOException $e) {
        error_log("Create notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get notifications for user
 * @param int $userId User ID
 * @param int $limit Limit
 * @return array Notifications
 */
function getNotifications($userId, $limit = 20) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            n.*,
            u.username as actor_username,
            u.profile_pic as actor_pic
        FROM notifications n
        LEFT JOIN users u ON n.actor_id = u.id
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT ?
    ");
    
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll();
}

/**
 * Mark notification as read
 * @param int $notificationId Notification ID
 * @param int $userId User ID
 * @return bool Success
 */
function markNotificationRead($notificationId, $userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE id = ? AND user_id = ?
    ");
    
    return $stmt->execute([$notificationId, $userId]);
}

// =======================================
// HASHTAGS
// =======================================

/**
 * Extract and save hashtags from text
 * @param int $postId Post ID
 * @param string $text Text to extract from
 */
function extractHashtags($postId, $text) {
    global $pdo;
    
    // Match hashtags
    preg_match_all('/#(\w+)/', $text, $matches);
    
    if (empty($matches[1])) return;
    
    foreach ($matches[1] as $tag) {
        $tag = strtolower($tag);
        
        // Insert or update hashtag
        $stmt = $pdo->prepare("
            INSERT INTO hashtags (tag, usage_count) 
            VALUES (?, 1)
            ON DUPLICATE KEY UPDATE usage_count = usage_count + 1
        ");
        $stmt->execute([$tag]);
        
        // Get hashtag ID
        $hashtagId = $pdo->lastInsertId() ?: $pdo->query("SELECT id FROM hashtags WHERE tag = '$tag'")->fetchColumn();
        
        // Link to post
        $pdo->prepare("INSERT IGNORE INTO post_hashtags (post_id, hashtag_id) VALUES (?, ?)")
            ->execute([$postId, $hashtagId]);
    }
}

// =======================================
// UTILITY FUNCTIONS
// =======================================

/**
 * Sanitize output for HTML
 * @param string $string String to sanitize
 * @return string Sanitized string
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Time ago format
 * @param string $datetime Datetime string
 * @return string Formatted time ago
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    
    return date('M j, Y', $time);
}
?>
