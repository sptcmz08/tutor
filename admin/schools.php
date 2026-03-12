<?php
$pageTitle = 'จัดการโรงเรียน';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdminLogin();

$db = getDB();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            $stmt = $db->prepare("INSERT INTO schools (name) VALUES (?)");
            $stmt->execute([$name]);
            setFlash('success', 'เพิ่มโรงเรียน "' . $name . '" เรียบร้อยแล้ว');
        }
    } elseif ($action === 'edit') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($id && $name) {
            $stmt = $db->prepare("UPDATE schools SET name = ? WHERE id = ?");
            $stmt->execute([$name, $id]);
            setFlash('success', 'แก้ไขโรงเรียนเรียบร้อยแล้ว');
        }
    } elseif ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id) {
            $db->prepare("UPDATE schools SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
            setFlash('success', 'เปลี่ยนสถานะเรียบร้อยแล้ว');
        }
    }

    redirect(BASE_URL . '/admin/schools.php');
}

$schools = $db->query("SELECT s.*, (SELECT COUNT(*) FROM teaching_records WHERE school_id = s.id) as record_count FROM schools s ORDER BY s.is_active DESC, s.name ASC")->fetchAll();

include __DIR__ . '/layout.php';
?>

<div class="max-w-4xl">
    <!-- Add School -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 mb-6">
        <h3 class="font-bold text-slate-800 mb-4"><i
                class="fas fa-plus-circle text-indigo-500 mr-2"></i>เพิ่มโรงเรียนใหม่</h3>
        <form method="POST" class="flex gap-3">
            <input type="hidden" name="action" value="add">
            <input type="text" name="name" required placeholder="พิมพ์ชื่อโรงเรียน เช่น โรงเรียนสวนกุหลาบวิทยาลัย"
                class="flex-1 px-4 py-3 rounded-xl border border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition-all">
            <button type="submit"
                class="px-6 py-3 bg-indigo-500 text-white font-medium rounded-xl hover:bg-indigo-600 transition-colors shadow-sm whitespace-nowrap">
                <i class="fas fa-plus mr-1"></i> เพิ่ม
            </button>
        </form>
    </div>

    <!-- Schools List -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-800"><i class="fas fa-list text-indigo-500 mr-2"></i>รายชื่อโรงเรียน</h3>
            <span class="text-sm text-slate-400">
                <?= count($schools) ?> รายการ
            </span>
        </div>

        <?php if (empty($schools)): ?>
            <div class="p-8 text-center text-slate-400">
                <i class="fas fa-school text-3xl mb-2"></i>
                <p>ยังไม่มีโรงเรียน</p>
            </div>
        <?php else: ?>
            <div class="divide-y divide-slate-50">
                <?php foreach ($schools as $school): ?>
                    <div class="px-6 py-4 hover:bg-slate-50/50 transition-colors" id="school-<?= $school['id'] ?>">
                        <!-- View mode -->
                        <div class="flex items-center gap-4 school-view" id="view-<?= $school['id'] ?>">
                            <div
                                class="w-10 h-10 rounded-xl flex items-center justify-center <?= $school['is_active'] ? 'bg-emerald-50' : 'bg-slate-100' ?>">
                                <i
                                    class="fas fa-school <?= $school['is_active'] ? 'text-emerald-500' : 'text-slate-300' ?>"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p
                                    class="font-medium text-slate-700 <?= !$school['is_active'] ? 'line-through text-slate-400' : '' ?>">
                                    <?= sanitize($school['name']) ?>
                                </p>
                                <p class="text-xs text-slate-400 mt-0.5">
                                    <?= $school['record_count'] ?> บันทึก
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span
                                    class="px-2 py-1 rounded-lg text-xs font-medium <?= $school['is_active'] ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-400' ?>">
                                    <?= $school['is_active'] ? 'เปิดใช้งาน' : 'ปิดใช้งาน' ?>
                                </span>
                                <button onclick="editSchool(<?= $school['id'] ?>, '<?= addslashes($school['name']) ?>')"
                                    class="p-2 text-slate-400 hover:text-indigo-500 transition-colors" title="แก้ไข">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" class="inline"
                                    onsubmit="return confirm('<?= $school['is_active'] ? 'ปิดใช้งาน' : 'เปิดใช้งาน' ?>โรงเรียนนี้?')">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $school['id'] ?>">
                                    <button type="submit" class="p-2 text-slate-400 hover:text-amber-500 transition-colors"
                                        title="<?= $school['is_active'] ? 'ปิดใช้งาน' : 'เปิดใช้งาน' ?>">
                                        <i
                                            class="fas <?= $school['is_active'] ? 'fa-toggle-on text-emerald-500' : 'fa-toggle-off' ?>"></i>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Edit mode (hidden default) -->
                        <form method="POST" class="hidden items-center gap-3" id="edit-<?= $school['id'] ?>">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="id" value="<?= $school['id'] ?>">
                            <input type="text" name="name" value="<?= sanitize($school['name']) ?>" required
                                class="flex-1 px-3 py-2 rounded-lg border border-indigo-300 focus:border-indigo-500 outline-none text-sm">
                            <button type="submit"
                                class="px-4 py-2 bg-indigo-500 text-white rounded-lg text-sm hover:bg-indigo-600 transition-colors">
                                <i class="fas fa-save mr-1"></i> บันทึก
                            </button>
                            <button type="button" onclick="cancelEdit(<?= $school['id'] ?>)"
                                class="px-4 py-2 bg-slate-100 text-slate-600 rounded-lg text-sm hover:bg-slate-200 transition-colors">
                                ยกเลิก
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function editSchool(id, name) {
        document.getElementById('view-' + id).classList.add('hidden');
        const editForm = document.getElementById('edit-' + id);
        editForm.classList.remove('hidden');
        editForm.classList.add('flex');
        editForm.querySelector('input[name="name"]').focus();
    }

    function cancelEdit(id) {
        document.getElementById('view-' + id).classList.remove('hidden');
        const editForm = document.getElementById('edit-' + id);
        editForm.classList.add('hidden');
        editForm.classList.remove('flex');
    }
</script>

<?php include __DIR__ . '/layout_footer.php'; ?>