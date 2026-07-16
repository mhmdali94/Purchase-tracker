/* ==================================================================
   assets/js/app.js
   جافاسكربت التطبيق (Vanilla JS):
   - تأكيد الحذف بالعربية
   - إدارة سطور الأوردر (إضافة/حذف/حساب المجاميع مباشرة)
   - قائمة أصناف قابلة للبحث + إضافة صنف جديد بدون مغادرة الصفحة
   ================================================================== */

/* ---------- أدوات مساعدة ---------- */
function fmtMoney(n) {
    return (Number(n) || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2, maximumFractionDigits: 2
    });
}

/* ---------- تأكيد الحذف قبل إرسال أي نموذج حذف ---------- */
document.addEventListener('submit', function (ev) {
    const form = ev.target;
    if (form.classList.contains('js-confirm-delete')) {
        const msg = form.dataset.confirm || 'هل أنت متأكد من الحذف؟ لا يمكن التراجع.';
        if (!window.confirm(msg)) {
            ev.preventDefault();
        }
    }
});

/* ==================================================================
   شاشة الأوردر الجديد / التعديل
   ================================================================== */
(function () {
    const orderForm = document.getElementById('orderForm');
    if (!orderForm) return; // لسنا في شاشة الأوردر

    const linesBody   = document.getElementById('orderLines');
    const addLineBtn  = document.getElementById('addLineBtn');
    const rateInput   = document.getElementById('usd_rate');
    const grandEgpEl  = document.getElementById('grandTotalEgp');
    const grandUsdEl  = document.getElementById('grandTotalUsd');
    const itemsList   = document.getElementById('itemsDatalist');

    // خريطة الأصناف: النص المعروض -> id ، و id -> بيانات
    const items = window.ITEMS || [];
    const labelToId = {};
    items.forEach(it => { labelToId[it.label] = it.id; });

    // بناء عناصر القائمة القابلة للبحث (datalist)
    function rebuildDatalist() {
        if (!itemsList) return;
        itemsList.innerHTML = '';
        items.forEach(it => {
            const opt = document.createElement('option');
            opt.value = it.label;
            itemsList.appendChild(opt);
        });
    }
    rebuildDatalist();

    // حساب مجموع سطر واحد
    function recalcLine(row) {
        const qty   = parseFloat(row.querySelector('.qty-input').value) || 0;
        const price = parseFloat(row.querySelector('.price-input').value) || 0;
        const total = qty * price;
        row.querySelector('.line-total-cell').textContent = fmtMoney(total) + ' ج.م';
        return total;
    }

    // ربط حقل اسم الصنف بالمعرّف المخفي
    function bindItemInput(row) {
        const input  = row.querySelector('.item-input');
        const hidden = row.querySelector('.item-id-input');
        input.addEventListener('input', function () {
            const id = labelToId[input.value];
            hidden.value = id || '';
            input.classList.toggle('is-invalid', input.value !== '' && !id);
        });
    }

    // إعادة حساب المجموع الكلي مع المعادل بالدولار
    function recalcGrand() {
        let grand = 0;
        linesBody.querySelectorAll('tr.order-line').forEach(row => {
            grand += recalcLine(row);
        });
        grandEgpEl.textContent = fmtMoney(grand) + ' ج.م';
        const rate = parseFloat(rateInput.value) || 0;
        grandUsdEl.textContent = rate > 0
            ? '≈ $' + fmtMoney(grand / rate)
            : '— أدخل سعر الدولار لعرض المعادل';
    }

    // إنشاء سطر جديد
    function makeLine() {
        const tr = document.createElement('tr');
        tr.className = 'order-line';
        tr.innerHTML =
            '<td style="min-width:200px">' +
                '<input type="text" class="form-control item-input" list="itemsDatalist" ' +
                'placeholder="اكتب للبحث عن صنف..." autocomplete="off" required>' +
                '<input type="hidden" name="item_id[]" class="item-id-input" required>' +
                '<div class="invalid-feedback">اختر صنفاً من القائمة أو أضِف صنفاً جديداً.</div>' +
            '</td>' +
            '<td style="min-width:110px">' +
                '<input type="number" step="0.001" min="0.001" name="quantity[]" ' +
                'class="form-control qty-input" placeholder="الكمية" required>' +
            '</td>' +
            '<td style="min-width:130px">' +
                '<input type="number" step="0.01" min="0" name="unit_price[]" ' +
                'class="form-control price-input" placeholder="سعر الوحدة" required>' +
            '</td>' +
            '<td class="line-total-cell">0.00 ج.م</td>' +
            '<td style="min-width:150px">' +
                '<input type="text" name="line_notes[]" class="form-control line-note-input" placeholder="مثال: مثل ما تم توريده...">' +
            '</td>' +
            '<td><button type="button" class="btn btn-outline-danger btn-sm remove-line" title="حذف السطر">🗑️</button></td>';
        linesBody.appendChild(tr);

        bindItemInput(tr);
        tr.querySelector('.qty-input').addEventListener('input', recalcGrand);
        tr.querySelector('.price-input').addEventListener('input', recalcGrand);
        tr.querySelector('.remove-line').addEventListener('click', function () {
            if (linesBody.querySelectorAll('tr.order-line').length > 1) {
                tr.remove();
            } else {
                // لا تحذف آخر سطر — فقط أفرغه
                tr.querySelector('.item-input').value = '';
                tr.querySelector('.item-id-input').value = '';
                tr.querySelector('.qty-input').value = '';
                tr.querySelector('.price-input').value = '';
            }
            recalcGrand();
        });
        return tr;
    }

    // تفعيل السطور الموجودة مسبقاً (في حالة التعديل)
    linesBody.querySelectorAll('tr.order-line').forEach(row => {
        bindItemInput(row);
        row.querySelector('.qty-input').addEventListener('input', recalcGrand);
        row.querySelector('.price-input').addEventListener('input', recalcGrand);
        row.querySelector('.remove-line')?.addEventListener('click', function () {
            if (linesBody.querySelectorAll('tr.order-line').length > 1) row.remove();
            else {
                row.querySelectorAll('input').forEach(i => { if (i.type !== 'hidden') i.value=''; });
                row.querySelector('.item-id-input').value='';
            }
            recalcGrand();
        });
    });

    // زر إضافة سطر
    addLineBtn.addEventListener('click', function () {
        const tr = makeLine();
        tr.querySelector('.item-input').focus();
    });

    // تحديث المعادل بالدولار عند تغيير سعر الصرف
    rateInput.addEventListener('input', recalcGrand);

    // إن لم توجد سطور (شاشة جديدة) أضِف سطرين للبداية
    if (linesBody.querySelectorAll('tr.order-line').length === 0) {
        makeLine();
        makeLine();
    }
    recalcGrand();

    // منع الإرسال إذا لم يُختر أي صنف صحيح
    orderForm.addEventListener('submit', function (ev) {
        let valid = false;
        linesBody.querySelectorAll('tr.order-line').forEach(row => {
            if (row.querySelector('.item-id-input').value) valid = true;
        });
        if (!valid) {
            ev.preventDefault();
            alert('أضِف صنفاً واحداً على الأقل إلى الأوردر.');
        }
    });

    /* ---------- إضافة صنف جديد بدون مغادرة الصفحة ---------- */
    const saveNewItemBtn = document.getElementById('saveNewItemBtn');
    if (saveNewItemBtn) {
        saveNewItemBtn.addEventListener('click', function () {
            const name = document.getElementById('newItemName').value.trim();
            if (!name) { alert('أدخل اسم الصنف.'); return; }
            const data = new FormData();
            data.append('csrf_token', document.getElementById('csrfToken').value);
            data.append('name', name);
            data.append('specs', document.getElementById('newItemSpecs').value.trim());
            data.append('unit', document.getElementById('newItemUnit').value.trim());
            data.append('category_id', document.getElementById('newItemCategory').value);

            saveNewItemBtn.disabled = true;
            fetch('ajax/add_item.php', { method: 'POST', body: data })
                .then(r => r.json())
                .then(res => {
                    saveNewItemBtn.disabled = false;
                    if (!res.ok) { alert(res.message || 'تعذّر حفظ الصنف.'); return; }
                    // أضِف الصنف إلى الذاكرة والقائمة
                    items.push(res.item);
                    labelToId[res.item.label] = res.item.id;
                    rebuildDatalist();
                    // اختره في أول سطر فارغ (أو سطر جديد)
                    let target = null;
                    linesBody.querySelectorAll('tr.order-line').forEach(row => {
                        if (!target && !row.querySelector('.item-id-input').value) target = row;
                    });
                    if (!target) target = makeLine();
                    target.querySelector('.item-input').value = res.item.label;
                    target.querySelector('.item-id-input').value = res.item.id;
                    // أغلق النافذة وأفرغ الحقول
                    document.getElementById('newItemForm').reset();
                    const modalEl = document.getElementById('newItemModal');
                    bootstrap.Modal.getInstance(modalEl)?.hide();
                    target.querySelector('.qty-input').focus();
                })
                .catch(() => {
                    saveNewItemBtn.disabled = false;
                    alert('حدث خطأ في الاتصال. حاول مرة أخرى.');
                });
        });
    }
})();
