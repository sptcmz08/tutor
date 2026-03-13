<?php
$pageTitle = 'แก้ไขบันทึกการสอน';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireTutorLogin();

$tutor = getCurrentTutor();
$db = getDB();
$schools = getSchools(true);

$recordId = (int) ($_GET['id'] ?? 0);
if (!$recordId) {
    setFlash('error', 'ไม่พบรายการที่ต้องการแก้ไข');
    redirect(BASE_URL . '/tutor/history.php');
}

// Load record (must belong to this tutor)
$stmt = $db->prepare("SELECT * FROM teaching_records WHERE id = ? AND tutor_id = ?");
$stmt->execute([$recordId, $tutor['id']]);
$record = $stmt->fetch();

if (!$record) {
    setFlash('error', 'ไม่พบรายการที่ต้องการแก้ไข หรือไม่มีสิทธิ์แก้ไข');
    redirect(BASE_URL . '/tutor/history.php');
}

// Load existing photos
$photoStmt = $db->prepare("SELECT * FROM teaching_photos WHERE record_id = ? ORDER BY id ASC");
$photoStmt->execute([$recordId]);
$existingPhotos = $photoStmt->fetchAll();

$errors = [];

// Detect PHP post_max_size overflow
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES)) {
    $errors[] = 'ไฟล์รูปภาพมีขนาดรวมใหญ่เกินไป กรุณาลดจำนวนรูปหรือลดขนาดรูปแล้วลองใหม่';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
    $schoolId = (int) ($_POST['school_id'] ?? 0);
    $teachingDate = $_POST['teaching_date'] ?? '';
    $startTime = $_POST['start_time'] ?? '';
    $endTime = $_POST['end_time'] ?? '';
    $teachingSummary = trim($_POST['teaching_summary'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $deletePhotos = $_POST['delete_photos'] ?? [];

    // Validate
    if (!$schoolId)
        $errors[] = 'กรุณาเลือกโรงเรียน';
    if (!$teachingDate)
        $errors[] = 'กรุณาเลือกวันที่สอน';
    if (!$startTime)
        $errors[] = 'กรุณาระบุเวลาเริ่มสอน';
    if (!$endTime)
        $errors[] = 'กรุณาระบุเวลาสิ้นสุด';
    if ($startTime && $endTime && $startTime >= $endTime)
        $errors[] = 'เวลาสิ้นสุดต้องมากกว่าเวลาเริ่มสอน';

    // Get school name for folder
    $schoolName = '';
    foreach ($schools as $s) {
        if ($s['id'] == $schoolId) {
            $schoolName = $s['name'];
            break;
        }
    }

    // Upload new photos
    $uploadedPhotos = [];
    if (isset($_FILES['photos'])) {
        $fileCount = count($_FILES['photos']['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_NO_FILE)
                continue;
            $file = [
                'name' => $_FILES['photos']['name'][$i],
                'type' => $_FILES['photos']['type'][$i],
                'tmp_name' => $_FILES['photos']['tmp_name'][$i],
                'error' => $_FILES['photos']['error'][$i],
                'size' => $_FILES['photos']['size'][$i],
            ];
            $uploadResult = uploadPhoto($file, $schoolName, $teachingDate);
            if ($uploadResult['success']) {
                $uploadedPhotos[] = $uploadResult['path'];
            } else {
                $errors[] = 'รูปใหม่ที่ ' . ($i + 1) . ': ' . $uploadResult['error'];
            }
        }
    }

    if (empty($errors)) {
        $db->beginTransaction();
        try {
            // Update record
            $stmt = $db->prepare("
                UPDATE teaching_records 
                SET school_id=?, teaching_date=?, start_time=?, end_time=?, teaching_summary=?, notes=?
                WHERE id=? AND tutor_id=?
            ");
            $stmt->execute([$schoolId, $teachingDate, $startTime, $endTime, $teachingSummary ?: null, $notes ?: null, $recordId, $tutor['id']]);

            // Delete selected photos
            if (!empty($deletePhotos)) {
                $placeholders = rtrim(str_repeat('?,', count($deletePhotos)), ',');
                $delStmt = $db->prepare("DELETE FROM teaching_photos WHERE id IN ($placeholders) AND record_id = ?");
                $delParams = array_merge($deletePhotos, [$recordId]);
                $delStmt->execute($delParams);
            }

            // Insert new photos
            if (!empty($uploadedPhotos)) {
                $photoStmt = $db->prepare("INSERT INTO teaching_photos (record_id, photo_path) VALUES (?, ?)");
                foreach ($uploadedPhotos as $photoPath) {
                    $photoStmt->execute([$recordId, $photoPath]);
                }
            }

            $db->commit();
            setFlash('success', 'แก้ไขบันทึกการสอนเรียบร้อยแล้ว!');
            redirect(BASE_URL . '/tutor/history.php');
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/layout.php';
?>

<div class="max-w-3xl">
    <?php if (!empty($errors)): ?>
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl">
            <p class="font-medium mb-1"><i class="fas fa-exclamation-triangle mr-1"></i> กรุณาแก้ไขข้อผิดพลาด:</p>
            <ul class="list-disc ml-5 text-sm">
                <?php foreach ($errors as $err): ?>
                    <li><?= $err ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form id="editRecordForm" method="POST" enctype="multipart/form-data" class="space-y-6">
        <!-- School Selection -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <label class="block font-bold text-gray-800 mb-3">
                <i class="fas fa-school text-indigo-500 mr-2"></i> โรงเรียนที่สอน <span class="text-red-400">*</span>
            </label>
            <select name="school_id" required
                class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all outline-none bg-gray-50/50 appearance-none text-gray-700">
                <option value="">-- เลือกโรงเรียน --</option>
                <?php foreach ($schools as $school): ?>
                    <option value="<?= $school['id'] ?>" <?= ($record['school_id'] == $school['id']) ? 'selected' : '' ?>>
                        <?= sanitize($school['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Date & Time -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <h3 class="font-bold text-gray-800 mb-4">
                <i class="fas fa-calendar-alt text-indigo-500 mr-2"></i> วันที่และเวลา
            </h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-2">วันที่สอน <span class="text-red-400">*</span></label>
                    <input type="date" name="teaching_date" required
                        value="<?= sanitize($record['teaching_date']) ?>"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all outline-none bg-gray-50/50">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-2">เวลาเริ่ม <span class="text-red-400">*</span></label>
                        <input type="time" name="start_time" required
                            value="<?= sanitize(substr($record['start_time'], 0, 5)) ?>"
                            class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all outline-none bg-gray-50/50">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-2">เวลาสิ้นสุด <span class="text-red-400">*</span></label>
                        <input type="time" name="end_time" required
                            value="<?= sanitize(substr($record['end_time'], 0, 5)) ?>"
                            class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all outline-none bg-gray-50/50">
                    </div>
                </div>
            </div>
        </div>

        <!-- Existing Photos -->
        <?php if (!empty($existingPhotos)): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <label class="block font-bold text-gray-800 mb-3">
                <i class="fas fa-images text-indigo-500 mr-2"></i> รูปภาพปัจจุบัน
                <span class="text-gray-400 font-normal text-sm">(เลือกรูปที่ต้องการลบ)</span>
            </label>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <?php foreach ($existingPhotos as $photo): ?>
                    <div class="relative group">
                        <img src="<?= BASE_URL ?>/uploads/<?= sanitize($photo['photo_path']) ?>"
                            alt="Photo" class="w-full aspect-square object-cover rounded-xl shadow-sm border border-gray-200">
                        <label class="absolute top-2 right-2 cursor-pointer">
                            <input type="checkbox" name="delete_photos[]" value="<?= $photo['id'] ?>"
                                class="hidden peer">
                            <div class="w-7 h-7 bg-white/80 rounded-full flex items-center justify-center text-gray-400 peer-checked:bg-red-500 peer-checked:text-white transition-all shadow-sm">
                                <i class="fas fa-trash text-xs"></i>
                            </div>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Add New Photos -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <label class="block font-bold text-gray-800 mb-3">
                <i class="fas fa-camera text-indigo-500 mr-2"></i> เพิ่มรูปภาพใหม่
                <span class="text-gray-400 font-normal text-sm">(ถ้าต้องการ)</span>
            </label>
            <div class="flex gap-3 mb-4">
                <button type="button" onclick="document.getElementById('photoCameraInput').click()"
                    class="flex-1 py-3 px-4 bg-indigo-50 text-indigo-600 font-medium rounded-xl border-2 border-dashed border-indigo-200 hover:bg-indigo-100 hover:border-indigo-400 transition-all text-center">
                    <i class="fas fa-camera mr-2"></i> ถ่ายรูป
                </button>
                <button type="button" onclick="document.getElementById('photoAlbumInput').click()"
                    class="flex-1 py-3 px-4 bg-purple-50 text-purple-600 font-medium rounded-xl border-2 border-dashed border-purple-200 hover:bg-purple-100 hover:border-purple-400 transition-all text-center">
                    <i class="fas fa-images mr-2"></i> เลือกจากอัลบั้ม
                </button>
            </div>
            <input type="file" id="photoCameraInput" accept="image/*" capture="environment"
                class="hidden" onchange="handleFiles(this)">
            <input type="file" id="photoAlbumInput" accept="image/*" multiple
                class="hidden" onchange="handleFiles(this)">
            <div id="previewGrid" class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-4 hidden"></div>
            <div id="photoCount" class="hidden mt-3 text-center">
                <span class="inline-flex items-center gap-1 px-3 py-1 bg-indigo-50 text-indigo-600 rounded-lg text-sm font-medium">
                    <i class="fas fa-plus-circle"></i> <span id="photoCountText">0</span> รูปใหม่
                </span>
            </div>
        </div>

        <!-- Teaching Summary -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <label class="block font-bold text-gray-800 mb-3">
                <i class="fas fa-clipboard-list text-indigo-500 mr-2"></i> สรุปการสอน
            </label>
            <textarea name="teaching_summary" id="teaching_summary" rows="3"
                class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all outline-none bg-gray-50/50 resize-none"
                placeholder="เช่น สอนวิชาคณิตศาสตร์ บทที่ 3..."><?= sanitize($record['teaching_summary'] ?? '') ?></textarea>
        </div>

        <!-- Notes -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <label class="block font-bold text-gray-800 mb-3">
                <i class="fas fa-sticky-note text-indigo-500 mr-2"></i> หมายเหตุ
            </label>
            <textarea name="notes" id="notes" rows="3"
                class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all outline-none bg-gray-50/50 resize-none"
                placeholder="เช่น นักเรียนตั้งใจเรียนดี..."><?= sanitize($record['notes'] ?? '') ?></textarea>
        </div>

        <!-- Submit -->
        <div class="flex gap-3">
            <a href="<?= BASE_URL ?>/tutor/history.php"
                class="px-6 py-3 bg-gray-100 text-gray-700 font-medium rounded-xl hover:bg-gray-200 transition-colors">
                ยกเลิก
            </a>
            <button type="button" id="submitBtn" onclick="submitEditForm()"
                class="flex-1 py-3 bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl hover:from-indigo-600 hover:to-purple-700 transform hover:-translate-y-0.5 transition-all duration-300">
                <i class="fas fa-save mr-2"></i> บันทึกการแก้ไข
            </button>
        </div>
    </form>
</div>

<!-- Loading overlay -->
<div id="uploadingOverlay" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center">
    <div class="bg-white rounded-2xl p-8 text-center shadow-2xl">
        <div class="w-16 h-16 border-4 border-indigo-200 border-t-indigo-500 rounded-full animate-spin mx-auto mb-4"></div>
        <p class="text-gray-700 font-medium">กำลังบันทึก...</p>
    </div>
</div>

<script>
    let collectedFiles = [];

    function handleFiles(input) {
        const files = input.files;
        let skipped = [];
        for (let i = 0; i < files.length; i++) {
            const f = files[i];
            if (f.type && !f.type.startsWith('image/')) {
                skipped.push(f.name + ' (ไม่ใช่รูปภาพ — อาจเป็น Live Photo)');
                continue;
            }
            collectedFiles.push(f);
        }
        input.value = '';
        if (skipped.length > 0) {
            alert('ไฟล์ต่อไปนี้ถูกข้ามไป:\n\n' + skipped.join('\n') + '\n\n💡 ถ้าเป็น Live Photo → ปิด Live Photo แล้วถ่ายใหม่ หรือบันทึกเป็นรูปภาพปกติก่อนเลือก');
        }
        renderPreviews();
    }

    function renderPreviews() {
        const grid = document.getElementById('previewGrid');
        const countEl = document.getElementById('photoCount');
        const countText = document.getElementById('photoCountText');
        grid.innerHTML = '';

        if (collectedFiles.length === 0) {
            grid.classList.add('hidden');
            countEl.classList.add('hidden');
            return;
        }

        grid.classList.remove('hidden');
        countEl.classList.remove('hidden');
        countText.textContent = collectedFiles.length;

        collectedFiles.forEach((file, i) => {
            const div = document.createElement('div');
            div.className = 'relative group';
            div.innerHTML = `
            <div class="aspect-square rounded-xl overflow-hidden bg-gray-100 shadow-sm border border-gray-200">
                <img class="w-full h-full object-cover" alt="Preview">
            </div>
            <button type="button" onclick="removePhoto(${i})"
                    class="absolute -top-2 -right-2 w-7 h-7 bg-red-500 text-white rounded-full flex items-center justify-center text-xs shadow-md transition-opacity hover:bg-red-600"
                    style="opacity:0.9;">
                <i class="fas fa-times"></i>
            </button>`;

            const img = div.querySelector('img');
            const reader = new FileReader();
            reader.onload = function (e) { img.src = e.target.result; };
            reader.readAsDataURL(file);
            grid.appendChild(div);
        });
    }

    function removePhoto(index) {
        collectedFiles.splice(index, 1);
        renderPreviews();
    }

    async function submitEditForm() {
        const form = document.getElementById('editRecordForm');
        const fd = new FormData(form);

        // Remove any existing photos[] from FormData (from native form)
        fd.delete('photos[]');

        // Add collected files
        for (let i = 0; i < collectedFiles.length; i++) {
            fd.append('photos[]', collectedFiles[i]);
        }

        const overlay = document.getElementById('uploadingOverlay');
        overlay.classList.remove('hidden');
        overlay.classList.add('flex');
        document.getElementById('submitBtn').disabled = true;

        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: fd
            });

            if (response.redirected) {
                window.location.href = response.url;
                return;
            }

            const html = await response.text();
            document.open();
            document.write(html);
            document.close();
        } catch (err) {
            overlay.classList.add('hidden');
            overlay.classList.remove('flex');
            document.getElementById('submitBtn').disabled = false;
            alert('เกิดข้อผิดพลาด: ' + err.message);
        }
    }
</script>

<?php include __DIR__ . '/layout_footer.php'; ?>
