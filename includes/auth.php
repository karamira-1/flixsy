<?php
// =======================================
// FLIXSY AUTHENTICATION (includes/auth.php)
// Real session management and authentication
// =======================================

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

// =======================================
// USER REGISTRATION
// =======================================

/**
 * Register a new user
 * @param string $username Username
 * @param string $email Email address
 * @param string $password Plain text password
 * @return array Result with success status and message
 */
function registerUser($username, $email, $password) {
    global $pdo;
    
    // Validate inputs
    $errors = [];
    
    if (strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters';
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address';
    }
    
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters';
    }
    
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }
    
    // Check if username or email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    
    if ($stmt->fetch()) {
        return ['success' => false, 'errors' => ['Username or email already exists']];
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    
    // Insert user
    try {
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        
        $stmt->execute([$username, $email, $hashedPassword]);
        $userId = $pdo->lastInsertId();
        
        // Log the user in immediately
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['logged_in_at'] = time();
        
        return [
            'success' => true,
            'user_id' => $userId,
            'message' => 'Registration successful'
        ];
        
    } catch (PDOException $e) {
        error_log("Registration error: " . $e->getMessage());
        return ['success' => false, 'errors' => ['Registration failed. Please try again.']];
    }
}

// =======================================
// USER LOGIN
// =======================================

/**
 * Log in a user
 * @param string $email Email address
 * @param string $password Plain text password
 * @return array Result with success status and message
 */
function loginUser($email, $password) {
    global $pdo;
    
    // Fetch user by email
    $stmt = $pdo->prepare("
        SELECT id, username, email, password, is_banned, sector, xp, level 
        FROM users 
        WHERE email = ?
    ");
    
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    // Check if user exists
    if (!$user) {
        return ['success' => false, 'error' => 'Invalid email or password'];
    }
    
    // Check if account is banned
    if ($user['is_banned']) {
        return ['success' => false, 'error' => 'Your account has been banned'];
    }
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'error' => 'Invalid email or password'];
    }
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['sector'] = $user['sector'];
    $_SESSION['xp'] = $user['xp'];
    $_SESSION['level'] = $user['level'];
    $_SESSION['logged_in_at'] = time();
    
    // Update last active timestamp
    $pdo->prepare("UPDATE users SET last_active = NOW() WHERE id = ?")
        ->execute([$user['id']]);
    
    return [
        'success' => true,
        'user' => $user,
        'message' => 'Login successful'
    ];
}

// =======================================
// SESSION MANAGEMENT
// =======================================

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require user to be logged in (redirect if not)
 * @param string $redirectTo URL to redirect to
 */
function requireLogin($redirectTo = '../pages/login.php') {
    if (!isLoggedIn()) {
        header("Location: $redirectTo");
        exit;
    }
}

/**
 * Get current user ID
 * @return int|null
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user data
 * @return array|false
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return false;
    }
    
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT id, username, email, bio, profile_pic, sector, xp, level, is_verified, is_admin
        FROM users 
        WHERE id = ?
    ");
    
    $stmt->execute([getCurrentUserId()]);
    return $stmt->fetch();
}

/**
 * Check if current user is admin
 * @return bool
 */
function isAdmin() {
    if (!isLoggedIn()) {
        return false;
    }
    
    global $pdo;
    $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $stmt->execute([getCurrentUserId()]);
    $user = $stmt->fetch();
    
    return $user && $user['is_admin'];
}

/**
 * Require admin access
 */
function requireAdmin() {
    if (!isAdmin()) {
        http_response_code(403);
        die("Access denied. Admin privileges required.");
    }
}

// =======================================
// LOGOUT
// =======================================

/**
 * Log out the current user
 * @param string $redirectTo URL to redirect to
 */
function logoutUser($redirectTo = '../pages/login.php') {
    // Unset all session variables
    $_SESSION = [];
    
    // Destroy session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    
    // Destroy session
    session_destroy();
    
    // Redirect
    header("Location: $redirectTo");
    exit;
}

// =======================================
// PASSWORD MANAGEMENT
// =======================================

/**
 * Change user password
 * @param int $userId User ID
 * @param string $oldPassword Current password
 * @param string $newPassword New password
 * @return array Result
 */
function changePassword($userId, $oldPassword, $newPassword) {
    global $pdo;
    
    // Validate new password
    if (strlen($newPassword) < 8) {
        return ['success' => false, 'error' => 'New password must be at least 8 characters'];
    }
    
    // Get current password hash
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return ['success' => false, 'error' => 'User not found'];
    }
    
    // Verify old password
    if (!password_verify($oldPassword, $user['password'])) {
        return ['success' => false, 'error' => 'Current password is incorrect'];
    }
    
    // Hash new password
    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    
    // Update password
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$hashedPassword, $userId]);
    
    return ['success' => true, 'message' => 'Password updated successfully'];
}

// =======================================
// CSRF PROTECTION
// =======================================

/**
 * Generate CSRF token
 * @return string
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token Token to verify
 * @return bool
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// =======================================
// SESSION TIMEOUT (30 minutes)
// =======================================
if (isLoggedIn()) {
    $timeout = 1800; // 30 minutes
    
    if (isset($_SESSION['logged_in_at']) && (time() - $_SESSION['logged_in_at'] > $timeout)) {
        logoutUser();
    }
    
    // Update last activity time
    $_SESSION['logged_in_at'] = time();
}
?>
