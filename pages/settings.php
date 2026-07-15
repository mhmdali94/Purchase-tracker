<?php
/**
 * pages/settings.php — الإعدادات:
 *   - تغيير اسم المستخدم وكلمة المرور.
 *   - نسخة احتياطية (تنزيل ملف SQL كامل) واستعادة (رفع ملف SQL).
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

/* ==================================================================
   دوال النسخ الاحتياطي / الاستعادة
   ================================================================== */

/**
 * توليد ملف SQL كامل (بنية الجداول + كل البيانات) اعتماداً على PDO فقط،
 * بدون الحاجة لأي أدوات خارجية. يتجاهل الأعمدة المحسوبة تلقائياً.
 */
function generate_backup_sql(PDO $pdo): string
{
    $out  = "-- نسخة احتياطية من متتبّع أسعار المشتريات\n";
    $out .= "-- تاريخ الإنشاء: " . date('Y-m-d H:i:s') . "\n";
    $out .= "SET NAMES utf8mb4;\n";
    $out .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        // بنية الجدول
        $create = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_ASSOC);
        $createSql = $create['Create Table'] ?? '';
        if ($createSql === '') {
            continue; // تجاهل الـ Views إن وُجدت
        }

        // تحديد الأعمدة المحسوبة (لا تُدرج في INSERT)
        $genCols = [];
        foreach ($pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC) as $ci) {
            if (stripos($ci['Extra'] ?? '', 'GENERATED') !== false) {
                $genCols[] = $ci['Field'];
            }
        }

        $out .= "DROP TABLE IF EXISTS `$table`;\n";
        $out .= $createSql . ";\n\n";

        // البيانات
        $rows = $pdo->query('SELECT * FROM `' . $table . '`');
        while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
            $cols = [];
            $vals = [];
            foreach ($row as $col => $val) {
                if (in_array($col, $genCols, true)) {
                    continue; // تخطّي الأعمدة المحسوبة
                }
                $cols[] = '`' . $col . '`';
                $vals[] = ($val === null) ? 'NULL' : $pdo->quote((string) $val);
            }
            if ($cols) {
                $out .= "INSERT INTO `$table` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ");\n";
            }
        }
        $out .= "\n";
    }

    $out .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $out;
}

/**
 * تقسيم نص SQL إلى جُمل منفصلة مع مراعاة النصوص بين علامات الاقتباس
 * والتعليقات، حتى لا تنكسر الجملة على فاصلة منقوطة داخل قيمة نصية،
 * ولا تُحذف قيمة تحتوي سطراً يبدأ بشرطتين.
 */
function split_sql_statements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $inString = false;
    $stringChar = '';
    $len = strlen($sql);

    for ($i = 0; $i < $len; $i++) {
        $ch   = $sql[$i];
        $next = ($i + 1 < $len) ? $sql[$i + 1] : '';

        // داخل نص مقتبس: انسخ كل شيء حتى نهاية النص
        if ($inString) {
            $buffer .= $ch;
            if ($ch === '\\' && $i + 1 < $len) {              // هروب الحرف التالي
                $buffer .= $next;
                $i++;
                continue;
            }
            if ($ch === $stringChar) {
                if ($next === $stringChar) {                  // اقتباس مزدوج داخل النص
                    $buffer .= $next;
                    $i++;
                    continue;
                }
                $inString = false;
            }
            continue;
        }

        // تعليق سطري: --  (يتبعه فراغ/نهاية سطر) أو #
        if ($ch === '-' && $next === '-') {
            $third = ($i + 2 < $len) ? $sql[$i + 2] : "\n";
            if ($third === ' ' || $third === "\t" || $third === "\n" || $third === "\r" || $i + 2 >= $len) {
                while ($i < $len && $sql[$i] !== "\n") { $i++; }
                continue;
            }
        }
        if ($ch === '#') {
            while ($i < $len && $sql[$i] !== "\n") { $i++; }
            continue;
        }
        // تعليق كتلة: /* ... */
        if ($ch === '/' && $next === '*') {
            $i += 2;
            while ($i + 1 < $len && !($sql[$i] === '*' && $sql[$i + 1] === '/')) { $i++; }
            $i++; // يقف على '/'
            continue;
        }

        // بداية نص مقتبس
        if ($ch === "'" || $ch === '"') {
            $inString = true;
            $stringChar = $ch;
            $buffer .= $ch;
            continue;
        }

        // نهاية جملة
        if ($ch === ';') {
            $stmt = trim($buffer);
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $ch;
    }

    $last = trim($buffer);
    if ($last !== '') {
        $statements[] = $last;
    }
    return $statements;
}

/* ==================================================================
   تنزيل النسخة الاحتياطية (قبل أي إخراج HTML)
   ================================================================== */
if (($_GET['action'] ?? '') === 'backup') {
    $sql = generate_backup_sql($pdo);
    $filename = 'purchase_tracker_backup_' . date('Y-m-d_His') . '.sql';

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($sql));
    header('Cache-Control: no-store');
    echo $sql;
    exit;
}

