<?php
$pageTitle = 'บันทึกการสอน';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireTutorLogin();

$tutor = getCurrentTutor();
$schools = getSchools(true);

$errors = [];

// Detect PHP post_max_size overflow — when exceeded, PHP silently empties $_POST and $_FILES
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

    // Upload all photos
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
                $errors[] = 'รูปที่ ' . ($i + 1) . ': ' . $uploadResult['error'];
            }
        }
    }

    if (empty($errors)) {
        $db = getDB();
        $db->beginTransaction();
        try {
            // Insert record
            $stmt = $db->prepare("
                INSERT INTO teaching_records (tutor_id, school_id, teaching_date, start_time, end_time, teaching_summary, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$tutor['id'], $schoolId, $teachingDate, $startTime, $endTime, $teachingSummary ?: null, $notes ?: null]);
            $recordId = $db->lastInsertId();

            // Insert photos
            if (!empty($uploadedPhotos)) {
                $photoStmt = $db->prepare("INSERT INTO teaching_photos (record_id, photo_path) VALUES (?, ?)");
                foreach ($uploadedPhotos as $photoPath) {
                    $photoStmt->execute([$recordId, $photoPath]);
                }
            }

            $db->commit();
            setFlash('success', 'บันทึกการสอนเรียบร้อยแล้ว! (' . count($uploadedPhotos) . ' รูป)');
            redirect(BASE_URL . '/tutor/dashboard.php');
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

    <form id="recordForm" method="POST" enctype="multipart/form-data" class="space-y-6">
        <!-- School Selection -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <label class="block font-bold text-gray-800 mb-3">
                <i class="fas fa-school text-indigo-500 mr-2"></i> โรงเรียนที่สอน <span class="text-red-400">*</span>
            </label>
            <select name="school_id" required
                class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all outline-none bg-gray-50/50 appearance-none text-gray-700">
                <option value="">-- เลือกโรงเรียน --</option>
                <?php foreach ($schools as $school): ?>
                    <option value="<?= $school['id'] ?>" <?= (isset($_POST['school_id']) && $_POST['school_id'] == $school['id']) ? 'selected' : '' ?>>
                        <?= sanitize($school['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (empty($schools)): ?>
                <p class="text-amber-500 text-sm mt-2"><i class="fas fa-info-circle mr-1"></i> ยังไม่มีรายชื่อโรงเรียน
                    กรุณาติดต่อผู้ดูแลระบบ</p>
            <?php endif; ?>
        </div>

        <!-- Date & Time -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <h3 class="font-bold text-gray-800 mb-4">
                <i class="fas fa-calendar-alt text-indigo-500 mr-2"></i> วันที่และเวลา
            </h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-2">วันที่สอน <span
                            class="text-red-400">*</span></label>
                    <input type="date" name="teaching_date" required
                        value="<?= sanitize($_POST['teaching_date'] ?? date('Y-m-d')) ?>"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all outline-none bg-gray-50/50">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-2">เวลาเริ่ม <span
                                class="text-red-400">*</span></label>
                        <input type="time" name="start_time" required
                            value="<?= sanitize($_POST['start_time'] ?? '') ?>"
                            class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all outline-none bg-gray-50/50">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-2">เวลาสิ้นสุด <span
                                class="text-red-400">*</span></label>
                        <input type="time" name="end_time" required value="<?= sanitize($_POST['end_time'] ?? '') ?>"
                            class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all outline-none bg-gray-50/50">
                    </div>
                </div>
            </div>
        </div>

        <!-- Multi Photo Upload -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <label class="block font-bold text-gray-800 mb-3">
                <i class="fas fa-camera text-indigo-500 mr-2"></i> รูปภาพการสอน <span
                    class="text-gray-400 font-normal text-sm">(เพิ่มได้ไม่จำกัด)</span>
            </label>

            <!-- Two Upload Buttons for iOS compatibility -->
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

            <!-- Hidden file inputs -->
            <input type="file" id="photoCameraInput" accept="image/*" capture="environment"
                class="hidden" onchange="handleFiles(this)">
            <input type="file" id="photoAlbumInput" accept="image/*" multiple
                class="hidden" onchange="handleFiles(this)">

            <!-- Drop zone (desktop) -->
            <div class="border-2 border-dashed border-gray-200 rounded-xl p-4 text-center hover:border-indigo-400 hover:bg-indigo-50/30 transition-all hidden sm:block"
                id="uploadZone">
                <p class="text-gray-400 text-sm"><i class="fas fa-cloud-upload-alt mr-1"></i> ลากไฟล์มาวางที่นี่ (Desktop)</p>
                <p class="text-gray-300 text-xs mt-1">รองรับ JPG, PNG, WEBP, HEIC (ไม่เกิน 10MB ต่อรูป)</p>
            </div>

            <!-- Preview Grid -->
            <div id="previewGrid" class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-4 hidden"></div>

            <!-- Photo count -->
            <div id="photoCount" class="hidden mt-3 text-center">
                <span
                    class="inline-flex items-center gap-1 px-3 py-1 bg-indigo-50 text-indigo-600 rounded-lg text-sm font-medium">
                    <i class="fas fa-images"></i>
                    <span id="photoCountText">0</span> รูป
                </span>
                <button type="button" onclick="clearPhotos()"
                    class="ml-2 text-sm text-red-400 hover:text-red-600 transition-colors">
                    <i class="fas fa-trash mr-1"></i> ล้างทั้งหมด
                </button>
            </div>
        </div>

        <!-- Teaching Summary -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <label class="block font-bold text-gray-800 mb-3">
                <i class="fas fa-clipboard-list text-indigo-500 mr-2"></i> สรุปการสอน <span
                    class="text-gray-400 font-normal text-sm">(สอนถึงหน้าไหน ข้อไหน เรื่องอะไร)</span>
            </label>
            <textarea name="teaching_summary" id="teaching_summary" rows="3"
                class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all outline-none bg-gray-50/50 resize-none"
                placeholder="เช่น สอนวิชาคณิตศาสตร์ บทที่ 3 เรื่องสมการเชิงเส้น หน้า 45-60..."><?= sanitize($_POST['teaching_summary'] ?? '') ?></textarea>
        </div>

        <!-- Notes -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <label class="block font-bold text-gray-800 mb-3">
                <i class="fas fa-sticky-note text-indigo-500 mr-2"></i> หมายเหตุ <span
                    class="text-gray-400 font-normal text-sm">(สรุปบรรยากาศการสอน ปัญหาที่พบ หรือระบุว่ามาสอนแทนใคร)</span>
            </label>
            <textarea name="notes" id="notes" rows="3"
                class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all outline-none bg-gray-50/50 resize-none"
                placeholder="เช่น นักเรียนตั้งใจเรียนดี / มีปัญหาเรื่อง... / มาสอนแทนคุณ..."><?= sanitize($_POST['notes'] ?? '') ?></textarea>
        </div>

        <!-- Submit -->
        <div class="flex gap-3">
            <a href="<?= BASE_URL ?>/tutor/dashboard.php"
                class="px-6 py-3 bg-gray-100 text-gray-700 font-medium rounded-xl hover:bg-gray-200 transition-colors">
                ยกเลิก
            </a>
            <button type="button" id="submitBtn" onclick="submitForm()"
                class="flex-1 py-3 bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl hover:from-indigo-600 hover:to-purple-700 transform hover:-translate-y-0.5 transition-all duration-300">
                <i class="fas fa-save mr-2"></i> บันทึกการสอน
            </button>
        </div>
    </form>
</div>

<!-- Loading overlay -->
<div id="uploadingOverlay" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center">
    <div class="bg-white rounded-2xl p-8 text-center shadow-2xl">
        <div class="w-16 h-16 border-4 border-indigo-200 border-t-indigo-500 rounded-full animate-spin mx-auto mb-4"></div>
        <p class="text-gray-700 font-medium">กำลังอัพโหลด...</p>
        <p class="text-gray-400 text-sm mt-1" id="uploadProgress">กรุณารอสักครู่</p>
    </div>
</div>

<script>
    // Use plain array instead of DataTransfer for iOS compatibility
    let collectedFiles = [];

    function handleFiles(input) {
        const files = input.files;
        let skipped = [];
        for (let i = 0; i < files.length; i++) {
            const f = files[i];
            // Filter out videos / Live Photos
            if (f.type && !f.type.startsWith('image/')) {
                skipped.push(f.name + ' (ไม่ใช่รูปภาพ — อาจเป็น Live Photo)');
                continue;
            }
            collectedFiles.push(f);
        }
        // Reset input so the same file can be selected again
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
                    class="absolute -top-2 -right-2 w-7 h-7 bg-red-500 text-white rounded-full flex items-center justify-center text-xs shadow-md group-hover:opacity-100 transition-opacity hover:bg-red-600"
                    style="opacity:0.9;">
                <i class="fas fa-times"></i>
            </button>
            <p class="text-xs text-gray-400 mt-1 truncate text-center">${file.name}</p>
        `;

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

    function clearPhotos() {
        collectedFiles = [];
        renderPreviews();
    }

    // Submit form via FormData + fetch (avoids iOS DataTransfer issues)
    async function submitForm() {
        const form = document.getElementById('recordForm');

        // Client-side validation
        const schoolId = form.querySelector('[name="school_id"]').value;
        const teachingDate = form.querySelector('[name="teaching_date"]').value;
        const startTime = form.querySelector('[name="start_time"]').value;
        const endTime = form.querySelector('[name="end_time"]').value;

        if (!schoolId) { alert('กรุณาเลือกโรงเรียน'); return; }
        if (!teachingDate) { alert('กรุณาเลือกวันที่สอน'); return; }
        if (!startTime) { alert('กรุณาระบุเวลาเริ่มสอน'); return; }
        if (!endTime) { alert('กรุณาระบุเวลาสิ้นสุด'); return; }
        if (startTime >= endTime) { alert('เวลาสิ้นสุดต้องมากกว่าเวลาเริ่มสอน'); return; }

        // Build FormData manually
        const fd = new FormData();
        fd.append('school_id', schoolId);
        fd.append('teaching_date', teachingDate);
        fd.append('start_time', startTime);
        fd.append('end_time', endTime);
        fd.append('teaching_summary', document.getElementById('teaching_summary').value);
        fd.append('notes', document.getElementById('notes').value);

        // Append all collected files
        for (let i = 0; i < collectedFiles.length; i++) {
            fd.append('photos[]', collectedFiles[i]);
        }

        // Calculate total size
        let totalSize = 0;
        for (let i = 0; i < collectedFiles.length; i++) totalSize += collectedFiles[i].size;
        const totalMB = (totalSize / 1024 / 1024).toFixed(1);

        // Show loading overlay
        const overlay = document.getElementById('uploadingOverlay');
        overlay.classList.remove('hidden');
        overlay.classList.add('flex');
        document.getElementById('submitBtn').disabled = true;
        document.getElementById('uploadProgress').textContent =
            'กำลังอัพโหลด ' + collectedFiles.length + ' รูป (' + totalMB + ' MB)...';

        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: fd
            });

            // The server will redirect on success, follow it
            if (response.redirected) {
                window.location.href = response.url;
                return;
            }

            // If not redirected, the response contains the page with errors
            const html = await response.text();
            document.open();
            document.write(html);
            document.close();
        } catch (err) {
            overlay.classList.add('hidden');
            overlay.classList.remove('flex');
            document.getElementById('submitBtn').disabled = false;
            alert('เกิดข้อผิดพลาดในการอัพโหลด: ' + err.message);
        }
    }

    // Drag and drop (desktop only)
    const zone = document.getElementById('uploadZone');
    if (zone) {
        zone.addEventListener('dragover', function (e) {
            e.preventDefault();
            this.classList.add('border-indigo-400', 'bg-indigo-50/30');
        });
        zone.addEventListener('dragleave', function () {
            this.classList.remove('border-indigo-400', 'bg-indigo-50/30');
        });
        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            this.classList.remove('border-indigo-400', 'bg-indigo-50/30');
            const files = e.dataTransfer.files;
            for (let i = 0; i < files.length; i++) {
                collectedFiles.push(files[i]);
            }
            renderPreviews();
        });
    }
</script>

<?php include __DIR__ . '/layout_footer.php'; ?>