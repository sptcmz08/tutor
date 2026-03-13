<?php
$pageTitle = 'คลังภาพ';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdminLogin();

$db = getDB();

// Get all photos with record/school/tutor info
$photos = $db->query("
    SELECT tp.photo_path, tr.teaching_date, tr.start_time, tr.end_time,
           s.name as school_name, t.first_name, t.last_name, t.nickname
    FROM teaching_photos tp
    JOIN teaching_records tr ON tp.record_id = tr.id
    JOIN schools s ON tr.school_id = s.id
    JOIN tutors t ON tr.tutor_id = t.id
    ORDER BY s.name ASC, tr.teaching_date DESC, tp.id ASC
")->fetchAll();

// Group by school > date
$gallery = [];
foreach ($photos as $p) {
    $schoolName = $p['school_name'];
    $date = $p['teaching_date'];
    if (!isset($gallery[$schoolName])) {
        $gallery[$schoolName] = [];
    }
    if (!isset($gallery[$schoolName][$date])) {
        $gallery[$schoolName][$date] = [];
    }
    $gallery[$schoolName][$date][] = $p;
}

// Filter
$filterSchool = $_GET['school'] ?? '';

include __DIR__ . '/layout.php';
?>

<div class="max-w-6xl">
    <!-- Filter -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 mb-6">
        <div class="flex flex-wrap gap-2 items-center">
            <a href="<?= BASE_URL ?>/admin/gallery.php"
                class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?= !$filterSchool ? 'bg-indigo-500 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                ทั้งหมด
            </a>
            <?php foreach (array_keys($gallery) as $schoolName): ?>
                <a href="<?= BASE_URL ?>/admin/gallery.php?school=<?= urlencode($schoolName) ?>"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?= $filterSchool === $schoolName ? 'bg-indigo-500 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                    <?= sanitize($schoolName) ?>
                </a>
            <?php endforeach; ?>
            <?php if (!empty($gallery)): ?>
            <a href="<?= BASE_URL ?>/admin/download_photos.php<?= $filterSchool ? '?school=' . urlencode($filterSchool) : '' ?>"
                class="ml-auto px-4 py-2 bg-emerald-500 text-white rounded-lg text-sm font-medium hover:bg-emerald-600 transition-colors">
                <i class="fas fa-download mr-1"></i> ดาวน์โหลด<?= $filterSchool ? '' : 'ทั้งหมด' ?>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($gallery)): ?>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-12 text-center">
            <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-images text-slate-300 text-3xl"></i>
            </div>
            <p class="text-slate-400">ยังไม่มีรูปภาพ</p>
        </div>
    <?php else: ?>
        <div class="space-y-8">
            <?php foreach ($gallery as $schoolName => $dates):
                if ($filterSchool && $filterSchool !== $schoolName)
                    continue;
                ?>
                <div>
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center">
                            <i class="fas fa-school text-indigo-500"></i>
                        </div>
                        <h3 class="text-lg font-bold text-slate-800"><?= sanitize($schoolName) ?></h3>
                        <span class="text-sm text-slate-400"><?= array_sum(array_map('count', $dates)) ?> รูป</span>
                        <a href="<?= BASE_URL ?>/admin/download_photos.php?school=<?= urlencode($schoolName) ?>"
                            class="ml-auto px-3 py-1.5 bg-emerald-50 text-emerald-600 rounded-lg text-xs font-medium hover:bg-emerald-100 transition-colors"
                            title="ดาวน์โหลดรูปทั้งหมดของ <?= sanitize($schoolName) ?>">
                            <i class="fas fa-download mr-1"></i> โหลดทั้งหมด
                        </a>
                        <?php $schoolPhotoCount = array_sum(array_map('count', $dates)); ?>
                        <form method="POST" action="<?= BASE_URL ?>/admin/clear_photos.php" class="inline"
                            onsubmit="return confirm('🗑️ ต้องการลบรูปทั้งหมดของ <?= sanitize($schoolName) ?> (<?= $schoolPhotoCount ?> รูป)?\n\nการดำเนินการนี้ไม่สามารถย้อนกลับได้')">
                            <input type="hidden" name="school" value="<?= sanitize($schoolName) ?>">
                            <button type="submit" class="px-3 py-1.5 bg-red-50 text-red-500 rounded-lg text-xs font-medium hover:bg-red-100 transition-colors"
                                title="ลบรูปทั้งหมดของ <?= sanitize($schoolName) ?>">
                                <i class="fas fa-trash mr-1"></i> ลบทั้งหมด
                            </button>
                        </form>
                    </div>

                    <?php foreach ($dates as $date => $datePhotos): ?>
                        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-4">
                            <div class="px-6 py-3 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
                                <p class="font-medium text-slate-700">
                                    <i class="fas fa-calendar text-slate-400 mr-2"></i>
                                    <?= formatThaiDate($date) ?>
                                </p>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-slate-400"><?= count($datePhotos) ?> รูป</span>
                                    <a href="<?= BASE_URL ?>/admin/download_photos.php?school=<?= urlencode($schoolName) ?>&date=<?= $date ?>"
                                        class="px-2 py-1 bg-emerald-50 text-emerald-600 rounded text-xs hover:bg-emerald-100 transition-colors"
                                        title="ดาวน์โหลดรูปวันนี้">
                                        <i class="fas fa-download"></i>
                                    </a>
                                    <form method="POST" action="<?= BASE_URL ?>/admin/clear_photos.php" class="inline"
                                        onsubmit="return confirm('🗑️ ลบรูปวันที่ <?= $date ?> ของ <?= sanitize($schoolName) ?> (<?= count($datePhotos) ?> รูป)?')">
                                        <input type="hidden" name="school" value="<?= sanitize($schoolName) ?>">
                                        <input type="hidden" name="date" value="<?= $date ?>">
                                        <button type="submit" class="px-2 py-1 bg-red-50 text-red-500 rounded text-xs hover:bg-red-100 transition-colors"
                                            title="ลบรูปวันนี้">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <div class="p-4">
                                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                                    <?php foreach ($datePhotos as $photo): ?>
                                        <div class="group relative">
                                            <img src="<?= BASE_URL ?>/uploads/<?= sanitize($photo['photo_path']) ?>"
                                                alt="Photo by <?= sanitize($photo['first_name']) ?>" class="w-full aspect-square object-cover rounded-xl shadow-sm cursor-pointer
                                        group-hover:shadow-md group-hover:scale-[1.02] transition-all duration-200"
                                                onclick="openLightbox('<?= BASE_URL ?>/uploads/<?= sanitize($photo['photo_path']) ?>', '<?= sanitize(addslashes($photo['first_name'] . ' ' . $photo['last_name'])) ?>', '<?= sanitize(addslashes($schoolName)) ?>', '<?= $date ?>')">
                                            <div
                                                class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent rounded-xl opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex items-end p-3">
                                                <div class="text-white text-xs">
                                                    <p class="font-medium"><?= sanitize($photo['first_name']) ?></p>
                                                    <p class="opacity-80">
                                                        <?= formatTimeRange($photo['start_time'], $photo['end_time']) ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Lightbox -->
