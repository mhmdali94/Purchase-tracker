<?php
/**
 * ajax/add_item.php
 * ------------------------------------------------------------------
 * نقطة نهاية AJAX لإضافة صنف جديد من داخل شاشة الأوردر دون مغادرتها.
 * ترجع JSON: { ok: bool, message?: string, item?: {...} }
 * ------------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

// يجب أن يكون المستخدم مسجّلاً
if (!is_logged_in()) {
    echo json_encode(['ok' => false, 'message' => 'انتهت الجلسة. سجّل الدخول مجدداً.']);
    exit;
}

// التحقق من رمز CSRF
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    echo json_encode(['ok' => false, 'message' => 'رمز الحماية غير صحيح.']);
    exit;
}

$name        = trim($_POST['name'] ?? '');
$specs       = trim($_POST['specs'] ?? '');
$unit        = trim($_POST['unit'] ?? '');
$category_id = (int) ($_POST['category_id'] ?? 0) ?: null;

if ($name === '') {
    echo json_encode(['ok' => false, 'message' => 'اسم الصنف مطلوب.']);
    exit;
}

try {
    $stmt = $pdo->prepare('INSERT INTO items (name, specs, unit, category_id) VALUES (?,?,?,?)');
    $stmt->execute([$name, $specs, $unit, $category_id]);
    $id = (int) $pdo->lastInsertId();

    // النص المعروض في قائمة البحث
    $label = $name . ($specs !== '' ? ' — ' . $specs : '') . ($unit !== '' ? ' (' . $unit . ')' : '');

    echo json_encode([
        'ok'   => true,
        'item' => ['id' => $id, 'name' => $name, 'specs' => $specs, 'unit' => $unit, 'label' => $label],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'message' => 'تعذّر حفظ الصنف.']);
}
