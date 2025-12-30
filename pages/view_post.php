<?php
// =======================================
// FLIXSY SINGLE POST VIEW WITH COMMENTS
// =======================================

require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();

$currentUser = getCurrentUser();
$postId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$postId) {
    header("Location: home.php");
    exit;
}

// Get post
$post = getPost($postId, $currentUser['id']);

if (!$post) {
    header("Location: home.php");
    exit;
}

// Get comments
$comments = getComments($postId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flixsy | Post by <?= e($post['username']) ?></title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
    
    <style>
        .post-view-container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .comment-item {
            display: flex;
            gap: var(--space-md);
            padding: var(--space-md) 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .comment-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        
        .comment-content {
            flex: 1;
        }
        
        .comment-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 5px;
        }
        
        .comment-username {
            font-weight: bold;
        }
        
        .comment-time {
            color: var(--color-text-subtle);
            font-size: 0.85em;
        }
        
        .comment-text {
            color: var(--color-text-light);
            margin-bottom: 8px;
            line-height: 1.5;
        }
        
        .comment-actions {
            display: flex;
            gap: var(--space-md);
            font-size: 0.9em;
        }
        
        .comment-action-btn {
            background: none;
            border: none;
            color: var(--color-text-subtle);
            cursor: pointer;
            padding: 0;
            transition: color 0.3s;
        }
        
        .comment-action-btn:hover {
            color: var(--color-primary);
        }
        
        .reply-form {
            display: none;
            margin-top: var(--space-sm);
        }
        
        .reply-form.active {
            display: block;
        }
        
        .comment-replies {
            margin-left: 40px;
            margin-top: var(--space-md);
        }
        
        .comment-input-container {
            display: flex;
            gap: var(--space-md);
            padding: var(--space-lg);
            background: var(--color-background-dark);
            border-radius: var(--border-radius);
            margin-top: var(--space-md);
        }
        
        .comment-input {
            flex: 1;
            background: var(--color-surface-dark);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 12px 20px;
            color: var(--color-text-light);
            font-family: inherit;
            resize: none;
            max-height: 100px;
        }
        
        .comment-input:focus {
            outline: none;
            border-color: var(--color-primary);
        }
    </style>
</head>
<body>

    <div class="app-container">

        <!-- LEFT SIDEBAR -->
        <aside class="app-left-sidebar">
            <h1 class="flixsy-logo">Flixsy</h1>
            
            <nav>
                <a href="home.php" class="nav-link">
                    <i class="fas fa-home"></i> Home
                </a>
                <a href="explore.php" class="nav-link">
                    <i class="fas fa-compass"></i> Explore
                </a>
                <a href="notifications.php" class="nav-link">
                    <i class="fas fa-bell"></i> Notifications
                </a>
                <a href="chat.php" class="nav-link">
                    <i class="fas fa-comments"></i> Chat
                </a>
                <a href="profile.php?id=<?= $currentUser['id'] ?>" class="nav-link">
                    <i class="fas fa-user-circle"></i> Profile
                </a>
                <a href="settings.php" class="nav-link">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </nav>

            <a href="post.php" class="post-button">
                <i class="fas fa-plus"></i> Create Post
            </a>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="app-main-content">
            
            <div class="post-view-container">
                
                <!-- Back Button -->
                <a href="javascript:history.back()" style="display: inline-flex; align-items: center; gap: 8px; margin-bottom: var(--space-lg); color: var(--color-text-subtle);">
                    <i class="fas fa-arrow-left"></i> Back
                </a>

                <!-- Post Card -->
                <div class="flixsy-card">
                    <!-- Post Header -->
                    <div class="post-header">
                        <img src="../<?= e($post['profile_pic']) ?>" 
                             alt="<?= e($post['username']) ?>" 
                             class="post-avatar"
                             onerror="this.src='https://via.placeholder.com/50/1E90FF/FFFFFF?text=<?= substr($post['username'], 0, 1) ?>'">
                        <div>
                            <a href="profile.php?id=<?= $post['user_id'] ?>" class="post-username">
                                <?= e($post['username']) ?>
                                <?php if ($post['is_verified']): ?>
                                    <i class="fas fa-check-circle" style="color: var(--color-primary); font-size: 0.9em;"></i>
                                <?php endif; ?>
                            </a>
                            <div class="post-metadata">
                                <?= timeAgo($post['created_at']) ?> • <?= e($post['sector']) ?> Sector
                            </div>
                        </div>
                    </div>

                    <!-- Post Caption -->
                    <?php if (!empty($post['caption'])): ?>
                        <p class="post-text"><?= nl2br(e($post['caption'])) ?></p>
                    <?php endif; ?>

                    <!-- Post Media -->
                    <?php if ($post['media_url']): ?>
                        <?php if ($post['media_type'] === 'image'): ?>
                            <img src="../<?= e($post['media_url']) ?>" 
                                 alt="Post media" 
                                 class="post-media">
                        <?php elseif ($post['media_type'] === 'video'): ?>
                            <video src="../<?= e($post['media_url']) ?>" 
                                   class="post-media" 
                                   controls></video>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Post Actions -->
                    <div class="post-actions">
                        <button class="action-button like-button" 
                                id="like-btn-<?= $post['id'] ?>" 
                                data-post-id="<?= $post['id'] ?>"
                                style="color: <?= $post['user_liked'] ? 'var(--color-secondary)' : 'var(--color-text-light)' ?>">
                            <i class="<?= $post['user_liked'] ? 'fas' : 'far' ?> fa-heart"></i>
                            <span class="like-count"><?= number_format($post['likes_count']) ?></span>
                        </button>

                        <button class="action-button">
                            <i class="fas fa-comment"></i>
                            <span class="comment-count"><?= number_format($post['comments_count']) ?></span>
                        </button>

                        <button class="action-button share-button">
                            <i class="fas fa-share"></i> Share
                        </button>
                    </div>
                </div>

                <!-- Comments Section -->
                <div class="flixsy-card" style="margin-top: var(--space-md);">
                    <h3 style="margin-bottom: var(--space-lg);">
                        <i class="fas fa-comments"></i> Comments (<?= count($comments) ?>)
                    </h3>

                    <!-- Add Comment -->
                    <div class="comment-input-container">
                        <img src="../<?= e($currentUser['profile_pic']) ?>" 
                             class="comment-avatar"
                             alt="Your avatar">
                        <textarea id="comment-input" 
                                  class="comment-input" 
                                  placeholder="Write a comment..."
                                  rows="1"></textarea>
                        <button onclick="addComment(<?= $postId ?>)" 
                                class="post-button" 
                                style="padding: 10px 20px; height: fit-content;">
                            Post
                        </button>
                    </div>

                    <!-- Comments List -->
                    <div id="comments-list" style="margin-top: var(--space-lg);">
                        <?php if (empty($comments)): ?>
                            <p style="text-align: center; color: var(--color-text-subtle); padding: 40px 0;">
                                No comments yet. Be the first to comment!
                            </p>
                        <?php else: ?>
                            <?php foreach ($comments as $comment): ?>
                                <?php if ($comment['parent_id'] === null): ?>
                                    <div class="comment-item" data-comment-id="<?= $comment['id'] ?>">
                                        <img src="../<?= e($comment['profile_pic']) ?>" 
                                             class="comment-avatar"
                                             alt="<?= e($comment['username']) ?>">
                                        
                                        <div class="comment-content">
                                            <div class="comment-header">
                                                <span class="comment-username">
                                                    <?= e($comment['username']) ?>
                                                    <?php if ($comment['is_verified']): ?>
                                                        <i class="fas fa-check-circle" style="color: var(--color-primary); font-size: 0.8em;"></i>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="comment-time"><?= timeAgo($comment['created_at']) ?></span>
                                            </div>
                                            
                                            <div class="comment-text"><?= nl2br(e($comment['content'])) ?></div>
                                            
                                            <div class="comment-actions">
                                                <button class="comment-action-btn" onclick="toggleReplyForm(<?= $comment['id'] ?>)">
                                                    <i class="fas fa-reply"></i> Reply
                                                </button>
                                                
                                                <?php if ($comment['user_id'] == $currentUser['id']): ?>
                                                    <button class="comment-action-btn" onclick="deleteComment(<?= $comment['id'] ?>)">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Reply Form -->
                                            <div class="reply-form" id="reply-form-<?= $comment['id'] ?>">
                                                <div class="comment-input-container" style="margin-top: var(--space-md);">
                                                    <img src="../<?= e($currentUser['profile_pic']) ?>" 
                                                         class="comment-avatar"
                                                         alt="Your avatar">
                                                    <textarea id="reply-input-<?= $comment['id'] ?>" 
                                                              class="comment-input" 
                                                              placeholder="Write a reply..."
                                                              rows="1"></textarea>
                                                    <button onclick="addReply(<?= $postId ?>, <?= $comment['id'] ?>)" 
                                                            class="post-button" 
                                                            style="padding: 10px 20px; height: fit-content;">
                                                        Reply
                                                    </button>
                                                </div>
                                            </div>
                                            
                                            <!-- Replies -->
                                            <?php
                                            $replies = array_filter($comments, function($c) use ($comment) {
                                                return $c['parent_id'] == $comment['id'];
                                            });
                                            ?>
                                            
                                            <?php if (!empty($replies)): ?>
                                                <div class="comment-replies">
                                                    <?php foreach ($replies as $reply): ?>
                                                        <div class="comment-item" data-comment-id="<?= $reply['id'] ?>">
                                                            <img src="../<?= e($reply['profile_pic']) ?>" 
                                                                 class="comment-avatar"
                                                                 alt="<?= e($reply['username']) ?>">
                                                            
                                                            <div class="comment-content">
                                                                <div class="comment-header">
                                                                    <span class="comment-username">
                                                                        <?= e($reply['username']) ?>
                                                                        <?php if ($reply['is_verified']): ?>
                                                                            <i class="fas fa-check-circle" style="color: var(--color-primary); font-size: 0.8em;"></i>
                                                                        <?php endif; ?>
                                                                    </span>
                                                                    <span class="comment-time"><?= timeAgo($reply['created_at']) ?></span>
                                                                </div>
                                                                
                                                                <div class="comment-text"><?= nl2br(e($reply['content'])) ?></div>
                                                                
                                                                <?php if ($reply['user_id'] == $currentUser['id']): ?>
                                                                    <div class="comment-actions">
                                                                        <button class="comment-action-btn" onclick="deleteComment(<?= $reply['id'] ?>)">
                                                                            <i class="fas fa-trash"></i> Delete
                                                                        </button>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

        </main>

        <!-- RIGHT SIDEBAR -->
        <aside class="app-right-section">
            <div class="flixsy-card">
                <h3><i class="fas fa-user"></i> About Creator</h3>
                <div style="margin-top: 15px;">
                    <img src="../<?= e($post['profile_pic']) ?>" 
                         style="width: 80px; height: 80px; border-radius: 50%; margin-bottom: 15px;"
                         alt="<?= e($post['username']) ?>">
                    <div style="font-weight: bold; margin-bottom: 5px;">
                        <?= e($post['username']) ?>
                    </div>
                    <div style="color: var(--color-text-subtle); font-size: 0.9em;">
                        <?= e($post['sector']) ?> Sector
                    </div>
                    
                    <?php if ($post['user_id'] != $currentUser['id']): ?>
                        <button class="post-button follow-btn" 
                                data-user-id="<?= $post['user_id'] ?>"
                                style="width: 100%; margin-top: 15px;">
                            <i class="fas fa-user-plus"></i> Follow
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </aside>

    </div>

    <script src="../assets/js/main.js"></script>
    
    <script>
        // Add comment
        async function addComment(postId) {
            const input = document.getElementById('comment-input');
            const content = input.value.trim();
            
            if (!content) return;
            
            try {
                const response = await fetch('../api/comment.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        post_id: postId,
                        content: content
                    })
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                alert('Failed to add comment');
            }
        }
        
        // Add reply
        async function addReply(postId, parentId) {
            const input = document.getElementById('reply-input-' + parentId);
            const content = input.value.trim();
            
            if (!content) return;
            
            try {
                const response = await fetch('../api/comment.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        post_id: postId,
                        parent_id: parentId,
                        content: content
                    })
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                alert('Failed to add reply');
            }
        }
        
        // Delete comment
        async function deleteComment(commentId) {
            if (!confirm('Delete this comment?')) return;
            
            try {
                const response = await fetch('../api/comment.php', {
                    method: 'DELETE',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({comment_id: commentId})
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                alert('Failed to delete comment');
            }
        }
        
        // Toggle reply form
        function toggleReplyForm(commentId) {
            const form = document.getElementById('reply-form-' + commentId);
            form.classList.toggle('active');
        }
        
        // Auto-expand textarea
        document.querySelectorAll('.comment-input').forEach(textarea => {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
        });
    </script>

</body>
</html>
