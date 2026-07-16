<?php
/**
 * pages/items.php — إدارة الأصناف مع بحث مباشر وتصفية بالمجموعة.
 * الأصناف غير مرتبطة بمورد — يمكن شراؤها من أي مورد.
 * تدعم الآن رفع صورة لكل صنف وتخزينها كـ BLOB في قاعدة البيانات.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

const PHOTO_MAX_BYTES = 4 * 1024 * 1024; // 4 MB
const ALLOWED_MIME    = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id          = (int) ($_POST['id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $specs       = trim($_POST['specs'] ?? '');
        $unit        = trim($_POST['unit'] ?? '');
        $category_id = (int) ($_POST['category_id'] ?? 0) ?: null;
        $delete_photo = !empty($_POST['delete_photo']);

        if ($name === '') {
            set_flash('error', 'اسم الصنف مطلوب.');
            redirect('index.php?page=items' . ($id ? '&edit=' . $id : ''));
        }

        // --- معالجة الصورة ---
        $new_photo      = null;
        $new_photo_mime = null;

        $file = $_FILES['photo'] ?? null;
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            // التحقق من نوع الملف باستخدام finfo (أكثر أماناً من MIME في $_FILES)
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($file['tmp_name']);
            if (!in_array($mime, ALLOWED_MIME, true)) {
                set_flash('error', 'نوع الملف غير مدعوم. المسموح: JPEG، PNG، GIF، WebP.');
                redirect('index.php?page=items' . ($id ? '&edit=' . $id : ''));
            }
            if ($file['size'] > PHOTO_MAX_BYTES) {
                set_flash('error', 'حجم الصورة كبير جداً (الحد الأقصى 4 ميجابايت).');
                redirect('index.php?page=items' . ($id ? '&edit=' . $id : ''));
            }
            $new_photo      = file_get_contents($file['tmp_name']);
            $new_photo_mime = $mime;
        } elseif ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
            set_flash('error', 'حدث خطأ أثناء رفع الصورة (كود: ' . $file['error'] . ').');
            redirect('index.php?page=items' . ($id ? '&edit=' . $id : ''));
        }

        if ($id > 0) {
            // تعديل
            if ($new_photo !== null) {
                // رفع صورة جديدة
                $stmt = $pdo->prepare('UPDATE items SET name=?, specs=?, unit=?, category_id=?, photo=?, photo_mime=? WHERE id=?');
                $stmt->execute([$name, $specs, $unit, $category_id, $new_photo, $new_photo_mime, $id]);
            } elseif ($delete_photo) {
                // حذف الصورة الحالية
                $stmt = $pdo->prepare('UPDATE items SET name=?, specs=?, unit=?, category_id=?, photo=NULL, photo_mime=NULL WHERE id=?');
                $stmt->execute([$name, $specs, $unit, $category_id, $id]);
            } else {
                // بدون تغيير الصورة
                $stmt = $pdo->prepare('UPDATE items SET name=?, specs=?, unit=?, category_id=? WHERE id=?');
                $stmt->execute([$name, $specs, $unit, $category_id, $id]);
            }
            set_flash('success', 'تم تعديل الصنف بنجاح.');
        } else {
            // إضافة جديدة
            $stmt = $pdo->prepare('INSERT INTO items (name, specs, unit, category_id, photo, photo_mime) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$name, $specs, $unit, $category_id, $new_photo, $new_photo_mime]);
            set_flash('success', 'تمت إضافة الصنف بنجاح.');
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $chk = $pdo->prepare('SELECT COUNT(*) FROM order_items WHERE item_id = ?');
        $chk->execute([$id]);
        if ((int)$chk->fetchColumn() > 0) {
            set_flash('error', 'لا يمكن حذف هذا الصنف لأنه مستخدم في أوردرات (سجل الأسعار).');
        } else {
            $pdo->prepare('DELETE FROM items WHERE id = ?')->execute([$id]);
            set_flash('success', 'تم حذف الصنف.');
        }
    }
    redirect('index.php?page=items');
}

$categories = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
// نجلب الصورة كـ has_photo فقط لتفادي نقل BLOBs ضخمة في قائمة الأصناف
$items = $pdo->query(
    'SELECT i.id, i.name, i.specs, i.unit, i.category_id, i.created_at,
            (i.photo IS NOT NULL) AS has_photo,
            c.name AS category_name
     FROM items i LEFT JOIN categories c ON c.id = i.category_id
     ORDER BY i.name'
)->fetchAll();

$edit = null;
$edit_has_photo = false;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT id, name, specs, unit, category_id, (photo IS NOT NULL) AS has_photo FROM items WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch();
    $edit_has_photo = $edit && (bool)$edit['has_photo'];
}

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
                <form method="post" enctype="multipart/form-data">
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

                    <!-- قسم الصورة -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">📷 صورة مرجعية</label>

                        <?php if ($edit_has_photo): ?>
                            <!-- عرض الصورة الحالية -->
                            <div class="item-current-photo mb-2">
                                <img src="ajax/item_photo.php?id=<?= (int)$edit['id'] ?>&t=<?= time() ?>"
                                     alt="صورة الصنف الحالية" class="item-thumb-form">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="delete_photo"
                                           id="deletePhotoCheck" value="1">
                                    <label class="form-check-label text-danger small fw-bold" for="deletePhotoCheck">
                                        🗑️ حذف الصورة الحالية
                                    </label>
                                </div>
                            </div>
                        <?php endif; ?>

                        <input type="file" name="photo" id="photoInput" class="form-control"
                               accept="image/jpeg,image/png,image/gif,image/webp">
                        <div class="form-text text-muted">
                            JPEG، PNG، GIF، WebP — بحد أقصى 4 ميجابايت
                        </div>

                        <!-- معاينة مباشرة للصورة المختارة -->
                        <div id="photoPreviewWrap" class="mt-2 d-none">
                            <p class="small text-muted mb-1">معاينة:</p>
                            <img id="photoPreviewImg" src="" alt="معاينة" class="item-thumb-form">
                        </div>
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
                                <th style="width:48px"></th>
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
                                        <?php if ($it['has_photo']): ?>
                                            <img src="ajax/item_photo.php?id=<?= (int)$it['id'] ?>"
                                                 alt="<?= e($it['name']) ?>"
                                                 class="item-thumb"
                                                 loading="lazy"
                                                 data-item-id="<?= (int)$it['id'] ?>"
                                                 data-item-name="<?= e($it['name']) ?>"
                                                 onclick="openPhotoModal(this)">
                                        <?php else: ?>
                                            <span class="item-thumb-placeholder">📦</span>
                                        <?php endif; ?>
                                    </td>
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

<!-- مودال عرض الصورة بالحجم الكامل -->
<div class="modal fade" id="photoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="photoModalTitle">صورة الصنف</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body text-center p-2">
                <img id="photoModalImg" src="" alt="صورة الصنف" class="item-thumb-lg">
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

// معاينة الصورة عند اختيارها
document.getElementById('photoInput').addEventListener('change', function () {
    const wrap = document.getElementById('photoPreviewWrap');
    const img  = document.getElementById('photoPreviewImg');
    if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            img.src = e.target.result;
            wrap.classList.remove('d-none');
        };
        reader.readAsDataURL(this.files[0]);
    } else {
        wrap.classList.add('d-none');
        img.src = '';
    }
});

// فتح مودال عرض الصورة الكبيرة
function openPhotoModal(el) {
    document.getElementById('photoModalTitle').textContent = el.dataset.itemName || 'صورة الصنف';
    document.getElementById('photoModalImg').src = el.src;
    new bootstrap.Modal(document.getElementById('photoModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