/* ==================================================================
   معالجة نماذج POST
   ================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? 'save_account';

    /* ---------- تغيير بيانات الدخول ---------- */
    if ($action === 'save_account') {
        $current  = $_POST['current_password'] ?? '';
        $username = trim($_POST['username'] ?? '');
        $new      = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($current, $user['password_hash'])) {
            set_flash('error', 'كلمة المرور الحالية غير صحيحة.');
        } elseif ($username === '') {
            set_flash('error', 'اسم المستخدم مطلوب.');
        } elseif ($new !== '' && $new !== $confirm) {
            set_flash('error', 'كلمة المرور الجديدة وتأكيدها غير متطابقين.');
        } elseif ($new !== '' && mb_strlen($new) < 4) {
            set_flash('error', 'كلمة المرور الجديدة قصيرة جداً (٤ أحرف على الأقل).');
        } else {
            if ($new !== '') {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $u = $pdo->prepare('UPDATE users SET username=?, password_hash=? WHERE id=?');
                $u->execute([$username, $hash, $user['id']]);
            } else {
                $u = $pdo->prepare('UPDATE users SET username=? WHERE id=?');
                $u->execute([$username, $user['id']]);
            }
            $_SESSION['username'] = $username;
            set_flash('success', 'تم حفظ الإعدادات بنجاح.');
        }
        redirect('index.php?page=settings');
    }

    /* ---------- استعادة نسخة احتياطية ---------- */
    if ($action === 'restore') {
        $file = $_FILES['backup_file'] ?? null;

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            set_flash('error', 'يُرجى اختيار ملف نسخة احتياطية صالح (.sql).');
            redirect('index.php?page=settings');
        }

        $name = strtolower($file['name'] ?? '');
        if (substr($name, -4) !== '.sql') {
            set_flash('error', 'صيغة الملف غير صحيحة. يجب أن يكون الملف بامتداد .sql');
            redirect('index.php?page=settings');
        }

        $content = file_get_contents($file['tmp_name']);
        if ($content === false || trim($content) === '') {
            set_flash('error', 'تعذّر قراءة الملف أو أنه فارغ.');
            redirect('index.php?page=settings');
        }

        $statements = split_sql_statements($content);
        if (!$statements) {
            set_flash('error', 'لم يُعثر على أي أوامر SQL صالحة داخل الملف.');
            redirect('index.php?page=settings');
        }

        // تنفيذ الاستعادة (تعطيل فحص المفاتيح الأجنبية أثناء العملية)
        $ok = 0;
        $fail = 0;
        $firstError = '';
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        } catch (Throwable $e) {
            // تجاهل
        }
        foreach ($statements as $stmt) {
            try {
                $pdo->exec($stmt);
                $ok++;
            } catch (Throwable $e) {
                $fail++;
                if ($firstError === '') {
                    $firstError = $e->getMessage();
                }
            }
        }
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        } catch (Throwable $e) {
            // تجاهل
        }

        if ($fail === 0) {
            set_flash('success', 'تمت الاستعادة بنجاح (' . $ok . ' أمر). قد تحتاج لتسجيل الدخول مجدداً إذا تغيّرت بيانات الحساب.');
        } else {
            set_flash('error', 'اكتملت الاستعادة مع بعض الأخطاء: نجح ' . $ok . ' وفشل ' . $fail . '. أول خطأ: ' . mb_substr($firstError, 0, 200));
        }
        redirect('index.php?page=settings');
    }

    /* ---------- تصفير كل البيانات (مع الاحتفاظ بحساب الدخول) ---------- */
    if ($action === 'reset_data') {
        $current = $_POST['reset_password'] ?? '';

        // التأكد من كلمة المرور قبل الحذف الكامل
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $u = $stmt->fetch();

        if (!$u || !password_verify($current, $u['password_hash'])) {
            set_flash('error', 'كلمة المرور غير صحيحة. لم يتم حذف أي بيانات.');
            redirect('index.php?page=settings');
        }

        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            // حذف البيانات (الجداول الفرعية أولاً) مع تصفير العدّاد
            foreach (['order_items', 'orders', 'items', 'vendors', 'categories'] as $t) {
                $pdo->exec('DELETE FROM `' . $t . '`');
                $pdo->exec('ALTER TABLE `' . $t . '` AUTO_INCREMENT = 1');
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

            // إعادة إدراج المجموعات الافتراضية (كحالة التثبيت الأولى)
            $default_categories = [
                'مواد غذائية', 'مشروبات', 'أدوات نظافة', 'خضروات وفاكهة',
                'لحوم ودواجن', 'مستلزمات مكتبية', 'أخرى',
            ];
            $ins = $pdo->prepare('INSERT INTO categories (name) VALUES (?)');
            foreach ($default_categories as $c) {
                $ins->execute([$c]);
            }

            set_flash('success', 'تم حذف كل البيانات وإعادة التطبيق إلى حالته الأولى. (تم الاحتفاظ بحساب الدخول)');
        } catch (Throwable $e) {
            set_flash('error', 'تعذّر حذف البيانات: ' . mb_substr($e->getMessage(), 0, 200));
        }
        redirect('index.php?page=settings');
    }

    // إجراء غير معروف
    redirect('index.php?page=settings');
}

