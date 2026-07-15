<?php
/**
 * pages/dashboard.php — الصفحة الرئيسية.
 * أزرار سريعة + إحصائيات + آخر ٥ أوردرات.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// الإحصائيات السريعة
$items_count   = (int) $pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();
$vendors_count = (int) $pdo->query('SELECT COUNT(*) FROM vendors')->fetchColumn();
$orders_count  = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$total_spent   = (float) $pdo->query('SELECT COALESCE(SUM(line_total),0) FROM order_items')->fetchColumn();

// آخر ٥ أوردرات مع اسم المورد وإجمالي الأوردر
$last_orders = $pdo->query(
    "SELECT o.id, o.order_date, o.usd_rate, v.name AS vendor_name,
            COALESCE(SUM(oi.line_total),0) AS total
     FROM orders o
     JOIN vendors v ON v.id = o.vendor_id
     LEFT JOIN order_items oi ON oi.order_id = o.id
     GROUP BY o.id
     ORDER BY o.order_date DESC, o.id DESC
     LIMIT 5"
)->fetchAll();

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

<!-- بطاقات الإحصائيات -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card bg-grad-1">
            <div class="stat-number"><?= number_format($items_count) ?></div>
            <div class="stat-label">📦 صنف</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card bg-grad-2">
            <div class="stat-number"><?= number_format($vendors_count) ?></div>
            <div class="stat-label">🏪 مورّد</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card bg-grad-4">
            <div class="stat-number"><?= number_format($orders_count) ?></div>
            <div class="stat-label">📋 أوردر</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card bg-grad-3">
            <div class="stat-number" style="font-size:1.5rem"><?= number_format($total_spent, 0) ?></div>
            <div class="stat-label">💰 إجمالي الإنفاق (ج.م)</div>
        </div>
    </div>
</div>

<!-- آخر الأوردرات -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>🕒 آخر الأوردرات</span>
        <a href="index.php?page=orders" class="btn btn-sm btn-outline-primary">عرض الكل</a>
    </div>
    <div class="card-body p-0">
        <?php if (!$last_orders): ?>
            <div class="p-4 text-center text-muted">
                لا توجد أوردرات بعد.
                <a href="index.php?page=order_new">أضِف أول أوردر ➕</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>المورد</th>
                            <th>الإجمالي</th>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
