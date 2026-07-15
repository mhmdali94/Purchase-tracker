<?php
/**
 * pages/reports.php — التقارير (عرض على الشاشة فقط):
 *   1) مقارنة أسعار صنف: أحدث سعر لكل مورد جنباً إلى جنب.
 *   2) الإنفاق لكل مورد ضمن نطاق تاريخ.
 *   3) الإنفاق لكل مجموعة ضمن نطاق تاريخ.
 *   4) إجمالي الإنفاق الشهري.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

// نطاق التاريخ (يطبَّق على تقريري المورد والمجموعة)
$from = trim($_GET['from'] ?? '');
$to   = trim($_GET['to'] ?? '');

// بناء شرط التاريخ بشكل آمن
$dateWhere = [];
$dateParams = [];
if ($from !== '') { $dateWhere[] = 'o.order_date >= ?'; $dateParams[] = $from; }
if ($to !== '')   { $dateWhere[] = 'o.order_date <= ?'; $dateParams[] = $to; }
$dateSql = $dateWhere ? ('WHERE ' . implode(' AND ', $dateWhere)) : '';

/* -------- (2) الإنفاق لكل مورد -------- */
$sqlVendor = "SELECT v.name, COUNT(DISTINCT o.id) AS orders_count, SUM(oi.line_total) AS total
              FROM order_items oi
              JOIN orders o  ON o.id = oi.order_id
              JOIN vendors v ON v.id = o.vendor_id
              $dateSql
              GROUP BY v.id ORDER BY total DESC";
$st = $pdo->prepare($sqlVendor);
$st->execute($dateParams);
$by_vendor = $st->fetchAll();

/* -------- (3) الإنفاق لكل مجموعة -------- */
$sqlCat = "SELECT COALESCE(c.name, 'بدون مجموعة') AS name, SUM(oi.line_total) AS total
           FROM order_items oi
           JOIN items i    ON i.id = oi.item_id
           LEFT JOIN categories c ON c.id = i.category_id
           JOIN orders o   ON o.id = oi.order_id
           $dateSql
           GROUP BY c.id ORDER BY total DESC";
$st = $pdo->prepare($sqlCat);
$st->execute($dateParams);
$by_category = $st->fetchAll();

/* -------- (4) الإنفاق الشهري (كل الفترات) -------- */
$by_month = $pdo->query(
    "SELECT DATE_FORMAT(o.order_date, '%Y-%m') AS ym, SUM(oi.line_total) AS total
     FROM order_items oi JOIN orders o ON o.id = oi.order_id
     GROUP BY ym ORDER BY ym DESC"
)->fetchAll();

// بيانات الرسم البياني الشهري (تصاعدي)
$month_chart = array_reverse($by_month);
$month_labels = array_column($month_chart, 'ym');
$month_values = array_map(fn($r) => (float)$r['total'], $month_chart);

/* -------- (1) مقارنة أسعار صنف -------- */
$cmp_item_id = (int) ($_GET['cmp_item_id'] ?? 0);
$items = $pdo->query('SELECT id, name, specs, unit FROM items ORDER BY name')->fetchAll();

$cmp_item = null;
$cmp_rows = [];
$cmp_min = null;
if ($cmp_item_id > 0) {
    foreach ($items as $it) { if ((int)$it['id'] === $cmp_item_id) { $cmp_item = $it; break; } }
    if ($cmp_item) {
        // كل المشتريات لهذا الصنف مرتبة بالأحدث؛ نأخذ أحدث سعر لكل مورد
        $q = $pdo->prepare(
            "SELECT v.id AS vendor_id, v.name AS vendor_name, oi.unit_price_egp,
                    o.order_date, o.usd_rate
             FROM order_items oi
             JOIN orders o  ON o.id = oi.order_id
             JOIN vendors v ON v.id = o.vendor_id
             WHERE oi.item_id = ?
             ORDER BY o.order_date DESC, o.id DESC"
        );
        $q->execute([$cmp_item_id]);
        $seen = [];
        foreach ($q->fetchAll() as $r) {
            if (isset($seen[$r['vendor_id']])) continue; // احتفظ بالأحدث فقط لكل مورد
            $seen[$r['vendor_id']] = true;
            $cmp_rows[] = $r;
            $p = (float)$r['unit_price_egp'];
            if ($cmp_min === null || $p < $cmp_min) $cmp_min = $p;
        }
        // رتّب حسب السعر تصاعدياً (الأرخص أولاً)
        usort($cmp_rows, fn($a, $b) => $a['unit_price_egp'] <=> $b['unit_price_egp']);
    }
}

$page_title = 'التقارير';
$active = 'reports';
require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="h3 fw-bold mb-4">📊 التقارير</h1>

