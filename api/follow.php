<?php
// =======================================
// FLIXSY FOLLOW API (api/follow.php)
// Handle follow/unfollow with real database
// =======================================

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check authentication
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Authentication required'
    ]);
    exit;
}

// Get and validate input
$data = json_decode(file_get_contents('php://input'), true);
$targetUserId = filter_var($data['target_id'] ?? null, FILTER_VALIDATE_INT);
$currentUserId = getCurrentUserId();

if (!$targetUserId) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid user ID'
    ]);
    exit;
}

// Can't follow yourself
if ($targetUserId == $currentUserId) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Cannot follow yourself'
    ]);
    exit;
}

try {
    global $pdo;
    
    // Check if target user exists
    $stmt = $pdo->prepare("SELECT id, username, is_banned FROM users WHERE id = ?");
    $stmt->execute([$targetUserId]);
    $targetUser = $stmt->fetch();
    
    if (!$targetUser) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'User not found'
        ]);
        exit;
    }
    
    if ($targetUser['is_banned']) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Cannot follow banned user'
        ]);
        exit;
    }
    
    // Check if already following
    $stmt = $pdo->prepare("
        SELECT id FROM follows 
        WHERE follower_id = ? AND followee_id = ?
    ");
    $stmt->execute([$currentUserId, $targetUserId]);
    $existingFollow = $stmt->fetch();
    
    // Begin transaction
    dbBeginTransaction();
    
    if ($existingFollow) {
        // UNFOLLOW
        $pdo->prepare("
            DELETE FROM follows 
            WHERE follower_id = ? AND followee_id = ?
        ")->execute([$currentUserId, $targetUserId]);
        
        $action = 'unfollowed';
        
    } else {
        // FOLLOW
        $pdo->prepare("
            INSERT INTO follows (follower_id, followee_id, created_at)
            VALUES (?, ?, NOW())
        ")->execute([$currentUserId, $targetUserId]);
        
        // Add XP
        addXP($currentUserId, 'follow');
        
        // Notify target user
        createNotification(
            $targetUserId,
            $currentUserId,
            'follow',
            'started following you',
            "/pages/profile.php?id=$currentUserId"
        );
        
        $action = 'followed';
    }
    
    // Commit transaction
    dbCommit();
    
    // Get updated counts
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM follows WHERE followee_id = ?) as followers_count,
            (SELECT COUNT(*) FROM follows WHERE follower_id = ?) as following_count
    ");
    $stmt->execute([$targetUserId, $currentUserId]);
    $counts = $stmt->fetch();
    
    // Success response
    echo json_encode([
        'status' => 'success',
        'action' => $action,
        'target_followers' => $counts['followers_count'],
        'your_following' => $counts['following_count'],
        'message' => ucfirst($action) . ' successfully'
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        dbRollback();
    }
    
    error_log("Follow API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred. Please try again.'
    ]);
}
?>
