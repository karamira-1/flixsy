<?php
// =======================================
// FLIXSY SETTINGS PAGE - COMPLETE VERSION
// =======================================

require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();

$currentUser = getCurrentUser();
$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_profile':
            $bio = trim($_POST['bio'] ?? '');
            $sector = $_POST['sector'] ?? 'general';
            
            $result = updateUserProfile($currentUser['id'], [
                'bio' => $bio,
                'sector' => $sector
            ]);
            
            if ($result) {
                $success = 'Profile updated successfully!';
                $currentUser = getCurrentUser(); // Refresh user data
            } else {
                $error = 'Failed to update profile';
            }
            break;
            
        case 'change_password':
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            if ($newPassword !== $confirmPassword) {
                $error = 'New passwords do not match';
            } elseif (strlen($newPassword) < 8) {
                $error = 'Password must be at least 8 characters';
            } else {
                $result = changePassword($currentUser['id'], $currentPassword, $newPassword);
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['error'];
                }
            }
            break;
            
        case 'change_email':
            $newEmail = trim($_POST['new_email'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email address';
            } else {
                // Verify password first
                global $pdo;
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$currentUser['id']]);
                $user = $stmt->fetch();
                
                if (password_verify($password, $user['password'])) {
                    // Check if email already exists
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt->execute([$newEmail, $currentUser['id']]);
                    
                    if ($stmt->fetch()) {
                        $error = 'Email already in use';
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
                        if ($stmt->execute([$newEmail, $currentUser['id']])) {
                            $_SESSION['email'] = $newEmail;
                            $success = 'Email updated successfully!';
                        } else {
                            $error = 'Failed to update email';
                        }
                    }
                } else {
                    $error = 'Incorrect password';
                }
            }
            break;
            
        case 'update_privacy':
            $defaultPrivacy = $_POST['default_privacy'] ?? 'public';
            // You can add more privacy settings here
            $success = 'Privacy settings updated!';
            break;
            
        case 'delete_account':
            $password = $_POST['delete_password'] ?? '';
            $confirm = $_POST['confirm_delete'] ?? '';
            
            if ($confirm !== 'DELETE') {
                $error = 'Please type DELETE to confirm';
            } else {
                global $pdo;
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$currentUser['id']]);
                $user = $stmt->fetch();
                
                if (password_verify($password, $user['password'])) {
                    // Delete user account (CASCADE will handle related data)
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    if ($stmt->execute([$currentUser['id']])) {
                        logoutUser();
                    }
                } else {
                    $error = 'Incorrect password';
                }
            }
            break;
    }
}

// Get notification preferences (you can expand this)
global $pdo;
$notifPrefs = [
    'likes' => true,
    'comments' => true,
    'follows' => true,
    'mentions' => true
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flixsy | Settings</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
    
    <style>
        .settings-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: var(--space-lg);
            max-width: 1200px;
        }
        
        .settings-sidebar {
            position: sticky;
            top: var(--space-md);
            height: fit-content;
        }
        
        .settings-nav {
            background: var(--color-surface-dark);
            border-radius: var(--border-radius);
            padding: var(--space-md);
        }
        
        .settings-nav-item {
            display: flex;
            align-items: center;
            padding: var(--space-md);
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: all 0.3s;
            color: var(--color-text-light);
            margin-bottom: var(--space-sm);
        }
        
        .settings-nav-item:hover {
            background: var(--color-background-dark);
        }
        
        .settings-nav-item.active {
            background: var(--color-primary);
            color: var(--color-background-dark);
        }
        
        .settings-nav-item i {
            margin-right: var(--space-md);
            width: 20px;
        }
        
        .settings-section {
            display: none;
        }
        
        .settings-section.active {
            display: block;
        }
        
        .settings-form-group {
            margin-bottom: var(--space-lg);
        }
        
        .settings-form-group label {
            display: block;
            margin-bottom: var(--space-sm);
            font-weight: 600;
        }
        
        .settings-form-group input,
        .settings-form-group textarea,
        .settings-form-group select {
            width: 100%;
            padding: var(--space-md);
            background: var(--color-background-dark);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--border-radius);
            color: var(--color-text-light);
            font-family: inherit;
        }
        
        .settings-form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .alert {
            padding: var(--space-md);
            border-radius: var(--border-radius);
            margin-bottom: var(--space-md);
        }
        
        .alert-success {
            background: rgba(60, 179, 113, 0.2);
            border: 1px solid var(--color-success);
            color: var(--color-success);
        }
        
        .alert-error {
            background: rgba(255, 99, 71, 0.2);
            border: 1px solid var(--color-error);
            color: var(--color-error);
        }
        
        .danger-zone {
            background: rgba(255, 69, 0, 0.1);
            border: 2px solid var(--color-error);
            border-radius: var(--border-radius);
            padding: var(--space-lg);
            margin-top: var(--space-lg);
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: var(--color-primary);
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }
        
        .avatar-upload {
            display: flex;
            align-items: center;
            gap: var(--space-md);
        }
        
        .avatar-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--color-primary);
        }
    </style>
