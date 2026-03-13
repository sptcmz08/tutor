<?php
$pageTitle = 'รายงานประจำเดือน';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdminLogin();

$db = getDB();

$filterMonth = (int) ($_GET['month'] ?? date('n'));
$filterYear = (int) ($_GET['year'] ?? date('Y'));

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    if ($_POST['action'] === 'update_fee') {
        $recordId = (int)($_POST['record_id'] ?? 0);
        $fee = $_POST['fee'] !== '' ? (float)$_POST['fee'] : null;
        $stmt = $db->prepare("UPDATE teaching_records SET teaching_fee = ? WHERE id = ?");
        $stmt->execute([$fee, $recordId]);
        echo json_encode(['success' => true]);
    } elseif ($_POST['action'] === 'update_record') {
        $recordId = (int)($_POST['record_id'] ?? 0);
        $schoolId = (int)($_POST['school_id'] ?? 0);
        $teachingDate = $_POST['teaching_date'] ?? '';
        $startTime = $_POST['start_time'] ?? '';
        $endTime = $_POST['end_time'] ?? '';
        $teachingSummary = trim($_POST['teaching_summary'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        if ($recordId && $schoolId && $teachingDate && $startTime && $endTime) {
            $stmt = $db->prepare("
                UPDATE teaching_records 
                SET school_id=?, teaching_date=?, start_time=?, end_time=?, teaching_summary=?, notes=?
                WHERE id=?
            ");
            $stmt->execute([$schoolId, $teachingDate, $startTime, $endTime, $teachingSummary ?: null, $notes ?: null, $recordId]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ครบถ้วน']);
        }
    } elseif ($_POST['action'] === 'delete_record') {
        $recordId = (int)($_POST['record_id'] ?? 0);
        if ($recordId) {
            // Delete physical photo files first
            $photoStmt = $db->prepare("SELECT photo_path FROM teaching_photos WHERE record_id = ?");
            $photoStmt->execute([$recordId]);
            $photos = $photoStmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($photos as $photoPath) {
                $fullPath = UPLOAD_DIR . '/' . $photoPath;
                if (file_exists($fullPath)) unlink($fullPath);
            }
            // Delete DB records (photos cascade via FK)
            $stmt = $db->prepare("DELETE FROM teaching_records WHERE id = ?");
            $stmt->execute([$recordId]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'ไม่พบรายการ']);
        }
    }
    exit;
}

$schools = getSchools(false);

// Get all records grouped by tutor
$records = getMonthlyRecords($filterMonth, $filterYear);

// Fetch photos for each record
foreach ($records as &$r) {
    $photoStmt = $db->prepare("SELECT photo_path FROM teaching_photos WHERE record_id = ?");
    $photoStmt->execute([$r['id']]);
    $r['photos'] = $photoStmt->fetchAll(PDO::FETCH_COLUMN);
}
unset($r);

// Group records by tutor
$groupedByTutor = [];
foreach ($records as $r) {
    $key = $r['tutor_id'];
    if (!isset($groupedByTutor[$key])) {
        $groupedByTutor[$key] = [
            'name' => $r['first_name'] . ' ' . $r['last_name'],
            'nickname' => $r['nickname'],
            'records' => [],
            'total_fee' => 0
        ];
    }
    $groupedByTutor[$key]['records'][] = $r;
    $groupedByTutor[$key]['total_fee'] += ($r['teaching_fee'] ?? 0);
}

$grandTotalFee = array_sum(array_column($groupedByTutor, 'total_fee'));

include __DIR__ . '/layout.php';
?>

<div class="max-w-6xl">
    <!-- Filter -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 mb-6">
        <form method="GET" class="flex flex-wrap gap-4 items-end">
            <div class="min-w-[160px]">
                <label class="block text-sm font-medium text-slate-600 mb-1">เดือน</label>
                <select name="month"
                    class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-indigo-500 outline-none text-sm">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m == $filterMonth ? 'selected' : '' ?>><?= THAI_MONTHS[$m] ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="min-w-[100px]">
                <label class="block text-sm font-medium text-slate-600 mb-1">ปี</label>
                <select name="year"
                    class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-indigo-500 outline-none text-sm">
                    <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
                    <option value="<?= $y ?>" <?= $y == $filterYear ? 'selected' : '' ?>><?= $y + 543 ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <button type="submit"
                class="px-5 py-2 bg-indigo-500 text-white rounded-lg hover:bg-indigo-600 transition-colors text-sm">
                <i class="fas fa-search mr-1"></i> แสดงรายงาน
            </button>

            <?php if (!empty($groupedByTutor)): ?>
            <div class="ml-auto flex gap-2">
                <a href="<?= BASE_URL ?>/admin/export.php?type=excel&month=<?= $filterMonth ?>&year=<?= $filterYear ?>"
                   class="px-4 py-2 bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition-colors text-sm">
                    <i class="fas fa-file-excel mr-1"></i> Excel
                </a>
                <a href="<?= BASE_URL ?>/admin/export.php?type=pdf&month=<?= $filterMonth ?>&year=<?= $filterYear ?>"
                   class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors text-sm">
                    <i class="fas fa-file-pdf mr-1"></i> PDF
                </a>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Summary -->
    <div class="flex flex-wrap items-center gap-4 mb-6">
        <h3 class="text-lg font-bold text-slate-800">
            <i class="fas fa-calendar-alt text-indigo-500 mr-2"></i>
            <?= formatThaiMonthYear($filterMonth, $filterYear) ?>
        </h3>
        <div class="flex gap-3">
            <span class="px-3 py-1 bg-indigo-50 text-indigo-600 rounded-lg text-sm font-medium">
                <?= count($groupedByTutor) ?> ติวเตอร์
            </span>
            <span class="px-3 py-1 bg-emerald-50 text-emerald-600 rounded-lg text-sm font-medium">
                <?= count($records) ?> รายการ
            </span>
            <?php if ($grandTotalFee > 0): ?>
            <span class="px-3 py-1 bg-amber-50 text-amber-600 rounded-lg text-sm font-medium">
                <i class="fas fa-coins mr-1"></i> รวม ฿<?= number_format($grandTotalFee, 0) ?>
            </span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($groupedByTutor)): ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-12 text-center">
        <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-file-alt text-slate-300 text-3xl"></i>
        </div>
        <p class="text-slate-400">ไม่มีบันทึกการสอนในเดือนนี้</p>
    </div>
    <?php else: ?>
    <!-- Tutor Reports -->
    <div class="space-y-6">
        <?php foreach ($groupedByTutor as $tutorId => $data): ?>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <!-- Tutor Header (Collapsible) -->
            <button
                class="w-full px-6 py-4 flex items-center justify-between hover:bg-slate-50/50 transition-colors text-left"
                onclick="toggleSection(<?= $tutorId ?>)">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 bg-indigo-50 rounded-full flex items-center justify-center text-indigo-600 font-bold">
                        <?= mb_substr($data['name'], 0, 1) ?>
                    </div>
                    <div>
                        <p class="font-bold text-slate-800">
                            <?= sanitize($data['name']) ?>
                            <?php if ($data['nickname']): ?>
                            <span class="text-slate-400 font-normal text-sm">(<?= sanitize($data['nickname']) ?>)</span>
                            <?php endif; ?>
                        </p>
                        <p class="text-sm text-slate-400">สอนทั้งหมด <?= count($data['records']) ?> ครั้ง</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="px-3 py-1 bg-indigo-50 text-indigo-600 rounded-lg font-bold">
                        <?= count($data['records']) ?> ครั้ง
                    </span>
                    <?php if ($data['total_fee'] > 0): ?>
                    <span class="px-3 py-1 bg-emerald-50 text-emerald-600 rounded-lg font-bold text-sm">
                        ฿<?= number_format($data['total_fee'], 0) ?>
                    </span>
                    <?php endif; ?>
                    <i class="fas fa-chevron-down text-slate-400 transform transition-transform"
                       id="icon-<?= $tutorId ?>"></i>
                </div>
            </button>

            <!-- Records Table -->
            <div class="border-t border-slate-100" id="section-<?= $tutorId ?>">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-slate-500 font-medium w-8">#</th>
                                <th class="px-3 py-2 text-left text-slate-500 font-medium">โรงเรียน</th>
                                <th class="px-3 py-2 text-left text-slate-500 font-medium">วันที่</th>
                                <th class="px-3 py-2 text-left text-slate-500 font-medium">เวลา</th>
                                <th class="px-3 py-2 text-left text-slate-500 font-medium">สรุปการสอน</th>
                                <th class="px-3 py-2 text-center text-slate-500 font-medium">รูป</th>
                                <th class="px-3 py-2 text-right text-slate-500 font-medium w-32">ค่าสอน (฿)</th>
                                <th class="px-3 py-2 text-center text-slate-500 font-medium w-16">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php foreach ($data['records'] as $i => $r): ?>
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-3 py-3 text-slate-400"><?= $i + 1 ?></td>
                                <td class="px-3 py-3 font-medium text-slate-700">
                                    <i class="fas fa-school text-slate-300 mr-1 text-xs"></i>
                                    <?= sanitize($r['school_name']) ?>
                                </td>
                                <td class="px-3 py-3 text-slate-600 whitespace-nowrap"><?= formatThaiDate($r['teaching_date']) ?></td>
                                <td class="px-3 py-3">
                                    <span class="px-2 py-1 bg-indigo-50 text-indigo-600 rounded text-xs font-medium">
                                        <?= formatTimeRange($r['start_time'], $r['end_time']) ?>
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-slate-600 max-w-[200px]">
                                    <?php if ($r['teaching_summary']): ?>
                                    <p class="truncate text-xs" title="<?= sanitize($r['teaching_summary']) ?>">
                                        <?= sanitize($r['teaching_summary']) ?>
                                    </p>
                                    <?php endif; ?>
                                    <?php if ($r['notes']): ?>
                                    <p class="truncate text-xs text-slate-400 italic" title="<?= sanitize($r['notes']) ?>">
                                        📝 <?= sanitize($r['notes']) ?>
                                    </p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <?php if (!empty($r['photos'])): ?>
                                    <div class="flex items-center justify-center gap-1">
                                        <?php foreach (array_slice($r['photos'], 0, 3) as $photo): ?>
                                        <img src="<?= BASE_URL ?>/uploads/<?= sanitize($photo) ?>" alt="Photo"
                                            class="w-10 h-10 object-cover rounded-lg inline-block cursor-pointer hover:opacity-80 transition-opacity shadow-sm"
                                            onclick="openLightbox('<?= BASE_URL ?>/uploads/<?= sanitize($photo) ?>')">
                                        <?php endforeach; ?>
                                        <?php if (count($r['photos']) > 3): ?>
                                        <span class="text-xs text-slate-400">+<?= count($r['photos']) - 3 ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-slate-300"><i class="fas fa-image"></i></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <input type="number" step="1" min="0"
                                           class="w-24 px-2 py-1 text-right rounded-lg border border-slate-200 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-200 outline-none text-sm fee-input"
                                           data-record-id="<?= $r['id'] ?>"
                                           value="<?= $r['teaching_fee'] !== null ? (int)$r['teaching_fee'] : '' ?>"
                                           placeholder="0"
                                           onchange="saveFee(this)">
                                </td>
                                <td class="px-3 py-3 text-center whitespace-nowrap">
                                    <button type="button" onclick='openEditRecord(<?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                        class="p-1.5 text-slate-400 hover:text-indigo-500 transition-colors" title="แก้ไข">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" onclick="deleteRecord(<?= $r['id'] ?>, '<?= sanitize($r['school_name']) ?> - <?= formatThaiDate($r['teaching_date']) ?>')"
                                        class="p-1.5 text-slate-400 hover:text-red-500 transition-colors" title="ลบ">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-slate-50 border-t-2 border-slate-200">
                            <tr>
                                <td colspan="8" class="px-3 py-3 text-right font-bold text-slate-700">
                                    รวมค่าสอน <?= sanitize($data['name']) ?>
                                </td>
                                <td class="px-3 py-3 text-right font-bold text-emerald-600 text-lg" id="total-<?= $tutorId ?>">
                                    ฿<?= number_format($data['total_fee'], 0) ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Lightbox -->
<div id="lightbox" class="fixed inset-0 bg-black/80 z-50 hidden items-center justify-center p-4" onclick="closeLightbox()">
    <img id="lightboxImg" class="max-w-full max-h-full rounded-xl shadow-2xl" alt="Photo">
    <button class="absolute top-4 right-4 text-white text-3xl hover:text-gray-300"><i class="fas fa-times"></i></button>
</div>

<!-- Save Toast -->
<div id="saveToast" class="fixed bottom-6 right-6 bg-emerald-500 text-white px-4 py-2 rounded-xl shadow-lg hidden transition-all duration-300 text-sm">
    <i class="fas fa-check-circle mr-1"></i> <span id="saveToastText">บันทึกเรียบร้อยแล้ว</span>
</div>

<!-- Edit Record Modal -->
<div id="editRecordModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-800"><i class="fas fa-edit text-indigo-500 mr-2"></i>แก้ไขบันทึกการสอน</h3>
            <button onclick="closeEditModal()" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-6 space-y-4">
            <input type="hidden" id="er_record_id">
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">โรงเรียน <span class="text-red-400">*</span></label>
                <select id="er_school_id" class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-indigo-500 outline-none text-sm">
                    <?php foreach ($schools as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= sanitize($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">วันที่สอน <span class="text-red-400">*</span></label>
                <input type="date" id="er_teaching_date" class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-indigo-500 outline-none text-sm">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">เวลาเริ่ม <span class="text-red-400">*</span></label>
                    <input type="time" id="er_start_time" class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-indigo-500 outline-none text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">เวลาสิ้นสุด <span class="text-red-400">*</span></label>
                    <input type="time" id="er_end_time" class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-indigo-500 outline-none text-sm">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">สรุปการสอน</label>
                <textarea id="er_teaching_summary" rows="2" class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-indigo-500 outline-none text-sm resize-none"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">หมายเหตุ</label>
                <textarea id="er_notes" rows="2" class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-indigo-500 outline-none text-sm resize-none"></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeEditModal()"
                    class="px-5 py-2 bg-slate-100 text-slate-600 rounded-lg hover:bg-slate-200 transition-colors text-sm">ยกเลิก</button>
                <button type="button" onclick="saveEditRecord()"
                    class="px-5 py-2 bg-indigo-500 text-white rounded-lg hover:bg-indigo-600 transition-colors text-sm"><i class="fas fa-save mr-1"></i> บันทึก</button>
            </div>
        </div>
    </div>
</div>

<script>
function toggleSection(id) {
    const section = document.getElementById('section-' + id);
    const icon = document.getElementById('icon-' + id);
    section.classList.toggle('hidden');
    icon.classList.toggle('rotate-180');
}

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

function showToast(text) {
    const toast = document.getElementById('saveToast');
    document.getElementById('saveToastText').textContent = text || 'บันทึกเรียบร้อยแล้ว';
    toast.classList.remove('hidden');
    setTimeout(() => toast.classList.add('hidden'), 2000);
}

function saveFee(input) {
    const recordId = input.dataset.recordId;
    const fee = input.value;

    fetch(window.location.pathname + window.location.search, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=update_fee&record_id=' + recordId + '&fee=' + fee
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            input.classList.add('bg-emerald-50', 'border-emerald-300');
            setTimeout(() => {
                input.classList.remove('bg-emerald-50', 'border-emerald-300');
            }, 1500);
            showToast('บันทึกค่าสอนแล้ว');
        }
    });
}

function openEditRecord(record) {
    document.getElementById('er_record_id').value = record.id;
    document.getElementById('er_school_id').value = record.school_id;
    document.getElementById('er_teaching_date').value = record.teaching_date;
    document.getElementById('er_start_time').value = record.start_time ? record.start_time.substring(0, 5) : '';
    document.getElementById('er_end_time').value = record.end_time ? record.end_time.substring(0, 5) : '';
    document.getElementById('er_teaching_summary').value = record.teaching_summary || '';
    document.getElementById('er_notes').value = record.notes || '';
    const m = document.getElementById('editRecordModal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}

function closeEditModal() {
    const m = document.getElementById('editRecordModal');
    m.classList.add('hidden');
    m.classList.remove('flex');
}

function saveEditRecord() {
    const data = new URLSearchParams();
    data.append('action', 'update_record');
    data.append('record_id', document.getElementById('er_record_id').value);
    data.append('school_id', document.getElementById('er_school_id').value);
    data.append('teaching_date', document.getElementById('er_teaching_date').value);
    data.append('start_time', document.getElementById('er_start_time').value);
    data.append('end_time', document.getElementById('er_end_time').value);
    data.append('teaching_summary', document.getElementById('er_teaching_summary').value);
    data.append('notes', document.getElementById('er_notes').value);

    fetch(window.location.pathname + window.location.search, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: data.toString()
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            closeEditModal();
            showToast('แก้ไขบันทึกการสอนเรียบร้อย');
            // Reload to reflect changes
            setTimeout(() => window.location.reload(), 500);
        } else {
            alert(result.error || 'เกิดข้อผิดพลาด');
        }
    });
}

// Close modals on outside click
document.getElementById('editRecordModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});

function deleteRecord(recordId, label) {
    if (!confirm('🗑️ ต้องการลบรายการนี้?\n\n' + label + '\n\nรูปภาพทั้งหมดจะถูกลบด้วย การดำเนินการนี้ไม่สามารถย้อนกลับได้')) return;

    fetch(window.location.pathname + window.location.search, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=delete_record&record_id=' + recordId
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            showToast('ลบรายการเรียบร้อย');
            setTimeout(() => window.location.reload(), 500);
        } else {
            alert(result.error || 'เกิดข้อผิดพลาด');
        }
    });
}

document.addEventListener('keydown', function(e) { if (e.key === 'Escape') { closeLightbox(); closeEditModal(); } });
</script>

<?php include __DIR__ . '/layout_footer.php'; ?>