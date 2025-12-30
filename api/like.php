<?php
// =======================================
// FLIXSY LIKE API (api/like.php)
// Handle post like/unlike with real database
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
$postId = filter_var($data['post_id'] ?? null, FILTER_VALIDATE_INT);
$userId = getCurrentUserId();

if (!$postId) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid post ID'
    ]);
    exit;
}

try {
    global $pdo;
    
    // Check if post exists
    $stmt = $pdo->prepare("SELECT id, user_id FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    $post = $stmt->fetch();
    
    if (!$post) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Post not found'
        ]);
        exit;
    }
    
    // Check if already liked
    $stmt = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND post_id = ?");
    $stmt->execute([$userId, $postId]);
    $existingLike = $stmt->fetch();
    
    // Begin transaction
    dbBeginTransaction();
    
    if ($existingLike) {
        // UNLIKE
        $pdo->prepare("DELETE FROM likes WHERE user_id = ? AND post_id = ?")
            ->execute([$userId, $postId]);
        
        $pdo->prepare("UPDATE posts SET likes_count = GREATEST(likes_count - 1, 0) WHERE id = ?")
            ->execute([$postId]);
        
        $action = 'unliked';
        
    } else {
        // LIKE
        $pdo->prepare("INSERT INTO likes (user_id, post_id, created_at) VALUES (?, ?, NOW())")
            ->execute([$userId, $postId]);
        
        $pdo->prepare("UPDATE posts SET likes_count = likes_count + 1 WHERE id = ?")
            ->execute([$postId]);
        
        // Add XP
        addXP($userId, 'like');
        
        // Notify post owner (if not self-like)
        if ($post['user_id'] != $userId) {
            createNotification(
                $post['user_id'],
                $userId,
                'like',
                'liked your post',
                "/pages/post.php?id=$postId"
            );
        }
        
        $action = 'liked';
    }
    
    // Commit transaction
    dbCommit();
    
    // Get updated count
    $stmt = $pdo->prepare("SELECT likes_count FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    $updatedPost = $stmt->fetch();
    
    // Success response
    echo json_encode([
        'status' => 'success',
        'action' => $action,
        'new_count' => $updatedPost['likes_count'],
        'message' => ucfirst($action) . ' successfully'
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        dbRollback();
    }
    
    error_log("Like API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred. Please try again.'
    ]);
}
?>
