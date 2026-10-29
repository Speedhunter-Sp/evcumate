<?php
// api/upload_photo.php
// POST multipart/form-data  { user_id, photo }
// Saves the image to /uploads/profile_pics/ and stores the public URL in users.profile_pic_url
// Returns { "success": true, "url": "https://..." }

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'error' => 'Method not allowed'], 405);
}

$userId = trim($_POST['user_id'] ?? '');
if (!$userId) {
    respond(['success' => false, 'error' => 'user_id required'], 400);
}

if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    respond(['success' => false, 'error' => 'No photo uploaded or upload error'], 400);
}

$file     = $_FILES['photo'];
$maxSize  = 2 * 1024 * 1024; // 2 MB limit
if ($file['size'] > $maxSize) {
    respond(['success' => false, 'error' => 'File too large (max 2 MB)'], 400);
}

// Allow only common image MIME types
$allowed  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);
if (!in_array($mimeType, $allowed, true)) {
    respond(['success' => false, 'error' => 'Invalid image type'], 400);
}

// Build storage path
$ext     = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
$dirPath = __DIR__ . '/../uploads/profile_pics/';
if (!is_dir($dirPath)) {
    mkdir($dirPath, 0755, true);
}

$filename = 'user_' . preg_replace('/[^a-zA-Z0-9]/', '', $userId)
          . '_' . time() . '.' . $ext;
$destPath = $dirPath . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    respond(['success' => false, 'error' => 'Failed to save file'], 500);
}

// Build public URL – assumes the project root is the domain root
$protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host      = $_SERVER['HTTP_HOST'];
$publicUrl = $protocol . '://' . $host . '/uploads/profile_pics/' . $filename;

// Store in DB
$stmt = db()->prepare(
    'UPDATE users SET profile_pic_url = :url WHERE user_id = :id AND deleted_at IS NULL'
);
$stmt->execute([':url' => $publicUrl, ':id' => $userId]);

respond(['success' => true, 'url' => $publicUrl]);