<!-- (1) مقارنة أسعار صنف -->
<div class="card mb-4">
    <div class="card-header">⚖️ مقارنة أسعار صنف بين الموردين (أحدث سعر لكل مورد)</div>
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end mb-3">
            <input type="hidden" name="page" value="reports">
            <div class="col-md-9">
                <label class="form-label fw-bold">اختر الصنف</label>
                <select name="cmp_item_id" class="form-select form-select-lg" onchange="this.form.submit()">
                    <option value="0">— اختر صنفاً —</option>
                    <?php foreach ($items as $it): ?>
                        <option value="<?= (int)$it['id'] ?>" <?= $cmp_item_id === (int)$it['id'] ? 'selected' : '' ?>>
                            <?= e($it['name']) ?><?= $it['specs'] ? ' — ' . e($it['specs']) : '' ?><?= $it['unit'] ? ' (' . e($it['unit']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-grid">
                <button class="btn btn-primary btn-lg">عرض المقارنة</button>
            </div>
        </form>

        <?php if ($cmp_item): ?>
            <?php if (!$cmp_rows): ?>
                <div class="alert alert-info mb-0">لا توجد مشتريات لهذا الصنف بعد.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>المورد</th><th>أحدث سعر (ج.م)</th>
                                <th>تاريخ آخر شراء</th><th>المعادل بالدولار</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cmp_rows as $r): ?>
                                <?php $low = ((float)$r['unit_price_egp'] === (float)$cmp_min); ?>
                                <tr class="<?= $low ? 'lowest-price' : '' ?>">
                                    <td><?= e($r['vendor_name']) ?></td>
                                    <td><b><?= money_egp($r['unit_price_egp']) ?></b></td>
                                    <td><?= fmt_date($r['order_date']) ?></td>
                                    <td><?= usd_equiv($r['unit_price_egp'], $r['usd_rate']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="text-muted small mt-2 mb-0">⭐ الصف المميّز هو الأرخص حالياً.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- فلتر التاريخ لتقريري المورد والمجموعة -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <input type="hidden" name="page" value="reports">
            <?php if ($cmp_item_id): ?><input type="hidden" name="cmp_item_id" value="<?= $cmp_item_id ?>"><?php endif; ?>
            <div class="col-md-4">
                <label class="form-label fw-bold">من تاريخ</label>
                <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">إلى تاريخ</label>
                <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
            </div>
            <div class="col-md-4 d-grid gap-1">
                <button class="btn btn-primary">🔍 تطبيق على تقارير الإنفاق</button>
                <a href="index.php?page=reports<?= $cmp_item_id ? '&cmp_item_id=' . $cmp_item_id : '' ?>"
                   class="btn btn-outline-secondary btn-sm">إلغاء الفلتر</a>
            </div>
        </form>
        <p class="text-muted small mt-2 mb-0">
            الفترة الحالية:
            <?= $from !== '' ? fmt_date($from) : 'البداية' ?> —
            <?= $to !== '' ? fmt_date($to) : 'الآن' ?>
        </p>
    </div>
</div>

<div class="row g-4">
    <!-- (2) الإنفاق لكل مورد -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">🏪 الإنفاق لكل مورد</div>
            <div class="card-body p-0">
                <?php if (!$by_vendor): ?>
                    <div class="p-4 text-center text-muted">لا توجد بيانات في هذه الفترة.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead><tr><th>المورد</th><th>عدد الأوردرات</th><th>الإجمالي</th></tr></thead>
                            <tbody>
                                <?php foreach ($by_vendor as $r): ?>
                                    <tr>
                                        <td><?= e($r['name']) ?></td>
                                        <td><span class="badge bg-secondary"><?= (int)$r['orders_count'] ?></span></td>
                                        <td><?= money_egp($r['total']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- (3) الإنفاق لكل مجموعة -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">🗂️ الإنفاق لكل مجموعة</div>
            <div class="card-body p-0">
                <?php if (!$by_category): ?>
                    <div class="p-4 text-center text-muted">لا توجد بيانات في هذه الفترة.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead><tr><th>المجموعة</th><th>الإجمالي</th></tr></thead>
                            <tbody>
                                <?php foreach ($by_category as $r): ?>
                                    <tr>
                                        <td><?= e($r['name']) ?></td>
                                        <td><?= money_egp($r['total']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- (4) الإنفاق الشهري -->
<div class="card mt-4">
    <div class="card-header">📅 إجمالي الإنفاق الشهري</div>
    <div class="card-body">
        <?php if (!$by_month): ?>
            <div class="p-3 text-center text-muted">لا توجد بيانات بعد.</div>
        <?php else: ?>
            <canvas id="monthChart" height="80" class="mb-3"></canvas>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead><tr><th>الشهر</th><th>الإجمالي</th></tr></thead>
                    <tbody>
                        <?php foreach ($by_month as $r): ?>
                            <tr><td><?= e($r['ym']) ?></td><td><?= money_egp($r['total']) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <script>
            window.addEventListener('DOMContentLoaded', function () {
                new Chart(document.getElementById('monthChart'), {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode($month_labels) ?>,
                        datasets: [{
                            label: 'الإنفاق الشهري (ج.م)',
                            data: <?= json_encode($month_values) ?>,
                            backgroundColor: 'rgba(13,110,253,.7)',
                            borderRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { labels: { font: { family: 'Cairo' } } } },
                        scales: {
                            y: { beginAtZero: true, ticks: { font: { family: 'Cairo' } } },
                            x: { ticks: { font: { family: 'Cairo' } } }
                        }
                    }
                });
            });
            </script>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
