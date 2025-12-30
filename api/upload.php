<?php
// =======================================
// FLIXSY UPLOAD API (api/upload.php)
// Handle media uploads and post creation
// =======================================

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check authentication
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Authentication required'
    ]);
    exit;
}

// Configuration
$uploadDir = '../uploads/';
$maxFileSize = 10 * 1024 * 1024; // 10MB
$allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$allowedVideoTypes = ['video/mp4', 'video/webm', 'video/quicktime'];
$allowedAudioTypes = ['audio/mpeg', 'audio/wav', 'audio/mp3'];

$userId = getCurrentUserId();

// Get form data
$caption = trim($_POST['caption'] ?? '');
$privacy = $_POST['privacy'] ?? 'public';

// Validate privacy setting
if (!in_array($privacy, ['public', 'followers', 'private'])) {
    $privacy = 'public';
}

// Validate: must have caption or media
if (empty($caption) && empty($_FILES['media']['name'])) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Post must have either caption or media'
    ]);
    exit;
}

$mediaUrl = null;
$mediaType = null;

// Handle media upload if present
if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['media'];
    
    // Validate file size
    if ($file['size'] > $maxFileSize) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'File size exceeds 10MB limit'
        ]);
        exit;
    }
    
    // Validate and determine media type
    $fileMimeType = mime_content_type($file['tmp_name']);
    
    if (in_array($fileMimeType, $allowedImageTypes)) {
        $mediaType = 'image';
    } elseif (in_array($fileMimeType, $allowedVideoTypes)) {
        $mediaType = 'video';
    } elseif (in_array($fileMimeType, $allowedAudioTypes)) {
        $mediaType = 'audio';
    } else {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid file type. Only images, videos, and audio files are allowed.'
        ]);
        exit;
    }
    
    // Generate safe filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safeFilename = uniqid('post_' . $userId . '_', true) . '.' . strtolower($extension);
    $targetPath = $uploadDir . $safeFilename;
    
    // Create upload directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to upload file. Please check server permissions.'
        ]);
        exit;
    }
    
    $mediaUrl = 'uploads/' . $safeFilename;
    
    // For images, create thumbnail (optional optimization)
    if ($mediaType === 'image' && extension_loaded('gd')) {
        createThumbnail($targetPath, $uploadDir . 'thumb_' . $safeFilename);
    }
}

// Create post in database
try {
    global $pdo;
    
    dbBeginTransaction();
    
    // Insert post
    $stmt = $pdo->prepare("
        INSERT INTO posts (user_id, caption, media_url, media_type, privacy, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $userId,
        $caption,
        $mediaUrl,
        $mediaType,
        $privacy
    ]);
    
    $postId = $pdo->lastInsertId();
    
    // Extract and save hashtags
    if (!empty($caption)) {
        extractHashtags($postId, $caption);
    }
    
    // Add XP
    addXP($userId, 'post');
    
    dbCommit();
    
    // Get the created post
    $post = getPost($postId, $userId);
    
    // Success response
    echo json_encode([
        'status' => 'success',
        'post_id' => $postId,
        'media_url' => $mediaUrl,
        'message' => 'Post created successfully',
        'post' => $post
    ]);
    
} catch (Exception $e) {
    // Rollback database changes
    if ($pdo->inTransaction()) {
        dbRollback();
    }
    
    // Delete uploaded file if it exists
    if ($mediaUrl && file_exists($targetPath)) {
        unlink($targetPath);
    }
    
    error_log("Upload API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to create post. Please try again.'
    ]);
}

// =======================================
// HELPER FUNCTIONS
// =======================================

/**
 * Create thumbnail for images
 * @param string $source Source image path
 * @param string $destination Destination thumbnail path
 * @param int $width Max width
 * @param int $height Max height
 */
function createThumbnail($source, $destination, $width = 300, $height = 300) {
    try {
        list($origWidth, $origHeight, $type) = getimagesize($source);
        
        // Calculate proportional dimensions
        $ratio = min($width / $origWidth, $height / $origHeight);
        $newWidth = (int)($origWidth * $ratio);
        $newHeight = (int)($origHeight * $ratio);
        
        // Create new image
        $thumb = imagecreatetruecolor($newWidth, $newHeight);
        
        // Load source image based on type
        switch ($type) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($source);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($source);
                imagealphablending($thumb, false);
                imagesavealpha($thumb, true);
                break;
            case IMAGETYPE_GIF:
                $image = imagecreatefromgif($source);
                break;
            default:
                return false;
        }
        
        // Resize
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
        
        // Save thumbnail
        imagejpeg($thumb, $destination, 85);
        
        // Clean up
        imagedestroy($image);
        imagedestroy($thumb);
        
        return true;
    } catch (Exception $e) {
        error_log("Thumbnail creation failed: " . $e->getMessage());
        return false;
    }
}
?>
