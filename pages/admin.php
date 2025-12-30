<?php
// =======================================
// FLIXSY ADMIN DASHBOARD - COMPLETE VERSION
// =======================================

require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();
requireAdmin();

$currentUser = getCurrentUser();

// Get statistics
global $pdo;

// Total counts
$stats = [
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'total_posts' => $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn(),
    'total_comments' => $pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn(),
    'total_likes' => $pdo->query("SELECT COUNT(*) FROM likes")->fetchColumn(),
    'total_stories' => $pdo->query("SELECT COUNT(*) FROM stories WHERE expires_at > NOW()")->fetchColumn(),
    'banned_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE is_banned = 1")->fetchColumn(),
];

// Today's stats
$stats['new_users_today'] = $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$stats['new_posts_today'] = $pdo->query("SELECT COUNT(*) FROM posts WHERE DATE(created_at) = CURDATE()")->fetchColumn();

// Get growth data for last 30 days
$growthData = $pdo->query("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as count
    FROM users
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
")->fetchAll();

// Posts per day (last 30 days)
$postsData = $pdo->query("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as count
    FROM posts
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
")->fetchAll();

// Engagement data (last 30 days)
$engagementData = $pdo->query("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as count
    FROM likes
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
")->fetchAll();

// Comments per day
$commentsData = $pdo->query("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as count
    FROM comments
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
")->fetchAll();

// Top users by XP
$topUsers = $pdo->query("
    SELECT id, username, xp, level, profile_pic,
           (SELECT COUNT(*) FROM posts WHERE user_id = users.id) as post_count,
           (SELECT COUNT(*) FROM follows WHERE followee_id = users.id) as follower_count
    FROM users
    WHERE is_banned = 0
    ORDER BY xp DESC
    LIMIT 10
")->fetchAll();

// Recent reports
$reports = $pdo->query("
    SELECT r.*, 
           u1.username as reporter_username,
           u2.username as target_username
    FROM reports r
    LEFT JOIN users u1 ON r.reporter_id = u1.id
    LEFT JOIN users u2 ON r.target_id = u2.id
    ORDER BY r.created_at DESC
    LIMIT 10
")->fetchAll();

// Sector distribution
$sectorData = $pdo->query("
    SELECT sector, COUNT(*) as count
    FROM users
    GROUP BY sector
    ORDER BY count DESC
")->fetchAll();

// Prepare data for charts
$userGrowthDates = array_column($growthData, 'date');
$userGrowthCounts = array_column($growthData, 'count');

$postsDates = array_column($postsData, 'date');
$postsCounts = array_column($postsData, 'count');

$likesDates = array_column($engagementData, 'date');
$likesCounts = array_column($engagementData, 'count');

$commentsDates = array_column($commentsData, 'date');
$commentsCounts = array_column($commentsData, 'count');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flixsy | Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <style>
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: var(--space-lg);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--space-lg);
            margin-bottom: var(--space-lg);
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
            padding: var(--space-lg);
            border-radius: var(--border-radius);
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .stat-card h3 {
            font-size: 2.5em;
            margin: 0 0 10px 0;
            position: relative;
        }
        
        .stat-card p {
            margin: 0;
            opacity: 0.9;
            position: relative;
        }
        
        .stat-card .stat-icon {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 2em;
            opacity: 0.3;
        }
        
        .chart-container {
            background: var(--color-surface-dark);
            padding: var(--space-lg);
            border-radius: var(--border-radius);
            margin-bottom: var(--space-lg);
        }
        
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: var(--space-lg);
            margin-bottom: var(--space-lg);
        }
        
        .table-container {
            background: var(--color-surface-dark);
            padding: var(--space-lg);
            border-radius: var(--border-radius);
            overflow-x: auto;
        }
        
        .admin-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .admin-table th {
            text-align: left;
            padding: var(--space-md);
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
            color: var(--color-text-subtle);
            font-weight: 600;
        }
        
        .admin-table td {
            padding: var(--space-md);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .admin-table tr:hover {
            background: var(--color-background-dark);
        }
        
        .user-avatar-small {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            vertical-align: middle;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
        }
        
        .badge-success {
            background: rgba(60, 179, 113, 0.2);
            color: var(--color-success);
        }
        
        .badge-warning {
            background: rgba(255, 165, 0, 0.2);
            color: #FFA500;
        }
        
        .badge-danger {
            background: rgba(255, 99, 71, 0.2);
            color: var(--color-error);
        }
        
        .action-btn {
            padding: 6px 12px;
            border-radius: var(--border-radius);
            border: none;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.3s;
        }
        
        .action-btn-primary {
            background: var(--color-primary);
            color: white;
        }
        
        .action-btn-danger {
            background: var(--color-error);
            color: white;
        }
        
        .action-btn:hover {
            opacity: 0.8;
            transform: translateY(-1px);
        }
        
        .tabs {
            display: flex;
            gap: var(--space-md);
            margin-bottom: var(--space-lg);
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }
        
        .tab {
            padding: var(--space-md) var(--space-lg);
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            color: var(--color-text-subtle);
        }
        
        .tab.active {
            border-bottom-color: var(--color-primary);
            color: var(--color-text-light);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>

    <div class="app-container" style="grid-template-columns: 0 1fr 0;">
        <aside></aside>
        
        <main class="app-main-content">
            <div class="admin-container">
                
                <!-- Header -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-lg);">
                    <div>
                        <h1><i class="fas fa-shield-alt"></i> Admin Dashboard</h1>
                        <p style="color: var(--color-text-subtle);">
                            Welcome back, <?= e($currentUser['username']) ?>
                        </p>
                    </div>
                    <a href="home.php" class="post-button" style="text-decoration: none;">
                        <i class="fas fa-home"></i> Back to Home
                    </a>
                </div>

                <!-- Stats Overview -->
                <div class="stats-grid">
                    <div class="stat-card" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                        <i class="fas fa-users stat-icon"></i>
                        <h3><?= number_format($stats['total_users']) ?></h3>
                        <p>Total Users</p>
                        <small style="opacity: 0.8;">+<?= $stats['new_users_today'] ?> today</small>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
                        <i class="fas fa-image stat-icon"></i>
                        <h3><?= number_format($stats['total_posts']) ?></h3>
                        <p>Total Posts</p>
                        <small style="opacity: 0.8;">+<?= $stats['new_posts_today'] ?> today</small>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, #4facfe, #00f2fe);">
                        <i class="fas fa-heart stat-icon"></i>
                        <h3><?= number_format($stats['total_likes']) ?></h3>
                        <p>Total Likes</p>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, #43e97b, #38f9d7);">
                        <i class="fas fa-comments stat-icon"></i>
                        <h3><?= number_format($stats['total_comments']) ?></h3>
                        <p>Total Comments</p>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, #fa709a, #fee140);">
                        <i class="fas fa-clock stat-icon"></i>
                        <h3><?= number_format($stats['total_stories']) ?></h3>
                        <p>Active Stories</p>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, #ff6b6b, #ee5a6f);">
                        <i class="fas fa-ban stat-icon"></i>
                        <h3><?= number_format($stats['banned_users']) ?></h3>
                        <p>Banned Users</p>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="tabs">
                    <div class="tab active" data-tab="analytics">
                        <i class="fas fa-chart-line"></i> Analytics
                    </div>
                    <div class="tab" data-tab="users">
                        <i class="fas fa-users"></i> Top Users
                    </div>
                    <div class="tab" data-tab="reports">
                        <i class="fas fa-flag"></i> Reports (<?= count($reports) ?>)
                    </div>
                    <div class="tab" data-tab="sectors">
                        <i class="fas fa-chart-pie"></i> Sectors
                    </div>
                </div>

                <!-- ANALYTICS TAB -->
                <div class="tab-content active" id="analytics-tab">
                    
                    <!-- Main Growth Chart -->
                    <div class="chart-container">
                        <h3 style="margin-bottom: var(--space-lg);">
                            <i class="fas fa-chart-line"></i> User Growth (Last 30 Days)
                        </h3>
                        <canvas id="userGrowthChart" height="80"></canvas>
                    </div>

                    <!-- Grid of Charts -->
                    <div class="chart-grid">
                        <!-- Posts Chart -->
                        <div class="chart-container">
                            <h3 style="margin-bottom: var(--space-lg);">
                                <i class="fas fa-image"></i> Posts Created
                            </h3>
                            <canvas id="postsChart"></canvas>
                        </div>

                        <!-- Engagement Chart -->
                        <div class="chart-container">
                            <h3 style="margin-bottom: var(--space-lg);">
                                <i class="fas fa-heart"></i> Likes Activity
                            </h3>
                            <canvas id="likesChart"></canvas>
                        </div>

                        <!-- Comments Chart -->
                        <div class="chart-container">
                            <h3 style="margin-bottom: var(--space-lg);">
                                <i class="fas fa-comments"></i> Comments Activity
                            </h3>
                            <canvas id="commentsChart"></canvas>
                        </div>

                        <!-- Overall Engagement -->
                        <div class="chart-container">
                            <h3 style="margin-bottom: var(--space-lg);">
                                <i class="fas fa-chart-area"></i> Overall Engagement
                            </h3>
                            <canvas id="overallChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- TOP USERS TAB -->
                <div class="tab-content" id="users-tab">
                    <div class="table-container">
                        <h3 style="margin-bottom: var(--space-lg);">
                            <i class="fas fa-trophy"></i> Top Users by XP
                        </h3>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>User</th>
                                    <th>XP</th>
                                    <th>Level</th>
                                    <th>Posts</th>
                                    <th>Followers</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topUsers as $index => $user): ?>
                                    <tr>
                                        <td>
                                            <?php
                                            $medal = ['🥇', '🥈', '🥉'];
                                            echo $index < 3 ? $medal[$index] : '#' . ($index + 1);
                                            ?>
                                        </td>
                                        <td>
                                            <img src="../<?= e($user['profile_pic']) ?>" 
                                                 class="user-avatar-small"
                                                 alt="<?= e($user['username']) ?>">
                                            <strong><?= e($user['username']) ?></strong>
                                        </td>
                                        <td><?= number_format($user['xp']) ?></td>
                                        <td><span class="badge badge-success">Lv <?= $user['level'] ?></span></td>
                                        <td><?= number_format($user['post_count']) ?></td>
                                        <td><?= number_format($user['follower_count']) ?></td>
                                        <td>
                                            <a href="profile.php?id=<?= $user['id'] ?>" class="action-btn action-btn-primary">
                                                View Profile
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- REPORTS TAB -->
                <div class="tab-content" id="reports-tab">
                    <div class="table-container">
                        <h3 style="margin-bottom: var(--space-lg);">
                            <i class="fas fa-flag"></i> Recent Reports
                        </h3>
                        <?php if (empty($reports)): ?>
                            <p style="text-align: center; color: var(--color-text-subtle); padding: 40px;">
                                No reports to review
                            </p>
                        <?php else: ?>
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Reporter</th>
                                        <th>Target</th>
                                        <th>Type</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reports as $report): ?>
                                        <tr>
                                            <td><?= e($report['reporter_username']) ?></td>
                                            <td><?= e($report['target_username']) ?></td>
                                            <td><span class="badge badge-warning"><?= e($report['target_type']) ?></span></td>
                                            <td><?= e(substr($report['reason'], 0, 50)) ?>...</td>
                                            <td>
                                                <span class="badge <?= $report['status'] == 'pending' ? 'badge-warning' : 'badge-success' ?>">
                                                    <?= e($report['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= timeAgo($report['created_at']) ?></td>
                                            <td>
                                                <button class="action-btn action-btn-danger" 
                                                        onclick="handleReport(<?= $report['id'] ?>, <?= $report['target_id'] ?>, 'ban')">
                                                    Ban User
                                                </button>
                                                <button class="action-btn action-btn-primary" 
                                                        onclick="handleReport(<?= $report['id'] ?>, null, 'dismiss')">
                                                    Dismiss
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- SECTORS TAB -->
                <div class="tab-content" id="sectors-tab">
                    <div class="chart-container">
                        <h3 style="margin-bottom: var(--space-lg);">
                            <i class="fas fa-chart-pie"></i> User Distribution by Sector
                        </h3>
                        <div style="max-width: 600px; margin: 0 auto;">
                            <canvas id="sectorChart"></canvas>
                        </div>
                        
                        <div style="margin-top: var(--space-lg);">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Sector</th>
                                        <th>Users</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $totalSectorUsers = array_sum(array_column($sectorData, 'count'));
                                    foreach ($sectorData as $sector): 
                                        $percentage = ($sector['count'] / $totalSectorUsers) * 100;
                                    ?>
                                        <tr>
                                            <td><strong><?= e($sector['sector']) ?></strong></td>
                                            <td><?= number_format($sector['count']) ?></td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <div style="flex: 1; height: 8px; background: rgba(255,255,255,0.1); border-radius: 4px; overflow: hidden;">
                                                        <div style="height: 100%; width: <?= $percentage ?>%; background: var(--color-primary);"></div>
                                                    </div>
                                                    <span><?= number_format($percentage, 1) ?>%</span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </main>
        
        <aside></aside>
    </div>

    <script src="../assets/js/main.js"></script>
    
    <script>
        // Chart.js global configuration
        Chart.defaults.color = '#a0a0a0';
        Chart.defaults.borderColor = 'rgba(255, 255, 255, 0.1)';
        
        // User Growth Chart (Main)
        new Chart(document.getElementById('userGrowthChart'), {
            type: 'line',
            data: {
                labels: <?= json_encode($userGrowthDates) ?>,
                datasets: [{
                    label: 'New Users',
                    data: <?= json_encode($userGrowthCounts) ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        // Posts Chart
        new Chart(document.getElementById('postsChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($postsDates) ?>,
                datasets: [{
                    label: 'Posts',
                    data: <?= json_encode($postsCounts) ?>,
                    backgroundColor: 'rgba(245, 87, 108, 0.6)',
                    borderColor: '#f5576c',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        // Likes Chart
        new Chart(document.getElementById('likesChart'), {
            type: 'line',
            data: {
                labels: <?= json_encode($likesDates) ?>,
                datasets: [{
                    label: 'Likes',
                    data: <?= json_encode($likesCounts) ?>,
                    borderColor: '#00f2fe',
                    backgroundColor: 'rgba(0, 242, 254, 0.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Comments Chart
        new Chart(document.getElementById('commentsChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($commentsDates) ?>,
                datasets: [{
                    label: 'Comments',
                    data: <?= json_encode($commentsCounts) ?>,
                    backgroundColor: 'rgba(67, 233, 123, 0.6)',
                    borderColor: '#43e97b',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        // Overall Engagement Chart (Combined)
        new Chart(document.getElementById('overallChart'), {
            type: 'line',
            data: {
                labels: <?= json_encode($postsDates) ?>,
                datasets: [
                    {
                        label: 'Posts',
                        data: <?= json_encode($postsCounts) ?>,
                        borderColor: '#f5576c',
                        backgroundColor: 'rgba(245, 87, 108, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Likes',
                        data: <?= json_encode($likesCounts) ?>,
                        borderColor: '#00f2fe',
                        backgroundColor: 'rgba(0, 242, 254, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Comments',
                        data: <?= json_encode($commentsCounts) ?>,
                        borderColor: '#43e97b',
                        backgroundColor: 'rgba(67, 233, 123, 0.1)',
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Sector Distribution Pie Chart
        new Chart(document.getElementById('sectorChart'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_column($sectorData, 'sector')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($sectorData, 'count')) ?>,
                    backgroundColor: [
                        '#667eea',
                        '#f5576c',
                        '#4facfe',
                        '#43e97b',
                        '#fa709a',
                        '#ff6b6b',
                        '#ffa500',
                        '#9b59b6',
                        '#3498db',
                        '#e74c3c'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Tab switching
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                this.classList.add('active');
                document.getElementById(this.dataset.tab + '-tab').classList.add('active');
            });
        });

        // Handle reports
        async function handleReport(reportId, targetUserId, action) {
            if (action === 'ban' && !confirm('Are you sure you want to ban this user?')) {
                return;
            }
            
            try {
                const response = await fetch('../api/admin_action.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        report_id: reportId,
                        user_id: targetUserId,
                        action: action
                    })
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    alert(data.message);
                    location.reloa
