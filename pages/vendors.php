<?php
/**
 * pages/vendors.php — إدارة الموردين (إضافة / تعديل / حذف).
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id      = (int) ($_POST['id'] ?? 0);
        $name    = trim($_POST['name'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $notes   = trim($_POST['notes'] ?? '');

        if ($name === '') {
            set_flash('error', 'اسم المورد مطلوب.');
        } elseif ($id > 0) {
            $stmt = $pdo->prepare('UPDATE vendors SET name=?, phone=?, address=?, notes=? WHERE id=?');
            $stmt->execute([$name, $phone, $address, $notes, $id]);
            set_flash('success', 'تم تعديل بيانات المورد.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO vendors (name, phone, address, notes) VALUES (?,?,?,?)');
            $stmt->execute([$name, $phone, $address, $notes]);
            set_flash('success', 'تمت إضافة المورد بنجاح.');
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        // منع الحذف إذا كان للمورد أوردرات (للحفاظ على السجل)
        $chk = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE vendor_id = ?');
        $chk->execute([$id]);
        if ((int)$chk->fetchColumn() > 0) {
            set_flash('error', 'لا يمكن حذف هذا المورد لوجود أوردرات مرتبطة به.');
        } else {
            $stmt = $pdo->prepare('DELETE FROM vendors WHERE id = ?');
            $stmt->execute([$id]);
            set_flash('success', 'تم حذف المورد.');
        }
    }
    redirect('index.php?page=vendors');
}

$vendors = $pdo->query(
    'SELECT v.*, COUNT(o.id) AS orders_count
     FROM vendors v LEFT JOIN orders o ON o.vendor_id = v.id
     GROUP BY v.id ORDER BY v.name'
)->fetchAll();

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM vendors WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch();
}

$page_title = 'الموردين';
$active = 'vendors';
require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="h3 fw-bold mb-4">🏪 الموردين</h1>

<div class="row g-4">
    <!-- النموذج -->
    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-header"><?= $edit ? '✏️ تعديل مورد' : '➕ إضافة مورد' ?></div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= $edit ? (int)$edit['id'] : 0 ?>">
                    <div class="mb-3">
                        <label class="form-label fw-bold">اسم المورد <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-lg"
                               value="<?= $edit ? e($edit['name']) : '' ?>" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">رقم الهاتف</label>
                        <input type="text" name="phone" class="form-control"
                               value="<?= $edit ? e($edit['phone']) : '' ?>" placeholder="0100...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">العنوان</label>
                        <input type="text" name="address" class="form-control"
                               value="<?= $edit ? e($edit['address']) : '' ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">ملاحظات</label>
                        <textarea name="notes" class="form-control" rows="2"><?= $edit ? e($edit['notes']) : '' ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <?= $edit ? 'حفظ التعديل' : 'إضافة' ?>
                    </button>
                    <?php if ($edit): ?>
                        <a href="index.php?page=vendors" class="btn btn-outline-secondary w-100 mt-2">إلغاء</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- القائمة -->
    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-header">قائمة الموردين (<?= count($vendors) ?>)</div>
            <div class="card-body p-0">
                <?php if (!$vendors): ?>
                    <div class="p-4 text-center text-muted">لا يوجد موردون بعد.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle vendors-list-table">
                            <thead>
                                <tr>
                                    <th>الاسم</th><th>الهاتف</th><th>العنوان</th>
                                    <th>الأوردرات</th><th class="text-end">إجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vendors as $v): ?>
                                    <tr>
                                        <td>
                                            <?= e($v['name']) ?>
                                            <?php if ($v['notes']): ?>
                                                <span class="usd-note"><?= e($v['notes']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $v['phone'] ? e($v['phone']) : '—' ?></td>
                                        <td><?= $v['address'] ? e($v['address']) : '—' ?></td>
                                        <td><span class="badge bg-secondary"><?= (int)$v['orders_count'] ?></span></td>
                                        <td class="text-end" style="white-space:nowrap">
                                            <a href="index.php?page=vendors&edit=<?= (int)$v['id'] ?>"
                                               class="btn btn-sm btn-outline-primary">✏️</a>
                                            <form method="post" class="d-inline js-confirm-delete"
                                                  data-confirm="حذف المورد «<?= e($v['name']) ?>»؟">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
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
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
