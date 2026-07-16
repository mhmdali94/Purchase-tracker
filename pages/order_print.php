<?php
/**
 * pages/order_print.php — نسخة الطباعة الرسمية لأمر التوريد (أمر توريد).
 * بتصميم مطابق لنموذج شركة سينا لمستحضرات التجميل.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);

// قراءة بيانات الأوردر
$stmt = $pdo->prepare(
    'SELECT o.*, v.name AS vendor_name, v.phone AS vendor_phone, v.address AS vendor_address
     FROM orders o 
     JOIN vendors v ON v.id = o.vendor_id
     WHERE o.id = ?'
);
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    die('الأوردر غير موجود.');
}

// قراءة سطور الأوردر مع تفاصيل الصنف (بما في ذلك ما إذا كان للصنف صورة)
$ls = $pdo->prepare(
    'SELECT oi.*, i.name AS item_name, i.specs, i.unit, (i.photo IS NOT NULL) AS has_photo
     FROM order_items oi 
     JOIN items i ON i.id = oi.item_id
     WHERE oi.order_id = ? ORDER BY oi.id'
);
$ls->execute([$id]);
$lines = $ls->fetchAll();
$total = array_sum(array_column($lines, 'line_total'));
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>أمر توريد رقم <?= $order['custom_order_number'] !== '' && $order['custom_order_number'] !== null ? e($order['custom_order_number']) : (int)$order['id'] ?></title>
    <!-- خط القاهرة العربي الرسمي -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            font-family: 'Cairo', Tahoma, Arial, sans-serif;
        }
        body {
            background-color: #fff;
            color: #000;
            margin: 0;
            padding: 20px;
            direction: rtl;
        }
        
        /* ورقة الطباعة A4 */
        .print-page {
            max-width: 900px;
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 30px;
            position: relative;
            background-color: #fff;
        }

        /* زر الطباعة العلوي على الشاشة فقط */
        .print-actions {
            max-width: 900px;
            margin: 10px auto;
            text-align: left;
        }
        .btn-print {
            background-color: #16a34a;
            color: #fff;
            border: none;
            padding: 10px 20px;
            font-size: 16px;
            font-weight: bold;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-print:hover {
            background-color: #15803d;
        }

        /* الهيدر العلوي */
        .print-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
        }
        
        /* لوجو سينا التجميلية */
        .sina-logo {
            display: flex;
            align-items: center;
        }
        .sina-logo-img {
            width: 90px;
            height: 90px;
            object-fit: contain;
        }

        /* البيانات على اليمين */
        .header-meta {
            text-align: right;
            font-size: 13px;
            line-height: 1.6;
        }
        .header-meta h1 {
            font-size: 16px;
            margin: 0 0 5px 0;
            font-weight: 700;
        }
        .header-meta p {
            margin: 0;
        }

        /* رقم أمر التوريد والتاريخ */
        .po-title-box {
            text-align: center;
            margin: 20px 0;
        }
        .po-title {
            background-color: #facc15; /* أصفر فاقع كما في الصورة */
            color: #000;
            font-size: 20px;
            font-weight: 700;
            padding: 8px 30px;
            border: 2px solid #000;
            border-radius: 6px;
            display: inline-block;
        }
        .po-date-vendor {
            display: flex;
            justify-content: space-between;
            margin: 15px 0;
            font-size: 14px;
            font-weight: 600;
        }

        /* جدول البنود */
        .po-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            border: 2px solid #000;
        }
        .po-table th, .po-table td {
            border: 1px solid #000;
            padding: 8px 10px;
            text-align: center;
            font-size: 13px;
        }
        .po-table th {
            background-color: #f8fafc;
            font-weight: 700;
        }
        
        /* تقسيم عمود الكمية */
        .sub-header-row th {
            font-size: 12px;
            padding: 4px;
            background-color: #f1f5f9;
        }
        .po-table td.item-name-cell {
            text-align: right;
            font-weight: 600;
        }
        .po-table td.notes-cell {
            text-align: right;
            font-size: 12px;
            color: #334155;
        }

        /* تفاصيل الدفع والتوريد */
        .po-terms-section {
            font-size: 14px;
            line-height: 1.8;
            margin-top: 15px;
            font-weight: 600;
        }
        .po-terms-row {
            display: flex;
            margin-bottom: 5px;
        }
        .po-terms-label {
            min-width: 120px;
            color: #000;
        }
        .po-terms-value {
            border-bottom: 1px dashed #000;
            flex-grow: 1;
            padding-right: 10px;
        }

        /* التوقيعات */
        .signatures-section {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            padding: 10px 0;
            font-size: 14px;
            font-weight: 600;
        }
        .sig-box {
            width: 30%;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .sig-line {
            border-bottom: 1px dashed #000;
            height: 25px;
            margin-top: 5px;
        }

        /* معرض صور الأصناف للمرجعية */
        .item-images-gallery {
            margin-top: 30px;
            border-top: 1px dashed #000;
            padding-top: 20px;
        }
        .gallery-title {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #334155;
        }
        .gallery-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        .gallery-item {
            border: 1px solid #ccc;
            border-radius: 6px;
            padding: 5px;
            width: 140px;
            text-align: center;
            background-color: #fafafa;
        }
        .gallery-img {
            width: 128px;
            height: 128px;
            object-fit: contain;
            border-radius: 4px;
            background-color: #fff;
        }
        .gallery-label {
            font-size: 11px;
            font-weight: 600;
            margin-top: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* فوتر الصفحة السفلي للطباعة */
        .print-footer {
            margin-top: 40px;
            border-top: 2px solid #000;
            padding-top: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
            font-weight: 700;
            background-color: #facc15; /* شريط سفلي أصفر كما في الصورة */
            padding: 8px 15px;
            border: 2px solid #000;
        }
        .print-footer div {
            color: #000;
        }

        /* إخفاء شاشة عند الطباعة وتنسيق الورقة لتناسب صفحة واحدة A4 */
        @media print {
            @page {
                size: A4;
                margin: 8mm 12mm; /* تقليل هوامش الصفحة الخارجية */
            }
            body {
                padding: 0;
                font-size: 11.5px;
            }
            .print-actions {
                display: none;
            }
            .print-page {
                border: none;
                padding: 0;
                width: 100%;
                max-width: 100%;
            }
            .print-header {
                margin-bottom: 10px;
                padding-bottom: 5px;
            }
            .sina-logo-img {
                width: 65px;
                height: 65px;
            }
            .po-title-box {
                margin: 10px 0;
            }
            .po-title {
                font-size: 16px;
                padding: 4px 20px;
            }
            .po-date-vendor {
                margin: 10px 0;
                font-size: 12px;
            }
            .po-table {
                margin-bottom: 10px;
            }
            .po-table th, .po-table td {
                padding: 4px 6px;
                font-size: 11px;
            }
            .po-terms-section {
                font-size: 12px;
                margin-top: 10px;
            }
            .signatures-section {
                margin-top: 15px;
                padding: 5px 0;
                font-size: 12px;
            }
            .sig-line {
                height: 18px;
            }
            .item-images-gallery {
                margin-top: 15px;
                padding-top: 10px;
            }
            .gallery-grid {
                gap: 8px;
            }
            .gallery-item {
                width: 90px;
                padding: 3px;
            }
            .gallery-img {
                width: 80px;
                height: 60px; /* جعل عينات الصور أصغر ومضغوطة أفقياً لتوفر مساحة */
            }
            .gallery-label {
                font-size: 9px;
            }
            .print-footer {
                margin-top: 15px;
                padding: 4px 10px;
                font-size: 9px;
            }
        }
    </style>
</head>
<body>

    <!-- أزرار التحكم -->
    <div class="print-actions" style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap; margin-bottom: 20px;">
        <a href="index.php?page=order_view&id=<?= (int)$order['id'] ?>" class="btn-print" style="background-color: #64748b;">↩️ رجوع للتفاصيل</a>
        <button onclick="window.print()" class="btn-print">🖨️ طباعة أو حفظ كـ PDF</button>
        <span style="font-size: 13px; color: #475569; font-weight: 600;">💡 لحفظ الملف كـ PDF: اختر <b>"حفظ بتنسيق PDF" (Save as PDF)</b> من خانة الوجهة (Destination) في نافذة الطباعة.</span>
    </div>

    <!-- حاوية الصفحة -->
    <div class="print-page">
        
        <!-- الهيدر العلوي -->
        <div class="print-header">
            <!-- اليمين: معلومات الشركة -->
            <div class="header-meta">
                <h1>شركة سينا لمستحضرات التجميل</h1>
                <p>اجراءات المشتريات وتقييم الموردين</p>
                <p>رقم ..-09-PEP</p>
            </div>
            
            <!-- اليسار: اللوجو الرسمي لشركة سينا -->
            <div class="sina-logo">
                <img src="../assets/images/sina-logo.jpg"
                     alt="Sina Cosmetics Industry Experts"
                     class="sina-logo-img">
            </div>
        </div>

        <!-- رقم أمر التوريد -->
        <div class="po-title-box">
            <div class="po-title">أمر توريد رقم ( <?= $order['custom_order_number'] !== '' && $order['custom_order_number'] !== null ? e($order['custom_order_number']) : (int)$order['id'] ?> )</div>
        </div>

        <!-- تاريخ وتفاصيل المورد -->
        <div class="po-date-vendor">
            <div>التاريخ: <?= fmt_date($order['order_date']) ?></div>
            <div>السادة: <?= e($order['vendor_name']) ?></div>
            <?php if (!empty($order['attention'])): ?>
                <div>عناية / <?= e($order['attention']) ?></div>
            <?php endif; ?>
        </div>
        
        <p style="margin: 0 0 15px 0; font-size: 14px; font-weight: 600;">الرجاء القيام بتوريد الآتي:</p>

        <!-- جدول البنود -->
        <table class="po-table">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 40px;">م</th>
                    <th rowspan="2">اسم ومواصفات الصنف</th>
                    <th colspan="2">الكمية</th>
                    <th rowspan="2">سعر الوحدة</th>
                    <th rowspan="2">الإجمالي (ج.م)</th>
                    <th rowspan="2" style="width: 200px;">ملاحظات</th>
                </tr>
                <tr class="sub-header-row">
                    <th style="width: 80px;">الوحدة</th>
                    <th style="width: 80px;">عدد</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($lines as $ln): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td class="item-name-cell">
                            <?= e($ln['item_name']) ?>
                            <?php if ($ln['specs']): ?>
                                <span style="font-size: 11px; font-weight: normal; color: #4b5563; display: block;">
                                    <?= e($ln['specs']) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($ln['unit'] ? $ln['unit'] : 'يوجد') ?></td>
                        <td><?= fmt_qty($ln['quantity']) ?></td>
                        <td><?= number_format($ln['unit_price_egp'], 2) ?></td>
                        <td><?= number_format($ln['line_total'], 2) ?></td>
                        <td class="notes-cell"><?= e($ln['notes'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                
                <!-- صف الإجمالي -->
                <tr style="font-weight: 700;">
                    <td colspan="2">الإجـــــــــمـــــــــالـــــــــي</td>
                    <td colspan="3"></td>
                    <td><?= number_format($total, 2) ?></td>
                    <td></td>
                </tr>
            </tbody>
        </table>

        <!-- شروط التوريد والسداد -->
        <div class="po-terms-section">
            <div class="po-terms-row">
                <span class="po-terms-label">على أن يتم التوريد خلال:</span>
                <span class="po-terms-value"><?= e($order['delivery_period'] ? $order['delivery_period'] : '10 أيام') ?></span>
            </div>
            <div class="po-terms-row">
                <span class="po-terms-label">شروط السداد:</span>
                <span class="po-terms-value"><?= e($order['payment_terms'] ? $order['payment_terms'] : 'شيك اجل بعد الفحص ومطابقة الاصناف للمواصفات') ?></span>
            </div>
            <div class="po-terms-row">
                <span class="po-terms-label">مكان التسليم:</span>
                <span class="po-terms-value"><?= $order['delivery_location'] ? e($order['delivery_location']) : 'المصنع' ?></span>
            </div>
        </div>

        <!-- التوقيعات -->
        <div class="signatures-section">
            <div class="sig-box">
                <span>يعتمد:</span>
                <div class="sig-line"></div>
            </div>
            <div class="sig-box">
                <span>الاسم:</span>
                <div class="sig-line"></div>
            </div>
            <div class="sig-box">
                <span>التوقيع:</span>
                <div class="sig-line"></div>
            </div>
        </div>

        <!-- معرض صور مرجعية للأصناف (إذا كانت موجودة) -->
        <?php 
        $has_any_photos = false;
        foreach ($lines as $ln) {
            if ($ln['has_photo']) {
                $has_any_photos = true;
                break;
            }
        }
        if ($has_any_photos):
        ?>
            <div class="item-images-gallery">
                <div class="gallery-title">📷 صور مرجعية للأصناف المطلوبة بالأوردر:</div>
                <div class="gallery-grid">
                    <?php foreach ($lines as $ln): ?>
                        <?php if ($ln['has_photo']): ?>
                            <div class="gallery-item">
                                <img src="ajax/item_photo.php?id=<?= (int)$ln['item_id'] ?>" 
                                     alt="<?= e($ln['item_name']) ?>" class="gallery-img">
                                <div class="gallery-label" title="<?= e($ln['item_name']) ?>">
                                    <?= e($ln['item_name']) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- الفوتر السفلي للطباعة -->
        <div class="print-footer">
            <div>الاصدار الاول</div>
            <div>تاريخ الاصدار: 1 - 6 - 2010</div>
            <div>صفحه رقم: 1 / 1</div>
            <div>PEF-09-02</div>
        </div>

    </div>

    <script>
        // إطلاق حوار الطباعة بمجرد تحميل الصفحة تلقائياً لراحة المستخدم
        window.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                window.print();
            }, 500);
        });
    </script>
</body>
</html>
