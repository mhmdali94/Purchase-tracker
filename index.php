<?php
/**
 * index.php
 * ------------------------------------------------------------------
 * الموجّه الرئيسي (Front Controller).
 * يحمّل الإعدادات والاتصال، يفرض تسجيل الدخول، ثم يستدعي صفحة المحتوى
 * المطلوبة من مجلد /pages بناءً على المتغيّر ?page= بعد التحقق من صحته.
 * كل صفحة مسؤولة عن استدعاء الرأس والتذييل بنفسها.
 * ------------------------------------------------------------------
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

// الصفحات المسموح بها فقط (قائمة بيضاء لمنع تضمين ملفات خارجية)
$allowed = [
    'dashboard', 'categories', 'items', 'vendors',
    'order_new', 'orders', 'order_view',
    'item_history', 'reports', 'settings', 'order_print',
];

$page = $_GET['page'] ?? 'dashboard';
if (!in_array($page, $allowed, true)) {
    $page = 'dashboard';
}

$file = __DIR__ . '/pages/' . $page . '.php';
if (!is_file($file)) {
    $file = __DIR__ . '/pages/dashboard.php';
}

require $file;
