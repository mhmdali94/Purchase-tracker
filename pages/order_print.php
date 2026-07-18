<?php
/**
 * pages/order_print.php — نسخة الطباعة الرسمية لأمر التوريد.
 * بتصميم مطابق لنموذج شركة سينا لمستحضرات التجميل (نموذج PEP09 / PEF-09-02).
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);

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

$ls = $pdo->prepare(
    'SELECT oi.*, i.name AS item_name, i.specs, i.unit, (i.photo IS NOT NULL) AS has_photo
     FROM order_items oi
     JOIN items i ON i.id = oi.item_id
     WHERE oi.order_id = ? ORDER BY oi.id'
);
$ls->execute([$id]);
$lines = $ls->fetchAll();
$total = array_sum(array_column($lines, 'line_total'));

$po_number = ($order['custom_order_number'] !== '' && $order['custom_order_number'] !== null)
    ? $order['custom_order_number']
    : (string) (int) $order['id'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>أمر توريد رقم <?= e($po_number) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Cairo', Tahoma, Arial, sans-serif; }

        :root {
            --tbl-fs: 13px;
            --tbl-py: 7px;
            --tbl-px: 8px;
        }

        body {
            background-color: #eef2f7;
            color: #000;
            margin: 0;
            padding: 20px;
            direction: rtl;
        }

        /* ورقة A4 على الشاشة */
        .print-page {
            max-width: 794px;
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 26px;
            background-color: #fff;
        }

        /* أزرار التحكم */
        .print-actions {
            max-width: 794px;
            margin: 10px auto 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn-action {
            color: #fff;
            border: none;
            padding: 10px 20px;
            font-size: 15px;
            font-weight: 700;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-family: 'Cairo', Tahoma, Arial, sans-serif;
            transition: opacity 0.15s;
        }
        .btn-action:hover { opacity: 0.88; }
        .btn-back      { background-color: #64748b; }
        .btn-print-btn { background-color: #16a34a; }
        .btn-pdf       { background-color: #2563eb; }

        /* الهيدر */
        .print-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 14px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .sina-logo { display: flex; align-items: center; }
        .sina-logo-img { width: 72px; height: 72px; object-fit: contain; }

        .header-meta { text-align: right; font-size: 12px; line-height: 1.55; }
        .header-meta h1 { font-size: 15px; margin: 0 0 3px 0; font-weight: 700; }
        .header-meta p  { margin: 0; }
        .header-meta p.doc-code { font-weight: 700; }

        /* رقم الأمر والتاريخ */
        .po-title-date-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin: 14px 0;
        }
        .po-title {
            background-color: #facc15;
            color: #000;
            font-size: 17px;
            font-weight: 700;
            padding: 6px 22px;
            border: 2px solid #000;
            border-radius: 6px;
            display: inline-block;
        }
        .po-date-text { font-size: 13px; font-weight: 600; }

        /* بيانات المورد */
        .po-vendor-block { margin: 0 0 12px 0; font-size: 13px; font-weight: 600; line-height: 1.7; }
        .po-lead-text    { margin: 0 0 10px 0; font-size: 13px; font-weight: 600; }

        /* تمرير أفقي للجدول على الجوال فقط */
        @media screen {
            .po-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .po-table      { min-width: 560px; }
        }

        /* جدول البنود */
        .po-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; border: 2px solid #000; }
        .po-table th, .po-table td {
            border: 1px solid #000;
            padding: var(--tbl-py) var(--tbl-px);
            text-align: center;
            font-size: var(--tbl-fs);
        }
        .po-table th { background-color: #f8fafc; font-weight: 700; }
        .po-table td.item-name-cell  { text-align: right; font-weight: 600; }
        .po-table td.notes-cell      { text-align: right; font-size: calc(var(--tbl-fs) - 1px); color: #334155; }
        .po-table td.price-flag      { color: #dc2626; font-weight: 700; }

        /* منع تقطيع الصفوف */
        .po-table tr   { page-break-inside: avoid; break-inside: avoid; }
        .po-table thead { display: table-header-group; }
        .po-terms-section, .signatures-section, .print-footer {
            page-break-inside: avoid; break-inside: avoid;
        }

        /* شروط التوريد */
        .po-terms-section { font-size: 13px; line-height: 1.8; margin-top: 10px; font-weight: 600; }
        .po-terms-row-split { display: flex; justify-content: space-between; gap: 16px; margin-bottom: 6px; flex-wrap: wrap; }
        .po-terms-row { margin-bottom: 5px; }

        /* التوقيعات */
        .signatures-section {
            margin-top: 18px; padding-top: 8px;
            font-size: 13px; font-weight: 600;
            display: flex; justify-content: space-between; gap: 16px;
        }
        .sig-row   { display: flex; align-items: center; gap: 8px; flex: 1 1 0; min-width: 0; }
        .sig-label { flex-shrink: 0; white-space: nowrap; }
        .sig-line  { flex-grow: 1; border-bottom: 1px dashed #000; height: 16px; min-width: 30px; }

        /* معرض الصور */
        .item-images-gallery { margin-top: 16px; border-top: 1px dashed #000; padding-top: 12px; }
        .gallery-title { font-size: 12px; font-weight: 700; margin-bottom: 8px; color: #334155; }
        .gallery-grid  { display: flex; flex-wrap: wrap; gap: 8px; }
        .gallery-item  {
            border: 1px solid #ccc; border-radius: 6px;
            padding: 4px; width: 78px;
            text-align: center; background-color: #fafafa;
            flex-shrink: 0;
        }
        .gallery-img   { width: 70px; height: 52px; object-fit: contain; border-radius: 4px; background-color: #fff; }
        .gallery-label {
            font-size: 9px; font-weight: 600; margin-top: 3px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        /* فوتر */
        .print-footer {
            margin-top: 16px; border-top: 2px solid #000;
            display: flex; justify-content: space-between; align-items: center;
            font-size: 10px; font-weight: 700;
            background-color: #facc15; padding: 6px 12px; border: 2px solid #000;
        }
        .print-footer div { color: #000; }

        /* وضع print-fit (يُفعَّل قبل الطباعة لتصغير الخط تلقائياً) */
        body.print-fit {
            --tbl-fs: 11px;
            --tbl-py: 4px;
            --tbl-px: 6px;
        }
        body.print-fit .print-actions { display: none; }
        body.print-fit .print-page    { border: none; }
        body.print-fit .sina-logo-img { width: 58px; height: 58px; }
        body.print-fit .po-title      { font-size: 15px; padding: 5px 18px; }
        body.print-fit .item-images-gallery { margin-top: 10px; padding-top: 8px; }
        body.print-fit .gallery-item  { width: 62px; padding: 3px; }
        body.print-fit .gallery-img   { width: 54px; height: 40px; }

        /* @media print — يصحح قطع الجانب الأيسر ويخفي عناصر الشاشة */
        @media print {
            @page { size: A4; margin: 10mm 12mm; }
            :root { --tbl-fs: 11px; --tbl-py: 4px; --tbl-px: 6px; }

            body, body.print-fit {
                padding: 0 !important; margin: 0 !important;
                background: #fff !important;
                width: 100% !important; max-width: 100% !important;
            }
            .print-actions, #pdf-guide { display: none !important; }
            .print-page {
                border: none !important; padding: 0 !important;
                margin: 0 !important;
                width: 100% !important; max-width: 100% !important;
                box-shadow: none !important;
            }
            .po-table-wrap { overflow: visible !important; }
            .gallery-grid  { flex-wrap: nowrap !important; }
            .sina-logo-img { width: 58px; height: 58px; }
            .po-title      { font-size: 15px; padding: 5px 18px; }
        }
    </style>
</head>
<body>

    <!-- أزرار التحكم -->
    <div class="print-actions">
        <a href="index.php?page=order_view&id=<?= (int)$order['id'] ?>" class="btn-action btn-back">↩️ رجوع</a>
        <button type="button" onclick="doPrint()" class="btn-action btn-print-btn">🖨️ طباعة / PDF</button>
    </div>

    <!-- حاوية الصفحة -->
    <div class="print-page">

        <!-- الهيدر -->
        <div class="print-header">
            <div class="header-meta">
                <h1>شركة سينا لمستحضرات التجميل</h1>
                <p>اجراءات المشتريات وتقييم الموردين</p>
                <p>رقم PEP09-</p>
                <p class="doc-code">PEF-09-02</p>
            </div>
            <div class="sina-logo">
                <img src="assets/images/sina-logo.jpg" alt="Sina Cosmetics" class="sina-logo-img">
            </div>
        </div>

        <!-- رقم الأمر والتاريخ -->
        <div class="po-title-date-row">
            <div class="po-title">أمر توريد رقم ( <?= e($po_number) ?> )</div>
            <div class="po-date-text">تاريخ <?= fmt_date($order['order_date']) ?></div>
        </div>

        <!-- بيانات المورد -->
        <div class="po-vendor-block">
            <div>السادة : <?= e($order['vendor_name']) ?></div>
            <?php if (!empty($order['attention'])): ?>
                <div>عناية / <?= e($order['attention']) ?></div>
            <?php endif; ?>
        </div>

        <p class="po-lead-text">الرجاء القيام بتوريد الأصناف التالية:</p>

        <!-- جدول البنود -->
        <div class="po-table-wrap">
        <table class="po-table">
            <thead>
                <tr>
                    <th style="width:32px;">م</th>
                    <th>اسم ومواصفات الصنف</th>
                    <th style="width:68px;">الكمية / عدد</th>
                    <th style="width:78px;">سعر الألف<br><small>جنيه/قرش</small></th>
                    <th style="width:78px;">الاجمالي<br><small>جنيه/قرش</small></th>
                    <th style="width:130px;">ملاحظات</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($lines as $ln): ?>
                    <?php $hasNote = trim($ln['notes'] ?? '') !== ''; ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td class="item-name-cell">
                            <?= e($ln['item_name']) ?>
                            <?php if ($ln['specs']): ?>
                                <span style="font-size:11px;font-weight:normal;color:#4b5563;display:block;">
                                    <?= e($ln['specs']) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?= fmt_qty($ln['quantity']) ?></td>
                        <td class="<?= $hasNote ? 'price-flag' : '' ?>"><?= number_format($ln['unit_price_egp'], 2) ?></td>
                        <td class="<?= $hasNote ? 'price-flag' : '' ?>"><?= number_format($ln['line_total'], 2) ?></td>
                        <td class="notes-cell"><?= e($ln['notes'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                <!-- صف الإجمالي -->
                <tr style="font-weight:700;">
                    <td colspan="2">الإجمالي</td>
                    <td colspan="2"></td>
                    <td><?= number_format($total, 2) ?></td>
                    <td></td>
                </tr>
            </tbody>
        </table>
        </div>

        <!-- شروط التوريد والسداد -->
        <div class="po-terms-section">
            <div class="po-terms-row-split">
                <span>على أن يتم التوريد خلال <?= e($order['delivery_period'] ? $order['delivery_period'] : '10 أيام') ?></span>
                <span>مكان التسليم<?= $order['delivery_location'] ? ' : ' . e($order['delivery_location']) : '' ?></span>
            </div>
            <div class="po-terms-row">
                شروط السداد : <?= e($order['payment_terms'] ? $order['payment_terms'] : 'شيك اجل بعد الفحص ومطابقة الاصناف للمواصفات') ?>
            </div>
        </div>

        <!-- التوقيعات -->
        <div class="signatures-section">
            <div class="sig-row"><span class="sig-label">يعتمد</span><span class="sig-line"></span></div>
            <div class="sig-row"><span class="sig-label">اسم</span><span class="sig-line"></span></div>
            <div class="sig-row"><span class="sig-label">توقيع</span><span class="sig-line"></span></div>
        </div>

        <!-- معرض الصور -->
        <?php
        $has_any_photos = false;
        foreach ($lines as $ln) { if ($ln['has_photo']) { $has_any_photos = true; break; } }
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

        <!-- الفوتر -->
        <div class="print-footer">
            <div>الاصدار الاول</div>
            <div>تاريخ الاصدار: 1 - 6 - 2010</div>
            <div>صفحه رقم: 1 / 1</div>
            <div>PEF-09-02</div>
        </div>

    </div><!-- /.print-page -->

    <script>
        /* تصغير تلقائي للجدول حتى يتّسع في صفحة A4 واحدة */
        function fitOnePage() {
            document.body.classList.add('print-fit');
            var page = document.querySelector('.print-page');
            if (!page) return;
            var target = 1000;
            var fs = 11, py = 4, iterations = 0;
            void page.offsetHeight;
            while (page.scrollHeight > target && iterations < 30 && fs > 7.5) {
                fs = Math.max(7.5, fs - 0.3);
                py = Math.max(2, py - 0.15);
                document.documentElement.style.setProperty('--tbl-fs', fs.toFixed(2) + 'px');
                document.documentElement.style.setProperty('--tbl-py', py.toFixed(2) + 'px');
                void page.offsetHeight;
                iterations++;
            }
        }

        function resetPageFit() {
            document.body.classList.remove('print-fit');
            document.documentElement.style.removeProperty('--tbl-fs');
            document.documentElement.style.removeProperty('--tbl-py');
        }

        window.addEventListener('afterprint', resetPageFit);

        function doPrint() {
            fitOnePage();
            window.print();
        }

        /* ?printonly=1 أو ?download=1: افتح نافذة الطباعة تلقائياً */
        window.addEventListener('DOMContentLoaded', function () {
            var params = new URLSearchParams(window.location.search);
            if (params.get('printonly') === '1' || params.get('download') === '1') {
                var imgs = Array.from(document.querySelectorAll('.print-page img'));
                var loaded = 0;
                function tryPrint() {
                    fitOnePage();
                    setTimeout(function () { window.print(); }, 300);
                }
                if (imgs.length === 0) {
                    setTimeout(tryPrint, 400);
                } else {
                    imgs.forEach(function (img) {
                        function onDone() { loaded++; if (loaded >= imgs.length) tryPrint(); }
                        if (img.complete) { onDone(); }
                        else { img.addEventListener('load', onDone); img.addEventListener('error', onDone); }
                    });
                    setTimeout(tryPrint, 3000);
                }
            }
        });
    </script>
</body>
</html>
