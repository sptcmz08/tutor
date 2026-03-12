<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Redirect if already logged in
if (isTutorLoggedIn()) {
    redirect(BASE_URL . '/tutor/dashboard.php');
}
if (isAdminLoggedIn()) {
    redirect(BASE_URL . '/admin/dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Try admin login first
    if (loginAdmin($username, $password)) {
        redirect(BASE_URL . '/admin/dashboard.php');
    }
    // Then try tutor login
    elseif (loginTutor($username, $password)) {
        redirect(BASE_URL . '/tutor/dashboard.php');
    } else {
        $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            font-family: 'Noto Sans Thai', sans-serif;
        }

        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .float-animation {
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-20px);
            }
        }

        .pulse-bg {
            animation: pulse-bg 4s ease-in-out infinite;
        }

        @keyframes pulse-bg {

            0%,
            100% {
                opacity: 0.3;
            }

            50% {
                opacity: 0.6;
            }
        }
    </style>
</head>

<body class="gradient-bg min-h-screen flex items-center justify-center p-4 relative overflow-hidden">
    <!-- Decorative circles -->
    <div class="absolute top-10 left-10 w-72 h-72 bg-white/10 rounded-full blur-3xl pulse-bg"></div>
    <div class="absolute bottom-10 right-10 w-96 h-96 bg-purple-300/20 rounded-full blur-3xl pulse-bg"
        style="animation-delay: 2s;"></div>
    <div class="absolute top-1/2 left-1/4 w-48 h-48 bg-indigo-300/15 rounded-full blur-2xl float-animation"></div>

    <div class="glass-card rounded-3xl shadow-2xl p-8 md:p-12 w-full max-w-md relative z-10">
        <div class="text-center mb-8">
            <?php
            $logoPath = __DIR__ . '/' . APP_LOGO;
            if (file_exists($logoPath)): ?>
                <img src="<?= BASE_URL ?>/<?= APP_LOGO ?>" alt="<?= APP_NAME ?>" 
                    class="mx-auto mb-4 max-h-24 object-contain drop-shadow-lg">
            <?php else: ?>
                <div
                    class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl shadow-lg mb-4 transform rotate-3 hover:rotate-0 transition-transform duration-300">
                    <i class="fas fa-chalkboard-teacher text-white text-3xl"></i>
                </div>
            <?php endif; ?>
            <h1 class="text-2xl font-bold text-gray-800"><?= APP_NAME ?></h1>
            <p class="text-gray-500 mt-1">ระบบบันทึกการสอนติวเตอร์</p>
        </div>

        <?php if ($error): ?>
            <div
                class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-xl mb-6 flex items-center gap-2 animate-pulse">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= $error ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-user mr-1 text-indigo-500"></i> ชื่อผู้ใช้
                </label>
                <input type="text" name="username" required autocomplete="username"
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all outline-none bg-gray-50/50"
                    placeholder="กรอกชื่อผู้ใช้" value="<?= sanitize($_POST['username'] ?? '') ?>">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-lock mr-1 text-indigo-500"></i> รหัสผ่าน
                </label>
                <div class="relative">
                    <input type="password" name="password" id="password" required autocomplete="current-password"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all outline-none bg-gray-50/50"
                        placeholder="กรอกรหัสผ่าน">
                    <button type="button" onclick="togglePassword()"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit"
                class="w-full py-3 bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl hover:from-indigo-600 hover:to-purple-700 transform hover:-translate-y-0.5 transition-all duration-300">
                <i class="fas fa-sign-in-alt mr-2"></i> เข้าสู่ระบบ
            </button>
        </form>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>

</html>