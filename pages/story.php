<?php
// =======================================
// FLIXSY STORIES SYSTEM - COMPLETE VERSION
// =======================================

require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();

$currentUser = getCurrentUser();

// Get active stories from followed users + own stories
global $pdo;

// Delete expired stories
$pdo->query("DELETE FROM stories WHERE expires_at < NOW() AND is_archived = 0");

// Get users with active stories
$storiesStmt = $pdo->prepare("
    SELECT DISTINCT 
        u.id, 
        u.username, 
        u.profile_pic,
        u.is_verified,
        COUNT(s.id) as story_count,
        (SELECT created_at FROM stories WHERE user_id = u.id AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1) as latest_story
    FROM users u
    JOIN stories s ON u.id = s.user_id
    WHERE s.expires_at > NOW()
    AND s.is_archived = 0
    AND (u.id = ? OR u.id IN (SELECT followee_id FROM follows WHERE follower_id = ?))
    GROUP BY u.id
    ORDER BY latest_story DESC
");
$storiesStmt->execute([$currentUser['id'], $currentUser['id']]);
$usersWithStories = $storiesStmt->fetchAll();

// Get specific user's stories if viewing
$viewingUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$currentStories = [];
$currentStoryIndex = isset($_GET['index']) ? (int)$_GET['index'] : 0;

if ($viewingUserId) {
    $currentStoriesStmt = $pdo->prepare("
        SELECT s.*, u.username, u.profile_pic, u.is_verified,
               (SELECT COUNT(*) FROM story_views WHERE story_id = s.id) as view_count,
               EXISTS(SELECT 1 FROM story_views WHERE story_id = s.id AND user_id = ?) as user_viewed
        FROM stories s
        JOIN users u ON s.user_id = u.id
        WHERE s.user_id = ?
        AND s.expires_at > NOW()
        AND s.is_archived = 0
        ORDER BY s.created_at ASC
    ");
    $currentStoriesStmt->execute([$currentUser['id'], $viewingUserId]);
    $currentStories = $currentStoriesStmt->fetchAll();
    
    // Mark current story as viewed
    if (isset($currentStories[$currentStoryIndex]) && $viewingUserId != $currentUser['id']) {
        $storyId = $currentStories[$currentStoryIndex]['id'];
        $pdo->prepare("INSERT IGNORE INTO story_views (story_id, user_id) VALUES (?, ?)")
            ->execute([$storyId, $currentUser['id']]);
        
        // Update view count
        $pdo->prepare("UPDATE stories SET views_count = views_count + 1 WHERE id = ?")
            ->execute([$storyId]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flixsy | Stories</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
    
    <style>
        body {
            background: #000;
            overflow: hidden;
        }
        
        .stories-container {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .stories-sidebar {
            width: 350px;
            height: 100vh;
            background: var(--color-surface-dark);
            padding: var(--space-lg);
            overflow-y: auto;
        }
        
        .stories-viewer {
            flex: 1;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #000;
            position: relative;
        }
        
        .story-card {
            width: 100%;
            max-width: 500px;
            height: 90vh;
            max-height: 800px;
            border-radius: 20px;
            overflow: hidden;
            position: relative;
            background: #000;
        }
        
        .story-media {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .story-header {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            padding: 20px;
            background: linear-gradient(to bottom, rgba(0,0,0,0.7), transparent);
            z-index: 10;
        }
        
        .story-progress-bars {
            display: flex;
            gap: 4px;
            margin-bottom: 15px;
        }
        
        .story-progress-bar {
            flex: 1;
            height: 3px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 2px;
            overflow: hidden;
        }
        
        .story-progress-fill {
            height: 100%;
            width: 0%;
            background: white;
            transition: width 0.1s linear;
        }
        
        .story-progress-fill.active {
            animation: progress 5s linear;
        }
        
        @keyframes progress {
            from { width: 0%; }
            to { width: 100%; }
        }
        
        .story-user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .story-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid white;
        }
        
        .story-username {
            color: white;
            font-weight: bold;
        }
        
        .story-time {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9em;
        }
        
        .story-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 100%;
            display: flex;
            justify-content: space-between;
            padding: 0 20px;
            z-index: 5;
        }
        
        .story-nav-btn {
            width: 50px;
            height: 50px;
            background: rgba(0, 0, 0, 0.5);
            border: none;
            border-radius: 50%;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s;
        }
        
        .story-nav-btn:hover {
            background: rgba(0, 0, 0, 0.8);
        }
        
        .story-close {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 40px;
            height: 40px;
            background: rgba(0, 0, 0, 0.5);
            border: none;
            border-radius: 50%;
            color: white;
            cursor: pointer;
            z-index: 15;
        }
        
        .story-close:hover {
            background: rgba(0, 0, 0, 0.8);
        }
        
        .story-views {
            position: absolute;
            bottom: 20px;
            left: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.5);
            padding: 10px 15px;
            border-radius: 10px;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .story-list-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: background 0.3s;
            margin-bottom: 8px;
        }
        
        .story-list-item:hover {
            background: var(--color-background-dark);
        }
        
        .story-list-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: 3px solid var(--color-primary);
            padding: 3px;
        }
        
        .story-list-avatar.viewed {
            border-color: rgba(255, 255, 255, 0.3);
        }
        
        .add-story-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--color-primary);
            border: none;
            color: white;
            font-size: 1.5em;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto var(--space-lg);
        }
        
        .story-upload-modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 100;
        }
        
        .story-upload-modal.active {
            display: flex;
        }
        
        .story-upload-content {
            background: var(--color-surface-dark);
            padding: 30px;
            border-radius: 20px;
            max-width: 500px;
            width: 90%;
        }
    </style>
