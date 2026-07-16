<?php
/**
 * pages/item_history.php — سجل أسعار صنف عبر كل الموردين.
 * ابحث/اختر صنفاً → جدول بكل مرات شرائه (الأحدث أولاً) مع تمييز أقل سعر،
 * ورسم بياني لتغيّر سعر الوحدة عبر الزمن.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

// كل الأصناف لقائمة البحث
$items_raw = $pdo->query('SELECT id, name, specs, unit FROM items ORDER BY name')->fetchAll();
$items_js = [];
foreach ($items_raw as $it) {
    $label = $it['name']
        . ($it['specs'] !== '' ? ' — ' . $it['specs'] : '')
        . ($it['unit'] !== '' ? ' (' . $it['unit'] . ')' : '');
    $items_js[] = ['id' => (int)$it['id'], 'label' => $label];
}

$item_id = (int) ($_GET['item_id'] ?? 0);

$item = null;
$rows = [];
$chart_labels = [];
$chart_values = [];
$min_price = null;

if ($item_id > 0) {
    $st = $pdo->prepare('SELECT *, (photo IS NOT NULL) AS has_photo FROM items WHERE id = ?');
    $st->execute([$item_id]);
    $item = $st->fetch();

    if ($item) {
        $q = $pdo->prepare(
            "SELECT oi.quantity, oi.unit_price_egp, o.order_date, o.usd_rate, v.name AS vendor_name
             FROM order_items oi
             JOIN orders o  ON o.id = oi.order_id
             JOIN vendors v ON v.id = o.vendor_id
             WHERE oi.item_id = ?
             ORDER BY o.order_date DESC, o.id DESC"
        );
        $q->execute([$item_id]);
        $rows = $q->fetchAll();

        // أقل سعر وحدة (لتمييز الصف)
        foreach ($rows as $r) {
            $p = (float) $r['unit_price_egp'];
            if ($min_price === null || $p < $min_price) $min_price = $p;
        }

        // بيانات الرسم البياني (بترتيب زمني تصاعدي)
        $chart = array_reverse($rows);
        foreach ($chart as $r) {
            $chart_labels[] = fmt_date($r['order_date']);
            $chart_values[] = (float) $r['unit_price_egp'];
        }
    }
}

// قيم عرض آمنة تماماً (لا تُصدر أي تحذير مهما كانت حالة الصف)
$item_name    = is_array($item) ? (string) ($item['name']  ?? '') : '';
$item_specs   = is_array($item) ? (string) ($item['specs'] ?? '') : '';
$item_unit    = is_array($item) ? (string) ($item['unit']  ?? '') : '';
$item_display = $item_name
    . ($item_specs !== '' ? ' — ' . $item_specs : '')
    . ($item_unit  !== '' ? ' (' . $item_unit . ')' : '');

$page_title = 'بحث عن صنف';
$active = 'item_history';
require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="h3 fw-bold mb-4">🔍 بحث عن صنف — سجل الأسعار</h1>

<!-- اختيار الصنف -->
<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end" id="itemPickForm">
            <input type="hidden" name="page" value="item_history">
            <input type="hidden" name="item_id" id="pickedItemId" value="<?= (int)$item_id ?>">
            <div class="col-md-9">
                <label class="form-label fw-bold">اختر الصنف</label>
                <input type="text" id="itemPicker" class="form-control form-control-lg" list="itemsDL"
                       placeholder="اكتب للبحث عن صنف..." autocomplete="off"
                       value="<?= $item ? e($item_display) : '' ?>">
                <datalist id="itemsDL">
                    <?php foreach ($items_js as $ij): ?>
                        <option value="<?= e($ij['label']) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="col-md-3 d-grid">
                <button type="submit" class="btn btn-primary btn-lg">عرض السجل</button>
            </div>
        </form>
    </div>
</div>

<?php if ($item_id > 0 && !$item): ?>
    <div class="alert alert-warning">الصنف غير موجود.</div>
<?php elseif ($item): ?>

    <div class="d-flex align-items-center gap-3 mb-3">
        <?php if (!empty($item['has_photo'])): ?>
            <img src="ajax/item_photo.php?id=<?= (int)$item_id ?>"
                 alt="<?= e($item_name) ?>" class="item-thumb-history">
        <?php else: ?>
            <span class="item-thumb-history-placeholder">📦</span>
        <?php endif; ?>
        <h2 class="h5 mb-0">
            <?= e($item_name !== '' ? $item_name : 'صنف رقم ' . (int)$item_id) ?>
            <?php if ($item_specs !== ''): ?><small class="text-muted">— <?= e($item_specs) ?></small><?php endif; ?>
        </h2>
    </div>

    <?php if (!$rows): ?>
        <div class="alert alert-info">لم يُشترَ هذا الصنف في أي أوردر بعد.</div>
    <?php else: ?>

        <!-- الرسم البياني -->
        <div class="card mb-3">
            <div class="card-header">📈 تغيّر سعر الوحدة عبر الزمن (ج.م)</div>
            <div class="card-body">
                <canvas id="priceChart" height="90"></canvas>
            </div>
        </div>

        <!-- جدول السجل -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>سجل المشتريات (<?= count($rows) ?>)</span>
                <span class="badge bg-success">⭐ أقل سعر: <?= money_egp($min_price) ?></span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>التاريخ</th><th>المورد</th><th>الكمية</th>
                                <th>سعر الوحدة (ج.م)</th><th>سعر $ يومها</th><th>المعادل بالدولار</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $r): ?>
                                <?php $isLowest = ((float)$r['unit_price_egp'] === (float)$min_price); ?>
                                <tr class="<?= $isLowest ? 'lowest-price' : '' ?>">
                                    <td><?= fmt_date($r['order_date']) ?></td>
                                    <td><?= e($r['vendor_name']) ?></td>
                                    <td><?= fmt_qty($r['quantity']) ?> <?= e($item_unit) ?></td>
                                    <td><?= money_egp($r['unit_price_egp']) ?></td>
                                    <td><?= $r['usd_rate'] > 0 ? number_format($r['usd_rate'], 2) : '—' ?></td>
                                    <td><?= usd_equiv($r['unit_price_egp'], $r['usd_rate']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <script>
        // رسم بياني لسعر الوحدة عبر الزمن (ننتظر تحميل مكتبة الرسم في التذييل)
        window.addEventListener('DOMContentLoaded', function () {
            const ctx = document.getElementById('priceChart');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?= json_encode($chart_labels, JSON_UNESCAPED_UNICODE) ?>,
                    datasets: [{
                        label: 'سعر الوحدة (ج.م)',
                        data: <?= json_encode($chart_values) ?>,
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13,110,253,.12)',
                        fill: true,
                        tension: .25,
                        pointRadius: 4,
                        pointBackgroundColor: '#0d6efd'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { labels: { font: { family: 'Cairo' } } } },
                    scales: {
                        y: { beginAtZero: false, ticks: { font: { family: 'Cairo' } } },
                        x: { ticks: { font: { family: 'Cairo' } } }
                    }
                }
            });
        });
        </script>

    <?php endif; ?>
<?php endif; ?>

<script>
// ربط حقل البحث بالمعرّف قبل الإرسال
(function () {
    const items = <?= json_encode($items_js, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const map = {};
    items.forEach(i => map[i.label] = i.id);
    const picker = document.getElementById('itemPicker');
    const hidden = document.getElementById('pickedItemId');
    const form = document.getElementById('itemPickForm');

    picker.addEventListener('input', function () {
        hidden.value = map[picker.value] || '';
    });
    form.addEventListener('submit', function (ev) {
        if (!hidden.value) {
            ev.preventDefault();
            alert('اختر صنفاً صحيحاً من القائمة.');
        }
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
