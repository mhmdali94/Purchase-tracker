<?php
/**
 * pages/item_history.php — سجل أسعار صنف عبر كل الموردين.
 * ابحث/اختر صنفاً → جدول بكل مرات شرائه (الأحدث أولاً) مع تمييز أقل سعر،
 * ورسم بياني لتغيّر سعر الوحدة عبر الزمن.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

// كل الأصناف لقائمة البحث
$items_raw = $pdo->query('SELECT id, name, specs, unit FROM items ORDER BY name')->fetchAll();
$items_js = [];
foreach ($items_raw as $it) {
    $label = $it['name']
        . ($it['specs'] !== '' ? ' — ' . $it['specs'] : '')
        . ($it['unit'] !== '' ? ' (' . $it['unit'] . ')' : '');
    $items_js[] = [
        'id' => (int)$it['id'],
        'label' => $label,
        'name' => $it['name'],
        'specs' => $it['specs'],
        'unit' => $it['unit']
    ];
}

$item_id = (int) ($_GET['item_id'] ?? 0);

$item = null;
$rows = [];
$chart_labels = [];
$chart_values = [];
$min_price = null;

if ($item_id > 0) {
    $st = $pdo->prepare('SELECT *, (photo IS NOT NULL) AS has_photo FROM items WHERE id = ?');
    $st->execute([$item_id]);
    $item = $st->fetch();

    if ($item) {
        $q = $pdo->prepare(
            "SELECT oi.quantity, oi.unit_price_egp, o.order_date, o.usd_rate, v.name AS vendor_name
             FROM order_items oi
             JOIN orders o  ON o.id = oi.order_id
             JOIN vendors v ON v.id = o.vendor_id
             WHERE oi.item_id = ?
             ORDER BY o.order_date DESC, o.id DESC"
        );
        $q->execute([$item_id]);
        $rows = $q->fetchAll();

        // أقل سعر وحدة (لتمييز الصف)
        foreach ($rows as $r) {
            $p = (float) $r['unit_price_egp'];
            if ($min_price === null || $p < $min_price) $min_price = $p;
        }

        // بيانات الرسم البياني (بترتيب زمني تصاعدي)
        $chart = array_reverse($rows);
        foreach ($chart as $r) {
            $chart_labels[] = fmt_date($r['order_date']);
            $chart_values[] = (float) $r['unit_price_egp'];
        }
    }
}

// قيم عرض آمنة تماماً (لا تُصدر أي تحذير مهما كانت حالة الصف)
$item_name    = is_array($item) ? (string) ($item['name']  ?? '') : '';
$item_specs   = is_array($item) ? (string) ($item['specs'] ?? '') : '';
$item_unit    = is_array($item) ? (string) ($item['unit']  ?? '') : '';
$item_display = $item_name
    . ($item_specs !== '' ? ' — ' . $item_specs : '')
    . ($item_unit  !== '' ? ' (' . $item_unit . ')' : '');

$page_title = 'بحث عن صنف';
$active = 'item_history';
require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="h3 fw-bold mb-4">🔍 بحث عن صنف — سجل الأسعار</h1>

<!-- اختيار الصنف -->
<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end" id="itemPickForm">
            <input type="hidden" name="page" value="item_history">
            <input type="hidden" name="item_id" id="pickedItemId" value="<?= (int)$item_id ?>">
            <div class="col-12 col-md-9">
                <label class="form-label fw-bold">اختر الصنف</label>
                <div class="position-relative">
                    <input type="text" id="itemPicker" class="form-control form-control-lg"
                           placeholder="اكتب للبحث عن صنف..." autocomplete="off"
                           value="<?= $item ? e($item_display) : '' ?>">
                    <div id="autocomplete-results" class="autocomplete-results-container d-none"></div>
                </div>
            </div>
            <div class="col-12 col-md-3 d-grid">
                <button type="submit" class="btn btn-primary btn-lg">عرض السجل</button>
            </div>
        </form>
    </div>
</div>

<?php if ($item_id > 0 && !$item): ?>
    <div class="alert alert-warning">الصنف غير موجود.</div>
<?php elseif ($item): ?>

    <div class="d-flex align-items-center gap-3 mb-3">
        <?php if (!empty($item['has_photo'])): ?>
            <img src="ajax/item_photo.php?id=<?= (int)$item_id ?>"
                 alt="<?= e($item_name) ?>" class="item-thumb-history">
        <?php else: ?>
            <span class="item-thumb-history-placeholder">📦</span>
        <?php endif; ?>
        <h2 class="h5 mb-0">
            <?= e($item_name !== '' ? $item_name : 'صنف رقم ' . (int)$item_id) ?>
            <?php if ($item_specs !== ''): ?><small class="text-muted">— <?= e($item_specs) ?></small><?php endif; ?>
        </h2>
    </div>

    <?php if (!$rows): ?>
        <div class="alert alert-info">لم يُشترَ هذا الصنف في أي أوردر بعد.</div>
    <?php else: ?>

        <!-- الرسم البياني -->
        <div class="card mb-3">
            <div class="card-header">📈 تغيّر سعر الوحدة عبر الزمن (ج.م)</div>
            <div class="card-body">
                <canvas id="priceChart" height="90"></canvas>
            </div>
        </div>

        <!-- جدول السجل -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>سجل المشتريات (<?= count($rows) ?>)</span>
                <span class="badge bg-success">⭐ أقل سعر: <?= money_egp($min_price) ?></span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle item-history-table">
                        <thead>
                            <tr>
                                <th>التاريخ</th><th>المورد</th><th>الكمية</th>
                                <th>سعر الوحدة (ج.م)</th><th>سعر $ يومها</th><th>المعادل بالدولار</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $r): ?>
                                <?php $isLowest = ((float)$r['unit_price_egp'] === (float)$min_price); ?>
                                <tr class="<?= $isLowest ? 'lowest-price' : '' ?>">
                                    <td><?= fmt_date($r['order_date']) ?></td>
                                    <td><?= e($r['vendor_name']) ?></td>
                                    <td><?= fmt_qty($r['quantity']) ?> <?= e($item_unit) ?></td>
                                    <td><?= money_egp($r['unit_price_egp']) ?></td>
                                    <td><?= $r['usd_rate'] > 0 ? number_format($r['usd_rate'], 2) : '—' ?></td>
                                    <td><?= usd_equiv($r['unit_price_egp'], $r['usd_rate']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <script>
        // رسم بياني لسعر الوحدة عبر الزمن (ننتظر تحميل مكتبة الرسم في التذييل)
        window.addEventListener('DOMContentLoaded', function () {
            const ctx = document.getElementById('priceChart');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?= json_encode($chart_labels, JSON_UNESCAPED_UNICODE) ?>,
                    datasets: [{
                        label: 'سعر الوحدة (ج.م)',
                        data: <?= json_encode($chart_values) ?>,
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13,110,253,.12)',
                        fill: true,
                        tension: .25,
                        pointRadius: 4,
                        pointBackgroundColor: '#0d6efd'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { labels: { font: { family: 'Cairo' } } } },
                    scales: {
                        y: { beginAtZero: false, ticks: { font: { family: 'Cairo' } } },
                        x: { ticks: { font: { family: 'Cairo' } } }
                    }
                }
            });
        });
        </script>

    <?php endif; ?>
<?php endif; ?>

<script>
// ربط حقل البحث بالمعرّف المخصّص ودعم عرض الصور والتفاصيل مباشرة تحت صندوق البحث مع دعم التنقل بلوحة المفاتيح والأنماط المباشرة لمنع التخزين المؤقت
(function () {
    const items = <?= json_encode($items_js, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const picker = document.getElementById('itemPicker');
    const hidden = document.getElementById('pickedItemId');
    const form = document.getElementById('itemPickForm');
    const resultsContainer = document.getElementById('autocomplete-results');

    // تطبيق الأنماط مباشرة على حاوية النتائج لضمان استقرار التصميم في حال التخزين المؤقت لملف CSS
    resultsContainer.style.position = 'absolute';
    resultsContainer.style.top = '100%';
    resultsContainer.style.left = '0';
    resultsContainer.style.right = '0';
    resultsContainer.style.zIndex = '1050';
    resultsContainer.style.maxHeight = '320px';
    resultsContainer.style.overflowY = 'auto';
    resultsContainer.style.backgroundColor = '#fff';
    resultsContainer.style.border = '1px solid rgba(0, 0, 0, 0.08)';
    resultsContainer.style.borderRadius = '8px';
    resultsContainer.style.boxShadow = '0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1)';
    resultsContainer.style.marginTop = '4px';
    resultsContainer.style.padding = '6px 0';
    resultsContainer.style.direction = 'rtl';
    resultsContainer.style.textAlign = 'right';

    let activeIndex = -1;
    let currentFiltered = [];

    function renderResults(filtered) {
        currentFiltered = filtered;
        activeIndex = -1;
        resultsContainer.innerHTML = '';
        if (filtered.length === 0) {
            resultsContainer.classList.add('d-none');
            return;
        }
        
        filtered.forEach((item, index) => {
            const div = document.createElement('div');
            div.className = 'autocomplete-item';
            div.dataset.index = index;
            
            // تصميم السطر كـ Flexbox لعرض الصورة بجانب النص مباشرة
            div.style.display = 'flex';
            div.style.alignItems = 'center';
            div.style.padding = '8px 12px';
            div.style.margin = '2px 6px';
            div.style.cursor = 'pointer';
            div.style.borderRadius = '6px';
            div.style.gap = '12px';
            div.style.transition = 'background-color 0.15s ease';
            div.style.backgroundColor = 'transparent';
            
            // تأثيرات الماوس
            div.addEventListener('mouseenter', function () {
                div.style.backgroundColor = '#f1f5f9';
            });
            div.addEventListener('mouseleave', function () {
                if (parseInt(div.dataset.index) !== activeIndex) {
                    div.style.backgroundColor = 'transparent';
                }
            });
            
            // Image element
            const img = document.createElement('img');
            img.src = 'ajax/item_photo.php?id=' + item.id;
            img.alt = item.name;
            img.style.width = '42px';
            img.style.height = '42px';
            img.style.objectFit = 'cover';
            img.style.flexShrink = '0';
            img.style.borderRadius = '6px';
            img.style.border = '1px solid #e2e8f0';
            
            // Details element
            const details = document.createElement('div');
            details.className = 'item-details';
            details.style.flexGrow = '1';
            details.style.minWidth = '0';
            details.style.textAlign = 'right';
            
            const nameSpan = document.createElement('div');
            nameSpan.className = 'item-name';
            nameSpan.textContent = item.name;
            nameSpan.style.fontWeight = '700';
            nameSpan.style.fontSize = '14.5px';
            nameSpan.style.color = '#1e293b';
            nameSpan.style.whiteSpace = 'nowrap';
            nameSpan.style.overflow = 'hidden';
            nameSpan.style.textOverflow = 'ellipsis';
            details.appendChild(nameSpan);
            
            if (item.specs || item.unit) {
                const specsSpan = document.createElement('div');
                specsSpan.className = 'item-specs';
                specsSpan.textContent = (item.specs ? item.specs : '') + (item.unit ? ' (' + item.unit + ')' : '');
                specsSpan.style.fontSize = '12px';
                specsSpan.style.color = '#64748b';
                specsSpan.style.marginTop = '2px';
                specsSpan.style.whiteSpace = 'nowrap';
                specsSpan.style.overflow = 'hidden';
                specsSpan.style.textOverflow = 'ellipsis';
                details.appendChild(specsSpan);
            }
            
            div.appendChild(img);
            div.appendChild(details);
            
            div.addEventListener('click', function () {
                selectItem(item);
            });
            
            resultsContainer.appendChild(div);
        });
        
        resultsContainer.classList.remove('d-none');
    }

    function selectItem(item) {
        picker.value = item.label;
        hidden.value = item.id;
        resultsContainer.classList.add('d-none');
        form.submit();
    }

    function updateActive() {
        const divs = resultsContainer.querySelectorAll('.autocomplete-item');
        divs.forEach((div, i) => {
            if (i === activeIndex) {
                div.classList.add('active');
                div.style.backgroundColor = '#f1f5f9';
                div.scrollIntoView({ block: 'nearest' });
            } else {
                div.classList.remove('active');
                div.style.backgroundColor = 'transparent';
            }
        });
    }

    picker.addEventListener('input', function () {
        const val = picker.value.trim().toLowerCase();
        hidden.value = ''; // Reset when typing changes
        
        if (!val) {
            resultsContainer.classList.add('d-none');
            return;
        }
        
        const filtered = items.filter(item => {
            return item.name.toLowerCase().includes(val) || 
                   (item.specs && item.specs.toLowerCase().includes(val)) ||
                   item.label.toLowerCase().includes(val);
        });
        
        renderResults(filtered.slice(0, 15));
    });

    picker.addEventListener('keydown', function (e) {
        if (resultsContainer.classList.contains('d-none')) return;
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeIndex = (activeIndex + 1) % currentFiltered.length;
            updateActive();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeIndex = (activeIndex - 1 + currentFiltered.length) % currentFiltered.length;
            updateActive();
        } else if (e.key === 'Enter') {
            if (activeIndex >= 0 && activeIndex < currentFiltered.length) {
                e.preventDefault();
                selectItem(currentFiltered[activeIndex]);
            }
        } else if (e.key === 'Escape') {
            resultsContainer.classList.add('d-none');
        }
    });

    document.addEventListener('click', function (e) {
        if (!picker.contains(e.target) && !resultsContainer.contains(e.target)) {
            resultsContainer.classList.add('d-none');
        }
    });

    picker.addEventListener('focus', function () {
        picker.dispatchEvent(new Event('input'));
    });

    form.addEventListener('submit', function (ev) {
        if (!hidden.value) {
            const val = picker.value.trim().toLowerCase();
            const exactMatch = items.find(i => i.label.toLowerCase() === val || i.name.toLowerCase() === val);
            if (exactMatch) {
                hidden.value = exactMatch.id;
                picker.value = exactMatch.label;
            } else {
                ev.preventDefault();
                alert('اختر صنفاً صحيحاً من القائمة.');
            }
        }
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
