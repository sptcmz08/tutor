<?php
$pageTitle = 'จัดการติวเตอร์';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdminLogin();

$db = getDB();
$currentMonth = (int) date('n');
$currentYear = (int) date('Y');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $nickname = trim($_POST['nickname'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        // Check unique username
        $stmt = $db->prepare("SELECT id FROM tutors WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            setFlash('error', 'ชื่อผู้ใช้ "' . $username . '" มีอยู่ในระบบแล้ว');
        } elseif ($username && $password && $firstName && $lastName) {
            $stmt = $db->prepare("INSERT INTO tutors (username, password, first_name, last_name, nickname, phone) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $firstName, $lastName, $nickname ?: null, $phone ?: null]);
            setFlash('success', 'เพิ่มติวเตอร์ "' . $firstName . ' ' . $lastName . '" เรียบร้อยแล้ว');
        }
    } elseif ($action === 'edit') {
        $id = (int) ($_POST['id'] ?? 0);
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $nickname = trim($_POST['nickname'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';

        if ($id && $firstName && $lastName) {
            if ($newPassword) {
                $stmt = $db->prepare("UPDATE tutors SET first_name=?, last_name=?, nickname=?, phone=?, password=? WHERE id=?");
                $stmt->execute([$firstName, $lastName, $nickname ?: null, $phone ?: null, password_hash($newPassword, PASSWORD_DEFAULT), $id]);
            } else {
                $stmt = $db->prepare("UPDATE tutors SET first_name=?, last_name=?, nickname=?, phone=? WHERE id=?");
                $stmt->execute([$firstName, $lastName, $nickname ?: null, $phone ?: null, $id]);
            }
            setFlash('success', 'แก้ไขข้อมูลติวเตอร์เรียบร้อยแล้ว');
        }
    } elseif ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id) {
            $db->prepare("UPDATE tutors SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
            setFlash('success', 'เปลี่ยนสถานะเรียบร้อยแล้ว');
        }
    }

    redirect(BASE_URL . '/admin/tutors.php');
}

// Fetch tutors with session count
$stmt = $db->prepare("
    SELECT t.*,
        (SELECT COUNT(*) FROM teaching_records WHERE tutor_id = t.id AND MONTH(teaching_date) = ? AND YEAR(teaching_date) = ?) as month_sessions,
        (SELECT COUNT(*) FROM teaching_records WHERE tutor_id = t.id) as total_sessions
    FROM tutors t
    ORDER BY t.is_active DESC, t.first_name ASC
");
$stmt->execute([$currentMonth, $currentYear]);
$tutors = $stmt->fetchAll();

include __DIR__ . '/layout.php';
?>

<div class="max-w-5xl">
    <!-- Add Tutor Button -->
    <div class="mb-6">
        <button onclick="showAddModal()"
            class="px-6 py-3 bg-indigo-500 text-white font-medium rounded-xl hover:bg-indigo-600 transition-colors shadow-sm">
            <i class="fas fa-user-plus mr-2"></i> เพิ่มติวเตอร์ใหม่
        </button>
    </div>

    <!-- Tutors List -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-800"><i class="fas fa-users text-indigo-500 mr-2"></i>รายชื่อติวเตอร์</h3>
            <span class="text-sm text-slate-400">
                <?= count($tutors) ?> คน
            </span>
        </div>

        <?php if (empty($tutors)): ?>
            <div class="p-8 text-center text-slate-400">
                <i class="fas fa-users text-3xl mb-2"></i>
                <p>ยังไม่มีติวเตอร์</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-slate-500 font-medium">ติวเตอร์</th>
                            <th class="px-4 py-3 text-left text-slate-500 font-medium">Username</th>
                            <th class="px-4 py-3 text-left text-slate-500 font-medium">เบอร์โทร</th>
                            <th class="px-4 py-3 text-center text-slate-500 font-medium">เดือนนี้</th>
                            <th class="px-4 py-3 text-center text-slate-500 font-medium">ทั้งหมด</th>
                            <th class="px-4 py-3 text-center text-slate-500 font-medium">สถานะ</th>
                            <th class="px-4 py-3 text-center text-slate-500 font-medium">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($tutors as $t): ?>
                            <tr class="hover:bg-slate-50/50 <?= !$t['is_active'] ? 'opacity-50' : '' ?>">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold
                                    <?= $t['is_active'] ? 'bg-indigo-50 text-indigo-600' : 'bg-slate-100 text-slate-400' ?>">
                                            <?= mb_substr($t['first_name'], 0, 1) ?>
                                        </div>
                                        <div>
                                            <p class="font-medium text-slate-700">
                                                <?= sanitize($t['first_name'] . ' ' . $t['last_name']) ?>
                                            </p>
                                            <?php if ($t['nickname']): ?>
                                                <p class="text-xs text-slate-400">(
                                                    <?= sanitize($t['nickname']) ?>)
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-500 font-mono text-xs">
                                    <?= sanitize($t['username']) ?>
                                </td>
                                <td class="px-4 py-3 text-slate-500">
                                    <?= sanitize($t['phone'] ?: '-') ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span
                                        class="px-2 py-1 rounded-lg text-xs font-bold <?= $t['month_sessions'] > 0 ? 'bg-indigo-50 text-indigo-600' : 'text-slate-300' ?>">
                                        <?= $t['month_sessions'] ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center text-slate-500">
                                    <?= $t['total_sessions'] ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <form method="POST" class="inline"
                                        onsubmit="return confirm('<?= $t['is_active'] ? 'ปิดใช้งาน' : 'เปิดใช้งาน' ?>ติวเตอร์นี้?')">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                        <button type="submit"
                                            class="<?= $t['is_active'] ? 'text-emerald-500' : 'text-slate-300' ?> hover:opacity-70"
                                            title="<?= $t['is_active'] ? 'ปิดใช้งาน' : 'เปิดใช้งาน' ?>">
                                            <i
                                                class="fas <?= $t['is_active'] ? 'fa-toggle-on text-lg' : 'fa-toggle-off text-lg' ?>"></i>
                                        </button>
                                    </form>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button onclick='showEditModal(<?= json_encode($t) ?>)'
                                        class="p-2 text-slate-400 hover:text-indigo-500 transition-colors" title="แก้ไข">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Modal -->
<div id="addModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-800"><i class="fas fa-user-plus text-indigo-500 mr-2"></i>เพิ่มติวเตอร์ใหม่
            </h3>
            <button onclick="closeModal('addModal')" class="text-slate-400 hover:text-slate-600"><i
                    class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="add">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">ชื่อ <span
                            class="text-red-400">*</span></label>
                    <input type="text" name="first_name" required
                        class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-indigo-500 outline-none text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">นามสกุล <span
                            class="text-red-400">*</span></label>
                    <input type="text" name="last_name" required
                        class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-indigo-500 outline-none text-sm">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">ชื่อเล่น</label>
                    <input type="text" name="nickname"
                        class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-indigo-500 outline-none text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">เบอร์โทร</label>
                    <input type="text" name="phone"
                        class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-indigo-500 outline-none text-sm">
                </div>
            </div>
            <hr class="border-slate-100">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Username <span
                            class="text-red-400">*</span></label>
                    <input type="text" name="username" required
                        class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-indigo-500 outline-none text-sm font-mono">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Password <span
                            class="text-red-400">*</span></label>
                    <input type="text" name="password" required
                        class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-indigo-500 outline-none text-sm font-mono">
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeModal('addModal')"
                    class="px-5 py-2 bg-slate-100 text-slate-600 rounded-lg hover:bg-slate-200 transition-colors text-sm">ยกเลิก</button>
                <button type="submit"
                    class="px-5 py-2 bg-indigo-500 text-white rounded-lg hover:bg-indigo-600 transition-colors text-sm"><i
                        class="fas fa-save mr-1"></i> บันทึก</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-800"><i class="fas fa-user-edit text-indigo-500 mr-2"></i>แก้ไขติวเตอร์</h3>
            <button onclick="closeModal('editModal')" class="text-slate-400 hover:text-slate-600"><i
                    class="fas fa-times"></i></button>
        </div>
        <form method="POST" id="editForm" class="p-6 space-y-4">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">ชื่อ <span
                            class="text-red-400">*</span></label>
                    <input type="text" name="first_name" id="edit_first_name" required
                        class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-indigo-500 outline-none text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">นามสกุล <span
                            class="text-red-400">*</span></label>
                    <input type="text" name="last_name" id="edit_last_name" required
                        class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-indigo-500 outline-none text-sm">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">ชื่อเล่น</label>
                    <input type="text" name="nickname" id="edit_nickname"
                        class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-indigo-500 outline-none text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">เบอร์โทร</label>
                    <input type="text" name="phone" id="edit_phone"
                        class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-indigo-500 outline-none text-sm">
                </div>
            </div>
            <hr class="border-slate-100">
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">รหัสผ่านใหม่ <span
                        class="text-slate-400 font-normal">(เว้นว่างถ้าไม่เปลี่ยน)</span></label>
                <input type="text" name="new_password"
                    class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-indigo-500 outline-none text-sm font-mono"
                    placeholder="กรอกรหัสผ่านใหม่">
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeModal('editModal')"
                    class="px-5 py-2 bg-slate-100 text-slate-600 rounded-lg hover:bg-slate-200 transition-colors text-sm">ยกเลิก</button>
                <button type="submit"
                    class="px-5 py-2 bg-indigo-500 text-white rounded-lg hover:bg-indigo-600 transition-colors text-sm"><i
                        class="fas fa-save mr-1"></i> บันทึก</button>
            </div>
        </form>
    </div>
</div>

<script>
    function showAddModal() {
        const m = document.getElementById('addModal');
        m.classList.remove('hidden');
        m.classList.add('flex');
    }

    function showEditModal(tutor) {
        document.getElementById('edit_id').value = tutor.id;
        document.getElementById('edit_first_name').value = tutor.first_name;
        document.getElementById('edit_last_name').value = tutor.last_name;
        document.getElementById('edit_nickname').value = tutor.nickname || '';
        document.getElementById('edit_phone').value = tutor.phone || '';
        const m = document.getElementById('editModal');
        m.classList.remove('hidden');
        m.classList.add('flex');
    }

    function closeModal(id) {
        const m = document.getElementById(id);
        m.classList.add('hidden');
        m.classList.remove('flex');
    }

    // Close modal on outside click
    document.querySelectorAll('[id$="Modal"]').forEach(m => {
        m.addEventListener('click', function (e) {
            if (e.target === this) closeModal(this.id);
        });
    });
</script>

<?php include __DIR__ . '/layout_footer.php'; ?>