<?php
/**
 * login.php
 * ------------------------------------------------------------------
 * صفحة تسجيل الدخول (مستقلة عن التخطيط العام).
 * تتحقق من اسم المستخدم وكلمة المرور المُخزّنة بشكل مُشفّر (password_hash).
 * ------------------------------------------------------------------
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// إن كان مسجّلاً بالفعل انتقل للرئيسية
if (is_logged_in()) {
    redirect('index.php?page=dashboard');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'يُرجى إدخال اسم المستخدم وكلمة المرور.';
    } else {
        $stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // نجاح — تجديد معرّف الجلسة لمنع تثبيت الجلسة
            session_regenerate_id(true);
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            redirect('index.php?page=dashboard');
        } else {
            $error = 'اسم المستخدم أو كلمة المرور غير صحيحة.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول — <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="login-wrapper">
    <div class="login-card">
        <div class="text-center mb-4">
            <div style="font-size:3rem">🧾</div>
            <h1 class="h4 fw-bold mt-2"><?= e(APP_NAME) ?></h1>
            <p class="text-muted small mb-0">سجّل الدخول للمتابعة</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label fw-bold">اسم المستخدم</label>
                <input type="text" name="username" class="form-control form-control-lg"
                       placeholder="admin" required autofocus>
            </div>
            <div class="mb-4">
                <label class="form-label fw-bold">كلمة المرور</label>
                <input type="password" name="password" class="form-control form-control-lg"
                       placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">دخول 🔓</button>
        </form>

        <p class="text-center text-muted small mt-4 mb-0">
            الحساب الافتراضي: <b>admin</b> / <b>admin123</b>
        </p>
    </div>
</div>
</body>
</html>
