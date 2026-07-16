<?php
/**
 * pages/items.php — إدارة الأصناف مع بحث مباشر وتصفية بالمجموعة.
 * الأصناف غير مرتبطة بمورد — يمكن شراؤها من أي مورد.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id          = (int) ($_POST['id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $specs       = trim($_POST['specs'] ?? '');
        $unit        = trim($_POST['unit'] ?? '');
        $category_id = (int) ($_POST['category_id'] ?? 0) ?: null;

        if ($name === '') {
            set_flash('error', 'اسم الصنف مطلوب.');
        } elseif ($id > 0) {
            $stmt = $pdo->prepare('UPDATE items SET name=?, specs=?, unit=?, category_id=? WHERE id=?');
            $stmt->execute([$name, $specs, $unit, $category_id, $id]);
            set_flash('success', 'تم تعديل الصنف بنجاح.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO items (name, specs, unit, category_id) VALUES (?,?,?,?)');
            $stmt->execute([$name, $specs, $unit, $category_id]);
            set_flash('success', 'تمت إضافة الصنف بنجاح.');
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        // منع الحذف إذا كان الصنف مستخدماً في أوردرات (للحفاظ على سجل الأسعار)
        $chk = $pdo->prepare('SELECT COUNT(*) FROM order_items WHERE item_id = ?');
        $chk->execute([$id]);
        if ((int)$chk->fetchColumn() > 0) {
            set_flash('error', 'لا يمكن حذف هذا الصنف لأنه مستخدم في أوردرات (سجل الأسعار).');
        } else {
            $stmt = $pdo->prepare('DELETE FROM items WHERE id = ?');
            $stmt->execute([$id]);
            set_flash('success', 'تم حذف الصنف.');
        }
    }
    redirect('index.php?page=items');
}

$categories = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$items = $pdo->query(
    'SELECT i.*, c.name AS category_name
     FROM items i LEFT JOIN categories c ON c.id = i.category_id
     ORDER BY i.name'
)->fetchAll();

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM items WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch();
}

// دمج الوحدات الافتراضية مع الوحدات المستخدمة فعلياً
$used_units = $pdo->query("SELECT DISTINCT unit FROM items WHERE unit <> '' ")->fetchAll(PDO::FETCH_COLUMN);
$units = array_values(array_unique(array_merge(default_units(), $used_units)));

$page_title = 'الأصناف';
$active = 'items';
require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="h3 fw-bold mb-4">📦 الأصناف</h1>

<div class="row g-4">
    <!-- النموذج -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><?= $edit ? '✏️ تعديل صنف' : '➕ إضافة صنف' ?></div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= $edit ? (int)$edit['id'] : 0 ?>">
                    <div class="mb-3">
                        <label class="form-label fw-bold">اسم الصنف <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-lg"
                               value="<?= $edit ? e($edit['name']) : '' ?>" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">المواصفات</label>
                        <textarea name="specs" class="form-control" rows="2"
                                  placeholder="وصف المواصفات..."><?= $edit ? e($edit['specs']) : '' ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">وحدة القياس</label>
                        <input type="text" name="unit" class="form-control" list="unitsList"
                               value="<?= $edit ? e($edit['unit']) : '' ?>"
                               placeholder="اختر أو اكتب وحدة جديدة">
                        <datalist id="unitsList">
                            <?php foreach ($units as $u): ?>
                                <option value="<?= e($u) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">المجموعة</label>
                        <select name="category_id" class="form-select">
                            <option value="0">— بدون مجموعة —</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= (int)$c['id'] ?>"
                                    <?= ($edit && (int)$edit['category_id'] === (int)$c['id']) ? 'selected' : '' ?>>
                                    <?= e($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <?= $edit ? 'حفظ التعديل' : 'إضافة' ?>
                    </button>
                    <?php if ($edit): ?>
                        <a href="index.php?page=items" class="btn btn-outline-secondary w-100 mt-2">إلغاء</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- القائمة -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">قائمة الأصناف (<?= count($items) ?>)</div>
            <div class="card-body">
                <!-- أدوات البحث والتصفية -->
                <div class="row g-2 mb-3">
                    <div class="col-md-7">
                        <input type="text" id="itemSearch" class="form-control"
                               placeholder="🔍 ابحث بالاسم أو المواصفات...">
                    </div>
                    <div class="col-md-5">
                        <select id="categoryFilter" class="form-select">
                            <option value="">كل المجموعات</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle items-list-table" id="itemsTable">
                        <thead>
                            <tr>
                                <th>الاسم / المواصفات</th><th>الوحدة</th>
                                <th>المجموعة</th><th class="text-end">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $it): ?>
                                <tr class="item-row"
                                    data-name="<?= e(mb_strtolower($it['name'] . ' ' . $it['specs'])) ?>"
                                    data-cat="<?= (int)$it['category_id'] ?>">
                                    <td>
                                        <b><?= e($it['name']) ?></b>
                                        <?php if ($it['specs']): ?>
                                            <span class="usd-note"><?= e($it['specs']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $it['unit'] ? e($it['unit']) : '—' ?></td>
                                    <td>
                                        <?php if ($it['category_name']): ?>
                                            <span class="badge bg-info text-dark"><?= e($it['category_name']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end" style="white-space:nowrap">
                                        <a href="index.php?page=item_history&item_id=<?= (int)$it['id'] ?>"
                                           class="btn btn-sm btn-outline-info" title="سجل الأسعار">📈</a>
                                        <a href="index.php?page=items&edit=<?= (int)$it['id'] ?>"
                                           class="btn btn-sm btn-outline-primary">✏️</a>
                                        <form method="post" class="d-inline js-confirm-delete"
                                              data-confirm="حذف الصنف «<?= e($it['name']) ?>»؟">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger">🗑️</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div id="noItemsMsg" class="text-center text-muted p-3 d-none">لا توجد أصناف مطابقة.</div>
            </div>
        </div>
    </div>
</div>

<script>
// بحث مباشر + تصفية بالمجموعة (من جهة العميل)
(function () {
    const search = document.getElementById('itemSearch');
    const catSel = document.getElementById('categoryFilter');
    const rows   = Array.from(document.querySelectorAll('#itemsTable .item-row'));
    const noMsg  = document.getElementById('noItemsMsg');

    function apply() {
        const q = search.value.trim().toLowerCase();
        const cat = catSel.value;
        let visible = 0;
        rows.forEach(r => {
            const matchName = r.dataset.name.includes(q);
            const matchCat  = !cat || r.dataset.cat === cat;
            const show = matchName && matchCat;
            r.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        noMsg.classList.toggle('d-none', visible !== 0);
    }
    search.addEventListener('input', apply);
    catSel.addEventListener('change', apply);
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
