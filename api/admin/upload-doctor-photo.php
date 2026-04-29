<?php
/**
 * Upload a doctor profile photo.
 *
 * Multipart POST with field "photo" (and optional doctor_id).
 * If doctor_id is given, also updates the doctors.profile_picture column.
 * Returns the saved filename so the caller can include it in add-doctor / update-doctor.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (!isLoggedIn() || !hasRole('admin')) {
    sendJSON(['success' => false, 'message' => 'Unauthorized'], 401);
}

if (empty($_FILES['photo']) || ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $code = $_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE;
    sendJSON(['success' => false, 'message' => 'Upload failed (error code ' . $code . ').'], 400);
}

$file    = $_FILES['photo'];
$tmp     = $file['tmp_name'];
$origRaw = (string) ($file['name'] ?? '');
$size    = (int) ($file['size'] ?? 0);

// Limits
$MAX = 5 * 1024 * 1024; // 5 MB
if ($size <= 0 || $size > $MAX) {
    sendJSON(['success' => false, 'message' => 'Photo must be between 1 byte and 5 MB.'], 400);
}

// MIME / extension check via finfo
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($tmp);
$allowed = [
    'image/jpeg' => 'jpg',
    'image/jpg'  => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];
if (!isset($allowed[$mime])) {
    sendJSON(['success' => false, 'message' => 'Only JPG, PNG, or WEBP images are allowed.'], 400);
}
$ext = $allowed[$mime];

// Build deterministic, non-guessable filename
$filename = sprintf('doctor_%d_%s.%s', time(), bin2hex(random_bytes(6)), $ext);
$destDir  = realpath(__DIR__ . '/../../uploads');
if ($destDir === false) {
    // Try to create it
    $destDir = __DIR__ . '/../../uploads';
    if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
    $destDir = realpath($destDir);
}
if (!$destDir || !is_writable($destDir)) {
    sendJSON(['success' => false, 'message' => 'Server upload directory is not writable.'], 500);
}
$destPath = $destDir . DIRECTORY_SEPARATOR . $filename;

if (!move_uploaded_file($tmp, $destPath)) {
    sendJSON(['success' => false, 'message' => 'Could not save uploaded photo.'], 500);
}
@chmod($destPath, 0644);

// Optionally update the doctors row
$doctor_id = isset($_POST['doctor_id']) ? (int) $_POST['doctor_id'] : 0;
$updated   = false;

try {
    $database = new Database();
    $db = $database->getConnection();

    if ($doctor_id > 0) {
        // Fetch existing photo so we can clean it up after a successful swap
        $stmt = $db->prepare('SELECT profile_picture FROM doctors WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $doctor_id]);
        $row = $stmt->fetch();
        if (!$row) {
            // Doctor not found; remove the just-saved upload to avoid orphan
            @unlink($destPath);
            sendJSON(['success' => false, 'message' => 'Doctor not found.'], 404);
        }
        $oldPic = $row['profile_picture'] ?? null;

        $upd = $db->prepare('UPDATE doctors SET profile_picture = :p, updated_at = NOW() WHERE id = :id');
        $upd->execute([':p' => $filename, ':id' => $doctor_id]);
        $updated = true;

        // Best-effort: remove the previous file
        if ($oldPic && $oldPic !== $filename) {
            $oldPath = $destDir . DIRECTORY_SEPARATOR . basename($oldPic);
            if (is_file($oldPath)) @unlink($oldPath);
        }

        logActivity($db, getCurrentUserId(), $_SESSION['username'] ?? '', 'admin', 'UPDATE', 'Doctors', $doctor_id, "Updated doctor photo: $filename");
    }
} catch (Throwable $e) {
    error_log('upload-doctor-photo DB error: ' . $e->getMessage());
    // Photo is already saved to disk — return the filename so the caller can still use it.
    // Don't fail the whole upload because of a DB hiccup.
}

sendJSON([
    'success'  => true,
    'filename' => $filename,
    'url'      => '/meditrack/uploads/' . $filename,
    'updated'  => $updated,
]);
