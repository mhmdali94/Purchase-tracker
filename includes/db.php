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

    // ---- الهجرات التلقائية (Auto-migrations) ----
    try {
        // تحقق من وجود عمود 'attention' في جدول 'orders'
        $colCheck = $pdo->query("SHOW COLUMNS FROM `orders` LIKE 'attention'")->fetch();
        if (!$colCheck) {
            $pdo->exec("ALTER TABLE `orders` 
                ADD COLUMN `attention` VARCHAR(150) NULL AFTER `notes`,
                ADD COLUMN `delivery_period` VARCHAR(100) NULL DEFAULT '10 أيام' AFTER `attention`,
                ADD COLUMN `payment_terms` VARCHAR(255) NULL DEFAULT 'شيك اجل بعد الفحص ومطابقة الاصناف للمواصفات' AFTER `delivery_period`,
                ADD COLUMN `delivery_location` VARCHAR(255) NULL AFTER `payment_terms`"
            );
        }

        // تحقق من وجود عمود 'notes' في جدول 'order_items'
        $colCheck2 = $pdo->query("SHOW COLUMNS FROM `order_items` LIKE 'notes'")->fetch();
        if (!$colCheck2) {
            $pdo->exec("ALTER TABLE `order_items` ADD COLUMN `notes` VARCHAR(255) NULL");
        }

        // تحقق من وجود عمود 'custom_order_number' في جدول 'orders'
        $colCheck3 = $pdo->query("SHOW COLUMNS FROM `orders` LIKE 'custom_order_number'")->fetch();
        if (!$colCheck3) {
            $pdo->exec("ALTER TABLE `orders` ADD COLUMN `custom_order_number` VARCHAR(50) NULL AFTER `notes`");
        }

        // تحقق من وجود عمودي 'photo' و 'photo_mime' في جدول 'items' (صورة مرجعية للصنف)
        $colCheck4 = $pdo->query("SHOW COLUMNS FROM `items` LIKE 'photo'")->fetch();
        if (!$colCheck4) {
            $pdo->exec("ALTER TABLE `items`
                ADD COLUMN `photo` MEDIUMBLOB NULL,
                ADD COLUMN `photo_mime` VARCHAR(20) NULL"
            );
        }
    } catch (PDOException $ex) {
        // تجاهل أخطاء الهجرة إن حدثت لكي لا ينهار التطبيق
    }
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
