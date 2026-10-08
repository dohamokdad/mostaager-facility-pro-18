/**
 * Mostaager — UX helpers (loaded in <head> so inline dashboard scripts can use them).
 *
 * MSUX.busy(button, true|false, [text])  → يعطّل الزر ويعرض "جاري..." ويمنع الضغط المزدوج
 * MSUX.error(json, [fallback])           → يحوّل رموز الخادم (not_owner, security_failed...) لرسالة عربية
 */
(function (window) {
    'use strict';

    var MESSAGES = {
        // عامة
        'not_logged_in': 'انتهت جلستك. سجّل الدخول مرة أخرى ثم أعد المحاولة.',
        'security_failed': 'انتهت صلاحية الصفحة. حدّث الصفحة ثم أعد المحاولة.',
        'invalid_request': 'الطلب غير مكتمل. حدّث الصفحة ثم أعد المحاولة.',
        'invalid_data': 'البيانات المُدخلة غير مكتملة.',
        'forbidden': 'ليس لديك صلاحية لتنفيذ هذا الإجراء.',
        'forbidden_building': 'هذا المبنى غير مرتبط بحسابك.',
        'forbidden_property': 'هذا العقار غير مرتبط بحسابك.',
        'function_not_available': 'هذه الخدمة غير متاحة حالياً. تواصل مع الدعم.',
        'function_unavailable': 'هذه الخدمة غير متاحة حالياً. تواصل مع الدعم.',
        'update_failed': 'تعذّر حفظ التغيير. حاول مرة أخرى.',
        'invalid_state': 'لا يمكن تنفيذ هذا الإجراء على الحالة الحالية.',
        // الفواتير والدفع
        'invoice_not_found': 'لم يتم العثور على الفاتورة.',
        'not_owner': 'هذه الفاتورة غير مرتبطة بحسابك.',
        'already_paid': 'هذه الفاتورة مدفوعة مسبقاً.',
        'agent_subscription_invoice_only': 'يمكن للوسيط دفع فواتير الاشتراك فقط من هذه اللوحة.',
        'woo_function_not_available': 'بوابة الدفع غير متاحة حالياً. تواصل مع الإدارة.',
        'order_creation_failed': 'تعذّر إنشاء طلب الدفع. حاول لاحقاً أو تواصل مع الإدارة.',
        'marked_paid': 'تم تسجيل الفاتورة كمدفوعة.',
        // المحفظة والتأمين
        'invalid_amount': 'المبلغ غير صالح.',
        'insufficient_balance': 'الرصيد غير كافٍ لإتمام العملية.',
        'deduction_reason_required': 'يجب كتابة سبب الخصم من التأمين.',
        // رفع الملفات
        'upload_error': 'تعذّر رفع الملف. حاول مرة أخرى.',
        'invalid_file_type': 'نوع الملف غير مدعوم.'
    };

    var DEFAULT_ERROR = 'حدث خطأ غير متوقع. حاول مرة أخرى.';

    function translate(value) {
        if (typeof value !== 'string') {
            return '';
        }
        var key = value.trim();
        if (MESSAGES[key]) {
            return MESSAGES[key];
        }
        // رموز تقنية إنجليزية مجهولة (snake_case) لا تُعرض للمستخدم كما هي
        if (/^[a-z0-9_\-]+$/i.test(key)) {
            return '';
        }
        return key;
    }

    var MSUX = {
        messages: MESSAGES,

        /**
         * رسالة عربية مفهومة من أي شكل ردّ: {data:'code'} | {data:{message:'code'|'نص'}} | -1 | 0
         */
        error: function (json, fallback) {
            fallback = fallback || DEFAULT_ERROR;
            if (json === -1 || json === '-1' || json === 0 || json === '0') {
                return MESSAGES.security_failed;
            }
            if (!json || typeof json !== 'object') {
                return fallback;
            }
            var data = json.data;
            var msg = '';
            if (typeof data === 'string') {
                msg = translate(data);
            } else if (data && typeof data === 'object') {
                // الرمز أولاً: بعض الرسائل النصية من الخادم موجّهة للإدارة (مثل إعدادات WooCommerce)
                msg = translate(data.code) || translate(data.message) || translate(data.error);
            }
            return msg || fallback;
        },

        /**
         * يعطّل الزر أثناء الطلب. يرجع دالة لإعادته لحالته الأصلية.
         */
        busy: function (button, on, text) {
            if (!button) {
                return function () {};
            }
            if (on) {
                if (button.dataset.msBusy === '1') {
                    return function () {};
                }
                button.dataset.msBusy = '1';
                button.dataset.msLabel = button.textContent;
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                button.style.opacity = '0.7';
                button.style.cursor = 'wait';
                button.textContent = text || 'جاري التنفيذ...';
            } else {
                button.dataset.msBusy = '';
                button.disabled = false;
                button.removeAttribute('aria-busy');
                button.style.opacity = '';
                button.style.cursor = '';
                if (button.dataset.msLabel) {
                    button.textContent = button.dataset.msLabel;
                }
            }
            return function () { MSUX.busy(button, false); };
        },

        isBusy: function (button) {
            return !!(button && button.dataset.msBusy === '1');
        },

        /**
         * يقرأ JSON بأمان — admin-ajax قد يرجع "-1" أو "0" كنص عند فشل الـ nonce.
         */
        json: function (response) {
            return response.text().then(function (text) {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    return text.trim() === '-1' || text.trim() === '0' ? -1 : null;
                }
            });
        }
    };

    window.MSUX = MSUX;
    MSUX.version = '18.19.0';

    /* ======================================================================
       تحسينات التخطيط والحركة للوحات التحكم
       ====================================================================== */
    var REDUCED = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var ICONS = {
        home: '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
        building: '<path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4M10 10h4M10 14h4M10 18h4"/>',
        file: '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5z"/><path d="M14 2v6h6"/><path d="M16 13H8M16 17H8"/>',
        wallet: '<path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4z"/>',
        chart: '<path d="M3 3v18h18"/><path d="M18 17V9M13 17V5M8 17v-3"/>',
        wrench: '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94z"/>',
        shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/>',
        users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        calendar: '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        grid: '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/>'
    };
    var KPI_RULES = [
        [/إيجار|الإيجار/, 'calendar'], [/محفظة|رصيد/, 'wallet'], [/إيراد|تحصيل|مدفوع/, 'chart'],
        [/فاتور|فواتير/, 'file'], [/صيانة/, 'wrench'], [/تأمين/, 'shield'],
        [/أبنية|مباني|المبنى/, 'building'], [/عقار|شقق|شقة|وحد/, 'home'], [/مستأجر|مستخدم|ملاك/, 'users']
    ];
    function svg(name) {
        return '<svg class="ms-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + (ICONS[name] || ICONS.grid) + '</svg>';
    }

    /* ---- جداول: تمرير أفقي تلقائي ---- */
    function hasScrollParent(el, stop) {
        var p = el.parentElement;
        while (p && p !== stop) {
            var ox = window.getComputedStyle(p).overflowX;
            if (ox === 'auto' || ox === 'scroll') return true;
            p = p.parentElement;
        }
        return false;
    }
    function wrapTables(root) {
        root.querySelectorAll('table').forEach(function (table) {
            if (table.closest('.ms-table-scroll, .ms-table-wrap') || hasScrollParent(table, root)) return;
            var wrap = document.createElement('div');
            wrap.className = 'ms-table-scroll';
            table.parentNode.insertBefore(wrap, table);
            wrap.appendChild(table);
        });
    }

    /* ---- مؤشر القائمة المنزلق ---- */
    function setupIndicator(dashboard) {
        var ul = dashboard.querySelector('.ms-sidebar-menu');
        if (!ul || ul.querySelector('.ms-nav-indicator')) return function () {};
        var ind = document.createElement('div');
        ind.className = 'ms-nav-indicator';
        ind.setAttribute('aria-hidden', 'true');
        ul.insertBefore(ind, ul.firstChild);
        ul.classList.add('has-indicator');

        function place(instant) {
            var a = ul.querySelector('.ms-tab-link.active') || ul.querySelector('li.active > a');
            if (!a) { ind.style.opacity = '0'; return; }
            var li = a.closest('li') || a;
            var horizontal = window.getComputedStyle(ul).flexDirection === 'row';
            if (instant) { ind.style.transition = 'none'; }
            ind.style.opacity = '1';
            if (horizontal) {
                ind.style.left = '0'; ind.style.right = 'auto';
                ind.style.width = li.offsetWidth + 'px';
                ind.style.height = li.offsetHeight + 'px';
                ind.style.transform = 'translate3d(' + li.offsetLeft + 'px,' + li.offsetTop + 'px,0)';
                if (!instant && li.scrollIntoView) {
                    try { li.scrollIntoView({ behavior: REDUCED ? 'auto' : 'smooth', block: 'nearest', inline: 'center' }); } catch (e) {}
                }
            } else {
                ind.style.left = '0'; ind.style.right = '0'; ind.style.width = '';
                ind.style.height = li.offsetHeight + 'px';
                ind.style.transform = 'translate3d(0,' + li.offsetTop + 'px,0)';
            }
            if (instant) { ind.offsetHeight; ind.style.transition = ''; }
        }
        place(true);
        if ('MutationObserver' in window) {
            new MutationObserver(function () { place(false); }).observe(ul, { subtree: true, attributes: true, attributeFilter: ['class'] });
        }
        window.addEventListener('resize', function () { place(true); });
        if (document.fonts && document.fonts.ready) { document.fonts.ready.then(function () { place(true); }); }
        return place;
    }

    /* ---- بطاقات المؤشرات: أيقونة + عدّاد متحرك ---- */
    function enhanceKpis(dashboard) {
        dashboard.querySelectorAll('.ms-owner-grid > .ms-card, #overview .ms-grid > .ms-card, .ms-tab-content > .ms-grid > .ms-card').forEach(function (card) {
            if (card.classList.contains('ms-kpi') || !card.querySelector('.ms-number, .ms-amount')) return;
            card.classList.add('ms-kpi');
            var h = card.querySelector('h3');
            var label = h ? h.textContent : '';
            var icon = 'grid';
            for (var i = 0; i < KPI_RULES.length; i++) { if (KPI_RULES[i][0].test(label)) { icon = KPI_RULES[i][1]; break; } }
            var chip = document.createElement('span');
            chip.className = 'ms-kpi-icon';
            chip.innerHTML = svg(icon);
            card.insertBefore(chip, card.firstChild);
        });
    }

    function countUp(el) {
        if (REDUCED || el.dataset.msCounted) return;
        el.dataset.msCounted = '1';
        var walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, null);
        var node, target = null;
        while ((node = walker.nextNode())) { if (/\d/.test(node.nodeValue)) { target = node; break; } }
        if (!target) return;
        var m = target.nodeValue.match(/\d[\d,]*(\.\d+)?/);
        if (!m) return;
        var raw = m[0], value = parseFloat(raw.replace(/,/g, ''));
        if (!isFinite(value) || value === 0) return;
        var decimals = m[1] ? m[1].length - 1 : 0;
        var useGroup = raw.indexOf(',') !== -1 || value >= 1000;
        var before = target.nodeValue.slice(0, m.index), after = target.nodeValue.slice(m.index + raw.length);
        var fmt = function (v) {
            return v.toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals, useGrouping: useGroup });
        };
        var dur = 900 + Math.min(600, Math.log10(value + 1) * 120), t0 = null;
        function frame(ts) {
            if (!target.parentNode) return; // استُبدل النص من كود آخر — توقف بهدوء
            if (!t0) t0 = ts;
            var p = Math.min(1, (ts - t0) / dur), e = 1 - Math.pow(1 - p, 3);
            target.nodeValue = before + fmt(value * e) + after;
            if (p < 1) requestAnimationFrame(frame); else target.nodeValue = before + raw + after;
        }
        requestAnimationFrame(frame);
    }

    /* ---- شارات الحالة داخل الجداول ---- */
    function enhancePills(root) {
        root.querySelectorAll('table td span[style*="--ms-success"], table td span[style*="--ms-danger"], table td span[style*="--ms-warning"]').forEach(function (s) {
            if (s.classList.contains('ms-pill') || s.textContent.trim().length > 24 || s.children.length) return;
            s.classList.add('ms-pill');
            if (/--ms-danger/.test(s.getAttribute('style'))) s.classList.add('is-overdue');
        });
    }

    /* ---- ظهور الجداول عند التمرير ---- */
    var io = ('IntersectionObserver' in window) ? new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
            if (!en.isIntersecting) return;
            var el = en.target;
            if (el.classList.contains('ms-reveal')) el.classList.add('is-in');
            if (el.matches('.ms-number, .ms-amount')) countUp(el);
            io.unobserve(el);
        });
    }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' }) : null;

    function observe(dashboard) {
        dashboard.querySelectorAll('.ms-number, .ms-amount').forEach(function (el) {
            if (el.dataset.msObserved) return;
            el.dataset.msObserved = '1';
            if (io) io.observe(el); else countUp(el);
        });
        if (!REDUCED && io) {
            dashboard.querySelectorAll('.ms-tab-content.active .ms-table-scroll, .ms-tab-content.active .ms-table-wrap').forEach(function (el) {
                if (el.dataset.msObserved) return;
                el.dataset.msObserved = '1';
                if (el.getBoundingClientRect().top > window.innerHeight) {
                    el.classList.add('ms-reveal');
                    io.observe(el);
                }
            });
        }
    }

    function syncCurrent(dashboard) {
        dashboard.querySelectorAll('.ms-sidebar .ms-tab-link').forEach(function (a) {
            if (a.classList.contains('active')) a.setAttribute('aria-current', 'page');
            else a.removeAttribute('aria-current');
        });
    }

    /* ---- روابط مباشرة للأقسام: #units أو ?tab=units ----
       لوحات المستأجر والمالك ومدير المبنى تقرأ الهاش عند DOMContentLoaded فقط،
       فلا يتغيّر القسم عند تغيير الهاش وحده (لصق الرابط ونحن على نفس الصفحة،
       أو الضغط على رابط من قائمة Houzez الجانبية). هذا المعالج يغطي الحالتين. */
    function requestedTab() {
        var hash = (window.location.hash || '').replace(/^#/, '').trim();
        if (hash) return hash;
        var q = new URLSearchParams(window.location.search).get('tab');
        return q ? q.trim() : '';
    }

    var MS_DEBUG = false;
    function log() {
        if (!MS_DEBUG) return;
        var a = ['[MSUX]'].concat([].slice.call(arguments));
        console.log.apply(console, a);
    }

    function visibleTabs() {
        return [].filter.call(document.querySelectorAll('.ms-tab-content'), function (p) {
            return getComputedStyle(p).display !== 'none';
        }).map(function (p) { return p.id; });
    }

    /** يعيد فرض القسم إذا قام سكربت آخر بإخفائه بعدنا مباشرة */
    function holdTab(dashboard, tab, tries) {
        tries = tries || 0;
        if (tries > 6) return;
        setTimeout(function () {
            var panel = document.getElementById(tab);
            if (!panel) return;
            if (getComputedStyle(panel).display === 'none' || !panel.classList.contains('active')) {
                log('أُعيد إخفاء القسم من سكربت آخر — إعادة فرض', tab, 'محاولة', tries + 1);
                openTab(dashboard, tab, true);
                holdTab(dashboard, tab, tries + 1);
            }
        }, 120);
    }

    function openTab(dashboard, tab, skipHold) {
        if (!tab) return false;

        // الأقسام قد تكون خارج عنصر .ms-dashboard في بعض القوالب
        var panel = dashboard.querySelector('#' + CSS.escape(tab)) || document.getElementById(tab);
        if (!panel || !panel.classList.contains('ms-tab-content')) return false;

        var scope = panel.closest('.ms-dashboard') || dashboard;

        // بدّل القسم مباشرة بدل الاعتماد على معالج النقر الخاص بكل لوحة
        scope.querySelectorAll('.ms-tab-content').forEach(function (p) {
            var on = p === panel;
            p.classList.toggle('active', on);
            p.style.display = on ? 'block' : 'none';
        });

        // حدّث حالة القوائم: قائمة الإضافة وقائمة Houzez المحقونة
        scope.querySelectorAll('.ms-tab-link').forEach(function (a) {
            var on = a.getAttribute('data-tab') === tab;
            a.classList.toggle('active', on);
            if (a.parentElement && a.parentElement.tagName === 'LI') {
                a.parentElement.classList.toggle('active', on);
            }
            if (on) { a.setAttribute('aria-current', 'page'); } else { a.removeAttribute('aria-current'); }
        });
        document.querySelectorAll('[data-ms-hz-nav] a[data-ms-tab]').forEach(function (a) {
            a.classList.toggle('active', a.getAttribute('data-ms-tab') === tab && a.getAttribute('href').charAt(0) === '#');
        });

        document.dispatchEvent(new CustomEvent('ms:tab-opened', { detail: { tab: tab } }));
        log('فُتح القسم', tab, '| الظاهر الآن:', visibleTabs());
        if (!skipHold) {
            holdTab(dashboard, tab);
        }
        return true;
    }

    function bindDeepLinks(dashboard) {
        var apply = function () {
            var t = requestedTab();
            log('apply()، المطلوب:', t, '| الرابط:', window.location.href);
            var ok = openTab(dashboard, t);
            if (!ok) { log('لم يُفتح — لا يوجد قسم بهذا الاسم:', t, '| الأقسام:', MSUX.tabs()); }
        };

        setTimeout(apply, 60);
        window.addEventListener('hashchange', apply);
        window.addEventListener('popstate', apply);

        // النقر على أي رابط #قسم داخل الصفحة (قائمة Houzez، أزرار الإجراءات السريعة،
        // روابط داخل الجداول) — يعمل حتى لو لم يتغيّر الهاش
        document.addEventListener('click', function (e) {
            var link = e.target.closest && e.target.closest('a[href*="#"]');
            if (!link) return;
            log('نقرة على رابط:', link.getAttribute('href'));
            var href = link.getAttribute('href') || '';
            var tab = href.substring(href.indexOf('#') + 1).trim();
            if (!tab) return;
            var panel = document.getElementById(tab);
            if (!panel || !panel.classList.contains('ms-tab-content')) return;
            // رابط لصفحة أخرى: اتركه يذهب
            var path = href.split('#')[0];
            if (path && path.indexOf('#') !== 0 && path !== window.location.pathname
                && path !== window.location.pathname + window.location.search
                && !path.startsWith('?')) {
                return;
            }
            e.preventDefault();
            if (window.location.hash.replace(/^#/, '') !== tab) {
                history.pushState(null, '', '#' + tab);
            }
            openTab(dashboard, tab);
        });
    }

    function enhanceDashboards() {
        document.querySelectorAll('.ms-dashboard').forEach(function (dashboard) {
            bindDeepLinks(dashboard);
            wrapTables(dashboard);
            enhanceKpis(dashboard);
            enhancePills(dashboard);
            setupIndicator(dashboard);
            syncCurrent(dashboard);
            observe(dashboard);
            dashboard.addEventListener('click', function (e) {
                if (!(e.target.closest && e.target.closest('.ms-tab-link, [data-tab-target]'))) return;
                setTimeout(function () {
                    syncCurrent(dashboard);
                    wrapTables(dashboard);
                    enhancePills(dashboard);
                    observe(dashboard);
                    // على الهاتف: ارجع لبداية المحتوى بانسيابية عند تبديل القسم
                    var content = dashboard.querySelector('.ms-content');
                    if (content && window.innerWidth < 992 && content.getBoundingClientRect().top < 0) {
                        window.scrollTo({ top: window.pageYOffset + content.getBoundingClientRect().top - 80, behavior: REDUCED ? 'auto' : 'smooth' });
                    }
                }, 0);
            });
        });
    }

    /* أدوات تشخيص من الكونسول:
         MSUX.version            → رقم النسخة المحمّلة فعلاً
         MSUX.openTab('units')   → تبديل القسم يدوياً (يرجع true إذا نجح)
         MSUX.tabs()             → أسماء الأقسام الموجودة في الصفحة */
    MSUX.openTab = function (tab) {
        var dashboard = document.querySelector('.ms-dashboard') || document.body;
        return openTab(dashboard, tab);
    };
    MSUX.debug = function (on) {
        MS_DEBUG = on !== false;
        console.log('[MSUX] وضع التشخيص:', MS_DEBUG ? 'مفعّل' : 'مطفأ', '| النسخة', MSUX.version);
        return MS_DEBUG;
    };
    MSUX.tabs = function () {
        return [].map.call(document.querySelectorAll('.ms-tab-content'), function (p) { return p.id; });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', enhanceDashboards);
    } else {
        enhanceDashboards();
    }
})(window);
