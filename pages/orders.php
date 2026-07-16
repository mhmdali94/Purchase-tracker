<?php
/**
 * pages/orders.php — قائمة الأوردرات مع تصفية بالمورد ونطاق التاريخ.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

// حذف أوردر
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        // سطور الأوردر تُحذف تلقائياً (ON DELETE CASCADE)
        $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([$id]);
        set_flash('success', 'تم حذف الأوردر.');
    }
    redirect('index.php?page=orders');
}

// قيم الفلاتر
$f_vendor = (int) ($_GET['vendor_id'] ?? 0);
$f_from   = trim($_GET['from'] ?? '');
$f_to     = trim($_GET['to'] ?? '');

// بناء الاستعلام بشكل آمن (استعلامات مُجهّزة)
$where = [];
$params = [];
if ($f_vendor > 0)          { $where[] = 'o.vendor_id = ?';   $params[] = $f_vendor; }
if ($f_from !== '')         { $where[] = 'o.order_date >= ?';  $params[] = $f_from; }
if ($f_to !== '')           { $where[] = 'o.order_date <= ?';  $params[] = $f_to; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT o.id, o.order_date, o.usd_rate, o.notes, o.custom_order_number, v.name AS vendor_name,
               COALESCE(SUM(oi.line_total),0) AS total,
               COUNT(oi.id) AS lines_count
        FROM orders o
        JOIN vendors v ON v.id = o.vendor_id
        LEFT JOIN order_items oi ON oi.order_id = o.id
        $whereSql
        GROUP BY o.id
        ORDER BY o.order_date DESC, o.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// إجمالي المعروض
$grand = array_sum(array_column($orders, 'total'));

$vendors = $pdo->query('SELECT id, name FROM vendors ORDER BY name')->fetchAll();

$page_title = 'الأوردرات';
$active = 'orders';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h3 fw-bold mb-0">📋 الأوردرات</h1>
    <a href="index.php?page=order_new" class="btn btn-primary btn-lg">➕ أوردر جديد</a>
</div>

<!-- الفلاتر -->
<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <input type="hidden" name="page" value="orders">
            <div class="col-md-4">
                <label class="form-label fw-bold">المورد</label>
                <select name="vendor_id" class="form-select">
                    <option value="0">كل الموردين</option>
                    <?php foreach ($vendors as $v): ?>
                        <option value="<?= (int)$v['id'] ?>" <?= $f_vendor === (int)$v['id'] ? 'selected' : '' ?>>
                            <?= e($v['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">من تاريخ</label>
                <input type="date" name="from" class="form-control" value="<?= e($f_from) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">إلى تاريخ</label>
                <input type="date" name="to" class="form-control" value="<?= e($f_to) ?>">
            </div>
            <div class="col-md-2 d-grid gap-1">
                <button type="submit" class="btn btn-primary">🔍 تصفية</button>
                <a href="index.php?page=orders" class="btn btn-outline-secondary btn-sm">إلغاء</a>
            </div>
        </form>
    </div>
</div>

<!-- النتائج -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>النتائج (<?= count($orders) ?>)</span>
        <span class="badge bg-primary fs-6">الإجمالي: <?= money_egp($grand) ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (!$orders): ?>
            <div class="p-4 text-center text-muted">لا توجد أوردرات مطابقة.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle orders-list-table">
                    <thead>
                        <tr>
                            <th>#</th><th>التاريخ</th><th>المورد</th>
                            <th>عدد الأصناف</th><th>الإجمالي</th><th>سعر $</th>
                            <th class="text-end">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $o): ?>
                            <?php $display_no = $o['custom_order_number'] !== '' && $o['custom_order_number'] !== null ? $o['custom_order_number'] : (int)$o['id']; ?>
                            <tr>
                                <td><?= e($display_no) ?></td>
                                <td><?= fmt_date($o['order_date']) ?></td>
                                <td><?= e($o['vendor_name']) ?></td>
                                <td><span class="badge bg-secondary"><?= (int)$o['lines_count'] ?></span></td>
                                <td>
                                    <?= money_egp($o['total']) ?>
                                    <span class="usd-note"><?= usd_equiv($o['total'], $o['usd_rate']) ?></span>
                                </td>
                                <td><?= $o['usd_rate'] > 0 ? number_format($o['usd_rate'], 2) : '—' ?></td>
                                <td class="text-end" style="white-space:nowrap">
                                    <a href="index.php?page=order_view&id=<?= (int)$o['id'] ?>"
                                       class="btn btn-sm btn-outline-secondary">👁️ عرض</a>
                                    <a href="index.php?page=order_new&id=<?= (int)$o['id'] ?>"
                                       class="btn btn-sm btn-outline-primary">✏️</a>
                                    <form method="post" class="d-inline js-confirm-delete"
                                          data-confirm="حذف الأوردر رقم <?= e($display_no) ?>؟ لا يمكن التراجع.">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger">🗑️</button>
                                    </form>
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
