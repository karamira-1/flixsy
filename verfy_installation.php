<?php
// =======================================
// FLIXSY INSTALLATION VERIFIER
// Place in root directory and run once
// =======================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

$checks = [];
$errors = [];
$warnings = [];

echo "<html><head><title>Flixsy Installation Check</title><style>
body { font-family: Arial; max-width: 800px; margin: 50px auto; background: #1a1a2e; color: #eee; padding: 20px; }
.check { padding: 10px; margin: 5px 0; border-radius: 5px; }
.success { background: rgba(60, 179, 113, 0.2); border-left: 4px solid #3CB371; }
.error { background: rgba(255, 99, 71, 0.2); border-left: 4px solid #FF6347; }
.warning { background: rgba(255, 165, 0, 0.2); border-left: 4px solid #FFA500; }
h2 { color: #667eea; }
</style></head><body>";

echo "<h1>🎊 Flixsy Installation Verification</h1>";

// 1. Check PHP Version
echo "<h2>1. PHP Environment</h2>";
$phpVersion = phpversion();
if (version_compare($phpVersion, '7.4.0', '>=')) {
    echo "<div class='check success'>✅ PHP Version: $phpVersion (OK)</div>";
} else {
    echo "<div class='check error'>❌ PHP Version: $phpVersion (Need 7.4+)</div>";
    $errors[] = "Upgrade PHP to 7.4 or higher";
}

// 2. Check Required Extensions
$extensions = ['pdo', 'pdo_mysql', 'gd', 'mbstring', 'json'];
foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<div class='check success'>✅ Extension '$ext' loaded</div>";
    } else {
        echo "<div class='check error'>❌ Extension '$ext' missing</div>";
        $errors[] = "Install PHP extension: $ext";
    }
}

// 3. Check Database Connection
echo "<h2>2. Database Connection</h2>";
if (file_exists('includes/db.php')) {
    require_once 'includes/db.php';
    try {
        $testQuery = $pdo->query("SELECT 1");
        echo "<div class='check success'>✅ Database connection successful</div>";
        
        // Check tables
        $tables = ['users', 'posts', 'likes', 'follows', 'comments', 'notifications', 
                   'hashtags', 'post_hashtags', 'xp_log', 'badges', 'stories', 
                   'story_views', 'messages'];
        
        $stmt = $pdo->query("SHOW TABLES");
        $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<h3>Database Tables:</h3>";
        foreach ($tables as $table) {
            if (in_array($table, $existingTables)) {
                // Count rows
                $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
                echo "<div class='check success'>✅ Table '$table' exists ($count rows)</div>";
            } else {
                echo "<div class='check error'>❌ Table '$table' missing</div>";
                $errors[] = "Run database_setup.sql to create table: $table";
            }
        }
        
    } catch (Exception $e) {
        echo "<div class='check error'>❌ Database error: " . $e->getMessage() . "</div>";
        $errors[] = "Fix database connection in includes/db.php";
    }
} else {
    echo "<div class='check error'>❌ includes/db.php not found</div>";
    $errors[] = "Create includes/db.php file";
}

// 4. Check File Structure
echo "<h2>3. File Structure</h2>";
$requiredDirs = [
    'includes' => 'Backend files',
    'api' => 'API endpoints',
    'pages' => 'Page files',
    'assets' => 'CSS/JS/Images',
    'uploads' => 'Media uploads'
];

foreach ($requiredDirs as $dir => $desc) {
    if (is_dir($dir)) {
        if (is_writable($dir) || $dir !== 'uploads') {
            echo "<div class='check success'>✅ Directory '$dir' exists ($desc)</div>";
        } else {
            echo "<div class='check warning'>⚠️ Directory '$dir' not writable</div>";
            $warnings[] = "Make '$dir' writable: chmod 777 $dir";
        }
    } else {
        echo "<div class='check error'>❌ Directory '$dir' missing</div>";
        $errors[] = "Create directory: $dir";
    }
}

// 5. Check Core Files
echo "<h2>4. Core Files</h2>";
$coreFiles = [
    'includes/db.php' => 'Database connection',
    'includes/auth.php' => 'Authentication system',
    'includes/functions.php' => 'Helper functions',
    'api/like.php' => 'Like API',
    'api/follow.php' => 'Follow API',
    'api/upload.php' => 'Upload API',
    'api/comment.php' => 'Comment API',
    'pages/login.php' => 'Login page',
    'pages/home.php' => 'Home feed',
    'pages/profile.php' => 'Profile page'
];

foreach ($coreFiles as $file => $desc) {
    if (file_exists($file)) {
        echo "<div class='check success'>✅ $file ($desc)</div>";
    } else {
        echo "<div class='check error'>❌ $file missing</div>";
        $errors[] = "Upload file: $file";
    }
}

// 6. Check Uploads Directory Permissions
echo "<h2>5. Permissions</h2>";
if (is_dir('uploads')) {
    if (is_writable('uploads')) {
        echo "<div class='check success'>✅ uploads/ is writable</div>";
    } else {
        echo "<div class='check error'>❌ uploads/ is not writable</div>";
        $errors[] = "Make uploads writable: chmod 777 uploads";
    }
    
    // Check .htaccess
    if (file_exists('uploads/.htaccess')) {
        echo "<div class='check success'>✅ uploads/.htaccess exists (security)</div>";
    } else {
        echo "<div class='check warning'>⚠️ uploads/.htaccess missing</div>";
        $warnings[] = "Create uploads/.htaccess for security";
    }
} else {
    echo "<div class='check error'>❌ uploads/ directory missing</div>";
}

// 7. Test Data Check
echo "<h2>6. Test Data</h2>";
try {
    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($userCount > 0) {
        echo "<div class='check success'>✅ $userCount users in database</div>";
        
        // Check for test user
        $stmt = $pdo->prepare("SELECT username FROM users WHERE email = ?");
        $stmt->execute(['test@flixsy.com']);
        if ($stmt->fetch()) {
            echo "<div class='check success'>✅ Test account exists (test@flixsy.com / password123)</div>";
        } else {
            echo "<div class='check warning'>⚠️ No test account found</div>";
            $warnings[] = "Create test account or run database_setup.sql";
        }
    } else {
        echo "<div class='check warning'>⚠️ No users in database</div>";
        $warnings[] = "Import test data from database_setup.sql";
    }
} catch (Exception $e) {
    echo "<div class='check error'>❌ Cannot check test data</div>";
}

// Summary
echo "<h2>📊 Summary</h2>";
if (empty($errors)) {
    echo "<div class='check success'>";
    echo "<h3>🎉 Installation Complete!</h3>";
    echo "<p>Your Flixsy platform is ready to use.</p>";
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ol>";
    echo "<li>Delete this verify-installation.php file</li>";
    echo "<li>Visit <a href='pages/login.php' style='color: #667eea;'>pages/login.php</a></li>";
    echo "<li>Login with: test@flixsy.com / password123</li>";
    echo "<li>Start testing features!</li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<div class='check error'>";
    echo "<h3>❌ Installation Incomplete</h3>";
    echo "<p>Please fix the following errors:</p>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul>";
    echo "</div>";
}

if (!empty($warnings)) {
    echo "<div class='check warning'>";
    echo "<h3>⚠️ Warnings (Recommended)</h3>";
    echo "<ul>";
    foreach ($warnings as $warning) {
        echo "<li>$warning</li>";
    }
    echo "</ul>";
    echo "</div>";
}

echo "</body></html>";
?>
