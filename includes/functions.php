<?php
require_once __DIR__ . '/../config/app.php';

function sanitize($str)
{
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function redirect($url)
{
    header("Location: $url");
    exit;
}

function formatThaiDate($dateStr)
{
    $date = new DateTime($dateStr);
    $day = (int) $date->format('j');
    $month = THAI_MONTHS[(int) $date->format('n')];
    $year = (int) $date->format('Y') + 543;
    $dayName = THAI_DAYS[$date->format('l')];
    return "วัน{$dayName}ที่ {$day} {$month} {$year}";
}

function formatThaiMonthYear($month, $year)
{
    return THAI_MONTHS[(int) $month] . ' ' . ($year + 543);
}

function formatTime($time)
{
    return date('H:i', strtotime($time)) . ' น.';
}

function formatTimeRange($start, $end)
{
    return formatTime($start) . ' - ' . formatTime($end);
}

function uploadPhoto($file, $schoolName, $teachingDate)
{
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'ไม่มีไฟล์ที่อัพโหลด'];
    }


    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        return ['success' => false, 'error' => 'รองรับเฉพาะไฟล์ JPG, PNG, WEBP, HEIC'];
    }

    // Create directory: uploads/school_name/date/
    $safeName = preg_replace('/[^a-zA-Z0-9ก-๙\s\-_]/u', '', $schoolName);
    $safeName = str_replace(' ', '_', $safeName);
    $dir = UPLOAD_DIR . '/' . $safeName . '/' . $teachingDate;

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filename = uniqid('teach_') . '.' . $ext;
    $path = $dir . '/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $path)) {
        // Return relative path from uploads dir
        $relativePath = $safeName . '/' . $teachingDate . '/' . $filename;
        return ['success' => true, 'path' => $relativePath];
    }

    return ['success' => false, 'error' => 'เกิดข้อผิดพลาดในการอัพโหลด'];
}

function getSchools($activeOnly = true)
{
    $db = getDB();
    $sql = "SELECT * FROM schools";
    if ($activeOnly)
        $sql .= " WHERE is_active = 1";
    $sql .= " ORDER BY name ASC";
    return $db->query($sql)->fetchAll();
}

function getMonthlyRecords($month, $year, $tutorId = null)
{
    $db = getDB();
    $sql = "SELECT tr.*, t.first_name, t.last_name, t.nickname, s.name as school_name
            FROM teaching_records tr
            JOIN tutors t ON tr.tutor_id = t.id
            JOIN schools s ON tr.school_id = s.id
            WHERE MONTH(tr.teaching_date) = ? AND YEAR(tr.teaching_date) = ?";
    $params = [$month, $year];

    if ($tutorId) {
        $sql .= " AND tr.tutor_id = ?";
        $params[] = $tutorId;
    }

    $sql .= " ORDER BY t.first_name ASC, tr.teaching_date ASC, tr.start_time ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getTutorMonthlyStats($tutorId, $month, $year)
{
    $db = getDB();
    $stmt = $db->prepare("
        SELECT COUNT(*) as total_sessions,
               COUNT(DISTINCT school_id) as total_schools
        FROM teaching_records
        WHERE tutor_id = ? AND MONTH(teaching_date) = ? AND YEAR(teaching_date) = ?
    ");
    $stmt->execute([$tutorId, $month, $year]);
    return $stmt->fetch();
}

function setFlash($type, $message)
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash()
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
