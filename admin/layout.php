<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdminLogin();

$admin = getCurrentAdmin();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$flash = getFlash();

$menuItems = [
    ['page' => 'dashboard', 'icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard'],
    ['page' => 'schools', 'icon' => 'fas fa-school', 'label' => 'จัดการโรงเรียน'],
    ['page' => 'tutors', 'icon' => 'fas fa-users', 'label' => 'จัดการติวเตอร์'],
    ['page' => 'reports', 'icon' => 'fas fa-chart-bar', 'label' => 'รายงานประจำเดือน'],
    ['page' => 'gallery', 'icon' => 'fas fa-images', 'label' => 'คลังภาพ'],
];
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= $pageTitle ?? 'Admin' ?> -
        <?= APP_NAME ?>
    </title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { thai: ['Noto Sans Thai', 'sans-serif'] }
                }
            }
        }
    </script>
    <style>
        * {
            font-family: 'Noto Sans Thai', sans-serif;
        }

        .sidebar-link {
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }

        .sidebar-link:hover {
            background: rgba(99, 102, 241, 0.1);
            border-left-color: #6366f1;
        }

        .sidebar-link.active {
            background: rgba(99, 102, 241, 0.15);
            border-left-color: #6366f1;
            color: #6366f1;
        }

        .sidebar-transition {
            transition: transform 0.3s ease, opacity 0.3s ease;
        }

        @media (max-width: 1023px) {
            .sidebar-overlay {
                background: rgba(0, 0, 0, 0.5);
                transition: opacity 0.3s ease;
            }
        }
    </style>
</head>

<body class="bg-slate-50 font-thai">
    <div class="flex min-h-screen">
        <!-- Mobile overlay -->
        <div id="sidebarOverlay" class="fixed inset-0 sidebar-overlay z-30 hidden lg:hidden" onclick="toggleSidebar()">
        </div>

        <!-- Sidebar -->
        <aside id="sidebar"
            class="fixed lg:static inset-y-0 left-0 w-72 bg-slate-900 text-white z-40 sidebar-transition -translate-x-full lg:translate-x-0 flex flex-col">
            <!-- Logo -->
            <div class="p-6 border-b border-slate-700/50">
                <div class="flex items-center gap-3">
                    <?php
                    $logoPath = __DIR__ . '/../' . APP_LOGO;
                    if (file_exists($logoPath)): ?>
                        <img src="<?= BASE_URL ?>/<?= APP_LOGO ?>" alt="<?= APP_NAME ?>"
                            class="w-11 h-11 object-contain rounded-xl shadow-lg bg-white/10 p-1">
                    <?php else: ?>
                        <div
                            class="w-11 h-11 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg">
                            <i class="fas fa-chalkboard-teacher text-white text-lg"></i>
                        </div>
                    <?php endif; ?>
                    <div>
                        <h1 class="font-bold text-lg">
                            <?= APP_NAME ?>
                        </h1>
                        <p class="text-slate-400 text-xs">Admin Panel</p>
                    </div>
                </div>
            </div>

            <!-- Menu -->
            <nav class="flex-1 py-4 px-3 space-y-1 overflow-y-auto">
                <?php foreach ($menuItems as $item): ?>
                    <a href="<?= BASE_URL ?>/admin/<?= $item['page'] ?>.php"
                        class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-slate-300 <?= $currentPage === $item['page'] ? 'active font-medium' : 'hover:text-white' ?>">
                        <i class="<?= $item['icon'] ?> w-5 text-center"></i>
                        <span>
                            <?= $item['label'] ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <!-- Admin info -->
            <div class="p-4 border-t border-slate-700/50">
                <div class="flex items-center gap-3">
                    <div
                        class="w-9 h-9 bg-slate-700 rounded-full flex items-center justify-center text-sm font-bold text-indigo-400">
                        <?= mb_substr($admin['name'], 0, 1) ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium truncate">
                            <?= sanitize($admin['name']) ?>
                        </p>
                        <p class="text-xs text-slate-400">Admin</p>
                    </div>
                    <a href="<?= BASE_URL ?>/logout.php" class="text-slate-400 hover:text-red-400 transition-colors"
                        title="ออกจากระบบ">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Top bar -->
            <header class="bg-white border-b border-slate-200 px-6 py-4 flex items-center gap-4 sticky top-0 z-20">
                <button onclick="toggleSidebar()" class="lg:hidden text-slate-600 hover:text-slate-800">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <h2 class="text-xl font-bold text-slate-800">
                    <?= $pageTitle ?? 'Dashboard' ?>
                </h2>
            </header>

            <!-- Page Content -->
            <main class="flex-1 p-6">
                <?php if ($flash): ?>
                    <div
                        class="mb-6 px-4 py-3 rounded-xl <?= $flash['type'] === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700' ?> flex items-center gap-2">
                        <i
                            class="fas <?= $flash['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                        <span>
                            <?= $flash['message'] ?>
                        </span>
                    </div>
                <?php endif; ?>