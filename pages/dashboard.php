<?php
/**
 * pages/dashboard.php — الصفحة الرئيسية.
 * أزرار سريعة + إحصائيات قابلة للتصفية (بالفترة/المورد/الصنف) + آخر الأوردرات.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

/* ---------------- قراءة الفلاتر ---------------- */
$period    = $_GET['period'] ?? 'all';
$vendor_id = (int) ($_GET['vendor_id'] ?? 0);
$item_id   = (int) ($_GET['item_id'] ?? 0);
$in_from   = trim($_GET['from'] ?? '');
$in_to     = trim($_GET['to'] ?? '');

// تحديد نطاق التاريخ حسب الفترة المختارة
$today = date('Y-m-d');
$from = '';
$to   = '';
switch ($period) {
    case 'day':
        $from = $today; $to = $today;
        break;
    case 'week':
        $from = date('Y-m-d', strtotime('-6 days')); $to = $today;
        break;
    case 'month':
        $from = date('Y-m-01'); $to = date('Y-m-t');
        break;
    case 'year':
        $from = date('Y-01-01'); $to = date('Y-12-31');
        break;
    case 'custom':
        $from = $in_from; $to = $in_to;
        break;
    case 'all':
    default:
        $from = ''; $to = '';
        break;
}

/* ---------------- بناء شرط التصفية (آمن) ---------------- */
$where  = ['1=1'];
$params = [];
if ($from !== '') { $where[] = 'o.order_date >= ?'; $params[] = $from; }
if ($to   !== '') { $where[] = 'o.order_date <= ?'; $params[] = $to; }
if ($vendor_id > 0) { $where[] = 'o.vendor_id = ?'; $params[] = $vendor_id; }
if ($item_id   > 0) { $where[] = 'oi.item_id = ?';  $params[] = $item_id; }
$whereSql = 'WHERE ' . implode(' AND ', $where);

/* ---------------- الإحصائيات المُصفّاة ---------------- */
$aggSql = "SELECT
        COALESCE(SUM(oi.line_total),0)   AS total,
        COUNT(DISTINCT o.id)             AS orders_count,
        COUNT(DISTINCT oi.item_id)       AS items_count,
        COUNT(DISTINCT o.vendor_id)      AS vendors_count,
        COALESCE(SUM(oi.quantity),0)     AS total_qty,
        COALESCE(MIN(oi.unit_price_egp),0) AS min_price,
        COALESCE(AVG(oi.unit_price_egp),0) AS avg_price
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    $whereSql";
$stmt = $pdo->prepare($aggSql);
$stmt->execute($params);
$agg = $stmt->fetch();

/* ---------------- آخر الأوردرات المُصفّاة ---------------- */
$recentSql = "SELECT o.id, o.order_date, o.usd_rate, v.name AS vendor_name,
                     SUM(oi.line_total) AS total
              FROM orders o
              JOIN vendors v ON v.id = o.vendor_id
              JOIN order_items oi ON oi.order_id = o.id
              $whereSql
              GROUP BY o.id
              ORDER BY o.order_date DESC, o.id DESC
              LIMIT 5";
$stmt = $pdo->prepare($recentSql);
$stmt->execute($params);
$last_orders = $stmt->fetchAll();

/* ---------------- بيانات القوائم ---------------- */
$vendors = $pdo->query('SELECT id, name FROM vendors ORDER BY name')->fetchAll();
$items   = $pdo->query('SELECT id, name, specs, unit FROM items ORDER BY name')->fetchAll();

// وحدة الصنف المختار (لعرض الكمية)
$sel_item_unit = '';
if ($item_id > 0) {
    foreach ($items as $it) {
        if ((int)$it['id'] === $item_id) { $sel_item_unit = (string)($it['unit'] ?? ''); break; }
    }
}

// وصف الفترة الحالية للعرض
$period_labels = [
    'all' => 'كل الفترات', 'day' => 'اليوم', 'week' => 'آخر ٧ أيام',
    'month' => 'هذا الشهر', 'year' => 'هذا العام', 'custom' => 'فترة مخصصة',
];
$period_label = $period_labels[$period] ?? 'كل الفترات';
$range_text = ($from !== '' || $to !== '')
    ? (($from !== '' ? fmt_date($from) : 'البداية') . ' — ' . ($to !== '' ? fmt_date($to) : 'الآن'))
    : 'كل الفترات';

$page_title = 'الرئيسية';
$active = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="h3 fw-bold mb-4">👋 أهلاً بك في <?= e(APP_NAME) ?></h1>

<!-- أزرار سريعة كبيرة -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="index.php?page=order_new" class="big-action bg-grad-1">
            <span class="emoji">➕</span> أوردر جديد
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="index.php?page=item_history" class="big-action bg-grad-2">
            <span class="emoji">🔍</span> بحث عن صنف
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="index.php?page=orders" class="big-action bg-grad-4">
            <span class="emoji">📋</span> الأوردرات
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="index.php?page=reports" class="big-action bg-grad-3">
            <span class="emoji">📊</span> التقارير
        </a>
    </div>
</div>

