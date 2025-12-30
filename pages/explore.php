<?php
// =======================================
// FLIXSY EXPLORE PAGE
// =======================================

require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();

$currentUser = getCurrentUser();

// Get search query if any
$searchQuery = trim($_GET['query'] ?? '');
$sector = $_GET['sector'] ?? 'all';

// Get trending posts
$trendingPosts = getTrendingPosts(20);

// Get trending hashtags
global $pdo;
$hashtagStmt = $pdo->prepare("
    SELECT tag, usage_count 
    FROM hashtags 
    ORDER BY usage_count DESC 
    LIMIT 10
");
$hashtagStmt->execute();
$trendingHashtags = $hashtagStmt->fetchAll();

// Get suggested creators
$suggestedStmt = $pdo->prepare("
    SELECT id, username, profile_pic, sector, xp, is_verified,
           (SELECT COUNT(*) FROM follows WHERE followee_id = users.id) as followers_count
    FROM users
    WHERE id != ?
    AND id NOT IN (SELECT followee_id FROM follows WHERE follower_id = ?)
    AND is_banned = 0
    ORDER BY xp DESC
    LIMIT 12
");
$suggestedStmt->execute([$currentUser['id'], $currentUser['id']]);
$suggestedCreators = $suggestedStmt->fetchAll();

// Search functionality
$searchResults = [];
if (!empty($searchQuery)) {
    $searchStmt = $pdo->prepare("
        SELECT p.*, u.username, u.profile_pic, u.is_verified
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE (p.caption LIKE ? OR u.username LIKE ?)
        AND p.privacy = 'public'
        AND p.is_archived = 0
        ORDER BY p.created_at DESC
        LIMIT 20
    ");
    $searchStmt->execute(["%$searchQuery%", "%$searchQuery%"]);
    $searchResults = $searchStmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flixsy | Explore</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
    
    <style>
        .explore-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: var(--space-sm);
            margin-bottom: var(--space-lg);
        }
        
        .explore-post {
            position: relative;
            aspect-ratio: 1;
            overflow: hidden;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .explore-post:hover {
            transform: scale(1.05);
        }
        
        .explore-post img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .explore-post-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-lg);
            opacity: 0;
            transition: opacity 0.3s;
            color: white;
            font-weight: bold;
        }
        
        .explore-post:hover .explore-post-overlay {
            opacity: 1;
        }
        
        .creator-card {
            background: var(--color-surface-dark);
            border-radius: var(--border-radius);
            padding: var(--space-md);
            text-align: center;
            transition: transform 0.3s;
        }
        
        .creator-card:hover {
            transform: translateY(-5px);
        }
        
        .creator-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto var(--space-md);
            border: 3px solid var(--color-primary);
        }
        
        .hashtag-chip {
            display: inline-block;
            background: var(--color-surface-dark);
            padding: 8px 16px;
            border-radius: 20px;
            margin: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .hashtag-chip:hover {
            background: var(--color-primary);
            transform: scale(1.1);
        }
        
        .sector-filter {
            display: flex;
            gap: var(--space-sm);
            flex-wrap: wrap;
            margin-bottom: var(--space-lg);
        }
        
        .sector-btn {
            padding: 8px 20px;
            border-radius: 20px;
            background: var(--color-surface-dark);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--color-text-light);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .sector-btn.active {
            background: var(--color-primary);
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
                <a href="explore.php" class="nav-link active">
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
            
            <h2><i class="fas fa-compass"></i> Discover & Trending</h2>
            <hr style="border-color: rgba(255, 255, 255, 0.1); margin-bottom: 24px;">

            <!-- Search Bar -->
            <div class="flixsy-card" style="padding: var(--space-md); margin-bottom: var(--space-lg);">
                <form method="GET" action="explore.php" style="display: flex; align-items: center;">
                    <i class="fas fa-search" style="color: var(--color-text-subtle); margin-right: var(--space-md);"></i>
                    <input type="text" 
                           name="query"
                           value="<?= e($searchQuery) ?>"
                           placeholder="Search posts, creators, and hashtags..." 
                           style="background: none; border: none; color: var(--color-text-light); padding: 10px; flex-grow: 1;">
                    <button type="submit" class="post-button" style="width: auto; padding: 8px 20px;">
                        Search
                    </button>
                </form>
            </div>

            <!-- Sector Filters -->
            <div class="sector-filter">
                <a href="?sector=all" class="sector-btn <?= $sector == 'all' ? 'active' : '' ?>">All</a>
                <a href="?sector=gaming" class="sector-btn <?= $sector == 'gaming' ? 'active' : '' ?>">Gaming</a>
                <a href="?sector=music" class="sector-btn <?= $sector == 'music' ? 'active' : '' ?>">Music</a>
                <a href="?sector=fashion" class="sector-btn <?= $sector == 'fashion' ? 'active' : '' ?>">Fashion</a>
                <a href="?sector=tech" class="sector-btn <?= $sector == 'tech' ? 'active' : '' ?>">Tech</a>
                <a href="?sector=art" class="sector-btn <?= $sector == 'art' ? 'active' : '' ?>">Art</a>
                <a href="?sector=fitness" class="sector-btn <?= $sector == 'fitness' ? 'active' : '' ?>">Fitness</a>
                <a href="?sector=food" class="sector-btn <?= $sector == 'food' ? 'active' : '' ?>">Food</a>
            </div>

            <!-- Search Results -->
            <?php if (!empty($searchQuery)): ?>
                <h3 style="margin-bottom: var(--space-md);">
                    Search Results for "<?= e($searchQuery) ?>"
                </h3>
                
                <?php if (empty($searchResults)): ?>
                    <div class="flixsy-card" style="text-align: center; padding: 40px;">
                        <i class="fas fa-search" style="font-size: 3em; color: var(--color-text-subtle); margin-bottom: 15px;"></i>
                        <p>No results found. Try different keywords.</p>
                    </div>
                <?php else: ?>
                    <div class="explore-grid">
                        <?php foreach ($searchResults as $post): ?>
                            <a href="post.php?id=<?= $post['id'] ?>" class="explore-post">
                                <?php if ($post['media_url']): ?>
                                    <img src="../<?= e($post['media_url']) ?>" alt="Post">
                                <?php else: ?>
                                    <div style="width: 100%; height: 100%; background: var(--color-surface-dark); display: flex; align-items: center; justify-content: center; padding: 20px;">
                                        <p style="font-size: 0.9em;">
                                            <?= substr(e($post['caption']), 0, 100) ?>...
                                        </p>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="explore-post-overlay">
                                    <span><i class="fas fa-heart"></i> <?= number_format($post['likes_count']) ?></span>
                                    <span><i class="fas fa-comment"></i> <?= number_format($post['comments_count']) ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Trending Posts -->
            <?php if (empty($searchQuery)): ?>
                <h3 style="margin-bottom: var(--space-md);">
                    <i class="fas fa-fire"></i> Trending Posts
                </h3>
                
                <div class="explore-grid">
                    <?php foreach ($trendingPosts as $post): ?>
                        <a href="post.php?id=<?= $post['id'] ?>" class="explore-post">
                            <?php if ($post['media_url']): ?>
                                <img src="../<?= e($post['media_url']) ?>" alt="Post">
                            <?php else: ?>
                                <div style="width: 100%; height: 100%; background: var(--color-surface-dark); display: flex; align-items: center; justify-content: center; padding: 20px;">
                                    <p style="font-size: 0.9em; text-align: center;">
                                        <?= substr(e($post['caption']), 0, 100) ?>...
                                    </p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="explore-post-overlay">
                                <span><i class="fas fa-heart"></i> <?= number_format($post['likes_count']) ?></span>
                                <span><i class="fas fa-comment"></i> <?= number_format($post['comments_count']) ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Suggested Creators -->
                <h3 style="margin: var(--space-lg) 0 var(--space-md);">
                    <i class="fas fa-user-plus"></i> Suggested Creators
                </h3>
                
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: var(--space-md);">
                    <?php foreach ($suggestedCreators as $creator): ?>
                        <div class="creator-card">
                            <img src="../<?= e($creator['profile_pic']) ?>" 
                                 alt="<?= e($creator['username']) ?>"
                                 class="creator-avatar"
                                 onerror="this.src='https://via.placeholder.com/80/1E90FF/FFFFFF?text=<?= substr($creator['username'], 0, 1) ?>'">
                            
                            <a href="profile.php?id=<?= $creator['id'] ?>" style="font-weight: bold; display: block; margin-bottom: 5px;">
                                <?= e($creator['username']) ?>
                                <?php if ($creator['is_verified']): ?>
                                    <i class="fas fa-check-circle" style="color: var(--color-primary); font-size: 0.8em;"></i>
                                <?php endif; ?>
                            </a>
                            
                            <p style="font-size: 0.8em; color: var(--color-text-subtle); margin-bottom: var(--space-sm);">
                                <?= e($creator['sector']) ?> • <?= number_format($creator['followers_count']) ?> followers
                            </p>
                            
                            <button class="post-button follow-quick-btn" 
                                    data-user-id="<?= $creator['id'] ?>"
                                    style="width: 100%; padding: 6px 12px; font-size: 0.9em;">
                                Follow
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </main>

        <!-- RIGHT SIDEBAR -->
        <aside class="app-right-section">
            <div class="flixsy-card">
                <h3><i class="fas fa-hashtag"></i> Trending Hashtags</h3>
                <div style="margin-top: 15px;">
                    <?php foreach ($trendingHashtags as $tag): ?>
                        <a href="?query=%23<?= urlencode($tag['tag']) ?>" class="hashtag-chip">
                            #<?= e($tag['tag']) ?> (<?= number_format($tag['usage_count']) ?>)
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </aside>

    </div>

    <script src="../assets/js/main.js"></script>
    
    <script>
    // Quick follow handler
    document.querySelectorAll('.follow-quick-btn').forEach(btn => {
        btn.addEventListener('click', async function(e) {
            e.preventDefault();
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
    </script>

</body>
</html>
