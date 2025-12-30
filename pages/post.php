<?php
// =======================================
// FLIXSY POST COMPOSER PAGE
// =======================================

require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();

$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flixsy | Create Post</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
    
    <style>
        .post-composer-container {
            max-width: 700px;
            margin: 0 auto;
        }
        
        .composer-textarea {
            width: 100%;
            border: none;
            background: none;
            color: var(--color-text-light);
            font-size: 1.2em;
            resize: none;
            min-height: 150px;
            font-family: inherit;
            line-height: 1.5;
        }
        
        .composer-textarea:focus {
            outline: none;
        }
        
        .composer-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: var(--space-md);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .composer-actions {
            display: flex;
            gap: var(--space-md);
        }
        
        .composer-action-btn {
            background: none;
            border: none;
            color: var(--color-text-light);
            cursor: pointer;
            padding: 8px;
            border-radius: var(--border-radius);
            transition: all 0.3s;
        }
        
        .composer-action-btn:hover {
            background: var(--color-surface-dark);
            color: var(--color-primary);
        }
        
        .media-preview-container {
            position: relative;
            margin-top: var(--space-md);
            border-radius: var(--border-radius);
            overflow: hidden;
        }
        
        .media-preview {
            max-width: 100%;
            max-height: 400px;
            display: block;
            margin: 0 auto;
            background: var(--color-background-dark);
        }
        
        .remove-media-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            border: none;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .remove-media-btn:hover {
            transform: scale(1.1);
            background: rgba(255, 69, 0, 0.9);
        }
        
        .char-counter {
            font-size: 0.9em;
            color: var(--color-text-subtle);
        }
        
        .char-counter.warning {
            color: var(--color-secondary);
        }
        
        .char-counter.danger {
            color: var(--color-error);
        }
        
        .privacy-selector {
            background: var(--color-surface-dark);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--color-text-light);
            padding: 8px 12px;
            border-radius: var(--border-radius);
            cursor: pointer;
        }
        
        .upload-progress {
            display: none;
            width: 100%;
            height: 4px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 2px;
            margin-top: var(--space-md);
            overflow: hidden;
        }
        
        .upload-progress-bar {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, var(--color-primary), var(--color-secondary));
            transition: width 0.3s;
        }
    </style>
