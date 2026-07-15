<?php
/**
 * pages/order_new.php — إنشاء أوردر جديد أو تعديل أوردر قائم.
 * أهم شاشة في التطبيق: اختيار المورد والتاريخ وسعر الدولار،
 * ثم إضافة عدة سطور أصناف مع حساب المجاميع مباشرة، وحفظ كل شيء دفعة واحدة.
 * ------------------------------------------------------------------
 * الوضع: جديد (افتراضي) — أو تعديل عند وجود ?id=  في الرابط.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$order_id = (int) ($_GET['id'] ?? 0);

/* ---------------- معالجة الحفظ ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $post_order_id = (int) ($_POST['order_id'] ?? 0);
    $vendor_id     = (int) ($_POST['vendor_id'] ?? 0);
    $order_date    = $_POST['order_date'] ?? date('Y-m-d');
    $usd_rate      = (float) str_replace(',', '', $_POST['usd_rate'] ?? '0');
    $notes         = trim($_POST['notes'] ?? '');

    // مصفوفات السطور
    $item_ids = $_POST['item_id']    ?? [];
    $qtys     = $_POST['quantity']   ?? [];
    $prices   = $_POST['unit_price'] ?? [];

    // بناء سطور صالحة فقط
    $lines = [];
    for ($i = 0; $i < count($item_ids); $i++) {
        $iid = (int) $item_ids[$i];
        $q   = (float) ($qtys[$i] ?? 0);
        $p   = (float) ($prices[$i] ?? 0);
        if ($iid > 0 && $q > 0) {
            $lines[] = ['item_id' => $iid, 'quantity' => $q, 'unit_price' => $p];
        }
    }

    // التحقق
    $errors = [];
    if ($vendor_id <= 0)      $errors[] = 'اختر المورد.';
    if (empty($order_date))   $errors[] = 'أدخل تاريخ الأوردر.';
    if (empty($lines))        $errors[] = 'أضِف صنفاً واحداً على الأقل بكمية صحيحة.';

    if ($errors) {
        set_flash('error', implode(' ', $errors));
        redirect('index.php?page=order_new' . ($post_order_id ? '&id=' . $post_order_id : ''));
    }

    // الحفظ داخل معاملة (transaction) لضمان التكامل
    try {
        $pdo->beginTransaction();

        if ($post_order_id > 0) {
            // تعديل: تحديث رأس الأوردر ثم استبدال سطوره
            $stmt = $pdo->prepare('UPDATE orders SET vendor_id=?, order_date=?, usd_rate=?, notes=? WHERE id=?');
            $stmt->execute([$vendor_id, $order_date, $usd_rate, $notes, $post_order_id]);
            $pdo->prepare('DELETE FROM order_items WHERE order_id = ?')->execute([$post_order_id]);
            $oid = $post_order_id;
        } else {
            // جديد
            $stmt = $pdo->prepare('INSERT INTO orders (vendor_id, order_date, usd_rate, notes) VALUES (?,?,?,?)');
            $stmt->execute([$vendor_id, $order_date, $usd_rate, $notes]);
            $oid = (int) $pdo->lastInsertId();
        }

        // إدراج السطور (line_total عمود محسوب في قاعدة البيانات)
        $li = $pdo->prepare('INSERT INTO order_items (order_id, item_id, quantity, unit_price_egp) VALUES (?,?,?,?)');
        foreach ($lines as $ln) {
            $li->execute([$oid, $ln['item_id'], $ln['quantity'], $ln['unit_price']]);
        }

        $pdo->commit();
        set_flash('success', $post_order_id ? 'تم تعديل الأوردر بنجاح.' : 'تم حفظ الأوردر بنجاح.');
        redirect('index.php?page=order_view&id=' . $oid);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        set_flash('error', 'حدث خطأ أثناء الحفظ. تأكد من صحة البيانات وحاول مجدداً.');
        redirect('index.php?page=order_new' . ($post_order_id ? '&id=' . $post_order_id : ''));
    }
}

/* ---------------- تجهيز بيانات العرض ---------------- */
$vendors    = $pdo->query('SELECT id, name FROM vendors ORDER BY name')->fetchAll();
$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();

// كل الأصناف مع نص العرض للبحث
$items_raw = $pdo->query('SELECT id, name, specs, unit FROM items ORDER BY name')->fetchAll();
$items_js = [];
foreach ($items_raw as $it) {
    $label = $it['name']
        . ($it['specs'] !== '' ? ' — ' . $it['specs'] : '')
        . ($it['unit'] !== '' ? ' (' . $it['unit'] . ')' : '');
    $items_js[] = ['id' => (int)$it['id'], 'name' => $it['name'], 'label' => $label];
}
// خريطة id -> label لعرض السطور في وضع التعديل
$label_by_id = [];
foreach ($items_js as $ij) { $label_by_id[$ij['id']] = $ij['label']; }

// وضع التعديل: تحميل الأوردر وسطوره
$order = null;
$order_lines = [];
if ($order_id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    if ($order) {
        $ls = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
        $ls->execute([$order_id]);
        $order_lines = $ls->fetchAll();
    } else {
        $order_id = 0; // غير موجود — عامله كجديد
    }
}

$is_edit = $order_id > 0;
$page_title = $is_edit ? 'تعديل أوردر' : 'أوردر جديد';
$active = $is_edit ? 'orders' : 'order_new';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h3 fw-bold mb-0"><?= $is_edit ? '✏️ تعديل أوردر' : '➕ أوردر جديد' ?></h1>
    <a href="index.php?page=orders" class="btn btn-outline-secondary">↩️ رجوع للأوردرات</a>
</div>

