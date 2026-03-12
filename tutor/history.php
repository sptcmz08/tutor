<?php
$pageTitle = 'ประวัติการสอน';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireTutorLogin();

$tutor = getCurrentTutor();
$db = getDB();

// Filter
$filterMonth = (int) ($_GET['month'] ?? date('n'));
$filterYear = (int) ($_GET['year'] ?? date('Y'));
$filterSchool = (int) ($_GET['school_id'] ?? 0);

$sql = "SELECT tr.*, s.name as school_name,
        (SELECT COUNT(*) FROM teaching_photos WHERE record_id = tr.id) as photo_count
        FROM teaching_records tr
        JOIN schools s ON tr.school_id = s.id
        WHERE tr.tutor_id = ? AND MONTH(tr.teaching_date) = ? AND YEAR(tr.teaching_date) = ?";
$params = [$tutor['id'], $filterMonth, $filterYear];

if ($filterSchool) {
    $sql .= " AND tr.school_id = ?";
    $params[] = $filterSchool;
}

$sql .= " ORDER BY tr.teaching_date DESC, tr.start_time DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

// Get photos for each record
foreach ($records as &$record) {
    $photoStmt = $db->prepare("SELECT * FROM teaching_photos WHERE record_id = ? ORDER BY id ASC");
    $photoStmt->execute([$record['id']]);
    $record['photos'] = $photoStmt->fetchAll();
}
unset($record);

$schools = getSchools(false);

include __DIR__ . '/layout.php';
?>

<div class="max-w-4xl">
    <!-- Filters -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
        <form method="GET" class="flex flex-wrap gap-4 items-end">
            <div class="flex-1 min-w-[140px]">
                <label class="block text-sm font-medium text-gray-600 mb-1">เดือน</label>
                <select name="month"
                    class="w-full px-3 py-2 rounded-lg border border-gray-200 focus:border-indigo-500 outline-none text-sm">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $m == $filterMonth ? 'selected' : '' ?>><?= THAI_MONTHS[$m] ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="min-w-[100px]">
                <label class="block text-sm font-medium text-gray-600 mb-1">ปี</label>
                <select name="year"
                    class="w-full px-3 py-2 rounded-lg border border-gray-200 focus:border-indigo-500 outline-none text-sm">
                    <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
                        <option value="<?= $y ?>" <?= $y == $filterYear ? 'selected' : '' ?>><?= $y + 543 ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="flex-1 min-w-[140px]">
                <label class="block text-sm font-medium text-gray-600 mb-1">โรงเรียน</label>
                <select name="school_id"
                    class="w-full px-3 py-2 rounded-lg border border-gray-200 focus:border-indigo-500 outline-none text-sm">
                    <option value="0">ทั้งหมด</option>
                    <?php foreach ($schools as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $s['id'] == $filterSchool ? 'selected' : '' ?>>
                            <?= sanitize($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit"
                class="px-5 py-2 bg-indigo-500 text-white rounded-lg hover:bg-indigo-600 transition-colors text-sm">
                <i class="fas fa-search mr-1"></i> กรอง
            </button>
        </form>
    </div>

    <!-- Summary -->
    <div class="flex items-center gap-4 mb-6">
        <div class="bg-indigo-50 px-4 py-2 rounded-xl">
            <span class="text-indigo-600 font-bold text-lg"><?= count($records) ?></span>
            <span class="text-indigo-500 text-sm ml-1">รายการ</span>
        </div>
        <p class="text-gray-500 text-sm"><?= formatThaiMonthYear($filterMonth, $filterYear) ?></p>
    </div>

    <!-- Records List -->
    <?php if (empty($records)): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center">
            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-search text-gray-300 text-3xl"></i>
            </div>
            <p class="text-gray-400">ไม่พบรายการในเดือนนี้</p>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($records as $record): ?>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow">
                    <div class="flex gap-4 mb-3">
                        <div class="flex-1">
                            <p class="font-bold text-gray-800">
                                <i class="fas fa-school text-indigo-400 mr-1 text-sm"></i>
                                <?= sanitize($record['school_name']) ?>
                            </p>
                            <p class="text-sm text-gray-500 mt-1">
                                <i class="fas fa-calendar text-gray-300 mr-1"></i>
                                <?= formatThaiDate($record['teaching_date']) ?>
                            </p>
                            <p class="text-sm text-gray-500 mt-0.5">
                                <i class="fas fa-clock text-gray-300 mr-1"></i>
                                <?= formatTimeRange($record['start_time'], $record['end_time']) ?>
                            </p>
                            <?php if ($record['teaching_summary']): ?>
                            <div class="mt-2 p-2 bg-blue-50 rounded-lg">
                                <p class="text-sm text-blue-700">
                                    <i class="fas fa-clipboard-list mr-1"></i> <?= sanitize($record['teaching_summary']) ?>
                                </p>
                            </div>
                            <?php endif; ?>
                            <?php if ($record['notes']): ?>
                                <p class="text-sm text-gray-400 mt-2 italic">
                                    <i class="fas fa-sticky-note mr-1"></i> <?= sanitize($record['notes']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="text-right space-y-2">
                            <?php if ($record['photo_count'] > 0): ?>
                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-indigo-50 text-indigo-600 rounded-lg text-xs font-medium">
                                    <i class="fas fa-camera"></i> <?= $record['photo_count'] ?> รูป
                                </span>
                            <?php endif; ?>
                            <?php if ($record['teaching_fee'] !== null): ?>
                                <div>
                                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-emerald-50 text-emerald-600 rounded-lg text-xs font-medium">
                                        <i class="fas fa-coins"></i> ฿<?= number_format($record['teaching_fee'], 0) ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            <div>
                                <a href="<?= BASE_URL ?>/tutor/edit_record.php?id=<?= $record['id'] ?>"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 bg-amber-50 text-amber-600 rounded-lg text-xs font-medium hover:bg-amber-100 transition-colors">
                                    <i class="fas fa-edit"></i> แก้ไข
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Photos grid -->
                    <?php if (!empty($record['photos'])): ?>
                        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-2 mt-3 pt-3 border-t border-gray-100">
                            <?php foreach ($record['photos'] as $photo): ?>
                                <img src="<?= BASE_URL ?>/uploads/<?= sanitize($photo['photo_path']) ?>" alt="Photo"
                                    class="w-full aspect-square object-cover rounded-lg shadow-sm cursor-pointer hover:opacity-90 hover:shadow-md transition-all"
                                    onclick="openLightbox(this.src)">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Lightbox -->
<div id="lightbox" class="fixed inset-0 bg-black/80 z-50 hidden items-center justify-center p-4"
    onclick="closeLightbox()">
    <img id="lightboxImg" class="max-w-full max-h-full rounded-xl shadow-2xl" alt="Full photo">
    <button class="absolute top-4 right-4 text-white text-3xl hover:text-gray-300" onclick="closeLightbox()">
        <i class="fas fa-times"></i>
    </button>
</div>

<script>
    function openLightbox(src) {
        document.getElementById('lightboxImg').src = src;
        const lb = document.getElementById('lightbox');
        lb.classList.remove('hidden');
        lb.classList.add('flex');
    }
    function closeLightbox() {
        const lb = document.getElementById('lightbox');
        lb.classList.add('hidden');
        lb.classList.remove('flex');
    }
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeLightbox(); });
</script>

<?php include __DIR__ . '/layout_footer.php'; ?>