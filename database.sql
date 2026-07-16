-- ==================================================================
--  database.sql — متتبّع أسعار المشتريات
-- ------------------------------------------------------------------
--  ملف جاهز للاستيراد عبر phpMyAdmin بضغطة واحدة (بدون أي خطوات يدوية).
--  ينشئ قاعدة البيانات + كل الجداول والعلاقات + المستخدم الافتراضي.
--
--  المستخدم الافتراضي:  admin  /  admin123
-- ==================================================================

-- إنشاء قاعدة البيانات واستخدامها
CREATE DATABASE IF NOT EXISTS `purchase_tracker`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `purchase_tracker`;

-- حذف الجداول القديمة (إن وُجدت) بترتيب يراعي المفاتيح الأجنبية
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `items`;
DROP TABLE IF EXISTS `vendors`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;

-- ------------------------------------------------------------------
-- جدول المستخدمين (مستخدم واحد لتسجيل الدخول)
-- ------------------------------------------------------------------
CREATE TABLE `users` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username`      VARCHAR(50)  NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- جدول المجموعات (تصنيفات الأصناف)
-- ------------------------------------------------------------------
CREATE TABLE `categories` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(150) NOT NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- جدول الأصناف (غير مرتبطة بمورد — تُشترى من أي مورد)
-- ------------------------------------------------------------------
CREATE TABLE `items` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(200) NOT NULL,
    `specs`       TEXT         NULL,
    `unit`        VARCHAR(50)  NOT NULL DEFAULT '',
    `category_id` INT UNSIGNED NULL,
    `photo`       MEDIUMBLOB   NULL,
    `photo_mime`  VARCHAR(20)  NULL,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_items_category` (`category_id`),
    KEY `idx_items_name` (`name`),
    CONSTRAINT `fk_items_category`
        FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- جدول الموردين
-- ------------------------------------------------------------------
CREATE TABLE `vendors` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(200) NOT NULL,
    `phone`      VARCHAR(50)  NULL,
    `address`    VARCHAR(255) NULL,
    `notes`      TEXT         NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_vendors_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- جدول الأوردرات (رأس الفاتورة)
--   order_date قابل للتعديل لإدخال أوردرات قديمة.
--   usd_rate = سعر صرف الدولار يوم الأوردر (للاطلاع فقط).
-- ------------------------------------------------------------------
CREATE TABLE `orders` (
    `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `vendor_id`         INT UNSIGNED  NOT NULL,
    `order_date`        DATE          NOT NULL,
    `usd_rate`          DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
    `notes`             TEXT          NULL,
    `custom_order_number` VARCHAR(50) NULL,
    `attention`         VARCHAR(150)  NULL,
    `delivery_period`   VARCHAR(100)  NULL DEFAULT '10 أيام',
    `payment_terms`     VARCHAR(255)  NULL DEFAULT 'شيك اجل بعد الفحص ومطابقة الاصناف للمواصفات',
    `delivery_location` VARCHAR(255)  NULL,
    `created_at`        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_orders_vendor` (`vendor_id`),
    KEY `idx_orders_date` (`order_date`),
    CONSTRAINT `fk_orders_vendor`
        FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- جدول سطور الأوردر (تفاصيل الأصناف)
--   line_total عمود محسوب تلقائياً = quantity × unit_price_egp
-- ------------------------------------------------------------------
CREATE TABLE `order_items` (
    `id`             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `order_id`       INT UNSIGNED   NOT NULL,
    `item_id`        INT UNSIGNED   NOT NULL,
    `quantity`       DECIMAL(12,3)  NOT NULL DEFAULT 0.000,
    `unit_price_egp` DECIMAL(14,2)  NOT NULL DEFAULT 0.00,
    `line_total`     DECIMAL(18,2)  AS (`quantity` * `unit_price_egp`) STORED,
    `notes`          VARCHAR(255)   NULL,
    PRIMARY KEY (`id`),
    KEY `idx_oi_order` (`order_id`),
    KEY `idx_oi_item` (`item_id`),
    CONSTRAINT `fk_oi_order`
        FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_oi_item`
        FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================================================================
--  البيانات الأولية
-- ==================================================================

-- المستخدم الافتراضي: admin / admin123
-- (كلمة المرور مُشفّرة بـ bcrypt المتوافقة مع password_hash في PHP)
INSERT INTO `users` (`username`, `password_hash`) VALUES
    ('admin', '$2y$10$R5um7jOEhnfxg6YQB/AZ0ub346ut0g05ofEr8tXKOYdAxVAqWn5A6');

-- بعض المجموعات الجاهزة للاستخدام مباشرةً (يمكن تعديلها أو حذفها)
INSERT INTO `categories` (`name`) VALUES
    ('مواد غذائية'),
    ('مشروبات'),
    ('أدوات نظافة'),
    ('خضروات وفاكهة'),
    ('لحوم ودواجن'),
    ('مستلزمات مكتبية'),
    ('أخرى');

-- ==================================================================
--  نهاية الملف
-- ==================================================================