<div id="lightbox" class="fixed inset-0 bg-black/90 z-50 hidden flex-col items-center justify-center p-4"
    onclick="closeLightbox()">
    <img id="lightboxImg" class="max-w-full max-h-[80vh] rounded-xl shadow-2xl" alt="Photo">
    <div class="mt-4 text-center text-white" onclick="event.stopPropagation()">
        <p class="font-medium" id="lightboxTutor"></p>
        <p class="text-white/70 text-sm" id="lightboxInfo"></p>
    </div>
    <button class="absolute top-4 right-4 text-white text-3xl hover:text-gray-300" onclick="closeLightbox()">
        <i class="fas fa-times"></i>
    </button>
</div>

<script>
    function openLightbox(src, tutor, school, date) {
        document.getElementById('lightboxImg').src = src;
        document.getElementById('lightboxTutor').textContent = tutor;
        document.getElementById('lightboxInfo').textContent = school + ' — ' + date;
        const lb = document.getElementById('lightbox');
        lb.classList.remove('hidden');
        lb.classList.add('flex');
    }

    function closeLightbox() {
        const lb = document.getElementById('lightbox');
        lb.classList.add('hidden');
        lb.classList.remove('flex');
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeLightbox();
    });
</script>

<?php include __DIR__ . '/layout_footer.php'; ?>