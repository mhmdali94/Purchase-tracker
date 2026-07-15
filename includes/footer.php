<?php
/**
 * includes/footer.php
 * ------------------------------------------------------------------
 * تذييل الصفحة المشترك: يغلق منطقة المحتوى ويحمّل ملفات JavaScript.
 * ------------------------------------------------------------------
 */
?>
</main>

<footer class="app-footer text-center text-muted py-3 small">
    <?= e(APP_NAME) ?> — كل الأسعار بالجنيه المصري (<?= e(CURRENCY) ?>)
</footer>

<!-- Bootstrap JS (بدون أدوات بناء — من CDN) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Chart.js للرسوم البيانية -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<!-- سكربت التطبيق -->
<script src="assets/js/app.js"></script>
</body>
</html>
