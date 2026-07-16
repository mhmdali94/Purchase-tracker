<?php
/**
 * pages/order_print.php — نسخة الطباعة الرسمية لأمر التوريد (أمر توريد).
 * بتصميم مطابق لنموذج شركة سينا لمستحضرات التجميل (نموذج PEP09 / PEF-09-02).
 * مضبوطة دائماً لتخرج في صفحة A4 واحدة فقط، عبر تصغير تلقائي لحجم الجدول
 * عند الحاجة (اعتماداً على عدد الأصناف)، مطبَّق بنفس الطريقة على زر الطباعة
 * وزر تحميل PDF معاً حتى يتطابق شكل الاثنين.
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
    <!-- خط القاهرة العربي الرسمي -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            font-family: 'Cairo', Tahoma, Arial, sans-serif;
        }

        /* متغيّرات حجم جدول البنود — تُصغَّر تلقائياً عبر JavaScript
           (بنفس الطريقة لكل من الطباعة وتحميل PDF) حتى يتّسع كل شيء
           في صفحة A4 واحدة مهما كان عدد الأصناف. */
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

        /* ورقة الطباعة A4 */
        .print-page {
            max-width: 794px; /* عرض A4 تقريباً عند 96dpi */
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 26px;
            position: relative;
            background-color: #fff;
        }

        /* زر الطباعة العلوي على الشاشة فقط */
        .print-actions {
            max-width: 794px;
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
            margin-bottom: 14px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }

        /* لوجو سينا التجميلية */
        .sina-logo {
            display: flex;
            align-items: center;
        }
        .sina-logo-img {
            width: 72px;
            height: 72px;
            object-fit: contain;
        }

        /* البيانات على اليمين */
        .header-meta {
            text-align: right;
            font-size: 12px;
            line-height: 1.55;
        }
        .header-meta h1 {
            font-size: 15px;
            margin: 0 0 3px 0;
            font-weight: 700;
        }
        .header-meta p {
            margin: 0;
        }
        .header-meta p.doc-code {
            font-weight: 700;
        }

        /* رقم أمر التوريد والتاريخ على نفس السطر */
        .po-title-date-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin: 14px 0;
        }
        .po-title {
            background-color: #facc15; /* أصفر فاقع كما في النموذج الأصلي */
            color: #000;
            font-size: 17px;
            font-weight: 700;
            padding: 6px 22px;
            border: 2px solid #000;
            border-radius: 6px;
            display: inline-block;
        }
        .po-date-text {
            font-size: 13px;
            font-weight: 600;
        }

        /* بيانات المورد */
        .po-vendor-block {
            margin: 0 0 12px 0;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.7;
        }
        .po-lead-text {
            margin: 0 0 10px 0;
            font-size: 13px;
            font-weight: 600;
        }

        /* على الشاشة (خارج الطباعة): سماح بتمرير أفقي للجدول فقط على الجوال
           بدلاً من تمرير الصفحة كاملة، مع إبقاء الهيدر واللوجو ثابتَين.
           لا تؤثر هذه القاعدة على مخرجات الطباعة/PDF إطلاقاً. */
        @media screen {
            .po-table-wrap {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            .po-table {
                min-width: 640px;
            }
        }

        /* جدول البنود */
        .po-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            border: 2px solid #000;
        }
        .po-table th, .po-table td {
            border: 1px solid #000;
            padding: var(--tbl-py) var(--tbl-px);
            text-align: center;
            font-size: var(--tbl-fs);
        }
        .po-table th {
            background-color: #f8fafc;
            font-weight: 700;
        }

        /* تقسيم عمود الكمية والأسعار */
        .sub-header-row th {
            font-size: calc(var(--tbl-fs) - 1px);
            padding: 3px;
            background-color: #f1f5f9;
        }
        .po-table td.item-name-cell {
            text-align: right;
            font-weight: 600;
        }
        .po-table td.notes-cell {
            text-align: right;
            font-size: calc(var(--tbl-fs) - 1px);
            color: #334155;
        }
        /* سطر مُعلَّم بملاحظة (مثال: "مثل ما تم توريده من قبل") يظهر بلون أحمر */
        .po-table td.price-flag {
            color: #dc2626;
            font-weight: 700;
        }

        /* ===== ضبط الصفحات ومنع تقطيع الصفوف ===== */
        .po-table tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .po-table thead {
            display: table-header-group;
        }
        .po-terms-section,
        .signatures-section,
        .print-footer {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        /* تفاصيل التوريد والسداد */
        .po-terms-section {
            font-size: 13px;
            line-height: 1.8;
            margin-top: 10px;
            font-weight: 600;
        }
        .po-terms-row-split {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 6px;
            flex-wrap: wrap;
        }
        .po-terms-row {
            margin-bottom: 5px;
        }

        /* التوقيعات — ثلاثة أسطر متتالية مع خط فارغ للتوقيع اليدوي */
        .signatures-section {
            margin-top: 18px;
            padding-top: 8px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .sig-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sig-label {
            min-width: 55px;
            flex-shrink: 0;
        }
        .sig-line {
            flex-grow: 1;
            border-bottom: 1px dashed #000;
            height: 16px;
        }

        /* معرض صور الأصناف للمرجعية */
        .item-images-gallery {
            margin-top: 16px;
            border-top: 1px dashed #000;
            padding-top: 12px;
        }
        .gallery-title {
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #334155;
        }
        .gallery-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .gallery-item {
            border: 1px solid #ccc;
            border-radius: 6px;
            padding: 4px;
            width: 78px;
            text-align: center;
            background-color: #fafafa;
        }
        .gallery-img {
            width: 70px;
            height: 52px;
            object-fit: contain;
            border-radius: 4px;
            background-color: #fff;
        }
        .gallery-label {
            font-size: 9px;
            font-weight: 600;
            margin-top: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* فوتر الصفحة السفلي */
        .print-footer {
            margin-top: 16px;
            border-top: 2px solid #000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 10px;
            font-weight: 700;
            background-color: #facc15;
            padding: 6px 12px;
            border: 2px solid #000;
        }
        .print-footer div {
            color: #000;
        }

        /* ============================================================
           وضع "التثبيت لصفحة واحدة" — يُفعَّل عبر JavaScript على body
           قبل الطباعة الفعلية وقبل تصدير PDF كليهما، لضمان تطابق شكل
           الاثنين وضمان خروج المستند في صفحة A4 واحدة دائماً.
           ============================================================ */
        body.print-fit {
            --tbl-fs: 11px;
            --tbl-py: 4px;
            --tbl-px: 6px;
        }
        body.print-fit .print-actions {
            display: none;
        }
        body.print-fit .print-page {
            width: 186mm;   /* عرض A4 الفعلي بعد هوامش 12مم يميناً ويساراً */
            max-width: 186mm;
            padding: 0;
            border: none;
        }
        body.print-fit .sina-logo-img {
            width: 58px;
            height: 58px;
        }
        body.print-fit .po-title {
            font-size: 15px;
            padding: 5px 18px;
        }
        body.print-fit .item-images-gallery {
            margin-top: 10px;
            padding-top: 8px;
        }
        body.print-fit .gallery-item {
            width: 62px;
            padding: 3px;
        }
        body.print-fit .gallery-img {
            width: 54px;
            height: 40px;
        }

        /* احتياط: طباعة يدوية مباشرة (Ctrl+P) بدون المرور بزر الصفحة */
        @media print {
            @page {
                size: A4;
                margin: 8mm 12mm;
            }
            :root {
                --tbl-fs: 11px;
                --tbl-py: 4px;
                --tbl-px: 6px;
            }
            body {
                padding: 0;
                background: #fff;
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
            .sina-logo-img {
                width: 58px;
                height: 58px;
            }
            .po-title {
                font-size: 15px;
                padding: 5px 18px;
            }
        }
    </style>
</head>
<body>

    <!-- أزرار التحكم -->
    <div class="print-actions" style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap; margin-bottom: 20px;">
        <a href="index.php?page=order_view&id=<?= (int)$order['id'] ?>" class="btn-print" style="background-color: #64748b;">↩️ رجوع للتفاصيل</a>
        <button type="button" onclick="triggerPrint()" class="btn-print">🖨️ طباعة أو حفظ كـ PDF</button>
        <span style="font-size: 13px; color: #475569; font-weight: 600;">💡 لحفظ الملف كـ PDF: اختر <b>"حفظ بتنسيق PDF" (Save as PDF)</b> من خانة الوجهة (Destination) في نافذة الطباعة. الأوردر مضبوط ليخرج دائماً في صفحة A4 واحدة.</span>
    </div>

    <!-- حاوية الصفحة -->
    <div class="print-page">

        <!-- الهيدر العلوي -->
        <div class="print-header">
            <!-- اليمين: معلومات الشركة -->
            <div class="header-meta">
                <h1>شركة سينا لمستحضرات التجميل</h1>
                <p>اجراءات المشتريات وتقييم الموردين</p>
                <p>رقم PEP09-</p>
                <p class="doc-code">PEF-09-02</p>
            </div>

            <!-- اليسار: اللوجو الرسمي لشركة سينا -->
            <div class="sina-logo">
                <img src="assets/images/sina-logo.jpg"
                     alt="Sina Cosmetics Industry Experts"
                     class="sina-logo-img">
            </div>
        </div>

        <!-- رقم أمر التوريد والتاريخ -->
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
                    <th rowspan="2" style="width: 32px;">م</th>
                    <th rowspan="2">اسم ومواصفات الصنف</th>
                    <th colspan="2">الكمية</th>
                    <th>سعر بالألف</th>
                    <th>الاجمالي</th>
                    <th rowspan="2" style="width: 130px;">ملاحظات</th>
                </tr>
                <tr class="sub-header-row">
                    <th style="width: 64px;">تجهيزات</th>
                    <th style="width: 56px;">عدد</th>
                    <th style="width: 72px;">جنيه/قرش</th>
                    <th style="width: 72px;">جنيه/قرش</th>
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
                                <span style="font-size: 11px; font-weight: normal; color: #4b5563; display: block;">
                                    <?= e($ln['specs']) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($ln['unit'] ? $ln['unit'] : 'يوجد') ?></td>
                        <td><?= fmt_qty($ln['quantity']) ?></td>
                        <td class="<?= $hasNote ? 'price-flag' : '' ?>"><?= number_format($ln['unit_price_egp'], 2) ?></td>
                        <td class="<?= $hasNote ? 'price-flag' : '' ?>"><?= number_format($ln['line_total'], 2) ?></td>
                        <td class="notes-cell"><?= e($ln['notes'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>

                <!-- صف الإجمالي -->
                <tr style="font-weight: 700;">
                    <td colspan="2">الإجمالي</td>
                    <td colspan="3"></td>
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

    <!-- html2pdf.js محلي لضمان عمله بدون إنترنت وبدون أي تبعيات -->
    <script src="assets/js/vendor/html2pdf.bundle.min.js"></script>
    <script>
        /**
         * تصغير تلقائي لحجم جدول البنود حتى يتّسع الأوردر بالكامل في صفحة
         * A4 واحدة، بغض النظر عن عدد الأصناف. تُستخدم نفس الدالة قبل
         * الطباعة العادية وقبل توليد PDF معاً، حتى يتطابق شكل الاثنين.
         */
        function fitOnePage() {
            document.body.classList.add('print-fit');

            var page = document.querySelector('.print-page');
            if (!page) return;

            // ميزانية آمنة بالبكسل لارتفاع صفحة A4 واحدة (٩٦ نقطة/بوصة، مع هامش أمان)
            var target = 1000;
            var fs = 11, py = 4, iterations = 0;

            // إعادة تدفق لقراءة الارتفاع الحقيقي بعرض الطباعة الفعلي (186مم)
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

        function triggerPrint() {
            fitOnePage();
            window.print();
        }

        // إعادة الشكل الطبيعي للمعاينة على الشاشة بعد إغلاق نافذة الطباعة
        window.addEventListener('afterprint', resetPageFit);

        window.addEventListener('DOMContentLoaded', function() {
            var params = new URLSearchParams(window.location.search);

            if (params.get('download') === '1') {
                // --- وضع التحميل المباشر (PDF) ---

                var actions = document.querySelector('.print-actions');
                if (actions) actions.style.display = 'none';

                // إظهار شاشة انتظار
                var overlay = document.createElement('div');
                overlay.id = 'pdf-loading';
                overlay.innerHTML = '<div style="text-align:center;padding:60px 20px;font-family:Cairo,sans-serif;">'
                    + '<div style="font-size:48px;margin-bottom:20px;">⏳</div>'
                    + '<div style="font-size:20px;font-weight:700;color:#16a34a;">جاري إنشاء ملف PDF...</div>'
                    + '<div style="font-size:14px;color:#64748b;margin-top:10px;">سيبدأ التحميل خلال ثوانٍ</div>'
                    + '</div>';
                overlay.style.cssText = 'position:fixed;inset:0;background:#fff;z-index:9999;display:flex;align-items:center;justify-content:center;';
                document.body.appendChild(overlay);

                // انتظار تحميل الصور أولاً ثم إنشاء PDF
                var imgs = document.querySelectorAll('.print-page img');
                var loaded = 0;
                var total  = imgs.length;

                function generate() {
                    // نفس آلية "صفحة واحدة" المستخدمة في زر الطباعة تماماً
                    fitOnePage();

                    var element = document.querySelector('.print-page');
                    var orderNo = <?= json_encode($po_number) ?>;

                    var opt = {
                        margin:      [10, 12, 10, 12],
                        filename:    'order-' + orderNo + '.pdf',
                        image:       { type: 'jpeg', quality: 0.95 },
                        html2canvas: { scale: 2, useCORS: true, allowTaint: true, logging: false },
                        jsPDF:       { unit: 'mm', format: 'a4', orientation: 'portrait' },
                        pagebreak:   { mode: ['avoid-all', 'css', 'legacy'] }
                    };

                    html2pdf().set(opt).from(element).save().then(function() {
                        overlay.innerHTML = '<div style="text-align:center;padding:60px 20px;font-family:Cairo,sans-serif;">'
                            + '<div style="font-size:48px;margin-bottom:20px;">✅</div>'
                            + '<div style="font-size:20px;font-weight:700;color:#16a34a;">تم تحميل PDF بنجاح!</div>'
                            + '<div style="font-size:14px;color:#64748b;margin-top:10px;">يمكنك إغلاق هذه النافذة</div>'
                            + '</div>';
                        if (actions) actions.style.display = '';
                        resetPageFit();
                    }).catch(function(err) {
                        overlay.innerHTML = '<div style="text-align:center;padding:60px 20px;font-family:Cairo,sans-serif;">'
                            + '<div style="font-size:48px;margin-bottom:20px;">❌</div>'
                            + '<div style="font-size:20px;font-weight:700;color:#dc2626;">حدث خطأ أثناء إنشاء PDF</div>'
                            + '<div style="font-size:14px;color:#64748b;margin-top:10px;">حاول الطباعة العادية بدلاً من ذلك</div>'
                            + '<button onclick="document.getElementById(\'pdf-loading\').remove();triggerPrint();" style="margin-top:20px;padding:10px 24px;background:#16a34a;color:#fff;border:none;border-radius:6px;font-size:16px;cursor:pointer;">🖨️ طباعة</button>'
                            + '</div>';
                        resetPageFit();
                    });
                }

                if (total === 0) {
                    setTimeout(generate, 400);
                } else {
                    imgs.forEach(function(img) {
                        if (img.complete) {
                            loaded++;
                            if (loaded >= total) setTimeout(generate, 200);
                        } else {
                            img.addEventListener('load', function() {
                                loaded++;
                                if (loaded >= total) setTimeout(generate, 200);
                            });
                            img.addEventListener('error', function() {
                                loaded++;
                                if (loaded >= total) setTimeout(generate, 200);
                            });
                        }
                    });
                    setTimeout(generate, 3000);
                }

            } else {
                // --- وضع الطباعة العادي ---
                setTimeout(function() { triggerPrint(); }, 500);
            }
        });
    </script>
</body>
</html>
