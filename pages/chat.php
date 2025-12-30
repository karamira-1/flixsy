<?php
// =======================================
// FLIXSY CHAT - COMPLETE WITH DMs & PERSISTENCE
// =======================================

require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();

$currentUser = getCurrentUser();

// Get chat ID from URL (or default to null for list view)
$chatId = isset($_GET['id']) ? (int)$_GET['id'] : null;

global $pdo;

// Get user's conversations
$conversationsStmt = $pdo->prepare("
    SELECT DISTINCT
        CASE 
            WHEN m.sender_id = ? THEN m.receiver_id
            ELSE m.sender_id
        END as other_user_id,
        u.username,
        u.profile_pic,
        u.is_verified,
        MAX(m.created_at) as last_message_time,
        (SELECT content FROM messages m2 
         WHERE (m2.sender_id = ? AND m2.receiver_id = u.id) 
            OR (m2.receiver_id = ? AND m2.sender_id = u.id)
         ORDER BY m2.created_at DESC LIMIT 1) as last_message,
        (SELECT COUNT(*) FROM messages 
         WHERE sender_id = other_user_id 
         AND receiver_id = ? 
         AND is_read = 0) as unread_count
    FROM messages m
    JOIN users u ON (
        CASE 
            WHEN m.sender_id = ? THEN m.receiver_id = u.id
            ELSE m.sender_id = u.id
        END
    )
    WHERE m.sender_id = ? OR m.receiver_id = ?
    GROUP BY other_user_id
    ORDER BY last_message_time DESC
");

$conversationsStmt->execute([
    $currentUser['id'], 
    $currentUser['id'], 
    $currentUser['id'],
    $currentUser['id'],
    $currentUser['id'],
    $currentUser['id'],
    $currentUser['id']
]);
$conversations = $conversationsStmt->fetchAll();

// Get current conversation details and messages
$currentChat = null;
$messages = [];

if ($chatId) {
    // Get other user details
    $chatUserStmt = $pdo->prepare("
        SELECT id, username, profile_pic, is_verified 
        FROM users 
        WHERE id = ?
    ");
    $chatUserStmt->execute([$chatId]);
    $currentChat = $chatUserStmt->fetch();
    
    if ($currentChat) {
        // Get messages
        $messagesStmt = $pdo->prepare("
            SELECT m.*, 
                   u.username, 
                   u.profile_pic
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE (m.sender_id = ? AND m.receiver_id = ?)
               OR (m.sender_id = ? AND m.receiver_id = ?)
            ORDER BY m.created_at ASC
        ");
        $messagesStmt->execute([
            $currentUser['id'], 
            $chatId, 
            $chatId, 
            $currentUser['id']
        ]);
        $messages = $messagesStmt->fetchAll();
        
        // Mark messages as read
        $pdo->prepare("
            UPDATE messages 
            SET is_read = 1 
            WHERE receiver_id = ? AND sender_id = ? AND is_read = 0
        ")->execute([$currentUser['id'], $chatId]);
    }
}

// Get all users for new chat
$allUsersStmt = $pdo->prepare("
    SELECT id, username, profile_pic, is_verified
    FROM users
    WHERE id != ? AND is_banned = 0
    ORDER BY username ASC
");
$allUsersStmt->execute([$currentUser['id']]);
$allUsers = $allUsersStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flixsy | Messages</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
    <script src="https://cdn.socket.io/4.7.5/socket.io.min.js"></script>
    
    <style>
        .chat-container {
            display: grid;
            grid-template-columns: 250px 350px 1fr;
            gap: var(--space-md);
            height: calc(100vh - 32px);
            padding: var(--space-md) 0;
        }
        
        .chat-sidebar {
            background: var(--color-surface-dark);
            border-radius: var(--border-radius);
            padding: var(--space-md);
            overflow-y: auto;
        }
        
        .chat-list {
            background: var(--color-surface-dark);
            border-radius: var(--border-radius);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .chat-list-header {
            padding: var(--space-lg);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .chat-list-items {
            flex: 1;
            overflow-y: auto;
        }
        
        .chat-list-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: var(--space-md);
            cursor: pointer;
            transition: background 0.3s;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .chat-list-item:hover {
            background: var(--color-background-dark);
        }
        
        .chat-list-item.active {
            background: var(--color-primary);
        }
        
        .chat-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        
        .chat-info {
            flex: 1;
            min-width: 0;
        }
        
        .chat-username {
            font-weight: bold;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .chat-last-message {
            font-size: 0.85em;
            color: var(--color-text-subtle);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .unread-badge {
            background: var(--color-error);
            color: white;
            border-radius: 10px;
            padding: 2px 8px;
            font-size: 0.75em;
            font-weight: bold;
        }
        
        .chat-window {
            background: var(--color-surface-dark);
            border-radius: var(--border-radius);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .chat-header {
            padding: var(--space-lg);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: var(--space-lg);
            display: flex;
            flex-direction: column;
            gap: var(--space-md);
        }
        
        .message {
            display: flex;
            gap: 10px;
            max-width: 70%;
        }
        
        .message.sent {
            margin-left: auto;
            flex-direction: row-reverse;
        }
        
        .message-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        
        .message-bubble {
            padding: 10px 15px;
            border-radius: 18px;
            word-wrap: break-word;
        }
        
        .message.received .message-bubble {
            background: var(--color-background-dark);
        }
        
        .message.sent .message-bubble {
            background: var(--color-primary);
            color: white;
        }
        
        .message-time {
            font-size: 0.75em;
            color: var(--color-text-subtle);
            margin-top: 4px;
        }
        
        .chat-input-container {
            padding: var(--space-lg);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            gap: var(--space-md);
            align-items: center;
        }
        
        .chat-input {
            flex: 1;
            background: var(--color-background-dark);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 25px;
            padding: 12px 20px;
            color: var(--color-text-light);
            resize: none;
            max-height: 100px;
        }
        
        .chat-input:focus {
            outline: none;
            border-color: var(--color-primary);
        }
        
        .new-chat-modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .new-chat-modal.active {
            display: flex;
        }
        
        .new-chat-content {
            background: var(--color-surface-dark);
            padding: var(--space-lg);
            border-radius: var(--border-radius);
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .user-select-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: var(--space-md);
            cursor: pointer;
            border-radius: var(--border-radius);
            margin-bottom: 8px;
        }
        
        .user-select-item:hover {
            background: var(--color-background-dark);
        }
    </style>
</head>
<body>

    <div class="chat-container">
        
        <!-- LEFT SIDEBAR (Navigation) -->
        <aside class="chat-sidebar">
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
                <a href="chat.php" class="nav-link active">
                    <i class="fas fa-comments"></i> Chat
                </a>
                <a href="profile.php?id=<?= $currentUser['id'] ?>" class="nav-link">
                    <i class="fas fa-user-circle"></i> Profile
                </a>
                <a href="settings.php" class="nav-link">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </nav>

            <button class="post-button" onclick="openNewChatModal()">
                <i class="fas fa-plus"></i> New Chat
            </button>
        </aside>

        <!-- CHAT LIST -->
        <div class="chat-list">
            <div class="chat-list-header">
                <h3>Messages</h3>
                <button onclick="openNewChatModal()" style="background: none; border: none; color: var(--color-primary); cursor: pointer; font-size: 1.2em;">
                    <i class="fas fa-edit"></i>
                </button>
            </div>
            
            <div class="chat-list-items">
                <?php if (empty($conversations)): ?>
                    <div style="text-align: center; padding: 40px 20px; color: var(--color-text-subtle);">
                        <i class="fas fa-inbox" style="font-size: 3em; margin-bottom: 15px; opacity: 0.5;"></i>
                        <p>No conversations yet</p>
                        <button onclick="openNewChatModal()" class="post-button" style="margin-top: 15px; width: auto;">
                            Start a chat
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($conversations as $conv): ?>
                        <a href="chat.php?id=<?= $conv['other_user_id'] ?>" 
                           class="chat-list-item <?= $chatId == $conv['other_user_id'] ? 'active' : '' ?>"
                           style="text-decoration: none; color: inherit;">
                            <img src="../<?= e($conv['profile_pic']) ?>" 
                                 class="chat-avatar"
                                 alt="<?= e($conv['username']) ?>">
                            <div class="chat-info">
                                <span class="chat-username">
                                    <?= e($conv['username']) ?>
                                    <?php if ($conv['is_verified']): ?>
                                        <i class="fas fa-check-circle" style="color: var(--color-primary); font-size: 0.8em;"></i>
                                    <?php endif; ?>
                                </span>
                                <span class="chat-last-message"><?= e($conv['last_message']) ?></span>
                            </div>
                            <?php if ($conv['unread_count'] > 0): ?>
                                <span class="unread-badge"><?= $conv['unread_count'] ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- CHAT WINDOW -->
        <div class="chat-window">
            <?php if ($currentChat): ?>
                <!-- Chat Header -->
                <div class="chat-header">
                    <img src="../<?= e($currentChat['profile_pic']) ?>" 
                         class="chat-avatar"
                         alt="<?= e($currentChat['username']) ?>">
                    <div style="flex: 1;">
                        <div style="font-weight: bold;">
                            <?= e($currentChat['username']) ?>
                            <?php if ($currentChat['is_verified']): ?>
                                <i class="fas fa-check-circle" style="color: var(--color-primary); font-size: 0.9em;"></i>
                            <?php endif; ?>
                        </div>
                        <div style="font-size: 0.85em; color: var(--color-text-subtle);">
                            <i class="fas fa-circle" style="font-size: 0.6em; color: var(--color-success);"></i> Online
                        </div>
                    </div>
                    <a href="profile.php?id=<?= $currentChat['id'] ?>" 
                       style="color: var(--color-primary);">
                        <i class="fas fa-info-circle"></i>
                    </a>
                </div>

                <!-- Messages -->
                <div class="messages-container" id="messages-container">
                    <?php if (empty($messages)): ?>
                        <div style="text-align: center; color: var(--color-text-subtle); margin: auto;">
                            <i class="fas fa-comment-dots" style="font-size: 3em; opacity: 0.5; margin-bottom: 15px;"></i>
                            <p>No messages yet. Start the conversation!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                            <div class="message <?= $msg['sender_id'] == $currentUser['id'] ? 'sent' : 'received' ?>">
                                <img src="../<?= e($msg['profile_pic']) ?>" 
                                     class="message-avatar"
                                     alt="<?= e($msg['username']) ?>">
                                <div>
                                    <div class="message-bubble">
                                        <?= nl2br(e($msg['content'])) ?>
                                    </div>
                                    <div class="message-time">
                                        <?= timeAgo($msg['created_at']) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Message Input -->
                <div class="chat-input-container">
                    <textarea id="message-input" 
                              class="chat-input"
                              placeholder="Type a message..."
                              rows="1"></textarea>
                    <button onclick="sendMessage()" 
                            class="post-button" 
                            style="padding: 12px 24px; border-radius: 25px;">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>

            <?php else: ?>
                <!-- No chat selected -->
                <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: var(--color-text-subtle);">
                    <div style="text-align: center;">
                        <i class="fas fa-comments" style="font-size: 5em; opacity: 0.3; margin-bottom: 20px;"></i>
                        <h3>Select a conversation</h3>
                        <p>Choose from your existing conversations<br>or start a new one</p>
                        <button onclick="openNewChatModal()" class="post-button" style="margin-top: 20px;">
                            <i class="fas fa-plus"></i> New Chat
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- New Chat Modal -->
    <div class="new-chat-modal" id="new-chat-modal">
        <div class="new-chat-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3>New Message</h3>
                <button onclick="closeNewChatModal()" style="background: none; border: none; color: var(--color-text-subtle); font-size: 1.5em; cursor: pointer;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <input type="text" 
                   id="user-search" 
                   placeholder="Search users..."
                   style="width: 100%; padding: 12px; background: var(--color-background-dark); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: var(--border-radius); color: var(--color-text-light); margin-bottom: 20px;"
                   oninput="filterUsers()">
            
            <div id="users-list">
                <?php foreach ($allUsers as $user): ?>
                    <div class="user-select-item" 
                         data-username="<?= e($user['username']) ?>"
                         onclick="startChat(<?= $user['id'] ?>)">
                        <img src="../<?= e($user['profile_pic']) ?>" 
                             class="chat-avatar"
                             alt="<?= e($user['username']) ?>"
                             style="width: 45px; height: 45px;">
                        <div>
                            <div style="font-weight: bold;">
                                <?= e($user['username']) ?>
                                <?php if ($user['is_verified']): ?>
                                    <i class="fas fa-check-circle" style="color: var(--color-primary); font-size: 0.8em;"></i>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    
    <script>
        const SOCKET_SERVER_URL = 'http://localhost:3001';
        const socket = io(SOCKET_SERVER_URL);
        const currentUserId = <?= $currentUser['id'] ?>;
        const chatId = <?= $chatId ?? 'null' ?>;

        // Connect and authenticate
        socket.on('connect', () => {
            socket.emit('authenticate_user', currentUserId);
        });

        // Receive messages
        socket.on('receive_direct_message', (data) => {
            if (data.sender_id == chatId || data.receiver_id == chatId) {
                appendMessage(data);
            }
        });

        // Send message
        async function sendMessage() {
            const input = document.getElementById('message-input');
            const content = input.value.trim();
            
            if (!content || !chatId) return;
            
            try {
                // Save to database
                const response = await fetch('../api/message_api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        receiver_id: chatId,
                        content: content
                    })
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    // Send via Socket.IO for real-time delivery
                    socket.emit('send_direct_message', {
                        sender_id: currentUserId,
                        receiver_id: chatId,
                        content: content,
                        timestamp: new Date().toISOString()
                    });
                    
                    // Append to UI
                    appendMessage({
                        sender_id: currentUserId,
                        content: content,
                        created_at: new Date().toISOString()
                    });
                    
                    input.value = '';
                    input.style.height = 'auto';
                } else {
                    alert('Failed to send message');
                }
            } catch (error) {
                console.error('Send message error:', error);
            }
        }

        // Append message to UI
        function appendMessage(data) {
            const container = document.getElementById('messages-container');
            const isSent = data.sender_id == currentUserId;
            
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${isSent ? 'sent' : 'received'}`;
            messageDiv.innerHTML = `
                <img src="../<?= e($currentUser['profile_pic']) ?>" 
                     class="message-avatar">
                <div>
                    <div class="message-bubble">${escapeHtml(data.content)}</div>
                    <div class="message-time">Just now</div>
                </div>
            `;
            
            container.appendChild(messageDiv);
            container.scrollTop = container.scrollHeight;
        }

        // Helper functions
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function openNewChatModal() {
            document.getElementById('new-chat-modal').classList.add('active');
        }

        function closeNewChatModal() {
            document.getElementById('new-chat-modal').classList.remove('active');
        }

        function startChat(userId) {
            window.location.href = 'chat.php?id=' + userId;
        }

        function filterUsers() {
            const search = document.getElementById('user-search').value.toLowerCase();
            const items = document.querySelectorAll('.user-select-item');
            
            items.forEach(item => {
                const username = item.dataset.username.toLowerCase();
                item.style.display = username.includes(search) ? 'flex' : 'none';
            });
        }

        // Auto-expand textarea
        document.getElementById('message-input')?.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });

        // Send on Enter (Shift+Enter for new line)
        document.getElementById('message-input')?.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        // Scroll to bottom on load
        if (chatId) {
            const container = document.getElementById('messages-container');
            container.scrollTop = container.scrollHeight;
        }
    </script>

</body>
</html>