<!-- فلاتر الإحصائيات -->
<div class="card mb-3">
    <div class="card-header">🎛️ تصفية الإحصائيات</div>
    <div class="card-body">
        <form method="get" id="statsFilter" class="row g-2 align-items-end">
            <input type="hidden" name="page" value="dashboard">

            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label fw-bold">الفترة</label>
                <select name="period" id="periodSelect" class="form-select">
                    <option value="all"   <?= $period==='all'   ?'selected':'' ?>>كل الفترات</option>
                    <option value="day"   <?= $period==='day'   ?'selected':'' ?>>اليوم</option>
                    <option value="week"  <?= $period==='week'  ?'selected':'' ?>>آخر ٧ أيام</option>
                    <option value="month" <?= $period==='month' ?'selected':'' ?>>هذا الشهر</option>
                    <option value="year"  <?= $period==='year'  ?'selected':'' ?>>هذا العام</option>
                    <option value="custom"<?= $period==='custom'?'selected':'' ?>>فترة مخصصة</option>
                </select>
            </div>

            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label fw-bold">من</label>
                <input type="date" name="from" id="fromDate" class="form-control"
                       value="<?= e($period==='custom' ? $in_from : $from) ?>"
                       <?= $period==='custom' ? '' : 'disabled' ?>>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label fw-bold">إلى</label>
                <input type="date" name="to" id="toDate" class="form-control"
                       value="<?= e($period==='custom' ? $in_to : $to) ?>"
                       <?= $period==='custom' ? '' : 'disabled' ?>>
            </div>

            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label fw-bold">المورد</label>
                <select name="vendor_id" class="form-select">
                    <option value="0">كل الموردين</option>
                    <?php foreach ($vendors as $v): ?>
                        <option value="<?= (int)$v['id'] ?>" <?= $vendor_id===(int)$v['id']?'selected':'' ?>>
                            <?= e($v['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-6 col-lg-2">
                <label class="form-label fw-bold">الصنف</label>
                <select name="item_id" class="form-select">
                    <option value="0">كل الأصناف</option>
                    <?php foreach ($items as $it): ?>
                        <option value="<?= (int)$it['id'] ?>" <?= $item_id===(int)$it['id']?'selected':'' ?>>
                            <?= e($it['name']) ?><?= $it['specs'] ? ' — '.e($it['specs']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-lg-2 d-grid gap-1">
                <button type="submit" class="btn btn-primary">🔍 عرض</button>
                <a href="index.php?page=dashboard" class="btn btn-outline-secondary btn-sm">إعادة تعيين</a>
            </div>
        </form>
        <div class="text-muted small mt-2">
            الفترة: <b><?= e($period_label) ?></b> (<?= e($range_text) ?>)
            <?php if ($vendor_id > 0): ?>• مورد محدد<?php endif; ?>
            <?php if ($item_id > 0): ?>• صنف محدد<?php endif; ?>
        </div>
    </div>
</div>

<!-- بطاقات الإحصائيات المُصفّاة -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card bg-grad-3">
            <div class="stat-number" style="font-size:1.6rem"><?= number_format($agg['total'], 0) ?></div>
            <div class="stat-label">💰 إجمالي الإنفاق (ج.م)</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card bg-grad-4">
            <div class="stat-number"><?= number_format($agg['orders_count']) ?></div>
            <div class="stat-label">📋 عدد الأوردرات</div>
        </div>
    </div>

    <?php if ($item_id > 0): ?>
        <!-- عند اختيار صنف واحد: الكمية وأقل سعر -->
        <div class="col-6 col-md-3">
            <div class="stat-card bg-grad-1">
                <div class="stat-number" style="font-size:1.6rem"><?= e(fmt_qty($agg['total_qty'])) ?></div>
                <div class="stat-label">📦 الكمية <?= e($sel_item_unit) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card bg-grad-2">
                <div class="stat-number" style="font-size:1.4rem"><?= number_format($agg['min_price'], 2) ?></div>
                <div class="stat-label">⭐ أقل سعر وحدة (ج.م)</div>
            </div>
        </div>
    <?php else: ?>
        <!-- الوضع العام: عدد الأصناف والموردين المشمولين -->
        <div class="col-6 col-md-3">
            <div class="stat-card bg-grad-1">
                <div class="stat-number"><?= number_format($agg['items_count']) ?></div>
                <div class="stat-label">📦 أصناف مختلفة</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card bg-grad-2">
                <div class="stat-number"><?= number_format($agg['vendors_count']) ?></div>
                <div class="stat-label">🏪 موردون مشمولون</div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- آخر الأوردرات (ضمن الفلاتر) -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>🕒 آخر الأوردرات (ضمن الفلاتر)</span>
        <a href="index.php?page=orders" class="btn btn-sm btn-outline-primary">عرض الكل</a>
    </div>
    <div class="card-body p-0">
        <?php if (!$last_orders): ?>
            <div class="p-4 text-center text-muted">
                لا توجد أوردرات مطابقة للفلاتر الحالية.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>المورد</th>
                            <th><?= $item_id > 0 ? 'إجمالي الصنف' : 'الإجمالي' ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($last_orders as $o): ?>
                            <tr>
                                <td><?= fmt_date($o['order_date']) ?></td>
                                <td><?= e($o['vendor_name']) ?></td>
                                <td>
                                    <?= money_egp($o['total']) ?>
                                    <span class="usd-note"><?= usd_equiv($o['total'], $o['usd_rate']) ?></span>
                                </td>
                                <td>
                                    <a href="index.php?page=order_view&id=<?= (int)$o['id'] ?>"
                                       class="btn btn-sm btn-outline-secondary">عرض</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// تفعيل حقلي التاريخ فقط عند اختيار "فترة مخصصة"، والإرسال التلقائي عند تغيير الفترة
(function () {
    const period = document.getElementById('periodSelect');
    const fromD  = document.getElementById('fromDate');
    const toD    = document.getElementById('toDate');
    function sync() {
        const custom = period.value === 'custom';
        fromD.disabled = !custom;
        toD.disabled   = !custom;
    }
    period.addEventListener('change', function () {
        sync();
        // للفترات الجاهزة (غير المخصصة) أرسل مباشرة
        if (period.value !== 'custom') {
            document.getElementById('statsFilter').submit();
        }
    });
    sync();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
