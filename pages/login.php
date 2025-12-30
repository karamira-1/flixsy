<?php
// =======================================
// FLIXSY LOGIN PAGE - COMPLETE VERSION
// =======================================

require_once '../includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: home.php");
    exit;
}

$error = '';
$email = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['remember_me']);
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $result = loginUser($email, $password);
        
        if ($result['success']) {
            // Set remember me cookie if checked
            if ($rememberMe) {
                setcookie('flixsy_remember', $email, time() + (86400 * 30), '/'); // 30 days
            }
            
            header("Location: home.php");
            exit;
        } else {
            $error = $result['error'];
        }
    }
}

// Check for remember me cookie
$rememberedEmail = $_COOKIE['flixsy_remember'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flixsy | Login</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
    
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }
        
        .auth-container {
            background: var(--color-surface-dark);
            border-radius: 20px;
            padding: 40px;
            max-width: 450px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .auth-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .auth-logo h1 {
            font-size: 3em;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }
        
        .auth-logo p {
            color: var(--color-text-subtle);
            font-size: 0.9em;
        }
        
        .auth-form-group {
            margin-bottom: 20px;
        }
        
        .auth-form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--color-text-light);
        }
        
        .auth-input {
            width: 100%;
            padding: 15px;
            background: var(--color-background-dark);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--border-radius);
            color: var(--color-text-light);
            font-size: 1em;
            transition: border-color 0.3s;
        }
        
        .auth-input:focus {
            outline: none;
            border-color: var(--color-primary);
        }
        
        .auth-input-icon {
            position: relative;
        }
        
        .auth-input-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--color-text-subtle);
        }
        
        .auth-input-icon input {
            padding-left: 45px;
        }
        
        .auth-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }
        
        .auth-checkbox input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .auth-checkbox label {
            cursor: pointer;
            font-size: 0.9em;
            color: var(--color-text-subtle);
        }
        
        .auth-submit {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: var(--border-radius);
            color: white;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .auth-submit:hover {
            transform: translateY(-2px);
        }
        
        .auth-submit:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .auth-divider {
            text-align: center;
            margin: 25px 0;
            color: var(--color-text-subtle);
            position: relative;
        }
        
        .auth-divider::before,
        .auth-divider::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 40%;
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .auth-divider::before {
            left: 0;
        }
        
        .auth-divider::after {
            right: 0;
        }
        
        .auth-links {
            text-align: center;
            margin-top: 20px;
        }
        
        .auth-links a {
            color: var(--color-primary);
            font-weight: 600;
        }
        
        .auth-error {
            background: rgba(255, 99, 71, 0.2);
            border: 1px solid var(--color-error);
            color: var(--color-error);
            padding: 12px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--color-text-subtle);
        }
    </style>
</head>
<body>

    <div class="auth-container">
        
        <!-- Logo -->
        <div class="auth-logo">
            <h1>Flixsy</h1>
            <p>Connect, Create, Share Your Story</p>
        </div>

        <!-- Error Message -->
        <?php if ($error): ?>
            <div class="auth-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" action="">
            
            <!-- Email -->
            <div class="auth-form-group">
                <label for="email">Email Address</label>
                <div class="auth-input-icon">
                    <i class="fas fa-envelope"></i>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           class="auth-input"
                           value="<?= htmlspecialchars($rememberedEmail ?: $email) ?>"
                           placeholder="Enter your email"
                           required
                           autofocus>
                </div>
            </div>

            <!-- Password -->
            <div class="auth-form-group">
                <label for="password">Password</label>
                <div class="auth-input-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="auth-input"
                           placeholder="Enter your password"
                           required>
                    <span class="password-toggle" onclick="togglePassword()">
                        <i class="fas fa-eye" id="password-icon"></i>
                    </span>
                </div>
            </div>

            <!-- Remember Me & Forgot Password -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <div class="auth-checkbox">
                    <input type="checkbox" id="remember_me" name="remember_me">
                    <label for="remember_me">Remember me</label>
                </div>
                <a href="#" style="font-size: 0.9em; color: var(--color-primary);">
                    Forgot password?
                </a>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="auth-submit">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>

        </form>

        <!-- Divider -->
        <div class="auth-divider">OR</div>

        <!-- Demo Account Info -->
        <div style="background: rgba(30, 144, 255, 0.1); padding: 15px; border-radius: var(--border-radius); margin-bottom: 20px;">
            <p style="font-size: 0.9em; color: var(--color-text-subtle); margin-bottom: 8px;">
                <i class="fas fa-info-circle"></i> Try Demo Account:
            </p>
            <p style="font-size: 0.85em; color: var(--color-text-light);">
                <strong>Email:</strong> test@flixsy.com<br>
                <strong>Password:</strong> password123
            </p>
        </div>

        <!-- Sign Up Link -->
        <div class="auth-links">
            <p style="color: var(--color-text-subtle);">
                Don't have an account? 
                <a href="signup.php">Sign up now</a>
            </p>
        </div>

    </div>

    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('password-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.classList.remove('fa-eye');
                passwordIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                passwordIcon.classList.remove('fa-eye-slash');
                passwordIcon.classList.add('fa-eye');
            }
        }

        // Auto-fill demo credentials
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('demo') === '1') {
                document.getElementById('email').value = 'test@flixsy.com';
                document.getElementById('password').value = 'password123';
            }
        });
    </script>

</body>
</html>
