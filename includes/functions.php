<?php
/**
 * includes/functions.php
 * ------------------------------------------------------------------
 * دوال مساعدة عامة: بدء الجلسة، الحماية (CSRF)، تنظيف المخرجات (XSS)،
 * تنسيق الأرقام والتواريخ، ورسائل النجاح/الخطأ (flash messages).
 * ------------------------------------------------------------------
 */

// بدء الجلسة مرة واحدة فقط
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ============================================================
 *  الحماية من XSS: تهريب أي نص قبل طباعته في HTML
 * ============================================================ */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/* ============================================================
 *  رمز الحماية CSRF
 * ============================================================ */

// توليد الرمز إن لم يكن موجوداً، وإرجاعه
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// حقل مخفي جاهز للإدراج داخل النماذج
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

// التحقق من صحة الرمز عند استقبال طلب POST — يوقف التنفيذ عند الفشل
function csrf_check(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $sent = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $sent)) {
            http_response_code(419);
            die('انتهت صلاحية الجلسة أو رمز الحماية غير صحيح. يُرجى تحديث الصفحة وإعادة المحاولة.');
        }
    }
}

/* ============================================================
 *  رسائل النجاح / الخطأ (تُعرض في الصفحة التالية بعد إعادة التوجيه)
 * ============================================================ */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/* ============================================================
 *  إعادة التوجيه ثم إنهاء التنفيذ
 * ============================================================ */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/* ============================================================
 *  تنسيق الأرقام والعملات والتواريخ
 * ============================================================ */

// تنسيق مبلغ بالجنيه المصري مع فواصل الآلاف
function money_egp($amount): string
{
    return number_format((float) $amount, 2) . ' ' . CURRENCY;
}

// تنسيق كمية (يحذف الأصفار الزائدة بعد الفاصلة)
function fmt_qty($qty): string
{
    $qty = (float) $qty;
    // إظهار حتى ٣ خانات عشرية بدون أصفار زائدة
    $formatted = rtrim(rtrim(number_format($qty, 3, '.', ','), '0'), '.');
    return $formatted === '' ? '0' : $formatted;
}

// حساب وتنسيق المعادل بالدولار (للعرض كمعلومة ثانوية فقط)
function usd_equiv($amount_egp, $usd_rate): string
{
    $rate = (float) $usd_rate;
    if ($rate <= 0) {
        return '—';
    }
    $usd = (float) $amount_egp / $rate;
    return '$' . number_format($usd, 2);
}

// تحويل تاريخ من صيغة قاعدة البيانات (Y-m-d) إلى dd/mm/yyyy
function fmt_date($date): string
{
    if (empty($date)) {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date('d/m/Y', $ts) : e($date);
}

// تحويل تاريخ ووقت إلى dd/mm/yyyy HH:MM
function fmt_datetime($datetime): string
{
    if (empty($datetime)) {
        return '—';
    }
    $ts = strtotime($datetime);
    return $ts ? date('d/m/Y H:i', $ts) : e($datetime);
}

/* ============================================================
 *  قائمة وحدات القياس الافتراضية (يمكن إضافة وحدات جديدة)
 * ============================================================ */
function default_units(): array
{
    return ['كجم', 'جرام', 'طن', 'لتر', 'مل', 'متر', 'سم', 'قطعة', 'علبة', 'كرتونة', 'دستة', 'عبوة', 'كيس', 'شيكارة', 'زجاجة'];
}