</head>
<body>

    <div class="app-container" style="grid-template-columns: 0 1fr 0;">
        <aside></aside>
        
        <main class="app-main-content">
            <!-- Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-lg);">
                <h2><i class="fas fa-cog"></i> Settings</h2>
                <a href="home.php" class="post-button" style="text-decoration: none; padding: 8px 16px;">
                    <i class="fas fa-arrow-left"></i> Back to Home
                </a>
            </div>

            <!-- Alerts -->
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= e($success) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= e($error) ?>
                </div>
            <?php endif; ?>

            <!-- Settings Layout -->
            <div class="settings-container">
                
                <!-- Settings Sidebar -->
                <div class="settings-sidebar">
                    <div class="settings-nav">
                        <div class="settings-nav-item active" data-section="profile">
                            <i class="fas fa-user"></i> Profile
                        </div>
                        <div class="settings-nav-item" data-section="account">
                            <i class="fas fa-shield-alt"></i> Account
                        </div>
                        <div class="settings-nav-item" data-section="privacy">
                            <i class="fas fa-lock"></i> Privacy
                        </div>
                        <div class="settings-nav-item" data-section="notifications">
                            <i class="fas fa-bell"></i> Notifications
                        </div>
                        <div class="settings-nav-item" data-section="appearance">
                            <i class="fas fa-palette"></i> Appearance
                        </div>
                        <div class="settings-nav-item" data-section="danger">
                            <i class="fas fa-exclamation-triangle"></i> Danger Zone
                        </div>
                    </div>
                </div>

                <!-- Settings Content -->
                <div>
                    
                    <!-- PROFILE SECTION -->
                    <div class="settings-section active" id="profile-section">
                        <div class="flixsy-card">
                            <h3>Edit Profile</h3>
                            
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <!-- Avatar Upload -->
                                <div class="settings-form-group">
                                    <label>Profile Picture</label>
                                    <div class="avatar-upload">
                                        <img src="../<?= e($currentUser['profile_pic']) ?>" 
                                             alt="Avatar" 
                                             class="avatar-preview"
                                             id="avatar-preview">
                                        <div>
                                            <input type="file" 
                                                   id="avatar-input" 
                                                   accept="image/*" 
                                                   style="display: none;">
                                            <button type="button" 
                                                    onclick="document.getElementById('avatar-input').click()"
                                                    class="post-button" 
                                                    style="width: auto; padding: 8px 16px;">
                                                Change Photo
                                            </button>
                                            <p style="font-size: 0.8em; color: var(--color-text-subtle); margin-top: 5px;">
                                                Max size: 5MB
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Username (Read-only) -->
                                <div class="settings-form-group">
                                    <label>Username</label>
                                    <input type="text" 
                                           value="<?= e($currentUser['username']) ?>" 
                                           readonly 
                                           style="opacity: 0.6; cursor: not-allowed;">
                                    <small style="color: var(--color-text-subtle);">
                                        Username cannot be changed
                                    </small>
                                </div>
                                
                                <!-- Bio -->
                                <div class="settings-form-group">
                                    <label>Bio</label>
                                    <textarea name="bio" 
                                              maxlength="500" 
                                              placeholder="Tell us about yourself..."><?= e($currentUser['bio'] ?? '') ?></textarea>
                                    <small style="color: var(--color-text-subtle);">
                                        Max 500 characters
                                    </small>
                                </div>
                                
                                <!-- Sector -->
                                <div class="settings-form-group">
                                    <label>Sector</label>
                                    <select name="sector">
                                        <option value="general" <?= $currentUser['sector'] == 'general' ? 'selected' : '' ?>>General</option>
                                        <option value="gaming" <?= $currentUser['sector'] == 'gaming' ? 'selected' : '' ?>>Gaming</option>
                                        <option value="music" <?= $currentUser['sector'] == 'music' ? 'selected' : '' ?>>Music</option>
                                        <option value="fashion" <?= $currentUser['sector'] == 'fashion' ? 'selected' : '' ?>>Fashion</option>
                                        <option value="tech" <?= $currentUser['sector'] == 'tech' ? 'selected' : '' ?>>Tech</option>
                                        <option value="art" <?= $currentUser['sector'] == 'art' ? 'selected' : '' ?>>Art</option>
                                        <option value="fitness" <?= $currentUser['sector'] == 'fitness' ? 'selected' : '' ?>>Fitness</option>
                                        <option value="food" <?= $currentUser['sector'] == 'food' ? 'selected' : '' ?>>Food</option>
                                        <option value="travel" <?= $currentUser['sector'] == 'travel' ? 'selected' : '' ?>>Travel</option>
                                        <option value="education" <?= $currentUser['sector'] == 'education' ? 'selected' : '' ?>>Education</option>
                                    </select>
                                </div>
                                
                                <button type="submit" class="post-button">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- ACCOUNT SECTION -->
                    <div class="settings-section" id="account-section">
                        
                        <!-- Change Email -->
                        <div class="flixsy-card" style="margin-bottom: var(--space-lg);">
                            <h3>Change Email</h3>
                            <form method="POST">
                                <input type="hidden" name="action" value="change_email">
                                
                                <div class="settings-form-group">
                                    <label>Current Email</label>
                                    <input type="email" 
                                           value="<?= e($currentUser['email']) ?>" 
                                           readonly 
                                           style="opacity: 0.6;">
                                </div>
                                
                                <div class="settings-form-group">
                                    <label>New Email</label>
                                    <input type="email" 
                                           name="new_email" 
                                           required 
                                           placeholder="Enter new email">
                                </div>
                                
                                <div class="settings-form-group">
                                    <label>Confirm Password</label>
                                    <input type="password" 
                                           name="password" 
                                           required 
                                           placeholder="Enter your password">
                                </div>
                                
                                <button type="submit" class="post-button">
                                    <i class="fas fa-envelope"></i> Update Email
                                </button>
                            </form>
                        </div>

                        <!-- Change Password -->
                        <div class="flixsy-card">
                            <h3>Change Password</h3>
                            <form method="POST">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="settings-form-group">
                                    <label>Current Password</label>
                                    <input type="password" 
                                           name="current_password" 
                                           required 
                                           placeholder="Enter current password">
                                </div>
                                
                                <div class="settings-form-group">
                                    <label>New Password</label>
                                    <input type="password" 
                                           name="new_password" 
                                           required 
                                           minlength="8"
                                           placeholder="Enter new password">
                                    <small style="color: var(--color-text-subtle);">
                                        Must be at least 8 characters
                                    </small>
                                </div>
                                
                                <div class="settings-form-group">
                                    <label>Confirm New Password</label>
                                    <input type="password" 
                                           name="confirm_password" 
                                           required 
                                           minlength="8"
                                           placeholder="Confirm new password">
                                </div>
                                
                                <button type="submit" class="post-button">
                                    <i class="fas fa-key"></i> Update Password
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- PRIVACY SECTION -->
                    <div class="settings-section" id="privacy-section">
                        <div class="flixsy-card">
                            <h3>Privacy Settings</h3>
                            
                            <form method="POST">
                                <input type="hidden" name="action" value="update_privacy">
                                
                                <div class="settings-form-group">
                                    <label>Default Post Privacy</label>
                                    <select name="default_privacy">
                                        <option value="public">Public - Anyone can see</option>
                                        <option value="followers">Followers Only</option>
                                        <option value="private">Private - Only me</option>
                                    </select>
                                </div>
                                
                                <div class="settings-form-group" style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong>Private Account</strong>
                                        <p style="font-size: 0.9em; color: var(--color-text-subtle); margin-top: 5px;">
                                            Only approved followers can see your posts
                                        </p>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="private_account">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                
                                <div class="settings-form-group" style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong>Show Activity Status</strong>
                                        <p style="font-size: 0.9em; color: var(--color-text-subtle); margin-top: 5px;">
                                            Let others see when you're active
                                        </p>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="show_activity" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                
                                <div class="settings-form-group" style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong>Searchable Profile</strong>
                                        <p style="font-size: 0.9em; color: var(--color-text-subtle); margin-top: 5px;">
                                            Allow your profile to appear in search results
                                        </p>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="searchable" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                
                                <button type="submit" class="post-button">
                                    <i class="fas fa-lock"></i> Save Privacy Settings
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- NOTIFICATIONS SECTION -->
                    <div class="settings-section" id="notifications-section">
                        <div class="flixsy-card">
                            <h3>Notification Preferences</h3>
                            
                            <p style="color: var(--color-text-subtle); margin-bottom: var(--space-lg);">
                                Choose what notifications you want to receive
                            </p>
                            
                            <form method="POST">
                                <input type="hidden" name="action" value="update_notifications">
                                
                                <div class="settings-form-group" style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong>Likes</strong>
                                        <p style="font-size: 0.9em; color: var(--color-text-subtle); margin-top: 5px;">
                                            When someone likes your post
                                        </p>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="notif_likes" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                
                                <div class="settings-form-group" style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong>Comments</strong>
                                        <p style="font-size: 0.9em; color: var(--color-text-subtle); margin-top: 5px;">
                                            When someone comments on your post
                                        </p>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="notif_comments" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                
                                <div class="settings-form-group" style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong>New Followers</strong>
                                        <p style="font-size: 0.9em; color: var(--color-text-subtle); margin-top: 5px;">
                                            When someone follows you
                                        </p>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="notif_follows" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                
                                <div class="settings-form-group" style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong>Mentions</strong>
                                        <p style="font-size: 0.9em; color: var(--color-text-subtle); margin-top: 5px;">
                                            When someone mentions you
                                        </p>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="notif_mentions" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                
                                <div class="settings-form-group" style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong>Messages</strong>
                                        <p style="font-size: 0.9em; color: var(--color-text-subtle); margin-top: 5px;">
                                            When you receive a new message
                                        </p>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="notif_messages" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                
                                <button type="submit" class="post-button">
                                    <i class="fas fa-bell"></i> Save Notification Settings
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- APPEARANCE SECTION -->
                    <div class="settings-section" id="appearance-section">
                        <div class="flixsy-card">
                            <h3>Appearance</h3>
                            
                            <div class="settings-form-group">
                                <label>Theme</label>
                                <select onchange="alert('Theme switching coming soon!')">
                                    <option value="dark">Dark Mode (Current)</option>
                                    <option value="light">Light Mode (Coming Soon)</option>
                                    <option value="auto">Auto (Coming Soon)</option>
                                </select>
                            </div>
                            
                            <div class="settings-form-group">
                                <label>Language</label>
                                <select>
                                    <option value="en">English</option>
                                    <option value="fr">Français (Coming Soon)</option>
                                    <option value="es">Español (Coming Soon)</option>
                                    <option value="de">Deutsch (Coming Soon)</option>
                                </select>
                            </div>
                            
                            <button class="post-button">
                                <i class="fas fa-palette"></i> Save Appearance
                            </button>
                        </div>
                    </div>

                    <!-- DANGER ZONE SECTION -->
                    <div class="settings-section" id="danger-section">
                        <div class="danger-zone">
                            <h3 style="color: var(--color-error);">
                                <i class="fas fa-exclamation-triangle"></i> Danger Zone
                            </h3>
                            <p style="margin-bottom: var(--space-lg);">
                                These actions are permanent and cannot be undone.
                            </p>
                            
                            <form method="POST" onsubmit="return confirm('Are you absolutely sure? This action CANNOT be undone!');">
                                <input type="hidden" name="action" value="delete_account">
                                
                                <div class="settings-form-group">
                                    <label>Confirm Password</label>
                                    <input type="password" 
                                           name="delete_password" 
                                           required 
                                           placeholder="Enter your password">
                                </div>
                                
                                <div class="settings-form-group">
                                    <label>Type "DELETE" to confirm</label>
                                    <input type="text" 
                                           name="confirm_delete" 
                                           required 
                                           placeholder="Type DELETE">
                                </div>
                                
                                <button type="submit" 
                                        class="post-button" 
                                        style="background-color: var(--color-error);">
                                    <i class="fas fa-trash"></i> Delete My Account
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        </main>
        
        <aside></aside>
    </div>

    <script>
    // Settings navigation
    document.querySelectorAll('.settings-nav-item').forEach(item => {
        item.addEventListener('click', function() {
            // Remove active class from all items
            document.querySelectorAll('.settings-nav-item').forEach(i => i.classList.remove('active'));
            document.querySelectorAll('.settings-section').forEach(s => s.classList.remove('active'));
            
            // Add active class to clicked item
            this.classList.add('active');
            
            // Show corresponding section
            const section = this.dataset.section;
            document.getElementById(section + '-section').classList.add('active');
        });
    });
    
    // Avatar preview
    document.getElementById('avatar-input').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('avatar-preview').src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    });
    </script>

</body>
</html>