</head>
<body>

    <div class="app-container" style="grid-template-columns: 250px 1fr 0;">

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
            
            <div class="post-composer-container">
                
                <!-- Header -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-lg);">
                    <h2><i class="fas fa-edit"></i> Create New Post</h2>
                    <a href="home.php" style="color: var(--color-text-subtle);">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>

                <!-- Success Message -->
                <div id="success-message" style="display: none;" class="flixsy-card" 
                     style="background: rgba(60, 179, 113, 0.2); border: 1px solid var(--color-success); margin-bottom: var(--space-lg);">
                    <i class="fas fa-check-circle" style="color: var(--color-success);"></i>
                    <span>Post created successfully!</span>
                    <a href="home.php" style="margin-left: var(--space-md); color: var(--color-success);">
                        View in feed
                    </a>
                </div>

                <!-- Post Composer -->
                <form id="post-composer-form" class="flixsy-card">
                    
                    <!-- User Header -->
                    <div class="post-header">
                        <img src="../<?= e($currentUser['profile_pic']) ?>" 
                             alt="<?= e($currentUser['username']) ?>" 
                             class="post-avatar"
                             onerror="this.src='https://via.placeholder.com/50/1E90FF/FFFFFF?text=<?= substr($currentUser['username'], 0, 1) ?>'">
                        <div>
                            <strong class="post-username"><?= e($currentUser['username']) ?></strong>
                            <div class="post-metadata"><?= e($currentUser['sector']) ?> Sector</div>
                        </div>
                    </div>

                    <!-- Caption Input -->
                    <textarea 
                        id="post-caption" 
                        name="caption" 
                        class="composer-textarea"
                        maxlength="2000"
                        placeholder="What's happening in your sector? Share your thoughts, experiences, or creations... #Flixsy"
                        oninput="updateCharCount()"></textarea>

                    <!-- Media Preview -->
                    <div id="media-preview-container" class="media-preview-container" style="display: none;">
                        <img id="media-preview" class="media-preview" alt="Media preview">
                        <video id="video-preview" class="media-preview" controls style="display: none;"></video>
                        <button type="button" class="remove-media-btn" onclick="removeMedia()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <!-- Upload Progress -->
                    <div id="upload-progress" class="upload-progress">
                        <div id="upload-progress-bar" class="upload-progress-bar"></div>
                    </div>

                    <!-- Toolbar -->
                    <div class="composer-toolbar">
                        
                        <!-- Actions -->
                        <div class="composer-actions">
                            <input type="file" 
                                   id="media-upload" 
                                   name="media" 
                                   accept="image/*,video/*"
                                   style="display: none;"
                                   onchange="previewMedia(this)">
                            
                            <button type="button" 
                                    class="composer-action-btn" 
                                    onclick="document.getElementById('media-upload').click()"
                                    title="Add photo or video">
                                <i class="fas fa-image" style="font-size: 1.3em;"></i>
                            </button>
                            
                            <button type="button" 
                                    class="composer-action-btn"
                                    onclick="insertHashtag()"
                                    title="Add hashtag">
                                <i class="fas fa-hashtag" style="font-size: 1.3em;"></i>
                            </button>
                            
                            <select name="privacy" class="privacy-selector">
                                <option value="public">🌍 Public</option>
                                <option value="followers">👥 Followers Only</option>
                                <option value="private">🔒 Private</option>
                            </select>
                        </div>

                        <!-- Submit Section -->
                        <div style="display: flex; align-items: center; gap: var(--space-md);">
                            <span id="char-count" class="char-counter">0/2000</span>
                            
                            <button type="submit" 
                                    id="post-submit-btn" 
                                    class="post-button" 
                                    style="padding: 10px 30px;"
                                    disabled>
                                <i class="fas fa-paper-plane"></i> Post
                            </button>
                        </div>
                    </div>

                </form>

                <!-- Post Tips -->
                <div class="flixsy-card" style="margin-top: var(--space-lg); background: rgba(30, 144, 255, 0.1); border: 1px solid rgba(30, 144, 255, 0.3);">
                    <h3><i class="fas fa-lightbulb"></i> Tips for Great Posts</h3>
                    <ul style="margin-top: var(--space-md); padding-left: 20px; color: var(--color-text-subtle);">
                        <li>Use hashtags to increase discoverability</li>
                        <li>Add high-quality images or videos to get more engagement</li>
                        <li>Keep your captions clear and engaging</li>
                        <li>Post during peak hours for maximum reach</li>
                        <li>Interact with comments to build community</li>
                    </ul>
                </div>

            </div>

        </main>
        
        <aside></aside>

    </div>

    <script src="../assets/js/main.js"></script>
    
    <script>
    let selectedFile = null;

    // Update character count
    function updateCharCount() {
        const textarea = document.getElementById('post-caption');
        const counter = document.getElementById('char-count');
        const submitBtn = document.getElementById('post-submit-btn');
        const length = textarea.value.length;
        
        counter.textContent = `${length}/2000`;
        
        // Color coding
        counter.classList.remove('warning', 'danger');
        if (length > 1800) {
            counter.classList.add('danger');
        } else if (length > 1600) {
            counter.classList.add('warning');
        }
        
        // Enable/disable submit button
        const hasContent = length > 0 || selectedFile !== null;
        submitBtn.disabled = !hasContent || length > 2000;
    }

    // Preview media
    function previewMedia(input) {
        const file = input.files[0];
        if (!file) return;
        
        selectedFile = file;
        const container = document.getElementById('media-preview-container');
        const imgPreview = document.getElementById('media-preview');
        const videoPreview = document.getElementById('video-preview');
        
        const fileURL = URL.createObjectURL(file);
        
        if (file.type.startsWith('image/')) {
            imgPreview.src = fileURL;
            imgPreview.style.display = 'block';
            videoPreview.style.display = 'none';
        } else if (file.type.startsWith('video/')) {
            videoPreview.src = fileURL;
            videoPreview.style.display = 'block';
            imgPreview.style.display = 'none';
        }
        
        container.style.display = 'block';
        updateCharCount();
    }

    // Remove media
    function removeMedia() {
        selectedFile = null;
        document.getElementById('media-upload').value = '';
        document.getElementById('media-preview-container').style.display = 'none';
        document.getElementById('media-preview').src = '';
        document.getElementById('video-preview').src = '';
        updateCharCount();
    }

    // Insert hashtag
    function insertHashtag() {
        const textarea = document.getElementById('post-caption');
        const cursorPos = textarea.selectionStart;
        const textBefore = textarea.value.substring(0, cursorPos);
        const textAfter = textarea.value.substring(cursorPos);
        
        textarea.value = textBefore + '#' + textAfter;
        textarea.focus();
        textarea.setSelectionRange(cursorPos + 1, cursorPos + 1);
        updateCharCount();
    }

    // Handle form submission
    document.getElementById('post-composer-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const submitBtn = document.getElementById('post-submit-btn');
        const progress = document.getElementById('upload-progress');
        const progressBar = document.getElementById('upload-progress-bar');
        
        // Disable button
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Posting...';
        
        // Show progress bar
        progress.style.display = 'block';
        
        // Prepare form data
        const formData = new FormData(this);
        
        // Upload with progress tracking
        const xhr = new XMLHttpRequest();
        
        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                const percent = (e.loaded / e.total) * 100;
                progressBar.style.width = percent + '%';
            }
        };
        
        xhr.onload = function() {
            progress.style.display = 'none';
            
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    
                    if (response.status === 'success') {
                        // Show success message
                        document.getElementById('success-message').style.display = 'block';
                        
                        // Reset form
                        document.getElementById('post-composer-form').reset();
                        removeMedia();
                        updateCharCount();
                        
                        // Redirect after 2 seconds
                        setTimeout(() => {
                            window.location.href = 'home.php';
                        }, 2000);
                    } else {
                        alert('Error: ' + (response.message || 'Failed to create post'));
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Post';
                    }
                } catch (e) {
                    alert('Server error. Please try again.');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Post';
                }
            } else {
                alert('Upload failed. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Post';
            }
        };
        
        xhr.onerror = function() {
            alert('Network error. Please check your connection.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Post';
            progress.style.display = 'none';
        };
        
        xhr.open('POST', '../api/upload.php');
        xhr.send(formData);
    });

    // Initialize
    updateCharCount();
    </script>

</body>
</html>
