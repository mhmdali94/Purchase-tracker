<?php
/**
 * pages/order_view.php — عرض تفاصيل أوردر (للقراءة) مع أزرار تعديل/حذف.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

// حذف من شاشة العرض
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([$id]);
        set_flash('success', 'تم حذف الأوردر.');
        redirect('index.php?page=orders');
    }
}

$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT o.*, v.name AS vendor_name, v.phone AS vendor_phone
     FROM orders o JOIN vendors v ON v.id = o.vendor_id
     WHERE o.id = ?'
);
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('error', 'الأوردر غير موجود.');
    redirect('index.php?page=orders');
}

// سطور الأوردر
$ls = $pdo->prepare(
    'SELECT oi.*, i.name AS item_name, i.specs, i.unit
     FROM order_items oi JOIN items i ON i.id = oi.item_id
     WHERE oi.order_id = ? ORDER BY oi.id'
);
$ls->execute([$id]);
$lines = $ls->fetchAll();
$total = array_sum(array_column($lines, 'line_total'));

$page_title = 'أوردر رقم ' . $id;
$active = 'orders';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h3 fw-bold mb-0">🧾 أوردر رقم <?= (int)$order['id'] ?></h1>
    <div class="d-flex gap-2 flex-wrap">
        <a href="index.php?page=orders" class="btn btn-outline-secondary">↩️ رجوع</a>
        <a href="index.php?page=order_print&id=<?= (int)$order['id'] ?>" target="_blank" class="btn btn-success">🖨️ طباعة أمر التوريد</a>
        <a href="index.php?page=order_new&id=<?= (int)$order['id'] ?>" class="btn btn-primary">✏️ تعديل</a>
        <form method="post" class="d-inline js-confirm-delete" data-confirm="حذف هذا الأوردر نهائياً؟">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
            <button class="btn btn-outline-danger">🗑️ حذف</button>
        </form>
    </div>
</div>

<!-- بيانات الأوردر -->
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-6 col-md-3">
                <div class="text-muted small">المورد</div>
                <div class="fw-bold"><?= e($order['vendor_name']) ?></div>
                <?php if ($order['vendor_phone']): ?>
                    <div class="usd-note"><?= e($order['vendor_phone']) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-muted small">تاريخ الأوردر</div>
                <div class="fw-bold"><?= fmt_date($order['order_date']) ?></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-muted small">سعر الدولار يومها</div>
                <div class="fw-bold"><?= $order['usd_rate'] > 0 ? number_format($order['usd_rate'], 2) . ' ج.م' : '—' ?></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-muted small">أُنشئ في</div>
                <div class="fw-bold"><?= fmt_datetime($order['created_at']) ?></div>
            </div>
            
            <!-- حقول أمر التوريد الإضافية (Sina Cosmetics PO) -->
            <div class="col-6 col-md-3">
                <div class="text-muted small">عناية / المسؤول</div>
                <div class="fw-bold"><?= $order['attention'] ? e($order['attention']) : '—' ?></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-muted small">مدة التوريد</div>
                <div class="fw-bold"><?= e($order['delivery_period'] ?? '10 أيام') ?></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-muted small">شروط السداد</div>
                <div class="fw-bold"><?= e($order['payment_terms'] ?? 'شيك اجل بعد الفحص ومطابقة الاصناف للمواصفات') ?></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-muted small">مكان التسليم</div>
                <div class="fw-bold"><?= $order['delivery_location'] ? e($order['delivery_location']) : '—' ?></div>
            </div>
            
            <?php if ($order['notes']): ?>
                <div class="col-12">
                    <div class="text-muted small">ملاحظات</div>
                    <div><?= e($order['notes']) ?></div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- الأصناف -->
<div class="card mb-3">
    <div class="card-header">📦 أصناف الأوردر (<?= count($lines) ?>)</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0 align-middle order-view-table">
                <thead>
                    <tr>
                        <th>الصنف</th><th>الكمية</th>
                        <th>سعر الوحدة (ج.م)</th><th>الإجمالي (ج.م)</th>
                        <th>ملاحظات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lines as $ln): ?>
                        <tr>
                            <td>
                                <b><?= e($ln['item_name']) ?></b>
                                <?php if ($ln['specs']): ?>
                                    <span class="usd-note"><?= e($ln['specs']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= fmt_qty($ln['quantity']) ?> <?= e($ln['unit']) ?></td>
                            <td>
                                <?= money_egp($ln['unit_price_egp']) ?>
                                <span class="usd-note"><?= usd_equiv($ln['unit_price_egp'], $order['usd_rate']) ?></span>
                            </td>
                            <td>
                                <b><?= money_egp($ln['line_total']) ?></b>
                                <span class="usd-note"><?= usd_equiv($ln['line_total'], $order['usd_rate']) ?></span>
                            </td>
                            <td><?= e($ln['notes'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-light">
                        <th colspan="4" class="text-end">الإجمالي الكلي</th>
                        <th>
                            <?= money_egp($total) ?>
                            <span class="usd-note"><?= usd_equiv($total, $order['usd_rate']) ?></span>
                        </th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
