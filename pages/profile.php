<?php
// =======================================
// FLIXSY PROFILE PAGE - COMPLETE VERSION
// =======================================

require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();

$currentUser = getCurrentUser();
$profileUserId = isset($_GET['id']) ? (int)$_GET['id'] : $currentUser['id'];

// Get profile data
$profile = getUserProfile($profileUserId, $currentUser['id']);

if (!$profile) {
    header("Location: home.php");
    exit;
}

// Check if viewing own profile
$isOwnProfile = ($profileUserId == $currentUser['id']);

// Get user's posts
$userPosts = getUserPosts($profileUserId, 20, 0);

// Get user's badges
global $pdo;
$badgesStmt = $pdo->prepare("
    SELECT * FROM badges 
    WHERE user_id = ? 
    ORDER BY awarded_at DESC
");
$badgesStmt->execute([$profileUserId]);
$badges = $badgesStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flixsy | <?= e($profile['username']) ?></title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
    
    <style>
        .profile-banner {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: var(--border-radius);
            position: relative;
            overflow: hidden;
        }
        
        .profile-header {
            display: flex;
            align-items: flex-end;
            margin-top: -60px;
            padding: 0 var(--space-lg);
            position: relative;
        }
        
        .profile-avatar-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid var(--color-background-dark);
            margin-right: var(--space-lg);
            object-fit: cover;
        }
        
        .profile-info {
            flex-grow: 1;
            padding-bottom: var(--space-md);
        }
        
        .profile-stats {
            display: flex;
            gap: var(--space-lg);
            margin-top: var(--space-sm);
        }
        
        .profile-stat {
            text-align: center;
        }
        
        .profile-stat-value {
            font-size: 1.5em;
            font-weight: bold;
            color: var(--color-primary);
        }
        
        .profile-stat-label {
            font-size: 0.9em;
            color: var(--color-text-subtle);
        }
        
        .profile-tabs {
            display: flex;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
            margin: var(--space-lg) 0;
            gap: var(--space-md);
        }
        
        .profile-tab {
            padding: var(--space-md) var(--space-lg);
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            color: var(--color-text-subtle);
        }
        
        .profile-tab.active {
            border-bottom-color: var(--color-primary);
            color: var(--color-text-light);
        }
        
        .profile-tab:hover {
            color: var(--color-primary);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .badge-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: var(--space-md);
            margin-top: var(--space-md);
        }
        
        .badge-card {
            background: var(--color-surface-dark);
            padding: var(--space-md);
            border-radius: var(--border-radius);
            text-align: center;
        }
        
        .badge-icon {
            font-size: 2em;
            margin-bottom: var(--space-sm);
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
                <a href="profile.php?id=<?= $currentUser['id'] ?>" class="nav-link active">
                    <i class="fas fa-user-circle"></i> Profile
                </a>
                <a href="settings.php" class="nav-link">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </nav>

            <a href="post.php" class="post-button">
                <i class="fas fa-plus"></i> Create Post
            </a>

            <div class="xp-progress-bar">
                <div class="xp-bar-label">
                    <span>Level <?= $currentUser['level'] ?></span>
                    <span><?= $currentUser['xp'] ?> XP</span>
                </div>
                <div class="xp-bar">
                    <?php 
                    $nextLevelXP = $currentUser['level'] * 100;
                    $currentLevelXP = ($currentUser['level'] - 1) * 100;
                    $progress = ($currentUser['xp'] - $currentLevelXP) / ($nextLevelXP - $currentLevelXP) * 100;
                    ?>
                    <div class="xp-bar-fill" style="width: <?= min($progress, 100) ?>%;"></div>
                </div>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="app-main-content">
            
            <!-- Profile Card -->
            <div class="flixsy-card" style="padding: 0; overflow: hidden;">
                <!-- Banner -->
                <div class="profile-banner" style="<?= $profile['banner_pic'] ? 'background-image: url(../' . e($profile['banner_pic']) . '); background-size: cover;' : '' ?>">
                </div>
                
                <!-- Profile Header -->
                <div class="profile-header">
                    <img src="../<?= e($profile['profile_pic']) ?>" 
                         alt="<?= e($profile['username']) ?>" 
                         class="profile-avatar-large"
                         onerror="this.src='https://via.placeholder.com/120/1E90FF/FFFFFF?text=<?= substr($profile['username'], 0, 1) ?>'">
                    
                    <div class="profile-info">
                        <h2 style="margin: 0;">
                            <?= e($profile['username']) ?>
                            <?php if ($profile['is_verified']): ?>
                                <i class="fas fa-check-circle" style="color: var(--color-primary);"></i>
                            <?php endif; ?>
                        </h2>
                        
                        <div class="profile-stats">
                            <div class="profile-stat">
                                <div class="profile-stat-value"><?= number_format($profile['posts_count']) ?></div>
                                <div class="profile-stat-label">Posts</div>
                            </div>
                            <div class="profile-stat">
                                <div class="profile-stat-value"><?= number_format($profile['followers_count']) ?></div>
                                <div class="profile-stat-label">Followers</div>
                            </div>
                            <div class="profile-stat">
                                <div class="profile-stat-value"><?= number_format($profile['following_count']) ?></div>
                                <div class="profile-stat-label">Following</div>
                            </div>
                            <div class="profile-stat">
                                <div class="profile-stat-value">Lv<?= $profile['level'] ?></div>
                                <div class="profile-stat-label"><?= number_format($profile['xp']) ?> XP</div>
                            </div>
                        </div>
                    </div>
                    
                    <div style="padding-bottom: var(--space-md);">
                        <?php if ($isOwnProfile): ?>
                            <a href="settings.php" class="post-button" style="text-decoration: none;">
                                <i class="fas fa-edit"></i> Edit Profile
                            </a>
                        <?php else: ?>
                            <button 
                                class="post-button follow-btn" 
                                id="follow-btn-<?= $profileUserId ?>"
                                data-user-id="<?= $profileUserId ?>"
                                style="<?= $profile['is_following'] ? 'background-color: var(--color-surface-dark);' : '' ?>">
                                <?php if ($profile['is_following']): ?>
                                    <i class="fas fa-check"></i> Following
                                <?php else: ?>
                                    <i class="fas fa-user-plus"></i> Follow
                                <?php endif; ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Bio & Sector -->
                <div style="padding: var(--space-lg);">
                    <?php if (!empty($profile['bio'])): ?>
                        <p style="margin-bottom: var(--space-md);"><?= nl2br(e($profile['bio'])) ?></p>
                    <?php endif; ?>
                    
                    <div style="display: flex; gap: var(--space-md); flex-wrap: wrap;">
                        <span style="background: var(--color-primary); padding: 5px 15px; border-radius: 20px; font-size: 0.9em;">
                            <i class="fas fa-tag"></i> <?= e($profile['sector']) ?>
                        </span>
                        <span style="background: var(--color-surface-dark); padding: 5px 15px; border-radius: 20px; font-size: 0.9em;">
                            <i class="fas fa-calendar"></i> Joined <?= date('M Y', strtotime($profile['created_at'])) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Profile Tabs -->
            <div class="profile-tabs">
                <div class="profile-tab active" data-tab="posts">
                    <i class="fas fa-th"></i> Posts
                </div>
                <div class="profile-tab" data-tab="badges">
                    <i class="fas fa-award"></i> Badges (<?= count($badges) ?>)
                </div>
            </div>

            <!-- Posts Tab -->
            <div class="tab-content active" id="posts-tab">
                <?php if (empty($userPosts)): ?>
                    <div class="flixsy-card" style="text-align: center; padding: 40px;">
                        <i class="fas fa-image" style="font-size: 3em; color: var(--color-text-subtle); margin-bottom: 15px;"></i>
                        <p style="color: var(--color-text-subtle);">No posts yet</p>
                    </div>
                <?php else: ?>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-sm);">
                        <?php foreach ($userPosts as $post): ?>
                            <a href="post.php?id=<?= $post['id'] ?>" style="position: relative; aspect-ratio: 1; overflow: hidden; border-radius: var(--border-radius);">
                                <?php if ($post['media_url']): ?>
                                    <img src="../<?= e($post['media_url']) ?>" 
                                         style="width: 100%; height: 100%; object-fit: cover;"
                                         alt="Post">
                                <?php else: ?>
                                    <div style="width: 100%; height: 100%; background: var(--color-surface-dark); display: flex; align-items: center; justify-content: center; padding: 10px;">
                                        <p style="font-size: 0.8em; text-align: center;">
                                            <?= substr(e($post['caption']), 0, 100) ?>...
                                        </p>
                                    </div>
                                <?php endif; ?>
                                
                                <div style="position: absolute; bottom: 5px; left: 5px; right: 5px; display: flex; justify-content: space-between; color: white; font-size: 0.8em; text-shadow: 1px 1px 2px black;">
                                    <span><i class="fas fa-heart"></i> <?= $post['likes_count'] ?></span>
                                    <span><i class="fas fa-comment"></i> <?= $post['comments_count'] ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Badges Tab -->
            <div class="tab-content" id="badges-tab">
                <?php if (empty($badges)): ?>
                    <div class="flixsy-card" style="text-align: center; padding: 40px;">
                        <i class="fas fa-trophy" style="font-size: 3em; color: var(--color-text-subtle); margin-bottom: 15px;"></i>
                        <p style="color: var(--color-text-subtle);">No badges earned yet</p>
                    </div>
                <?php else: ?>
                    <div class="badge-grid">
                        <?php foreach ($badges as $badge): ?>
                            <div class="badge-card">
                                <div class="badge-icon">
                                    <?php
                                    // Map badge names to icons
                                    $icon = 'fa-trophy';
                                    if (strpos($badge['name'], 'Level') !== false) $icon = 'fa-star';
                                    if (strpos($badge['name'], 'Creator') !== false) $icon = 'fa-crown';
                                    if (strpos($badge['name'], 'Streak') !== false) $icon = 'fa-fire';
                                    ?>
                                    <i class="fas <?= $icon ?>" style="color: var(--color-secondary);"></i>
                                </div>
                                <strong style="display: block; margin-bottom: 5px;"><?= e($badge['name']) ?></strong>
                                <?php if ($badge['description']): ?>
                                    <p style="font-size: 0.8em; color: var(--color-text-subtle);">
                                        <?= e($badge['description']) ?>
                                    </p>
                                <?php endif; ?>
                                <p style="font-size: 0.7em; color: var(--color-text-subtle); margin-top: 5px;">
                                    <?= date('M d, Y', strtotime($badge['awarded_at'])) ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </main>

        <!-- RIGHT SIDEBAR -->
        <aside class="app-right-section">
            <div class="flixsy-card">
                <h3><i class="fas fa-chart-line"></i> Activity</h3>
                <div style="margin-top: 15px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span>Posts</span>
                        <strong><?= number_format($profile['posts_count']) ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span>Total XP</span>
                        <strong><?= number_format($profile['xp']) ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span>Level</span>
                        <strong><?= $profile['level'] ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>Badges</span>
                        <strong><?= count($badges) ?></strong>
                    </div>
                </div>
            </div>
        </aside>

    </div>

    <script src="../assets/js/main.js"></script>
    
    <script>
    // Tab switching
    document.querySelectorAll('.profile-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            // Remove active class from all tabs and content
            document.querySelectorAll('.profile-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            // Add active class to clicked tab
            this.classList.add('active');
            
            // Show corresponding content
            const tabName = this.dataset.tab;
            document.getElementById(tabName + '-tab').classList.add('active');
        });
    });
    
    // Follow button handler
    <?php if (!$isOwnProfile): ?>
    document.getElementById('follow-btn-<?= $profileUserId ?>').addEventListener('click', async function() {
        const btn = this;
        const userId = this.dataset.userId;
        
        try {
            const response = await fetch('../api/follow.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({target_id: userId})
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                if (data.action === 'followed') {
                    btn.innerHTML = '<i class="fas fa-check"></i> Following';
                    btn.style.backgroundColor = 'var(--color-surface-dark)';
                } else {
                    btn.innerHTML = '<i class="fas fa-user-plus"></i> Follow';
                    btn.style.backgroundColor = 'var(--color-primary)';
                }
                
                // Update follower count
                const followerCountElem = document.querySelector('.profile-stat-value');
                if (followerCountElem) {
                    const currentCount = parseInt(followerCountElem.textContent.replace(/,/g, ''));
                    const newCount = data.action === 'followed' ? currentCount + 1 : currentCount - 1;
                    followerCountElem.textContent = newCount.toLocaleString();
                }
            }
        } catch (error) {
            console.error('Follow error:', error);
            alert('Failed to update follow status');
        }
    });
    <?php endif; ?>
    </script>

</body>
</html>