$page_title = 'الإعدادات';
$active = 'settings';
require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="h3 fw-bold mb-4">⚙️ الإعدادات</h1>

<div class="row g-4 justify-content-center">
    <!-- بيانات الدخول -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">🔐 بيانات الدخول</div>
            <div class="card-body">
                <form method="post" autocomplete="off">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_account">
                    <div class="mb-3">
                        <label class="form-label fw-bold">اسم المستخدم</label>
                        <input type="text" name="username" class="form-control form-control-lg"
                               value="<?= e(current_username()) ?>" required>
                    </div>
                    <hr>
                    <div class="mb-3">
                        <label class="form-label fw-bold">كلمة المرور الحالية <span class="text-danger">*</span></label>
                        <input type="password" name="current_password" class="form-control"
                               placeholder="للتأكيد قبل الحفظ" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">كلمة المرور الجديدة</label>
                        <input type="password" name="new_password" class="form-control"
                               placeholder="اتركها فارغة إن لم ترغب بتغييرها">
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">تأكيد كلمة المرور الجديدة</label>
                        <input type="password" name="confirm_password" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100">حفظ الإعدادات</button>
                </form>
            </div>
        </div>
    </div>

    <!-- النسخ الاحتياطي والاستعادة -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">💾 النسخ الاحتياطي والاستعادة</div>
            <div class="card-body">

                <!-- تنزيل نسخة -->
                <h6 class="fw-bold mb-2">📥 تنزيل نسخة احتياطية</h6>
                <p class="text-muted small mb-3">
                    احفظ نسخة كاملة من قاعدة البيانات (كل الأصناف والموردين والأوردرات)
                    كملف <code>.sql</code> على جهازك. يُنصح بعملها بشكل دوري.
                </p>
                <a href="index.php?page=settings&action=backup" class="btn btn-success btn-lg w-100 mb-4">
                    ⬇️ تنزيل نسخة احتياطية الآن
                </a>

                <hr>

                <!-- استعادة نسخة -->
                <h6 class="fw-bold mb-2">📤 استعادة من نسخة احتياطية</h6>
                <div class="alert alert-warning small mb-3">
                    ⚠️ تحذير: الاستعادة ستحذف كل البيانات الحالية وتستبدلها ببيانات الملف.
                    تأكد من اختيار ملف صحيح تم تنزيله من هذا التطبيق.
                </div>
                <form method="post" enctype="multipart/form-data" class="js-confirm-delete"
                      data-confirm="سيتم استبدال كل البيانات الحالية ببيانات الملف. لا يمكن التراجع. هل أنت متأكد؟">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="restore">
                    <div class="mb-3">
                        <label class="form-label fw-bold">اختر ملف النسخة الاحتياطية (.sql)</label>
                        <input type="file" name="backup_file" accept=".sql" class="form-control form-control-lg" required>
                    </div>
                    <button type="submit" class="btn btn-danger btn-lg w-100">♻️ استعادة البيانات</button>
                </form>

            </div>
        </div>
    </div>
</div>

<!-- منطقة الخطر: تصفير كل البيانات -->
<div class="row justify-content-center mt-4">
    <div class="col-lg-12">
        <div class="card border-danger">
            <div class="card-header text-danger">🧨 تصفير كل البيانات</div>
            <div class="card-body">
                <div class="alert alert-danger small mb-3">
                    ⚠️ هذا الإجراء يحذف <b>كل</b> الأوردرات والأصناف والموردين والمجموعات نهائياً،
                    ويعيد التطبيق إلى حالته الأولى. <b>لا يمكن التراجع.</b>
                    (يبقى حساب الدخول كما هو). يُنصَح بتنزيل نسخة احتياطية أولاً.
                </div>
                <form method="post" class="row g-2 align-items-end js-confirm-delete"
                      data-confirm="سيتم حذف كل بياناتك نهائياً (أوردرات، أصناف، موردين، مجموعات). لا يمكن التراجع. هل أنت متأكد تماماً؟">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reset_data">
                    <div class="col-md-8">
                        <label class="form-label fw-bold">أكّد بكلمة المرور الحالية</label>
                        <input type="password" name="reset_password" class="form-control"
                               placeholder="أدخل كلمة المرور للتأكيد" required autocomplete="off">
                    </div>
                    <div class="col-md-4 d-grid">
                        <button type="submit" class="btn btn-danger btn-lg">🗑️ حذف كل البيانات</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
