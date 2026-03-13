<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdminLogin();

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/gallery.php');
    exit;
}

$filterSchool = $_POST['school'] ?? '';
$filterDate = $_POST['date'] ?? '';
$filterMonth = $_POST['month'] ?? '';
$filterYear = $_POST['year'] ?? '';

// Build query to find photos to delete
$sql = "SELECT tp.id, tp.photo_path, tr.teaching_date, s.name as school_name
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
if ($filterMonth && $filterYear) {
    $sql .= " AND MONTH(tr.teaching_date) = ? AND YEAR(tr.teaching_date) = ?";
    $params[] = (int)$filterMonth;
    $params[] = (int)$filterYear;
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$photos = $stmt->fetchAll();

if (empty($photos)) {
    setFlash('error', 'ไม่มีรูปภาพสำหรับลบ');
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? BASE_URL . '/admin/gallery.php'));
    exit;
}

// Delete physical files
$deletedCount = 0;
$photoIds = [];
foreach ($photos as $photo) {
    $filePath = UPLOAD_DIR . '/' . $photo['photo_path'];
    if (file_exists($filePath)) {
        unlink($filePath);
    }
    $photoIds[] = $photo['id'];
    $deletedCount++;
}

// Delete from DB
if (!empty($photoIds)) {
    $placeholders = rtrim(str_repeat('?,', count($photoIds)), ',');
    $delStmt = $db->prepare("DELETE FROM teaching_photos WHERE id IN ($placeholders)");
    $delStmt->execute($photoIds);
}

// Clean up empty directories
$uploadBase = UPLOAD_DIR;
$dirs = glob($uploadBase . '/*/*', GLOB_ONLYDIR);
if ($dirs) {
    foreach ($dirs as $dir) {
        if (count(glob($dir . '/*')) === 0) @rmdir($dir);
    }
}
$dirs = glob($uploadBase . '/*', GLOB_ONLYDIR);
if ($dirs) {
    foreach ($dirs as $dir) {
        if (count(glob($dir . '/*')) === 0) @rmdir($dir);
    }
}

setFlash('success', "ลบรูปภาพทั้งหมด {$deletedCount} รูปเรียบร้อยแล้ว");
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? BASE_URL . '/admin/gallery.php'));
exit;
