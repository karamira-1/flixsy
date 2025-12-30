<?php
// =======================================
// FLIXSY NOTIFICATIONS PAGE
// =======================================

require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();

$currentUser = getCurrentUser();

// Get notifications
$notifications = getNotifications($currentUser['id'], 50);

// Handle mark as read
if (isset($_GET['mark_read'])) {
    $notifId = (int)$_GET['mark_read'];
    markNotificationRead($notifId, $currentUser['id']);
    header("Location: notifications.php");
    exit;
}

// Handle mark all as read
if (isset($_POST['mark_all_read'])) {
    global $pdo;
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")
        ->execute([$currentUser['id']]);
    header("Location: notifications.php");
    exit;
}

// Count unread
$unreadCount = 0;
foreach ($notifications as $notif) {
    if (!$notif['is_read']) $unreadCount++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flixsy | Notifications (<?= $unreadCount ?>)</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
    
    <style>
        .notification-item {
            display: flex;
            align-items: center;
            padding: var(--space-md);
            border-radius: var(--border-radius);
            margin-bottom: var(--space-sm);
            transition: background 0.3s;
            cursor: pointer;
        }
        
        .notification-item:hover {
            background: var(--color-background-dark);
        }
        
        .notification-item.unread {
            background: rgba(30, 144, 255, 0.1);
            border-left: 3px solid var(--color-primary);
        }
        
        .notification-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin-right: var(--space-md);
            object-fit: cover;
        }
        
        .notification-content {
            flex-grow: 1;
        }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: var(--space-md);
        }
        
        .notification-icon.like {
            background: rgba(255, 69, 0, 0.2);
            color: var(--color-secondary);
        }
        
        .notification-icon.comment {
            background: rgba(30, 144, 255, 0.2);
            color: var(--color-primary);
        }
        
        .notification-icon.follow {
            background: rgba(60, 179, 113, 0.2);
            color: var(--color-success);
        }
        
        .notification-icon.badge {
            background: rgba(255, 215, 0, 0.2);
            color: #FFD700;
        }
        
        .notification-icon.system {
            background: rgba(138, 43, 226, 0.2);
            color: #8A2BE2;
        }
        
        .filter-tabs {
            display: flex;
            gap: var(--space-md);
            margin-bottom: var(--space-lg);
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }
        
        .filter-tab {
            padding: var(--space-md);
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            color: var(--color-text-subtle);
        }
        
        .filter-tab.active {
            border-bottom-color: var(--color-primary);
            color: var(--color-text-light);
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
                <a href="notifications.php" class="nav-link active">
                    <i class="fas fa-bell"></i> Notifications
                    <?php if ($unreadCount > 0): ?>
                        <span style="background: var(--color-error); color: white; border-radius: 10px; padding: 2px 6px; font-size: 0.7em; margin-left: 5px;">
                            <?= $unreadCount ?>
                        </span>
                    <?php endif; ?>
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
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-lg);">
                <h2>
                    <i class="fas fa-bell"></i> Notifications
                    <?php if ($unreadCount > 0): ?>
                        <span style="color: var(--color-primary);">(<?= $unreadCount ?> new)</span>
                    <?php endif; ?>
                </h2>
                
                <?php if ($unreadCount > 0): ?>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="mark_all_read" class="post-button" style="width: auto; padding: 8px 16px;">
                            <i class="fas fa-check-double"></i> Mark All Read
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <hr style="border-color: rgba(255, 255, 255, 0.1); margin-bottom: 24px;">

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <div class="filter-tab active" data-filter="all">
                    All Notifications
                </div>
                <div class="filter-tab" data-filter="like">
                    <i class="fas fa-heart"></i> Likes
                </div>
                <div class="filter-tab" data-filter="comment">
                    <i class="fas fa-comment"></i> Comments
                </div>
                <div class="filter-tab" data-filter="follow">
                    <i class="fas fa-user-plus"></i> Follows
                </div>
                <div class="filter-tab" data-filter="badge">
                    <i class="fas fa-trophy"></i> Badges
                </div>
            </div>

            <!-- Notifications List -->
            <div class="flixsy-card" style="padding: 0;">
                <?php if (empty($notifications)): ?>
                    <div style="text-align: center; padding: 60px 20px;">
                        <i class="fas fa-bell-slash" style="font-size: 4em; color: var(--color-text-subtle); margin-bottom: 20px;"></i>
                        <h3>No notifications yet</h3>
                        <p style="color: var(--color-text-subtle);">
                            When people interact with your posts, you'll see it here
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                        <div class="notification-item <?= $notif['is_read'] ? '' : 'unread' ?> notification-type-<?= $notif['type'] ?>"
                             onclick="window.location.href='<?= $notif['link'] ?? '#' ?>'">
                            
                            <?php if ($notif['actor_pic']): ?>
                                <img src="../<?= e($notif['actor_pic']) ?>" 
                                     alt="<?= e($notif['actor_username'] ?? 'System') ?>" 
                                     class="notification-avatar"
                                     onerror="this.src='https://via.placeholder.com/50/1E90FF/FFFFFF?text=?'">
                            <?php else: ?>
                                <div class="notification-avatar" style="background: var(--color-primary); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-bell" style="font-size: 1.5em;"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="notification-content">
                                <p style="margin: 0;">
                                    <?php if ($notif['actor_username']): ?>
                                        <strong><?= e($notif['actor_username']) ?></strong>
                                    <?php endif; ?>
                                    <?= e($notif['message']) ?>
                                </p>
                                <small style="color: var(--color-text-subtle);">
                                    <?= timeAgo($notif['created_at']) ?>
                                </small>
                            </div>
                            
                            <div class="notification-icon <?= $notif['type'] ?>">
                                <?php
                                $icons = [
                                    'like' => 'fa-heart',
                                    'comment' => 'fa-comment',
                                    'follow' => 'fa-user-plus',
                                    'mention' => 'fa-at',
                                    'badge' => 'fa-trophy',
                                    'system' => 'fa-info-circle'
                                ];
                                $icon = $icons[$notif['type']] ?? 'fa-bell';
                                ?>
                                <i class="fas <?= $icon ?>"></i>
                            </div>
                            
                            <?php if (!$notif['is_read']): ?>
                                <div style="width: 10px; height: 10px; background: var(--color-primary); border-radius: 50%; margin-left: var(--space-sm);"></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </main>

        <!-- RIGHT SIDEBAR -->
        <aside class="app-right-section">
            <div class="flixsy-card">
                <h3><i class="fas fa-chart-bar"></i> Notification Stats</h3>
                <div style="margin-top: 15px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span>Total</span>
                        <strong><?= count($notifications) ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span>Unread</span>
                        <strong style="color: var(--color-primary);"><?= $unreadCount ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>Today</span>
                        <strong>
                            <?php
                            $todayCount = 0;
                            foreach ($notifications as $n) {
                                if (date('Y-m-d', strtotime($n['created_at'])) == date('Y-m-d')) {
                                    $todayCount++;
                                }
                            }
                            echo $todayCount;
                            ?>
                        </strong>
                    </div>
                </div>
            </div>
            
            <div class="flixsy-card" style="margin-top: 16px;">
                <h3><i class="fas fa-cog"></i> Quick Settings</h3>
                <a href="settings.php#notifications-section" style="display: block; margin-top: 10px; color: var(--color-primary);">
                    <i class="fas fa-bell"></i> Notification Preferences
                </a>
            </div>
        </aside>

    </div>

    <script src="../assets/js/main.js"></script>
    
    <script>
    // Filter notifications
    document.querySelectorAll('.filter-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            // Update active tab
            document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            const filter = this.dataset.filter;
            
            // Show/hide notifications
            document.querySelectorAll('.notification-item').forEach(item => {
                if (filter === 'all' || item.classList.contains('notification-type-' + filter)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });
    </script>

</body>
</html>
