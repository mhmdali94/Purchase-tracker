<?php
/**
 * pages/categories.php — إدارة المجموعات (إضافة / تعديل / حذف).
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

// ---- معالجة الإجراءات (POST) قبل أي إخراج ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id   = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            set_flash('error', 'اسم المجموعة مطلوب.');
        } elseif ($id > 0) {
            $stmt = $pdo->prepare('UPDATE categories SET name = ? WHERE id = ?');
            $stmt->execute([$name, $id]);
            set_flash('success', 'تم تعديل المجموعة بنجاح.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO categories (name) VALUES (?)');
            $stmt->execute([$name]);
            set_flash('success', 'تمت إضافة المجموعة بنجاح.');
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        // الأصناف المرتبطة ستُفصل تلقائياً (category_id = NULL)
        $stmt = $pdo->prepare('DELETE FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        set_flash('success', 'تم حذف المجموعة.');
    }
    redirect('index.php?page=categories');
}

// ---- تحميل البيانات ----
$categories = $pdo->query(
    'SELECT c.id, c.name, COUNT(i.id) AS items_count
     FROM categories c
     LEFT JOIN items i ON i.category_id = c.id
     GROUP BY c.id ORDER BY c.name'
)->fetchAll();

// صنف قيد التعديل (اختياري)
$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch();
}

$page_title = 'المجموعات';
$active = 'categories';
require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="h3 fw-bold mb-4">🗂️ المجموعات</h1>

<div class="row g-4">
    <!-- نموذج الإضافة/التعديل -->
    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-header"><?= $edit ? '✏️ تعديل مجموعة' : '➕ إضافة مجموعة' ?></div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= $edit ? (int)$edit['id'] : 0 ?>">
                    <div class="mb-3">
                        <label class="form-label fw-bold">اسم المجموعة</label>
                        <input type="text" name="name" class="form-control form-control-lg"
                               value="<?= $edit ? e($edit['name']) : '' ?>"
                               placeholder="مثال: مواد غذائية" required autofocus>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <?= $edit ? 'حفظ التعديل' : 'إضافة' ?>
                    </button>
                    <?php if ($edit): ?>
                        <a href="index.php?page=categories" class="btn btn-outline-secondary w-100 mt-2">إلغاء</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- القائمة -->
    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-header">قائمة المجموعات (<?= count($categories) ?>)</div>
            <div class="card-body p-0">
                <?php if (!$categories): ?>
                    <div class="p-4 text-center text-muted">لا توجد مجموعات بعد.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead>
                                <tr><th>الاسم</th><th>عدد الأصناف</th><th class="text-end">إجراءات</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $c): ?>
                                    <tr>
                                        <td><?= e($c['name']) ?></td>
                                        <td><span class="badge bg-secondary"><?= (int)$c['items_count'] ?></span></td>
                                        <td class="text-end">
                                            <a href="index.php?page=categories&edit=<?= (int)$c['id'] ?>"
                                               class="btn btn-sm btn-outline-primary">✏️ تعديل</a>
                                            <form method="post" class="d-inline js-confirm-delete"
                                                  data-confirm="حذف مجموعة «<?= e($c['name']) ?>»؟ سيتم فصل أصنافها دون حذفها.">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                                <button class="btn btn-sm btn-outline-danger">🗑️ حذف</button>
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
