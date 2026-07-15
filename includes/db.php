<?php
/**
 * includes/db.php
 * ------------------------------------------------------------------
 * إنشاء اتصال PDO بقاعدة البيانات مع تفعيل الاستعلامات المُجهّزة
 * (Prepared Statements) للحماية من حقن SQL.
 * يُتاح الاتصال عبر المتغيّر $pdo في كل الصفحات.
 * ------------------------------------------------------------------
 */

require_once __DIR__ . '/../config.php';

$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // إظهار الأخطاء كاستثناءات
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // إرجاع الصفوف كمصفوفات ترابطية
    PDO::ATTR_EMULATE_PREPARES   => false,                    // استخدام استعلامات مُجهّزة حقيقية
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // رسالة واضحة بالعربية في حال فشل الاتصال
    http_response_code(500);
    echo '<div style="font-family:Tahoma,Arial;direction:rtl;text-align:center;'
       . 'margin:40px auto;max-width:600px;background:#fff3cd;border:1px solid #ffc107;'
       . 'padding:20px;border-radius:10px;color:#664d03">'
       . '<h3>تعذّر الاتصال بقاعدة البيانات</h3>'
       . '<p>تأكّد من تشغيل خدمتي Apache وMySQL من لوحة تحكم XAMPP، '
       . 'وأنك استوردت الملف <b>database.sql</b> عبر phpMyAdmin.</p>'
       . '<p style="color:#842029;font-size:13px">تفاصيل الخطأ: '
       . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>'
       . '</div>';
    exit;
}