</head>
<body>

    <div class="stories-container">
        
        <!-- Stories List Sidebar -->
        <div class="stories-sidebar">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-lg);">
                <h2>Stories</h2>
                <a href="home.php" style="color: var(--color-text-subtle);">
                    <i class="fas fa-times"></i>
                </a>
            </div>
            
            <!-- Add Story Button -->
            <div style="text-align: center; margin-bottom: var(--space-lg);">
                <button class="add-story-btn" onclick="openUploadModal()">
                    <i class="fas fa-plus"></i>
                </button>
                <p style="color: var(--color-text-subtle); font-size: 0.9em; margin-top: 8px;">
                    Add Your Story
                </p>
            </div>
            
            <!-- Stories List -->
            <div>
                <h3 style="margin-bottom: 15px;">Recent Stories</h3>
                
                <?php if (empty($usersWithStories)): ?>
                    <p style="color: var(--color-text-subtle); text-align: center; padding: 20px;">
                        No stories available
                    </p>
                <?php else: ?>
                    <?php foreach ($usersWithStories as $user): ?>
                        <div class="story-list-item" onclick="window.location.href='story.php?user_id=<?= $user['id'] ?>'">
                            <img src="../<?= e($user['profile_pic']) ?>" 
                                 class="story-list-avatar"
                                 alt="<?= e($user['username']) ?>">
                            <div style="flex: 1;">
                                <div style="font-weight: bold;">
                                    <?= e($user['username']) ?>
                                    <?php if ($user['is_verified']): ?>
                                        <i class="fas fa-check-circle" style="color: var(--color-primary); font-size: 0.8em;"></i>
                                    <?php endif; ?>
                                </div>
                                <div style="color: var(--color-text-subtle); font-size: 0.9em;">
                                    <?= $user['story_count'] ?> <?= $user['story_count'] == 1 ? 'story' : 'stories' ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Story Viewer -->
        <div class="stories-viewer">
            <?php if ($viewingUserId && !empty($currentStories)): ?>
                <?php $story = $currentStories[$currentStoryIndex]; ?>
                
                <div class="story-card">
                    <!-- Close Button -->
                    <button class="story-close" onclick="window.location.href='story.php'">
                        <i class="fas fa-times"></i>
                    </button>
                    
                    <!-- Story Header -->
                    <div class="story-header">
                        <!-- Progress Bars -->
                        <div class="story-progress-bars">
                            <?php foreach ($currentStories as $idx => $s): ?>
                                <div class="story-progress-bar">
                                    <div class="story-progress-fill <?= $idx == $currentStoryIndex ? 'active' : ($idx < $currentStoryIndex ? 'completed' : '') ?>" 
                                         style="width: <?= $idx < $currentStoryIndex ? '100%' : '0%' ?>"></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- User Info -->
                        <div class="story-user-info">
                            <img src="../<?= e($story['profile_pic']) ?>" 
                                 class="story-avatar"
                                 alt="<?= e($story['username']) ?>">
                            <div>
                                <div class="story-username">
                                    <?= e($story['username']) ?>
                                    <?php if ($story['is_verified']): ?>
                                        <i class="fas fa-check-circle" style="font-size: 0.8em;"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="story-time"><?= timeAgo($story['created_at']) ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Story Media -->
                    <?php if ($story['media_type'] === 'image'): ?>
                        <img src="../<?= e($story['media_url']) ?>" 
                             class="story-media"
                             alt="Story">
                    <?php else: ?>
                        <video src="../<?= e($story['media_url']) ?>" 
                               class="story-media"
                               autoplay
                               muted></video>
                    <?php endif; ?>
                    
                    <!-- Navigation -->
                    <div class="story-nav">
                        <?php if ($currentStoryIndex > 0): ?>
                            <button class="story-nav-btn" onclick="window.location.href='story.php?user_id=<?= $viewingUserId ?>&index=<?= $currentStoryIndex - 1 ?>'">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                        <?php else: ?>
                            <div></div>
                        <?php endif; ?>
                        
                        <?php if ($currentStoryIndex < count($currentStories) - 1): ?>
                            <button class="story-nav-btn" onclick="window.location.href='story.php?user_id=<?= $viewingUserId ?>&index=<?= $currentStoryIndex + 1 ?>'">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        <?php else: ?>
                            <div></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Views (only for own stories) -->
                    <?php if ($viewingUserId == $currentUser['id']): ?>
                        <div class="story-views">
                            <i class="fas fa-eye"></i>
                            <span><?= number_format($story['view_count']) ?> views</span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Auto-advance script -->
                <script>
                    <?php if ($currentStoryIndex < count($currentStories) - 1): ?>
                        setTimeout(function() {
                            window.location.href = 'story.php?user_id=<?= $viewingUserId ?>&index=<?= $currentStoryIndex + 1 ?>';
                        }, 5000);
                    <?php else: ?>
                        setTimeout(function() {
                            window.location.href = 'story.php';
                        }, 5000);
                    <?php endif; ?>
                </script>
                
            <?php else: ?>
                <!-- No story selected or no stories -->
                <div style="text-align: center; color: white;">
                    <i class="fas fa-images" style="font-size: 4em; margin-bottom: 20px; opacity: 0.5;"></i>
                    <h2>Select a story to view</h2>
                    <p style="color: rgba(255, 255, 255, 0.6);">
                        Choose from the list or add your own story
                    </p>
                </div>
            <?php endif; ?>
        </div>
        
    </div>

    <!-- Upload Story Modal -->
    <div class="story-upload-modal" id="upload-modal">
        <div class="story-upload-content">
            <h3 style="margin-bottom: 20px;">Add to Your Story</h3>
            
            <form id="story-upload-form" enctype="multipart/form-data">
                <input type="file" 
                       id="story-media" 
                       accept="image/*,video/*"
                       style="display: none;"
                       onchange="previewStory(this)">
                
                <div id="story-preview" style="margin-bottom: 20px; display: none;">
                    <img id="story-preview-img" style="max-width: 100%; border-radius: 10px; display: none;">
                    <video id="story-preview-video" style="max-width: 100%; border-radius: 10px; display: none;" controls></video>
                </div>
                
                <button type="button" 
                        class="post-button" 
                        onclick="document.getElementById('story-media').click()"
                        style="width: 100%; margin-bottom: 15px;">
                    <i class="fas fa-image"></i> Choose Photo or Video
                </button>
                
                <div style="display: flex; gap: 10px;">
                    <button type="button" 
                            class="post-button" 
                            onclick="closeUploadModal()"
                            style="flex: 1; background: var(--color-surface-dark);">
                        Cancel
                    </button>
                    <button type="submit" 
                            id="upload-story-btn"
                            class="post-button" 
                            style="flex: 1;"
                            disabled>
                        <i class="fas fa-upload"></i> Upload
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    
    <script>
        function openUploadModal() {
            document.getElementById('upload-modal').classList.add('active');
        }
        
        function closeUploadModal() {
            document.getElementById('upload-modal').classList.remove('active');
            document.getElementById('story-upload-form').reset();
            document.getElementById('story-preview').style.display = 'none';
            document.getElementById('upload-story-btn').disabled = true;
        }
        
        function previewStory(input) {
            const file = input.files[0];
            if (!file) return;
            
            const preview = document.getElementById('story-preview');
            const img = document.getElementById('story-preview-img');
            const video = document.getElementById('story-preview-video');
            const uploadBtn = document.getElementById('upload-story-btn');
            
            const fileURL = URL.createObjectURL(file);
            
            if (file.type.startsWith('image/')) {
                img.src = fileURL;
                img.style.display = 'block';
                video.style.display = 'none';
            } else if (file.type.startsWith('video/')) {
                video.src = fileURL;
                video.style.display = 'block';
                img.style.display = 'none';
            }
            
            preview.style.display = 'block';
            uploadBtn.disabled = false;
        }
        
        // Handle story upload
        document.getElementById('story-upload-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const fileInput = document.getElementById('story-media');
            const uploadBtn = document.getElementById('upload-story-btn');
            
            if (!fileInput.files[0]) {
                alert('Please select a file');
                return;
            }
            
            uploadBtn.disabled = true;
            uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
            
            const formData = new FormData();
            formData.append('story_media', fileInput.files[0]);
            
            try {
                const response = await fetch('../api/story_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to upload story'));
                    uploadBtn.disabled = false;
                    uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Upload';
                }
            } catch (error) {
                alert('Upload failed. Please try again.');
                uploadBtn.disabled = false;
                uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Upload';
            }
        });
    </script>

</body>
</html>
