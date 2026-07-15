<?php
/**
 * includes/auth.php
 * ------------------------------------------------------------------
 * حماية الصفحات: يجب استدعاء require_login() في أعلى كل صفحة محميّة.
 * إن لم يكن المستخدم مسجّل الدخول تتم إعادة توجيهه إلى صفحة الدخول.
 * ------------------------------------------------------------------
 */

require_once __DIR__ . '/functions.php';

// هل المستخدم مسجّل الدخول؟
function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

// اسم المستخدم الحالي
function current_username(): string
{
    return $_SESSION['username'] ?? '';
}

// فرض تسجيل الدخول
function require_login(): void
{
    if (!is_logged_in()) {
        redirect('login.php');
    }
}
