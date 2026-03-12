<?php
// Application Configuration
define('APP_NAME', 'Tutor Tracking');
define('APP_VERSION', '1.0');
define('BASE_URL', '');
define('UPLOAD_DIR', __DIR__ . '/../uploads');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp', 'heic']);
define('APP_LOGO', 'assets/logo.jpg'); // วางไฟล์ logo ไว้ที่ tutor/assets/logo.jpg

// Thai month names
define('THAI_MONTHS', [
    1 => 'มกราคม',
    2 => 'กุมภาพันธ์',
    3 => 'มีนาคม',
    4 => 'เมษายน',
    5 => 'พฤษภาคม',
    6 => 'มิถุนายน',
    7 => 'กรกฎาคม',
    8 => 'สิงหาคม',
    9 => 'กันยายน',
    10 => 'ตุลาคม',
    11 => 'พฤศจิกายน',
    12 => 'ธันวาคม'
]);

define('THAI_DAYS', [
    'Sunday' => 'อาทิตย์',
    'Monday' => 'จันทร์',
    'Tuesday' => 'อังคาร',
    'Wednesday' => 'พุธ',
    'Thursday' => 'พฤหัสบดี',
    'Friday' => 'ศุกร์',
    'Saturday' => 'เสาร์'
]);
