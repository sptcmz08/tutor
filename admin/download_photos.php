<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdminLogin();

$db = getDB();

$filterSchool = $_GET['school'] ?? '';
$filterDate = $_GET['date'] ?? '';

// Build query based on filters
$sql = "SELECT tp.photo_path, tr.teaching_date, s.name as school_name
        FROM teaching_photos tp
        JOIN teaching_records tr ON tp.record_id = tr.id
        JOIN schools s ON tr.school_id = s.id
        WHERE 1=1";
$params = [];

if ($filterSchool) {
    $sql .= " AND s.name = ?";
    $params[] = $filterSchool;
}
if ($filterDate) {
    $sql .= " AND tr.teaching_date = ?";
    $params[] = $filterDate;
}

$sql .= " ORDER BY s.name ASC, tr.teaching_date DESC, tp.id ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$photos = $stmt->fetchAll();

if (empty($photos)) {
    setFlash('error', 'ไม่มีรูปภาพสำหรับดาวน์โหลด');
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? BASE_URL . '/admin/gallery.php'));
    exit;
}

// Build ZIP filename
$filenameParts = ['photos'];
if ($filterSchool) {
    $safeName = preg_replace('/[^a-zA-Z0-9ก-๙\s\-_]/u', '', $filterSchool);
    $filenameParts[] = str_replace(' ', '_', $safeName);
}
if ($filterDate) {
    $filenameParts[] = $filterDate;
}
$zipFilename = implode('_', $filenameParts) . '.zip';

// Create temp ZIP file
$tmpFile = tempnam(sys_get_temp_dir(), 'tutor_photos_');
$zip = new ZipArchive();
if ($zip->open($tmpFile, ZipArchive::OVERWRITE) !== true) {
    setFlash('error', 'ไม่สามารถสร้างไฟล์ ZIP ได้');
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? BASE_URL . '/admin/gallery.php'));
    exit;
}

foreach ($photos as $photo) {
    $filePath = UPLOAD_DIR . '/' . $photo['photo_path'];
    if (file_exists($filePath)) {
        // Organize inside ZIP: school_name/date/filename
        $safeName = preg_replace('/[^a-zA-Z0-9ก-๙\s\-_]/u', '', $photo['school_name']);
        $safeName = str_replace(' ', '_', $safeName);
        $zipPath = $safeName . '/' . $photo['teaching_date'] . '/' . basename($photo['photo_path']);
        $zip->addFile($filePath, $zipPath);
    }
}

$zip->close();

// Stream the ZIP file
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: no-cache, no-store, must-revalidate');
readfile($tmpFile);

// Clean up
unlink($tmpFile);
exit;
