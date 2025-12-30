<?php
// =======================================
// FLIXSY HOME PAGE - COMPLETE VERSION
// Real database integration
// =======================================

require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require login
requireLogin();

// Get current user
$currentUser = getCurrentUser();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get feed posts
$feed = getFeed($currentUser['id'], $limit, $offset);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flixsy | Home</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
</head>
<body>

    <div class="app-container">

        <!-- LEFT SIDEBAR -->
        <aside class="app-left-sidebar">
            <h1 class="flixsy-logo">Flixsy</h1>
            
            <nav>
                <a href="home.php" class="nav-link active">
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

            <!-- XP Progress Bar -->
            <div class="xp-progress-bar">
                <div class="xp-bar-label">
                    <span>Level <?= $currentUser['level'] ?></span>
                    <span><?= $currentUser['xp'] ?> XP</span>
                </div>
                <div class="xp-bar">
                    <?php 
                    $nextLevelXP = ($currentUser['level']) * 100;
                    $currentLevelXP = ($currentUser['level'] - 1) * 100;
                    $progress = ($currentUser['xp'] - $currentLevelXP) / ($nextLevelXP - $currentLevelXP) * 100;
                    ?>
                    <div class="xp-bar-fill" style="width: <?= min($progress, 100) ?>%;"></div>
                </div>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="app-main-content">
            <h2>Your Feed</h2>
            <hr style="border-color: rgba(255, 255, 255, 0.1); margin-bottom: 24px;">

            <?php if (empty($feed)): ?>
                <!-- Empty State -->
                <div class="flixsy-card" style="text-align: center; padding: 40px;">
                    <i class="fas fa-inbox" style="font-size: 4em; color: var(--color-text-subtle); margin-bottom: 20px;"></i>
                    <h3>Your feed is empty</h3>
                    <p style="color: var(--color-text-subtle); margin-bottom: 20px;">
                        Start following people to see their posts here!
                    </p>
                    <a href="explore.php" class="post-button" style="display: inline-block; text-decoration: none;">
                        <i class="fas fa-compass"></i> Explore Creators
                    </a>
                </div>
            <?php else: ?>
                <!-- Feed Posts -->
                <?php foreach ($feed as $post): ?>
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
                                     class="post-media"
                                     loading="lazy">
                            <?php elseif ($post['media_type'] === 'video'): ?>
                                <video src="../<?= e($post['media_url']) ?>" 
                                       class="post-media" 
                                       controls 
                                       preload="metadata"></video>
                            <?php elseif ($post['media_type'] === 'audio'): ?>
                                <audio src="../<?= e($post['media_url']) ?>" 
                                       controls 
                                       style="width: 100%; margin-top: 10px;"></audio>
                            <?php endif; ?>
                        <?php endif; ?>

                        <!-- Post Actions -->
                        <div class="post-actions">
                            <!-- Like Button -->
                            <button class="action-button like-button" 
                                    id="like-btn-<?= $post['id'] ?>" 
                                    data-post-id="<?= $post['id'] ?>"
                                    style="color: <?= $post['user_liked'] ? 'var(--color-secondary)' : 'var(--color-text-light)' ?>">
                                <i class="<?= $post['user_liked'] ? 'fas' : 'far' ?> fa-heart"></i>
                                <span class="like-count"><?= number_format($post['likes_count']) ?></span>
                            </button>

                            <!-- Comment Button -->
                            <button class="action-button" onclick="window.location.href='post.php?id=<?= $post['id'] ?>#comments'">
                                <i class="fas fa-comment"></i>
                                <span class="comment-count"><?= number_format($post['comments_count']) ?></span>
                            </button>

                            <!-- Share Button -->
                            <button class="action-button share-button" data-post-id="<?= $post['id'] ?>">
                                <i class="fas fa-share"></i> Share
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Pagination -->
                <?php if (count($feed) >= $limit): ?>
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="?page=<?= $page + 1 ?>" class="post-button" style="display: inline-block; text-decoration: none;">
                            Load More Posts
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

        </main>

        <!-- RIGHT SIDEBAR -->
        <aside class="app-right-section">
            <!-- Trending Section -->
            <div class="flixsy-card">
                <h3><i class="fas fa-chart-line"></i> Trending Now</h3>
                <?php 
                // Get trending hashtags (you can implement this later)
                $trendingTags = [
                    ['tag' => 'FlixsyLive', 'count' => '5.2K'],
                    ['tag' => 'NewCreator', 'count' => '1.8K'],
                    ['tag' => 'Gaming', 'count' => '950']
                ];
                ?>
                <ul style="list-style: none; padding-left: 0;">
                    <?php foreach ($trendingTags as $tag): ?>
                        <li style="margin-top: 10px;">
                            <a href="explore.php?tag=<?= urlencode($tag['tag']) ?>" style="color: var(--color-text-light);">
                                #<?= e($tag['tag']) ?> (<?= $tag['count'] ?> posts)
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Suggested Users -->
            <div class="flixsy-card" style="margin-top: 16px;">
                <h3><i class="fas fa-user-plus"></i> Suggested Creators</h3>
                <?php
                // Get suggested users (people you don't follow yet)
                global $pdo;
                $suggestedStmt = $pdo->prepare("
                    SELECT id, username, profile_pic, sector, is_verified
                    FROM users
                    WHERE id != ?
                    AND id NOT IN (SELECT followee_id FROM follows WHERE follower_id = ?)
                    AND is_banned = 0
                    ORDER BY xp DESC
                    LIMIT 3
                ");
                $suggestedStmt->execute([$currentUser['id'], $currentUser['id']]);
                $suggested = $suggestedStmt->fetchAll();
                ?>

                <?php if (empty($suggested)): ?>
                    <p style="color: var(--color-text-subtle); font-size: 0.9em;">
                        No suggestions right now. Check explore page!
                    </p>
                <?php else: ?>
                    <?php foreach ($suggested as $user): ?>
                        <div style="display: flex; align-items: center; margin-top: 15px; padding: 10px; background-color: var(--color-background-dark); border-radius: var(--border-radius);">
                            <img src="../<?= e($user['profile_pic']) ?>" 
                                 style="width: 40px; height: 40px; border-radius: 50%; margin-right: 10px;"
                                 onerror="this.src='https://via.placeholder.com/40/1E90FF/FFFFFF?text=<?= substr($user['username'], 0, 1) ?>'">
                            <div style="flex-grow: 1;">
                                <a href="profile.php?id=<?= $user['id'] ?>" style="font-weight: bold; display: block;">
                                    <?= e($user['username']) ?>
                                    <?php if ($user['is_verified']): ?>
                                        <i class="fas fa-check-circle" style="color: var(--color-primary); font-size: 0.8em;"></i>
                                    <?php endif; ?>
                                </a>
                                <span style="font-size: 0.8em; color: var(--color-text-subtle);">
                                    <?= e($user['sector']) ?>
                                </span>
                            </div>
                            <button class="post-button follow-quick-btn" 
                                    data-user-id="<?= $user['id'] ?>"
                                    style="padding: 5px 15px; font-size: 0.8em; width: auto;">
                                Follow
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>

    </div>

    <script src="../assets/js/main.js"></script>
    
    <!-- Quick Follow Handler -->
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // Handle quick follow buttons
        document.querySelectorAll('.follow-quick-btn').forEach(btn => {
            btn.addEventListener('click', async function() {
                const userId = this.dataset.userId;
                
                try {
                    const response = await fetch('../api/follow.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({target_id: userId})
                    });
                    
                    const data = await response.json();
                    
                    if (data.status === 'success') {
                        this.textContent = data.action === 'followed' ? 'Following' : 'Follow';
                        this.style.backgroundColor = data.action === 'followed' ? 'var(--color-surface-dark)' : 'var(--color-primary)';
                    }
                } catch (error) {
                    console.error('Follow error:', error);
                }
            });
        });
    });
    </script>

</body>
</html>
