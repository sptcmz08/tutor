<?php
$pageTitle = 'เปลี่ยนรหัสผ่าน';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireTutorLogin();

$tutor = getCurrentTutor();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!$currentPassword) $errors[] = 'กรุณากรอกรหัสผ่านปัจจุบัน';
    if (!$newPassword) $errors[] = 'กรุณากรอกรหัสผ่านใหม่';
    if (strlen($newPassword) < 4) $errors[] = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 4 ตัวอักษร';
    if ($newPassword !== $confirmPassword) $errors[] = 'รหัสผ่านใหม่ไม่ตรงกัน';

    if (empty($errors)) {
        if (!password_verify($currentPassword, $tutor['password'])) {
            $errors[] = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
        } else {
            $db = getDB();
            $stmt = $db->prepare("UPDATE tutors SET password = ? WHERE id = ?");
            $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $tutor['id']]);
            $success = true;
        }
    }
}

include __DIR__ . '/layout.php';
?>

<div class="max-w-lg">
    <?php if ($success): ?>
        <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl flex items-center gap-2">
            <i class="fas fa-check-circle"></i>
            <span>เปลี่ยนรหัสผ่านเรียบร้อยแล้ว!</span>
        </div>
    <?php endif; ?>

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

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center">
                <i class="fas fa-key text-indigo-500 text-xl"></i>
            </div>
            <div>
                <h3 class="font-bold text-gray-800">เปลี่ยนรหัสผ่าน</h3>
                <p class="text-sm text-gray-400"><?= sanitize($tutor['first_name'] . ' ' . $tutor['last_name']) ?></p>
            </div>
        </div>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-2">รหัสผ่านปัจจุบัน <span class="text-red-400">*</span></label>
                <div class="relative">
                    <input type="password" name="current_password" required id="current_password"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all outline-none bg-gray-50/50"
                        placeholder="กรอกรหัสผ่านปัจจุบัน">
                    <button type="button" onclick="togglePw('current_password')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <hr class="border-gray-100">

            <div>
                <label class="block text-sm font-medium text-gray-600 mb-2">รหัสผ่านใหม่ <span class="text-red-400">*</span></label>
                <div class="relative">
                    <input type="password" name="new_password" required id="new_password"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all outline-none bg-gray-50/50"
                        placeholder="กรอกรหัสผ่านใหม่ (อย่างน้อย 4 ตัว)">
                    <button type="button" onclick="togglePw('new_password')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-600 mb-2">ยืนยันรหัสผ่านใหม่ <span class="text-red-400">*</span></label>
                <div class="relative">
                    <input type="password" name="confirm_password" required id="confirm_password"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all outline-none bg-gray-50/50"
                        placeholder="กรอกรหัสผ่านใหม่อีกครั้ง">
                    <button type="button" onclick="togglePw('confirm_password')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <a href="<?= BASE_URL ?>/tutor/dashboard.php"
                    class="px-5 py-3 bg-gray-100 text-gray-700 font-medium rounded-xl hover:bg-gray-200 transition-colors">
                    ยกเลิก
                </a>
                <button type="submit"
                    class="flex-1 py-3 bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl hover:from-indigo-600 hover:to-purple-700 transform hover:-translate-y-0.5 transition-all duration-300">
                    <i class="fas fa-save mr-2"></i> เปลี่ยนรหัสผ่าน
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function togglePw(id) {
    const input = document.getElementById(id);
    const icon = input.nextElementSibling.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
</script>

<?php include __DIR__ . '/layout_footer.php'; ?>
