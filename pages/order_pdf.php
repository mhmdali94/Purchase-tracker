<?php
/**
 * pages/order_pdf.php
 * توليد PDF مباشر من الخادم لأمر التوريد باستخدام mPDF.
 * يدعم العربية RTL بشكل كامل ويُحمَّل مباشرة كملف.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../vendor/autoload.php';

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
    http_response_code(404);
    die('الأوردر غير موجود.');
}

$ls = $pdo->prepare(
    'SELECT oi.*, i.name AS item_name, i.specs, i.unit, i.photo,
            (i.photo IS NOT NULL) AS has_photo
     FROM order_items oi
     JOIN items i ON i.id = oi.item_id
     WHERE oi.order_id = ? ORDER BY oi.id'
);
$ls->execute([$id]);
$lines = $ls->fetchAll();
$total = array_sum(array_column($lines, 'line_total'));
$total_fmt = number_format($total, 2);

$po_number = ($order['custom_order_number'] !== '' && $order['custom_order_number'] !== null)
    ? $order['custom_order_number']
    : (string) (int) $order['id'];

// ---- اللوجو ----
$logo_img = '';
$logo_path = __DIR__ . '/../assets/images/sina-logo.jpg';
if (file_exists($logo_path)) {
    $b64 = base64_encode(file_get_contents($logo_path));
    $logo_img = '<img src="data:image/jpeg;base64,' . $b64 . '" style="width:58pt;height:58pt;object-fit:contain;">';
}

// ---- صفوف الجدول ----
$rows_html = '';
$i = 1;
foreach ($lines as $ln) {
    $hasNote   = trim($ln['notes'] ?? '') !== '';
    $flagStyle = $hasNote ? 'color:#dc2626;font-weight:bold;' : '';
    $item_name = htmlspecialchars($ln['item_name'], ENT_QUOTES, 'UTF-8');
    $specs_html = '';
    if (!empty($ln['specs'])) {
        $specs_html = '<br><span style="font-size:8.5pt;font-weight:normal;color:#555;">'
            . htmlspecialchars($ln['specs'], ENT_QUOTES, 'UTF-8') . '</span>';
    }
    $notes = htmlspecialchars($ln['notes'] ?? '', ENT_QUOTES, 'UTF-8');
    $qty   = htmlspecialchars(fmt_qty($ln['quantity']), ENT_QUOTES, 'UTF-8');

    $rows_html .= '<tr>
        <td style="border:1pt solid #000;padding:4pt 3pt;text-align:center;">' . $i++ . '</td>
        <td style="border:1pt solid #000;padding:4pt 5pt;text-align:right;font-weight:bold;">' . $item_name . $specs_html . '</td>
        <td style="border:1pt solid #000;padding:4pt 3pt;text-align:center;">' . $qty . '</td>
        <td style="border:1pt solid #000;padding:4pt 3pt;text-align:center;' . $flagStyle . '">' . number_format($ln['unit_price_egp'], 2) . '</td>
        <td style="border:1pt solid #000;padding:4pt 3pt;text-align:center;' . $flagStyle . '">' . number_format($ln['line_total'], 2) . '</td>
        <td style="border:1pt solid #000;padding:4pt 5pt;text-align:right;font-size:8.5pt;">' . $notes . '</td>
    </tr>';
}

// ---- التوقيعات ----
function sig_cell(string $label): string {
    return '<td style="width:33%;padding:5pt 8pt;vertical-align:bottom;">
        <span style="font-weight:bold;">' . $label . '</span>
        <div style="border-bottom:1pt dashed #000;margin-top:16pt;"></div>
    </td>';
}

// ---- شروط ----
$delivery  = htmlspecialchars($order['delivery_period'] ?: '10 أيام', ENT_QUOTES, 'UTF-8');
$location  = $order['delivery_location'] ? ' : ' . htmlspecialchars($order['delivery_location'], ENT_QUOTES, 'UTF-8') : '';
$payment   = htmlspecialchars($order['payment_terms'] ?: 'شيك اجل بعد الفحص ومطابقة الاصناف للمواصفات', ENT_QUOTES, 'UTF-8');
$vendor    = htmlspecialchars($order['vendor_name'], ENT_QUOTES, 'UTF-8');
$attention = !empty($order['attention'])
    ? '<br>عناية / ' . htmlspecialchars($order['attention'], ENT_QUOTES, 'UTF-8')
    : '';
$order_date = fmt_date($order['order_date']);

// ---- معرض الصور ----
$gallery_html = '';
$has_photos = false;
foreach ($lines as $ln) { if ($ln['has_photo']) { $has_photos = true; break; } }

if ($has_photos) {
    $gallery_html  = '<div style="margin-top:10pt;border-top:1pt dashed #000;padding-top:8pt;">';
    $gallery_html .= '<div style="font-size:9pt;font-weight:bold;color:#334155;margin-bottom:5pt;">صور مرجعية للأصناف المطلوبة بالأوردر:</div>';
    $gallery_html .= '<table><tr>';
    foreach ($lines as $ln) {
        if ($ln['has_photo'] && $ln['photo']) {
            $b64  = base64_encode($ln['photo']);
            $name = htmlspecialchars(mb_substr($ln['item_name'], 0, 12), ENT_QUOTES, 'UTF-8');
            $gallery_html .= '<td style="border:1pt solid #ccc;padding:2pt;width:55pt;text-align:center;background:#fafafa;">
                <img src="data:image/jpeg;base64,' . $b64 . '" style="width:48pt;height:36pt;object-fit:contain;">
                <div style="font-size:7pt;font-weight:bold;margin-top:2pt;">' . $name . '</div>
            </td>';
        }
    }
    $gallery_html .= '</tr></table></div>';
}

// ---- HTML الكامل ----
$html = '
<html><head><meta charset="UTF-8"></head>
<body style="direction:rtl;font-family:dejavusans;font-size:11pt;color:#000;margin:0;padding:0;">

<!-- الهيدر -->
<table style="width:100%;border-bottom:2pt solid #000;padding-bottom:8pt;margin-bottom:10pt;" cellpadding="0" cellspacing="0">
    <tr>
        <td style="text-align:right;vertical-align:top;">
            <div style="font-size:13pt;font-weight:bold;">شركة سينا لمستحضرات التجميل</div>
            <div style="font-size:9pt;margin-top:2pt;">اجراءات المشتريات وتقييم الموردين</div>
            <div style="font-size:9pt;">رقم PEP09-</div>
            <div style="font-size:9pt;font-weight:bold;">PEF-09-02</div>
        </td>
        <td style="text-align:left;vertical-align:middle;width:65pt;">' . $logo_img . '</td>
    </tr>
</table>

<!-- رقم الأمر والتاريخ -->
<table style="width:100%;margin-bottom:10pt;" cellpadding="0" cellspacing="0">
    <tr>
        <td>
            <span style="background-color:#facc15;color:#000;font-size:13pt;font-weight:bold;padding:4pt 14pt;border:2pt solid #000;">
                أمر توريد رقم ( ' . e($po_number) . ' )
            </span>
        </td>
        <td style="text-align:left;font-size:11pt;font-weight:bold;">تاريخ ' . $order_date . '</td>
    </tr>
</table>

<!-- المورد -->
<div style="font-size:11pt;font-weight:bold;margin-bottom:6pt;line-height:1.7;">السادة : ' . $vendor . $attention . '</div>
<div style="font-size:11pt;font-weight:bold;margin-bottom:8pt;">الرجاء القيام بتوريد الأصناف التالية:</div>

<!-- جدول البنود -->
<table style="width:100%;border-collapse:collapse;border:2pt solid #000;margin-bottom:10pt;" cellspacing="0">
    <thead>
        <tr style="background-color:#f8fafc;font-weight:bold;font-size:10pt;">
            <th style="border:1pt solid #000;padding:4pt 3pt;width:22pt;text-align:center;">م</th>
            <th style="border:1pt solid #000;padding:4pt 5pt;text-align:right;">اسم ومواصفات الصنف</th>
            <th style="border:1pt solid #000;padding:4pt 3pt;width:50pt;text-align:center;">الكمية / عدد</th>
            <th style="border:1pt solid #000;padding:4pt 3pt;width:56pt;text-align:center;">سعر الألف<br>جنيه/قرش</th>
            <th style="border:1pt solid #000;padding:4pt 3pt;width:56pt;text-align:center;">الاجمالي<br>جنيه/قرش</th>
            <th style="border:1pt solid #000;padding:4pt 5pt;width:96pt;text-align:right;">ملاحظات</th>
        </tr>
    </thead>
    <tbody>
        ' . $rows_html . '
        <tr style="font-weight:bold;background:#f8fafc;">
            <td style="border:1pt solid #000;padding:4pt 3pt;text-align:center;" colspan="2">الإجمالي</td>
            <td style="border:1pt solid #000;padding:4pt 3pt;" colspan="2"></td>
            <td style="border:1pt solid #000;padding:4pt 3pt;text-align:center;">' . $total_fmt . '</td>
            <td style="border:1pt solid #000;padding:4pt 5pt;"></td>
        </tr>
    </tbody>
</table>

<!-- شروط التوريد -->
<table style="width:100%;margin-bottom:6pt;font-size:11pt;font-weight:bold;" cellpadding="0" cellspacing="0">
    <tr>
        <td>على أن يتم التوريد خلال ' . $delivery . '</td>
        <td style="text-align:left;">مكان التسليم' . $location . '</td>
    </tr>
</table>
<div style="font-size:11pt;font-weight:bold;margin-bottom:10pt;">شروط السداد : ' . $payment . '</div>

<!-- التوقيعات -->
<table style="width:100%;margin-top:12pt;font-size:11pt;" cellpadding="0" cellspacing="0">
    <tr>
        ' . sig_cell('يعتمد') . sig_cell('اسم') . sig_cell('توقيع') . '
    </tr>
</table>

' . $gallery_html . '

<!-- الفوتر -->
<table style="width:100%;margin-top:12pt;background-color:#facc15;border:2pt solid #000;font-size:9pt;font-weight:bold;" cellpadding="5" cellspacing="0">
    <tr>
        <td>الاصدار الاول</td>
        <td style="text-align:center;">تاريخ الاصدار: 1 - 6 - 2010</td>
        <td style="text-align:center;">صفحه رقم: 1 / 1</td>
        <td style="text-align:left;">PEF-09-02</td>
    </tr>
</table>

</body></html>';

// ---- توليد وتنزيل PDF ----
try {
    $mpdf = new \Mpdf\Mpdf([
        'mode'             => 'utf-8',
        'format'           => 'A4',
        'orientation'      => 'P',
        'margin_top'       => 10,
        'margin_right'     => 12,
        'margin_bottom'    => 10,
        'margin_left'      => 12,
        'margin_header'    => 0,
        'margin_footer'    => 0,
        'default_font'     => 'dejavusans',
        'autoScriptToLang' => true,
        'autoLangToFont'   => true,
        'tempDir'          => sys_get_temp_dir(),
    ]);

    $mpdf->SetDirectionality('rtl');
    $mpdf->WriteHTML($html);
    $mpdf->Output('order-' . $po_number . '.pdf', \Mpdf\Output\Destination::DOWNLOAD);

} catch (\Exception $e) {
    http_response_code(500);
    echo '<div style="font-family:Arial;padding:40px;direction:rtl;">';
    echo '<h2 style="color:red;">خطأ في إنشاء PDF</h2>';
    echo '<p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<a href="index.php?page=order_print&id=' . $id . '">← الرجوع للطباعة</a>';
    echo '</div>';
}
