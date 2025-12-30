<?php
// =======================================
// FLIXSY SIGNUP PAGE - COMPLETE VERSION
// =======================================

require_once '../includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: home.php");
    exit;
}

$errors = [];
$username = '';
$email = '';

// Handle signup form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $agreeTerms = isset($_POST['agree_terms']);
    
    // Validation
    if (empty($username)) {
        $errors[] = 'Username is required';
    } elseif (strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = 'Username can only contain letters, numbers, and underscores';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters';
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match';
    }
    
    if (!$agreeTerms) {
        $errors[] = 'You must agree to the Terms of Service';
    }
    
    // If no validation errors, attempt registration
    if (empty($errors)) {
        $result = registerUser($username, $email, $password);
        
        if ($result['success']) {
            // Redirect to home (user is already logged in via registerUser)
            header("Location: home.php");
            exit;
        } else {
            $errors = $result['errors'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flixsy | Sign Up</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
    
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            padding: 20px;
        }
        
        .auth-container {
            background: var(--color-surface-dark);
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .auth-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .auth-logo h1 {
            font-size: 3em;
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
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
        
        .auth-input.error {
            border-color: var(--color-error);
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
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .auth-checkbox input {
            width: 18px;
            height: 18px;
            cursor: pointer;
            margin-top: 2px;
        }
        
        .auth-checkbox label {
            cursor: pointer;
            font-size: 0.9em;
            color: var(--color-text-subtle);
            line-height: 1.5;
        }
        
        .auth-submit {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
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
        
        .auth-links {
            text-align: center;
            margin-top: 20px;
        }
        
        .auth-links a {
            color: var(--color-primary);
            font-weight: 600;
        }
        
        .auth-errors {
            background: rgba(255, 99, 71, 0.2);
            border: 1px solid var(--color-error);
            border-radius: var(--border-radius);
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .auth-errors ul {
            margin: 0;
            padding-left: 20px;
            color: var(--color-error);
        }
        
        .auth-errors li {
            margin-bottom: 5px;
        }
        
        .password-strength {
            height: 4px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0;
            transition: width 0.3s, background-color 0.3s;
        }
        
        .password-strength-bar.weak {
            width: 33%;
            background: var(--color-error);
        }
        
        .password-strength-bar.medium {
            width: 66%;
            background: var(--color-secondary);
        }
        
        .password-strength-bar.strong {
            width: 100%;
            background: var(--color-success);
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--color-text-subtle);
        }
        
        .username-check {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
        }
    </style>
</head>
<body>

    <div class="auth-container">
        
        <!-- Logo -->
        <div class="auth-logo">
            <h1>Flixsy</h1>
            <p>Join the Community</p>
        </div>

        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="auth-errors">
                <i class="fas fa-exclamation-circle"></i>
                <strong>Please fix the following errors:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Signup Form -->
        <form method="POST" action="" id="signup-form">
            
            <!-- Username -->
            <div class="auth-form-group">
                <label for="username">Username</label>
                <div class="auth-input-icon">
                    <i class="fas fa-user"></i>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           class="auth-input"
                           value="<?= htmlspecialchars($username) ?>"
                           placeholder="Choose a username"
                           pattern="[a-zA-Z0-9_]+"
                           minlength="3"
                           maxlength="20"
                           required
                           autofocus>
                    <span id="username-check" class="username-check" style="display: none;"></span>
                </div>
                <small style="color: var(--color-text-subtle); font-size: 0.85em;">
                    3-20 characters, letters, numbers, and underscores only
                </small>
            </div>

            <!-- Email -->
            <div class="auth-form-group">
                <label for="email">Email Address</label>
                <div class="auth-input-icon">
                    <i class="fas fa-envelope"></i>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           class="auth-input"
                           value="<?= htmlspecialchars($email) ?>"
                           placeholder="Enter your email"
                           required>
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
                           placeholder="Create a password"
                           minlength="8"
                           required
                           oninput="checkPasswordStrength()">
                    <span class="password-toggle" onclick="togglePassword('password')">
                        <i class="fas fa-eye" id="password-icon"></i>
                    </span>
                </div>
                <div class="password-strength">
                    <div id="password-strength-bar" class="password-strength-bar"></div>
                </div>
                <small id="password-strength-text" style="color: var(--color-text-subtle); font-size: 0.85em;">
                    Minimum 8 characters
                </small>
            </div>

            <!-- Confirm Password -->
            <div class="auth-form-group">
                <label for="confirm_password">Confirm Password</label>
                <div class="auth-input-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" 
                           id="confirm_password" 
                           name="confirm_password" 
                           class="auth-input"
                           placeholder="Re-enter your password"
                           minlength="8"
                           required
                           oninput="checkPasswordMatch()">
                    <span class="password-toggle" onclick="togglePassword('confirm_password')">
                        <i class="fas fa-eye" id="confirm-password-icon"></i>
                    </span>
                </div>
                <small id="password-match-text" style="font-size: 0.85em;"></small>
            </div>

            <!-- Terms Checkbox -->
            <div class="auth-checkbox">
                <input type="checkbox" id="agree_terms" name="agree_terms" required>
                <label for="agree_terms">
                    I agree to the <a href="#" style="color: var(--color-primary);">Terms of Service</a> 
                    and <a href="#" style="color: var(--color-primary);">Privacy Policy</a>
                </label>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="auth-submit">
                <i class="fas fa-user-plus"></i> Create Account
            </button>

        </form>

        <!-- Login Link -->
        <div class="auth-links">
            <p style="color: var(--color-text-subtle);">
                Already have an account? 
                <a href="login.php">Login here</a>
            </p>
        </div>

    </div>

    <script>
        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + '-icon');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Check password strength
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthBar = document.getElementById('password-strength-bar');
            const strengthText = document.getElementById('password-strength-text');
            
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            strengthBar.className = 'password-strength-bar';
            
            if (strength === 0 || strength === 1) {
                strengthBar.classList.add('weak');
                strengthText.textContent = 'Weak password';
                strengthText.style.color = 'var(--color-error)';
            } else if (strength === 2 || strength === 3) {
                strengthBar.classList.add('medium');
                strengthText.textContent = 'Medium password';
                strengthText.style.color = 'var(--color-secondary)';
            } else {
                strengthBar.classList.add('strong');
                strengthText.textContent = 'Strong password';
                strengthText.style.color = 'var(--color-success)';
            }
            
            checkPasswordMatch();
        }

        // Check password match
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchText = document.getElementById('password-match-text');
            
            if (confirmPassword === '') {
                matchText.textContent = '';
                return;
            }
            
            if (password === confirmPassword) {
                matchText.textContent = '✓ Passwords match';
                matchText.style.color = 'var(--color-success)';
            } else {
                matchText.textContent = '✗ Passwords do not match';
                matchText.style.color = 'var(--color-error)';
            }
        }

        // Real-time username validation
        let usernameTimeout;
        document.getElementById('username').addEventListener('input', function() {
            clearTimeout(usernameTimeout);
            const username = this.value;
            const checkIcon = document.getElementById('username-check');
            
            if (username.length < 3) {
                checkIcon.style.display = 'none';
                return;
            }
            
            checkIcon.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            checkIcon.style.display = 'block';
            
            // Simulate username check (you can add AJAX call here)
            usernameTimeout = setTimeout(() => {
                const isValid = /^[a-zA-Z0-9_]+$/.test(username);
                if (isValid) {
                    checkIcon.innerHTML = '<i class="fas fa-check" style="color: var(--color-success);"></i>';
                } else {
                    checkIcon.innerHTML = '<i class="fas fa-times" style="color: var(--color-error);"></i>';
                }
            }, 500);
        });
    </script>

</body>
</html>
