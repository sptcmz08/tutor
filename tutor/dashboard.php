<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireTutorLogin();

$tutor = getCurrentTutor();
$currentMonth = (int) date('n');
$currentYear = (int) date('Y');

// Get recent records with photo count
$db = getDB();
$stmt = $db->prepare("
    SELECT tr.*, s.name as school_name,
        (SELECT COUNT(*) FROM teaching_photos WHERE record_id = tr.id) as photo_count,
        (SELECT photo_path FROM teaching_photos WHERE record_id = tr.id LIMIT 1) as first_photo
    FROM teaching_records tr
    JOIN schools s ON tr.school_id = s.id
    WHERE tr.tutor_id = ?
    ORDER BY tr.teaching_date DESC, tr.start_time DESC
    LIMIT 10
");
$stmt->execute([$tutor['id']]);
$recentRecords = $stmt->fetchAll();

include __DIR__ . '/layout.php';
?>

<!-- Welcome -->
<div class="mb-8">
    <h2 class="text-2xl font-bold text-gray-800">สวัสดี, <?= sanitize($tutor['nickname'] ?: $tutor['first_name']) ?>! 👋
    </h2>
    <p class="text-gray-500 mt-1">สรุปข้อมูลการสอนของคุณในเดือน<?= THAI_MONTHS[$currentMonth] ?>
        <?= $currentYear + 543 ?></p>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
    <div
        class="bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl p-6 text-white shadow-lg hover:shadow-xl hover:-translate-y-1 transition-all">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-white/80 text-sm">สอนแล้ว</p>
                <p class="text-4xl font-bold mt-1"><?= $stats['total_sessions'] ?? 0 ?></p>
                <p class="text-white/70 text-sm mt-1">ครั้ง</p>
            </div>
            <div class="w-14 h-14 bg-white/20 rounded-2xl flex items-center justify-center">
                <i class="fas fa-book-open text-2xl"></i>
            </div>
        </div>
    </div>
    <div
        class="bg-gradient-to-br from-pink-500 to-rose-500 rounded-2xl p-6 text-white shadow-lg hover:shadow-xl hover:-translate-y-1 transition-all">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-white/80 text-sm">โรงเรียน</p>
                <p class="text-4xl font-bold mt-1"><?= $stats['total_schools'] ?? 0 ?></p>
                <p class="text-white/70 text-sm mt-1">แห่ง</p>
            </div>
            <div class="w-14 h-14 bg-white/20 rounded-2xl flex items-center justify-center">
                <i class="fas fa-school text-2xl"></i>
            </div>
        </div>
    </div>
    <a href="<?= BASE_URL ?>/tutor/record.php"
        class="bg-gradient-to-br from-cyan-500 to-blue-500 rounded-2xl p-6 text-white shadow-lg hover:shadow-xl hover:-translate-y-1 transition-all block group">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-white/80 text-sm">บันทึกการสอน</p>
                <p class="text-xl font-bold mt-2">+ เพิ่มรายการ</p>
                <p class="text-white/70 text-sm mt-1">กดเพื่อบันทึก</p>
            </div>
            <div
                class="w-14 h-14 bg-white/20 rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform">
                <i class="fas fa-plus text-2xl"></i>
            </div>
        </div>
    </a>
</div>

<!-- Recent Records -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="font-bold text-gray-800"><i class="fas fa-clock mr-2 text-indigo-500"></i>บันทึกล่าสุด</h3>
        <a href="<?= BASE_URL ?>/tutor/history.php"
            class="text-sm text-indigo-500 hover:text-indigo-700 transition-colors">ดูทั้งหมด →</a>
    </div>

    <?php if (empty($recentRecords)): ?>
        <div class="p-12 text-center">
            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-inbox text-gray-300 text-3xl"></i>
            </div>
            <p class="text-gray-400">ยังไม่มีบันทึกการสอน</p>
            <a href="<?= BASE_URL ?>/tutor/record.php"
                class="inline-block mt-4 px-6 py-2 bg-indigo-500 text-white rounded-xl hover:bg-indigo-600 transition-colors">
                <i class="fas fa-plus mr-1"></i> บันทึกรายการแรก
            </a>
        </div>
    <?php else: ?>
        <div class="divide-y divide-gray-50">
            <?php foreach ($recentRecords as $record): ?>
                <div class="px-6 py-4 hover:bg-gray-50/50 transition-colors">
                    <div class="flex items-center gap-4">
                        <?php if ($record['first_photo']): ?>
                            <img src="<?= BASE_URL ?>/uploads/<?= sanitize($record['first_photo']) ?>" alt="Teaching photo"
                                class="w-14 h-14 object-cover rounded-xl shadow-sm">
                        <?php else: ?>
                            <div class="w-14 h-14 bg-gray-100 rounded-xl flex items-center justify-center">
                                <i class="fas fa-image text-gray-300"></i>
                            </div>
                        <?php endif; ?>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-gray-800 truncate">
                                <i class="fas fa-school text-indigo-400 mr-1 text-sm"></i>
                                <?= sanitize($record['school_name']) ?>
                            </p>
                            <p class="text-sm text-gray-500 mt-0.5">
                                <i class="fas fa-calendar text-gray-300 mr-1"></i>
                                <?= formatThaiDate($record['teaching_date']) ?>
                            </p>
                        </div>
                        <div class="text-right">
                            <span class="inline-block px-3 py-1 bg-indigo-50 text-indigo-600 text-sm font-medium rounded-lg">
                                <?= formatTimeRange($record['start_time'], $record['end_time']) ?>
                            </span>
                            <?php if ($record['photo_count'] > 0): ?>
                                <p class="text-xs text-gray-400 mt-1"><i
                                        class="fas fa-camera mr-1"></i><?= $record['photo_count'] ?> รูป</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/layout_footer.php'; ?>