/**
 * Mostaager ↔ Houzez shell: active state for the injected menu section,
 * and closing the Houzez off-canvas sidebar after choosing a section (< 1200px).
 */
(function () {
    'use strict';

    // "إضافة عقار" داخل لوحات مستأجر → نموذج Houzez الأصلي بدل النموذج المكرر
    var submitUrl = (window.MSHouzezShell && window.MSHouzezShell.submitUrl) || '';
    if (submitUrl && document.body.classList.contains('ms-hz-shell')) {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest && e.target.closest('[data-tab-target="add-property"], .ms-tab-link[data-tab="add-property"]');
            if (!btn) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            window.location.href = submitUrl;
        }, true);
    }

    var nav = document.querySelector('[data-ms-hz-nav]');
    if (!nav) return;

    function currentTab() {
        var h = (window.location.hash || '').replace(/^#/, '');
        return h || 'overview';
    }

    function sync() {
        var tab = currentTab();
        var onPage = !!document.querySelector('.ms-dashboard');
        nav.querySelectorAll('a[data-ms-tab]').forEach(function (a) {
            var isHash = (a.getAttribute('href') || '').charAt(0) === '#';
            a.classList.toggle('active', onPage && isHash && a.getAttribute('data-ms-tab') === tab);
        });
    }

    nav.addEventListener('click', function (e) {
        var a = e.target.closest && e.target.closest('a[data-ms-tab]');
        if (!a) return;
        if ((a.getAttribute('href') || '').charAt(0) === '#' && window.innerWidth < 1200) {
            document.body.classList.remove('sidebar-collapsed');
        }
        setTimeout(sync, 0);
    });

    window.addEventListener('hashchange', sync);
    sync();
})();
