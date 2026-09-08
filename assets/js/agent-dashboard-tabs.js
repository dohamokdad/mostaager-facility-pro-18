(function () {
    'use strict';

    var dynamicTabs = ['discussions', 'analytics', 'profile'];

    function getPanel(tab) {
        return document.querySelector('#' + tab + '.ms-tab-content');
    }

    function showNonDestructiveNotice(panel, message) {
        if (!panel || panel.querySelector('.msfp-tab-load-notice')) return;
        var notice = document.createElement('div');
        notice.className = 'msfp-tab-load-notice';
        notice.setAttribute('role', 'status');
        notice.textContent = message;
        notice.style.cssText = 'margin:12px 0;padding:10px 12px;background:#fff7ed;color:#9a3412;border:1px solid #fed7aa;border-radius:8px;font-size:13px;';
        panel.insertBefore(notice, panel.firstChild);
    }

    function load(tab) {
        if (dynamicTabs.indexOf(tab) === -1 || !window.MostaagerAjax) return;
        var panel = getPanel(tab);
        if (!panel || panel.dataset.msfpLoaded === '1' || panel.dataset.msfpLoading === '1') return;

        panel.dataset.msfpLoading = '1';
        panel.setAttribute('aria-busy', 'true');
        var body = new URLSearchParams({
            action: 'ms_get_agent_tab',
            tab: tab,
            security: MostaagerAjax.nonce
        });

        fetch(MostaagerAjax.ajax_url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: body
        }).then(function (response) {
            return response.json();
        }).then(function (json) {
            if (json && json.success && json.data && json.data.html) {
                panel.innerHTML = json.data.html;
                panel.dataset.msfpLoaded = '1';
            } else {
                showNonDestructiveNotice(panel, 'تعذر تحديث هذا القسم الآن؛ تم إبقاء المحتوى الحالي. حاول تحديث الصفحة لاحقًا.');
            }
        }).catch(function () {
            showNonDestructiveNotice(panel, 'تعذر الاتصال بالخادم؛ تم إبقاء المحتوى الحالي دون إخفائه.');
        }).finally(function () {
            panel.dataset.msfpLoading = '0';
            panel.removeAttribute('aria-busy');
        });
    }

    function currentTab() {
        return (window.location.hash || '').replace(/^#/, '').split('&')[0];
    }

    document.addEventListener('DOMContentLoaded', function () {
        var tab = currentTab();
        if (dynamicTabs.indexOf(tab) !== -1) load(tab);
        document.querySelectorAll('.ms-invoice-subtab').forEach(function (button) {
            button.remove();
        });
        enhanceSubscriptionCards();
    });

    window.addEventListener('hashchange', function () {
        var tab = currentTab();
        if (dynamicTabs.indexOf(tab) !== -1) load(tab);
    });

    document.addEventListener('click', function (event) {
        var link = event.target.closest && event.target.closest('.ms-tab-link, [data-tab]');
        if (!link) return;
        var tab = link.dataset.tab || link.getAttribute('href') || '';
        tab = tab.replace(/^#/, '').split('&')[0];
        if (dynamicTabs.indexOf(tab) !== -1) {
            window.setTimeout(function () { load(tab); }, 0);
        }
    });

    function enhanceSubscriptionCards() {
        var root = document.getElementById('subscriptions');
        if (!root || document.getElementById('ms-agent-plan-theme')) return;
        var style = document.createElement('style');
        style.id = 'ms-agent-plan-theme';
        style.textContent = '.ms-subscription-cards{align-items:stretch!important}.ms-subscription-card.ms-plan-gold,.ms-subscription-card.ms-plan-silver,.ms-subscription-card.ms-plan-bronze{position:relative!important;overflow:hidden!important;border-width:2px!important;transition:transform .2s ease,box-shadow .2s ease!important}.ms-subscription-card.ms-plan-gold{border-color:#d4af37!important;background:linear-gradient(155deg,#fffdf3 0%,#fff 72%)!important}.ms-subscription-card.ms-plan-silver{border-color:#94a3b8!important;background:linear-gradient(155deg,#f8fafc 0%,#fff 72%)!important}.ms-subscription-card.ms-plan-bronze{border-color:#b87333!important;background:linear-gradient(155deg,#fff8f2 0%,#fff 72%)!important}.ms-subscription-card.ms-plan-gold:hover,.ms-subscription-card.ms-plan-silver:hover,.ms-subscription-card.ms-plan-bronze:hover{transform:translateY(-3px);box-shadow:0 12px 28px rgba(15,23,42,.12)!important}.ms-plan-icon{display:inline-flex;width:42px;height:42px;align-items:center;justify-content:center;border-radius:50%;margin-left:8px;margin-right:0;font-size:20px;vertical-align:middle}.ms-plan-gold .ms-plan-icon{color:#8a6500;background:#fff1a8}.ms-plan-silver .ms-plan-icon{color:#475569;background:#e2e8f0}.ms-plan-bronze .ms-plan-icon{color:#8a451f;background:#f5d0b5}.ms-plan-badge{display:inline-block;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:700;margin-bottom:12px}.ms-plan-gold .ms-plan-badge{color:#795b00;background:#fff1a8}.ms-plan-silver .ms-plan-badge{color:#334155;background:#e2e8f0}.ms-plan-bronze .ms-plan-badge{color:#7c3f1d;background:#f5d0b5}@media(max-width:767px){.ms-subscription-cards{grid-template-columns:1fr!important}.ms-subscription-card{min-width:0!important}}';
        document.head.appendChild(style);
        root.querySelectorAll('.ms-subscription-card').forEach(function (card) {
            var button = card.querySelector('[data-plan-key]');
            if (!button) return;
            var key = button.getAttribute('data-plan-key');
            var title = card.querySelector('h4');
            if (!title || card.classList.contains('ms-plan-' + key)) return;
            card.classList.add('ms-plan-' + key);
            var icon = key === 'gold' ? 'fa-crown' : (key === 'silver' ? 'fa-gem' : 'fa-medal');
            var label = key === 'gold' ? 'الأعلى مزايا' : (key === 'silver' ? 'الأكثر توازنًا' : 'للبداية');
            var iconEl = document.createElement('i');
            iconEl.className = 'fas ' + icon + ' ms-plan-icon';
            iconEl.setAttribute('aria-hidden', 'true');
            title.prepend(iconEl);
            var badge = document.createElement('span');
            badge.className = 'ms-plan-badge';
            badge.textContent = label;
            title.parentNode.insertBefore(badge, title);
        });
    }

    function postForm(form, statusSelector) {
        if (!form || !window.MostaagerAjax) return;
        var status = form.querySelector(statusSelector);
        var body = new FormData(form);
        fetch(MostaagerAjax.ajax_url, {method: 'POST', credentials: 'same-origin', body: body})
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (status) status.textContent = json && json.success ? ((json.data && json.data.message) || 'تم الحفظ بنجاح.') : ((json.data && json.data.message) || 'تعذر الحفظ.');
                if (json && json.success) { form.dataset.saved = '1'; if (json.data && json.data.avatar_url) { var avatar = document.querySelector('.ms-unified-profile .ms-profile-avatar img'); if (avatar) avatar.src = json.data.avatar_url + (json.data.avatar_url.indexOf('?') >= 0 ? '&' : '?') + 'ms_avatar=' + Date.now(); } }
            })
            .catch(function () { if (status) status.textContent = 'تعذر الاتصال بالخادم.'; });
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest && event.target.closest('.ms-renewal-toggle');
        if (!button || !window.MostaagerAjax) return;
        if (!window.confirm(button.getAttribute('data-renewal-action') === 'cancel' ? 'هل تريد إلغاء التجديد التلقائي؟ سيبقى الاشتراك فعالًا حتى تاريخ انتهائه.' : 'هل تريد إعادة تفعيل إمكانية التجديد؟')) return;
        button.disabled = true;
        var body = new URLSearchParams({action:'ms_agent_renewal_action', renewal_action:button.getAttribute('data-renewal-action'), security:button.getAttribute('data-renewal-nonce') || MostaagerAjax.nonce});
        fetch(MostaagerAjax.ajax_url, {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'}, body:body})
            .then(function(r){return r.json();})
            .then(function(json){
                if (!json || !json.success) throw new Error((json && json.data && json.data.message) || 'تعذر تحديث حالة التجديد.');
                var box = button.closest('.ms-renewal-control');
                var cancelled = json.data.state === 'cancelled';
                if (box) { box.dataset.renewalState = cancelled ? 'cancelled' : 'active'; var text = box.querySelector('p'); var status = box.querySelector('.ms-renewal-status'); if (text) text.textContent = cancelled ? 'تم إلغاء التجديد. يبقى اشتراكك فعالًا حتى تاريخ انتهائه.' : 'التجديد مفعّل. يمكنك إلغاؤه مع بقاء اشتراكك فعالًا حتى تاريخ الانتهاء.'; if (status) { status.textContent = cancelled ? 'الحالة: ملغى' : 'الحالة: مفعّل'; status.style.color = cancelled ? '#b91c1c' : '#15803d'; } }
                button.textContent = cancelled ? 'إعادة تفعيل التجديد' : 'إلغاء التجديد التلقائي'; button.setAttribute('data-renewal-action', cancelled ? 'restore' : 'cancel'); button.style.color = cancelled ? '#15803d' : '#b91c1c'; button.style.borderColor = cancelled ? '#16a34a' : '#dc2626';
            })
            .catch(function(error){window.alert(error.message);})
            .finally(function(){button.disabled = false;});
    });

    document.addEventListener('submit', function (event) {
        if (event.target.id === 'ms-unified-profile-form') {
            event.preventDefault();
            postForm(event.target, '[data-profile-status]');
        }
        if (event.target.id === 'ms-unified-password-form') {
            event.preventDefault();
            postForm(event.target, '[data-password-status]');
        }
    });
})();
