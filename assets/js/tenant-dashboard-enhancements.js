(function () {
    'use strict';

    // The rent dashboard renders every panel server-side. Do not lazy-load and
    // overwrite those panels with a reduced AJAX template; that caused the
    // visible flash followed by «تعذر تحميل القسم».
    function bindMaintenanceFilters() {
        document.querySelectorAll('.ms-maintenance-filter').forEach(function (button) {
            if (button.dataset.msRentBound === '1') return;
            button.dataset.msRentBound = '1';
            button.addEventListener('click', function () {
                var status = button.dataset.status || 'all';
                document.querySelectorAll('.ms-maintenance-filter').forEach(function (item) {
                    item.classList.toggle('active', item === button);
                });
                document.querySelectorAll('.ms-tenant-maintenance-row').forEach(function (row) {
                    row.style.display = status === 'all' || row.dataset.status === status ? '' : 'none';
                });
            });
        });
    }

    function bindPayments() {
        document.querySelectorAll('.ms-tenant-pay-maintenance').forEach(function (button) {
            if (button.dataset.msRentBound === '1') return;
            button.dataset.msRentBound = '1';
            button.addEventListener('click', function () {
                button.disabled = true;
                var body = new URLSearchParams({
                    action: 'ms_pay_maintenance_invoice',
                    request_id: button.dataset.requestId || '',
                    amount: button.dataset.amount || '0',
                    security: (window.MostaagerAjax && MostaagerAjax.nonce) || ''
                });
                fetch((window.MostaagerAjax && MostaagerAjax.ajax_url) || '/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                    body: body
                }).then(function (response) {
                    return response.json();
                }).then(function (json) {
                    if (json.success && json.data && json.data.payment_url) {
                        window.location.href = json.data.payment_url;
                        return;
                    }
                    alert((json.data && json.data.message) || 'تعذر إنشاء رابط دفع الصيانة.');
                    button.disabled = false;
                }).catch(function () {
                    alert('حدث خطأ في الاتصال.');
                    button.disabled = false;
                });
            });
        });
    }

    function initRentEnhancements() {
        bindMaintenanceFilters();
        bindPayments();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initRentEnhancements);
    } else {
        initRentEnhancements();
    }
})();