<?php if (!$vendors): ?>
    <div class="alert alert-warning">
        يجب إضافة مورد واحد على الأقل قبل إنشاء أوردر.
        <a href="index.php?page=vendors" class="alert-link">أضِف مورداً الآن</a>.
    </div>
<?php else: ?>

<form method="post" id="orderForm">
    <?= csrf_field() ?>
    <input type="hidden" id="csrfToken" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="order_id" value="<?= $is_edit ? (int)$order['id'] : 0 ?>">

    <!-- بيانات رأس الأوردر -->
    <div class="card mb-3">
        <div class="card-header">🧾 بيانات الأوردر</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold">المورد <span class="text-danger">*</span></label>
                    <select name="vendor_id" class="form-select form-select-lg" required>
                        <option value="">— اختر المورد —</option>
                        <?php foreach ($vendors as $v): ?>
                            <option value="<?= (int)$v['id'] ?>"
                                <?= ($is_edit && (int)$order['vendor_id'] === (int)$v['id']) ? 'selected' : '' ?>>
                                <?= e($v['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">تاريخ الأوردر <span class="text-danger">*</span></label>
                    <input type="date" name="order_date" class="form-control form-control-lg"
                           value="<?= $is_edit ? e($order['order_date']) : date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">سعر الدولار</label>
                    <input type="number" step="0.0001" min="0" name="usd_rate" id="usd_rate"
                           class="form-control form-control-lg"
                           value="<?= $is_edit ? e($order['usd_rate']) : '' ?>" placeholder="48.75">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">ملاحظات</label>
                    <input type="text" name="notes" class="form-control form-control-lg"
                           value="<?= $is_edit ? e($order['notes']) : '' ?>" placeholder="اختياري">
                </div>
            </div>
        </div>
    </div>

    <!-- سطور الأصناف -->
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span>📦 أصناف الأوردر</span>
            <div>
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal"
                        data-bs-target="#newItemModal">➕ صنف جديد</button>
                <button type="button" class="btn btn-primary btn-sm" id="addLineBtn">➕ إضافة سطر</button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>الصنف</th><th>الكمية</th>
                            <th>سعر الوحدة (ج.م)</th><th>الإجمالي</th><th></th>
                        </tr>
                    </thead>
                    <tbody id="orderLines">
                        <?php if ($is_edit && $order_lines): ?>
                            <?php foreach ($order_lines as $ln): ?>
                                <?php $lbl = $label_by_id[(int)$ln['item_id']] ?? ('#' . (int)$ln['item_id']); ?>
                                <tr class="order-line">
                                    <td style="min-width:200px">
                                        <input type="text" class="form-control item-input" list="itemsDatalist"
                                               value="<?= e($lbl) ?>" placeholder="اكتب للبحث عن صنف..."
                                               autocomplete="off" required>
                                        <input type="hidden" name="item_id[]" class="item-id-input"
                                               value="<?= (int)$ln['item_id'] ?>" required>
                                        <div class="invalid-feedback">اختر صنفاً من القائمة.</div>
                                    </td>
                                    <td style="min-width:110px">
                                        <input type="number" step="0.001" min="0.001" name="quantity[]"
                                               class="form-control qty-input" value="<?= e($ln['quantity']) ?>" required>
                                    </td>
                                    <td style="min-width:130px">
                                        <input type="number" step="0.01" min="0" name="unit_price[]"
                                               class="form-control price-input" value="<?= e($ln['unit_price_egp']) ?>" required>
                                    </td>
                                    <td class="line-total-cell">0.00 ج.م</td>
                                    <td><button type="button" class="btn btn-outline-danger btn-sm remove-line" title="حذف السطر">🗑️</button></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <!-- في وضع الإضافة تُنشأ السطور تلقائياً عبر JavaScript -->
                    </tbody>
                </table>
            </div>
            <datalist id="itemsDatalist"></datalist>
        </div>
    </div>

    <!-- المجموع الكلي والحفظ -->
    <div class="row g-3 align-items-stretch">
        <div class="col-md-6">
            <div class="grand-total-box h-100 d-flex flex-column justify-content-center">
                <div>الإجمالي الكلي</div>
                <div class="amount" id="grandTotalEgp">0.00 ج.م</div>
                <div id="grandTotalUsd" class="small">— أدخل سعر الدولار لعرض المعادل</div>
            </div>
        </div>
        <div class="col-md-6 d-flex align-items-center">
            <button type="submit" class="btn btn-success btn-lg w-100 fw-bold" style="min-height:80px">
                💾 <?= $is_edit ? 'حفظ التعديلات' : 'حفظ الأوردر' ?>
            </button>
        </div>
    </div>
</form>

<!-- نافذة إضافة صنف جديد سريعاً -->
<div class="modal fade" id="newItemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">➕ صنف جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body">
                <form id="newItemForm" onsubmit="return false;">
                    <div class="mb-3">
                        <label class="form-label fw-bold">اسم الصنف <span class="text-danger">*</span></label>
                        <input type="text" id="newItemName" class="form-control form-control-lg" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">المواصفات</label>
                        <input type="text" id="newItemSpecs" class="form-control">
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label fw-bold">الوحدة</label>
                            <input type="text" id="newItemUnit" class="form-control" list="unitsListModal" placeholder="كجم...">
                            <datalist id="unitsListModal">
                                <?php foreach (default_units() as $u): ?>
                                    <option value="<?= e($u) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">المجموعة</label>
                            <select id="newItemCategory" class="form-select">
                                <option value="0">— بدون —</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-success" id="saveNewItemBtn">حفظ واختيار الصنف</button>
            </div>
        </div>
    </div>
</div>

<script>
    // تمرير قائمة الأصناف إلى JavaScript لبناء البحث
    window.ITEMS = <?= json_encode($items_js, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>

<?php endif; // vendors ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
