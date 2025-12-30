<?php
// =======================================
// FLIXSY COMMENT API (api/comment.php)
// Handle comments and replies
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

$userId = getCurrentUserId();
$method = $_SERVER['REQUEST_METHOD'];

// =======================================
// GET COMMENTS FOR A POST
// =======================================
if ($method === 'GET') {
    $postId = filter_var($_GET['post_id'] ?? null, FILTER_VALIDATE_INT);
    
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
        
        // Get all comments for the post (including replies)
        $stmt = $pdo->prepare("
            SELECT 
                c.*,
                u.username,
                u.profile_pic,
                u.is_verified,
                (SELECT COUNT(*) FROM comments WHERE parent_id = c.id) as reply_count
            FROM comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.post_id = ?
            ORDER BY 
                CASE WHEN c.parent_id IS NULL THEN c.id ELSE c.parent_id END ASC,
                c.created_at ASC
        ");
        
        $stmt->execute([$postId]);
        $comments = $stmt->fetchAll();
        
        // Organize comments into threads
        $threaded = [];
        $replies = [];
        
        foreach ($comments as $comment) {
            if ($comment['parent_id'] === null) {
                $comment['replies'] = [];
                $threaded[$comment['id']] = $comment;
            } else {
                $replies[$comment['parent_id']][] = $comment;
            }
        }
        
        // Add replies to parent comments
        foreach ($replies as $parentId => $replyList) {
            if (isset($threaded[$parentId])) {
                $threaded[$parentId]['replies'] = $replyList;
            }
        }
        
        echo json_encode([
            'status' => 'success',
            'comments' => array_values($threaded),
            'total' => count($threaded)
        ]);
        
    } catch (Exception $e) {
        error_log("Get comments error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to load comments'
        ]);
    }
    
    exit;
}

// =======================================
// ADD NEW COMMENT OR REPLY
// =======================================
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $postId = filter_var($data['post_id'] ?? null, FILTER_VALIDATE_INT);
    $content = trim($data['content'] ?? '');
    $parentId = filter_var($data['parent_id'] ?? null, FILTER_VALIDATE_INT);
    
    // Validation
    if (!$postId) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid post ID'
        ]);
        exit;
    }
    
    if (empty($content)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Comment cannot be empty'
        ]);
        exit;
    }
    
    if (strlen($content) > 1000) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Comment too long (max 1000 characters)'
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
        
        // If replying, check parent comment exists
        if ($parentId) {
            $stmt = $pdo->prepare("
                SELECT id, user_id FROM comments 
                WHERE id = ? AND post_id = ?
            ");
            $stmt->execute([$parentId, $postId]);
            $parentComment = $stmt->fetch();
            
            if (!$parentComment) {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Parent comment not found'
                ]);
                exit;
            }
        }
        
        dbBeginTransaction();
        
        // Insert comment
        $stmt = $pdo->prepare("
            INSERT INTO comments (user_id, post_id, parent_id, content, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$userId, $postId, $parentId, $content]);
        $commentId = $pdo->lastInsertId();
        
        // Update post comment count
        $pdo->prepare("
            UPDATE posts 
            SET comments_count = comments_count + 1 
            WHERE id = ?
        ")->execute([$postId]);
        
        // Add XP
        addXP($userId, 'comment');
        
        // Notify post owner (if not self-comment)
        if ($post['user_id'] != $userId) {
            createNotification(
                $post['user_id'],
                $userId,
                'comment',
                'commented on your post',
                "/pages/post.php?id=$postId"
            );
        }
        
        // If replying, notify parent comment author
        if ($parentId && isset($parentComment) && $parentComment['user_id'] != $userId) {
            createNotification(
                $parentComment['user_id'],
                $userId,
                'comment',
                'replied to your comment',
                "/pages/post.php?id=$postId"
            );
        }
        
        dbCommit();
        
        // Get the created comment with user data
        $stmt = $pdo->prepare("
            SELECT 
                c.*,
                u.username,
                u.profile_pic,
                u.is_verified
            FROM comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.id = ?
        ");
        
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch();
        
        echo json_encode([
            'status' => 'success',
            'comment' => $comment,
            'message' => $parentId ? 'Reply added successfully' : 'Comment added successfully'
        ]);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            dbRollback();
        }
        
        error_log("Add comment error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to add comment'
        ]);
    }
    
    exit;
}

// =======================================
// DELETE COMMENT
// =======================================
if ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $commentId = filter_var($data['comment_id'] ?? null, FILTER_VALIDATE_INT);
    
    if (!$commentId) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid comment ID'
        ]);
        exit;
    }
    
    try {
        global $pdo;
        
        // Get comment
        $stmt = $pdo->prepare("
            SELECT id, user_id, post_id, parent_id 
            FROM comments 
            WHERE id = ?
        ");
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch();
        
        if (!$comment) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Comment not found'
            ]);
            exit;
        }
        
        // Check if user owns the comment or is admin
        if ($comment['user_id'] != $userId && !isAdmin()) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'You can only delete your own comments'
            ]);
            exit;
        }
        
        dbBeginTransaction();
        
        // Count replies (they will be deleted via CASCADE)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM comments 
            WHERE parent_id = ?
        ");
        $stmt->execute([$commentId]);
        $replyCount = $stmt->fetch()['count'];
        
        // Delete comment (CASCADE will delete replies)
        $pdo->prepare("DELETE FROM comments WHERE id = ?")
            ->execute([$commentId]);
        
        // Update post comment count
        $totalDeleted = 1 + $replyCount;
        $pdo->prepare("
            UPDATE posts 
            SET comments_count = GREATEST(comments_count - ?, 0)
            WHERE id = ?
        ")->execute([$totalDeleted, $comment['post_id']]);
        
        dbCommit();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Comment deleted successfully',
            'deleted_count' => $totalDeleted
        ]);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            dbRollback();
        }
        
        error_log("Delete comment error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to delete comment'
        ]);
    }
    
    exit;
}

// Invalid method
http_response_code(405);
echo json_encode([
    'status' => 'error',
    'message' => 'Method not allowed'
]);
?>
