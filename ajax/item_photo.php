<?php
/**
 * ajax/item_photo.php
 * ------------------------------------------------------------------
 * يُخدِّم صورة الصنف المُخزَّنة كـ BLOB في قاعدة البيانات.
 * الاستخدام: ajax/item_photo.php?id=<item_id>
 * إذا لم تكن هناك صورة أو الصنف غير موجود، يُعيد صورة placeholder شفافة.
 * ------------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// يجب أن يكون المستخدم مسجّلاً
if (!is_logged_in()) {
    http_response_code(403);
    exit;
}

$id = (int) ($_GET['id'] ?? 0);

$photo    = null;
$mime     = null;

if ($id > 0) {
    $st = $pdo->prepare('SELECT photo, photo_mime FROM items WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row && $row['photo'] !== null && $row['photo_mime'] !== null) {
        $photo = $row['photo'];
        $mime  = $row['photo_mime'];
    }
}

if ($photo === null) {
    // إرجاع صورة placeholder SVG شفافة بدلاً من خطأ 404
    header('Content-Type: image/svg+xml');
    header('Cache-Control: public, max-age=60');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120">'
       . '<rect width="120" height="120" fill="#f0f4f8" rx="8"/>'
       . '<text x="60" y="68" font-size="40" text-anchor="middle" fill="#94a3b8">📦</text>'
       . '</svg>';
    exit;
}

// إرسال الصورة الحقيقية مع headers مناسبة للتخزين المؤقت
header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($photo));
header('Cache-Control: private, max-age=3600');
echo $photo;
