<?php
/**
 * pages/settings.php — تغيير اسم المستخدم وكلمة المرور.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $current  = $_POST['current_password'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $new      = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // جلب المستخدم الحالي للتحقق من كلمة المرور الحالية
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($current, $user['password_hash'])) {
        set_flash('error', 'كلمة المرور الحالية غير صحيحة.');
    } elseif ($username === '') {
        set_flash('error', 'اسم المستخدم مطلوب.');
    } elseif ($new !== '' && $new !== $confirm) {
        set_flash('error', 'كلمة المرور الجديدة وتأكيدها غير متطابقين.');
    } elseif ($new !== '' && mb_strlen($new) < 4) {
        set_flash('error', 'كلمة المرور الجديدة قصيرة جداً (٤ أحرف على الأقل).');
    } else {
        if ($new !== '') {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $u = $pdo->prepare('UPDATE users SET username=?, password_hash=? WHERE id=?');
            $u->execute([$username, $hash, $user['id']]);
        } else {
            $u = $pdo->prepare('UPDATE users SET username=? WHERE id=?');
            $u->execute([$username, $user['id']]);
        }
        $_SESSION['username'] = $username;
        set_flash('success', 'تم حفظ الإعدادات بنجاح.');
    }
    redirect('index.php?page=settings');
}

$page_title = 'الإعدادات';
$active = 'settings';
require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="h3 fw-bold mb-4">⚙️ الإعدادات</h1>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">🔐 بيانات الدخول</div>
            <div class="card-body">
                <form method="post" autocomplete="off">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold">اسم المستخدم</label>
                        <input type="text" name="username" class="form-control form-control-lg"
                               value="<?= e(current_username()) ?>" required>
                    </div>
                    <hr>
                    <div class="mb-3">
                        <label class="form-label fw-bold">كلمة المرور الحالية <span class="text-danger">*</span></label>
                        <input type="password" name="current_password" class="form-control"
                               placeholder="للتأكيد قبل الحفظ" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">كلمة المرور الجديدة</label>
                        <input type="password" name="new_password" class="form-control"
                               placeholder="اتركها فارغة إن لم ترغب بتغييرها">
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">تأكيد كلمة المرور الجديدة</label>
                        <input type="password" name="confirm_password" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100">حفظ الإعدادات</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
