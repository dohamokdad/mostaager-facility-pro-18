/**
 * يضيف زر «تحويل لمستأجر» إلى صفوف الصفقات في صفحة CRM الخاصة بـ Houzez.
 * يعتمد على <tr data-id="deal_id"> التي يطبعها قالب Houzez (deal-item.php).
 */
(function () {
    'use strict';
    var cfg = window.MSCrmBridge;
    if (!cfg) return;

    function addButtons() {
        document.querySelectorAll('tr[data-id]').forEach(function (row) {
            if (row.querySelector('.ms-convert-deal')) return;
            var cells = row.querySelectorAll('td');
            if (!cells.length) return;
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-primary btn-sm ms-convert-deal';
            btn.textContent = cfg.label;
            btn.style.cssText = 'margin-inline-start:8px;white-space:nowrap';
            btn.dataset.deal = row.getAttribute('data-id');
            cells[cells.length - 1].appendChild(btn);
        });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('.ms-convert-deal');
        if (!btn || btn.disabled) return;
        e.preventDefault();
        if (!window.confirm(cfg.confirm)) return;

        var label = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'جاري التحويل...';

        var body = new FormData();
        body.append('action', 'ms_crm_convert_deal');
        body.append('deal_id', btn.dataset.deal);
        body.append('security', cfg.nonce);

        fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                btn.disabled = false;
                btn.textContent = label;
                if (res && res.success) {
                    window.alert(res.data.message);
                    if (res.data.next_url && window.confirm('فتح صفحة ربط الوحدة الآن؟')) {
                        window.location.href = res.data.next_url;
                    }
                } else {
                    window.alert((res && res.data && res.data.message) || 'تعذّر التحويل.');
                }
            })
            .catch(function () {
                btn.disabled = false;
                btn.textContent = label;
                window.alert('تعذّر الاتصال بالخادم.');
            });
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', addButtons);
    } else {
        addButtons();
    }
    // جداول CRM تُحدَّث عبر AJAX داخل Houzez
    if ('MutationObserver' in window) {
        new MutationObserver(addButtons).observe(document.body, { childList: true, subtree: true });
    }
})();
