<?php
/**
 * includes/header.php
 * ------------------------------------------------------------------
 * رأس الصفحة المشترك: يفتح الصفحة، يحمّل Bootstrap RTL والأنماط،
 * ويعرض شريط التنقّل العلوي. يجب ضبط المتغيّرين التاليين قبل تضمينه:
 *   $page_title  : عنوان الصفحة
 *   $active      : مفتاح الصفحة النشطة لتمييزها في القائمة
 * ------------------------------------------------------------------
 */

require_once __DIR__ . '/auth.php';
require_login();

$page_title = $page_title ?? APP_NAME;
$active     = $active ?? '';

// روابط القائمة الرئيسية
$nav = [
    'dashboard'    => ['label' => 'الرئيسية',      'icon' => '🏠'],
    'order_new'    => ['label' => 'أوردر جديد',    'icon' => '➕'],
    'orders'       => ['label' => 'الأوردرات',     'icon' => '📋'],
    'item_history' => ['label' => 'بحث عن صنف',    'icon' => '🔍'],
    'items'        => ['label' => 'الأصناف',       'icon' => '📦'],
    'vendors'      => ['label' => 'الموردين',      'icon' => '🏪'],
    'categories'   => ['label' => 'المجموعات',     'icon' => '🗂️'],
    'reports'      => ['label' => 'التقارير',      'icon' => '📊'],
    'settings'     => ['label' => 'الإعدادات',     'icon' => '⚙️'],
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($page_title) ?> — <?= e(APP_NAME) ?></title>

    <!-- Bootstrap 5 RTL (بدون أدوات بناء — من CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <!-- خط عربي واضح -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- أنماط التطبيق -->
    <link href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>" rel="stylesheet">
</head>
<body>

<!-- شريط التنقّل السفلي للهواتف -->
<div class="mobile-bottom-nav d-md-none">
    <a href="index.php?page=dashboard" class="mobile-bottom-nav-item <?= $active === 'dashboard' ? 'active' : '' ?>">
        <span class="icon">🏠</span>
        <span class="label">الرئيسية</span>
    </a>
    <a href="index.php?page=orders" class="mobile-bottom-nav-item <?= $active === 'orders' ? 'active' : '' ?>">
        <span class="icon">📋</span>
        <span class="label">الأوردرات</span>
    </a>
    <a href="index.php?page=order_new" class="mobile-bottom-nav-item <?= $active === 'order_new' ? 'active' : '' ?>">
        <div class="plus-icon-wrapper">
            <span class="icon">➕</span>
        </div>
        <span class="label">أوردر جديد</span>
    </a>
    <a href="index.php?page=item_history" class="mobile-bottom-nav-item <?= $active === 'item_history' ? 'active' : '' ?>">
        <span class="icon">🔍</span>
        <span class="label">بحث صنف</span>
    </a>
    <a href="index.php?page=settings" class="mobile-bottom-nav-item <?= $active === 'settings' ? 'active' : '' ?>">
        <span class="icon">⚙️</span>
        <span class="label">الإعدادات</span>
    </a>
</div>

<!-- شريط التنقّل العلوي -->
<nav class="navbar navbar-expand-xl navbar-dark app-navbar sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="index.php?page=dashboard">
            🧾 <?= e(APP_NAME) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#mainNav" aria-controls="mainNav"
                aria-expanded="false" aria-label="القائمة">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-xl-0">
                <?php foreach ($nav as $key => $item): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $active === $key ? 'active fw-bold' : '' ?>"
                           href="index.php?page=<?= e($key) ?>">
                            <span class="nav-emoji"><?= $item['icon'] ?></span>
                            <?= e($item['label']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="d-flex align-items-center gap-2">
                <span class="navbar-text text-white-50 small">
                    مرحباً، <b class="text-white"><?= e(current_username()) ?></b>
                </span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">🚪 خروج</a>
            </div>
        </div>
    </div>
</nav>

<!-- منطقة المحتوى -->
<main class="container my-4">

    <!-- رسائل النجاح / الخطأ -->
    <?php foreach (get_flashes() as $flash): ?>
        <?php
            $cls = $flash['type'] === 'success' ? 'alert-success'
                 : ($flash['type'] === 'error' ? 'alert-danger' : 'alert-info');
        ?>
        <div class="alert <?= $cls ?> alert-dismissible fade show" role="alert">
            <?= e($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
        </div>
    <?php endforeach; ?>
