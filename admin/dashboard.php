<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdminLogin();

$db = getDB();

// Stats
$totalTutors = $db->query("SELECT COUNT(*) FROM tutors WHERE is_active = 1")->fetchColumn();
$totalSchools = $db->query("SELECT COUNT(*) FROM schools WHERE is_active = 1")->fetchColumn();

$currentMonth = (int) date('n');
$currentYear = (int) date('Y');
$stmt = $db->prepare("SELECT COUNT(*) FROM teaching_records WHERE MONTH(teaching_date) = ? AND YEAR(teaching_date) = ?");
$stmt->execute([$currentMonth, $currentYear]);
$monthlyRecords = $stmt->fetchColumn();

// Recent records
$recentRecords = $db->query("
    SELECT tr.*, t.first_name, t.last_name, t.nickname, s.name as school_name
    FROM teaching_records tr
    JOIN tutors t ON tr.tutor_id = t.id
    JOIN schools s ON tr.school_id = s.id
    ORDER BY tr.created_at DESC
    LIMIT 10
")->fetchAll();

// Top tutors this month
$stmt = $db->prepare("
    SELECT t.id, t.first_name, t.last_name, t.nickname, COUNT(tr.id) as sessions
    FROM tutors t
    LEFT JOIN teaching_records tr ON t.id = tr.tutor_id
        AND MONTH(tr.teaching_date) = ? AND YEAR(tr.teaching_date) = ?
    WHERE t.is_active = 1
    GROUP BY t.id
    ORDER BY sessions DESC
    LIMIT 5
");
$stmt->execute([$currentMonth, $currentYear]);
$topTutors = $stmt->fetchAll();

include __DIR__ . '/layout.php';
?>

<!-- Stats Grid -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-500 text-sm">ติวเตอร์ทั้งหมด</p>
                <p class="text-3xl font-bold text-slate-800 mt-1">
                    <?= $totalTutors ?>
                </p>
            </div>
            <div class="w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center">
                <i class="fas fa-users text-indigo-500 text-xl"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-500 text-sm">โรงเรียน</p>
                <p class="text-3xl font-bold text-slate-800 mt-1">
                    <?= $totalSchools ?>
                </p>
            </div>
            <div class="w-12 h-12 bg-emerald-50 rounded-xl flex items-center justify-center">
                <i class="fas fa-school text-emerald-500 text-xl"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-500 text-sm">สอนเดือนนี้</p>
                <p class="text-3xl font-bold text-slate-800 mt-1">
                    <?= $monthlyRecords ?>
                </p>
            </div>
            <div class="w-12 h-12 bg-amber-50 rounded-xl flex items-center justify-center">
                <i class="fas fa-book-open text-amber-500 text-xl"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-500 text-sm">เดือน</p>
                <p class="text-lg font-bold text-slate-800 mt-1">
                    <?= THAI_MONTHS[$currentMonth] ?>
                </p>
                <p class="text-sm text-slate-400">
                    <?= $currentYear + 543 ?>
                </p>
            </div>
            <div class="w-12 h-12 bg-purple-50 rounded-xl flex items-center justify-center">
                <i class="fas fa-calendar text-purple-500 text-xl"></i>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Recent Records -->
    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-800"><i class="fas fa-clock text-indigo-500 mr-2"></i>การสอนล่าสุด</h3>
            <a href="<?= BASE_URL ?>/admin/reports.php" class="text-sm text-indigo-500 hover:text-indigo-700">ดูทั้งหมด
                →</a>
        </div>
        <?php if (empty($recentRecords)): ?>
            <div class="p-8 text-center text-slate-400">
                <i class="fas fa-inbox text-3xl mb-2"></i>
                <p>ยังไม่มีบันทึก</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-slate-500 font-medium">ติวเตอร์</th>
                            <th class="px-4 py-3 text-left text-slate-500 font-medium">โรงเรียน</th>
                            <th class="px-4 py-3 text-left text-slate-500 font-medium">วันที่</th>
                            <th class="px-4 py-3 text-left text-slate-500 font-medium">เวลา</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($recentRecords as $r): ?>
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3">
                                    <span class="font-medium text-slate-700">
                                        <?= sanitize($r['first_name']) ?>
                                    </span>
                                    <?php if ($r['nickname']): ?>
                                        <span class="text-slate-400 text-xs">(
                                            <?= sanitize($r['nickname']) ?>)
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    <?= sanitize($r['school_name']) ?>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    <?= date('d/m/y', strtotime($r['teaching_date'])) ?>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 bg-indigo-50 text-indigo-600 rounded-md text-xs font-medium">
                                        <?= formatTimeRange($r['start_time'], $r['end_time']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Top Tutors -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h3 class="font-bold text-slate-800"><i class="fas fa-trophy text-amber-500 mr-2"></i>ติวเตอร์เดือนนี้</h3>
        </div>
        <div class="p-4 space-y-3">
            <?php foreach ($topTutors as $i => $tt): ?>
                <div
                    class="flex items-center gap-3 p-3 rounded-xl <?= $i === 0 && $tt['sessions'] > 0 ? 'bg-amber-50' : 'bg-slate-50' ?>">
                    <div
                        class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold
                    <?= $i === 0 && $tt['sessions'] > 0 ? 'bg-amber-400 text-white' : 'bg-slate-200 text-slate-500' ?>">
                        <?= $i + 1 ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-slate-700 text-sm truncate">
                            <?= sanitize($tt['first_name'] . ' ' . $tt['last_name']) ?>
                        </p>
                        <?php if ($tt['nickname']): ?>
                            <p class="text-xs text-slate-400">(
                                <?= sanitize($tt['nickname']) ?>)
                            </p>
                        <?php endif; ?>
                    </div>
                    <span class="text-sm font-bold <?= $tt['sessions'] > 0 ? 'text-indigo-600' : 'text-slate-300' ?>">
                        <?= $tt['sessions'] ?> ครั้ง
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/layout_footer.php'; ?>