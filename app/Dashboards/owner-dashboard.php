<?php
if (!defined('ABSPATH')) exit;

add_shortcode('owner_dashboard_v4', function () {
    if (!is_user_logged_in()) {
        ob_start();
        ?>
        <div style="max-width:400px;margin:50px auto;padding:40px;background:#fff;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,0.1);">
            <h2 style="text-align:center;margin-bottom:30px;color:#0f172a;">تسجيل الدخول</h2>
            <?php
            $args = array(
                'echo' => true,
                'redirect' => (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
                'form_id' => 'ms-loginform',
                'label_username' => 'البريد الإلكتروني',
                'label_password' => 'كلمة المرور',
                'label_remember' => 'تذكرني',
                'label_log_in' => 'تسجيل الدخول',
                'id_username' => 'user_login',
                'id_password' => 'user_pass',
                'id_remember' => 'rememberme',
                'id_submit' => 'wp-submit',
                'remember' => true,
                'value_username' => '',
                'value_remember' => false,
            );
            wp_login_form($args);
            ?>
            <p style="text-align:center;margin-top:20px;">
                <a href="<?php echo wp_lostpassword_url(); ?>" style="color:#2563eb;text-decoration:none;">نسيت كلمة المرور؟</a>
            </p>
        </div>
        <?php
        return ob_get_clean();
    }

    $user = wp_get_current_user();
    
    // Check permissions: only site admin or owner can access
    if (!current_user_can('manage_options') && !function_exists('ms_user_has_role')) {
        return '<p>غير مصرح لك بالوصول إلى هذه الصفحة.</p>';
    }
    
    if (!current_user_can('manage_options') && !ms_user_has_role($user->ID, 'owner')) {
        return '<p>غير مصرح لك بالوصول إلى هذه الصفحة. هذه الصفحة مخصصة للمالكين فقط.</p>';
    }

    ob_start();

    $properties = function_exists('ms_get_properties_by_owner') ? ms_get_properties_by_owner($user->ID, 100) : [];
    $invoices = function_exists('ms_get_owner_invoices') ? ms_get_owner_invoices($user->ID, 100) : [];
    
    // Group invoices by category
    $invoice_groups = [
        'property' => [], // property-rent, property-monthly, property-sale
        'building' => [], // building-maintenance, building-facilities, building-utilities
    ];
    foreach ($invoices as $invoice) {
        $category = isset($invoice->invoice_category) ? $invoice->invoice_category : 'property-rent';
        if (in_array($category, ['property-rent', 'property-monthly', 'property-sale', 'agent-fees'])) {
            $invoice_groups['property'][] = $invoice;
        } else {
            $invoice_groups['building'][] = $invoice;
        }
    }
    
    $wallet = function_exists('ms_get_wallet_balance') ? ms_get_wallet_balance($user->ID) : 0;
    $owner_summary = function_exists('ms_get_owner_revenue_summary') ? ms_get_owner_revenue_summary($user->ID) : array('total_paid' => 0, 'total_due' => 0, 'next_due' => null);
    $owner_wallet_transactions = function_exists('ms_get_wallet_transactions_for_user') ? ms_get_wallet_transactions_for_user($user->ID, 20) : array();
    $wallet_topup_url = '';

    // Owner notifications & unread count
    $owner_notifications = function_exists('ms_get_notifications_by_user') ? ms_get_notifications_by_user($user->ID, 20) : array();
    $owner_unread_count = function_exists('ms_get_unread_notifications_count') ? ms_get_unread_notifications_count($user->ID) : 0;

    // Gather maintenance requests for buildings linked to owner's properties
    global $wpdb;
    $owner_maintenance_requests = array();
    $building_ids = array();
    if (!empty($properties)) {
        foreach ($properties as $prop) {
            $prop_id = isset($prop->id) ? intval($prop->id) : (isset($prop->ID) ? intval($prop->ID) : 0);
            $b = 0;
            if ($prop_id) {
                $b = intval(get_post_meta($prop_id, 'building_id', true));
            }
            if (!$b && isset($prop->building_id)) {
                $b = intval($prop->building_id);
            }
            if (!$b && isset($prop->unit) && is_object($prop->unit) && isset($prop->unit->building_id)) {
                $b = intval($prop->unit->building_id);
            }
            if ($b) {
                $building_ids[] = $b;
            }
        }
    }
    $building_ids = array_values(array_unique(array_filter($building_ids, 'absint')));
    if (!empty($building_ids)) {
        $ids_csv = implode(',', array_map('intval', $building_ids));
        $maint_table = $wpdb->prefix . 'ms_maintenance_requests';
        // Important: $building_ids contains building IDs, not unit/property IDs.
        // Do not pass it to ms_get_maintenance_by_property_ids(), which filters unit_id.
        $owner_maintenance_requests = $wpdb->get_results("SELECT * FROM {$maint_table} WHERE building_id IN ({$ids_csv}) ORDER BY created_at DESC LIMIT 50");
    }

    ?>
    <style>
        .property-nav-tabs {
            position: relative;
            z-index: 9999;
            pointer-events: auto;
        }
        .houzez-data-content {
            position: relative;
            z-index: 1;
        }
        .property-nav-tabs > ul {
            gap: 32px !important;
            flex-wrap: nowrap !important;
            display: flex !important;
        }
        .property-nav-tabs ul li {
            flex-shrink: 0 !important;
        }
        .property-nav-tabs ul li a,
        .property-nav-tabs ul li button.property-tab-button {
            margin: 0 !important;
            padding: 10px 24px !important;
            white-space: nowrap !important;
            min-width: 180px !important;
            text-align: center !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
        }
        .property-nav-tabs ul li button.property-tab-button span {
            margin-right: 0;
            font-weight: 600;
        }
        .ms-property-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        .ms-property-card-title a:hover {
            color: #2563eb;
        }
        .agent-upload-contract-button:hover {
            opacity: 0.9;
        }
        /* Owner quick actions stay on one desktop row and do not duplicate the sidebar menu. */
        .ms-owner-top .ms-dashboard-actions {
            display: flex !important;
            flex-wrap: nowrap !important;
            align-items: center !important;
            justify-content: flex-start !important;
            gap: 10px !important;
            white-space: nowrap !important;
        }
        .ms-owner-top .ms-dashboard-actions .ms-action-button {
            flex: 0 0 auto !important;
            margin: 0 !important;
        }
        @media (max-width: 768px) {
            .property-nav-tabs > ul {
                gap: 12px !important;
                flex-wrap: wrap !important;
            }
            .property-nav-tabs ul li a,
            .property-nav-tabs ul li button.property-tab-button {
                padding: 8px 12px !important;
                min-width: 80px !important;
                font-size: 13px !important;
            }
        }
    </style>

    <div class="ms-dashboard">

        <?php
        $ms_dashboard_menu_items = array(
            array('label' => 'نظرة عامة', 'data_tab' => 'overview', 'icon' => '🏠'),
            array('label' => 'إضافة عقار', 'data_tab' => 'add-property', 'icon' => '✍️'),
            array('label' => 'العقارات', 'data_tab' => 'properties', 'icon' => '🏘️', 'badge' => intval(count($properties))),
            array('label' => 'الفواتير', 'data_tab' => 'invoices', 'icon' => '🧾', 'badge' => intval(count($invoices))),
            array('label' => 'الصيانة', 'data_tab' => 'maintenance', 'icon' => '🛠️'),
            array('label' => 'المحفظة', 'data_tab' => 'wallet', 'icon' => '💰'),
            array('label' => 'تأمينات الأمانة', 'data_tab' => 'deposits', 'icon' => '🔒'),
            array('label' => 'المناقشات', 'data_tab' => 'discussions', 'icon' => '💬'),
            array('label' => 'البروفايل', 'data_tab' => 'profile', 'icon' => '👤'),
            array('href' => wp_logout_url(), 'label' => 'تسجيل الخروج', 'external' => true, 'icon' => '🚪'),
        );
        // Keep one navigation item per tab even if Houzez or an integration adds duplicates.
        $ms_seen_dashboard_tabs = array();
        $ms_dashboard_menu_items = array_values(array_filter($ms_dashboard_menu_items, function ($item) use (&$ms_seen_dashboard_tabs) {
            $tab_key = !empty($item['data_tab']) ? sanitize_key($item['data_tab']) : (!empty($item['href']) ? (string) $item['href'] : wp_json_encode($item));
            if (isset($ms_seen_dashboard_tabs[$tab_key])) {
                return false;
            }
            $ms_seen_dashboard_tabs[$tab_key] = true;
            return true;
        }));
        ms_load_dashboard_sidebar($ms_dashboard_menu_items);
        ?>

        <main class="ms-content">

            <div class="ms-owner-top">
                <div>
                    <p class="ms-eyebrow">مركز إدارة العقارات</p>
                    <h1>مرحبا، <?php echo esc_html($user->display_name); ?></h1>
                    <p class="ms-page-subtitle">تابع العقارات والإيرادات والطلبات التي تحتاج إلى إجراء.</p>
                </div>
                <div class="ms-dashboard-actions" role="group" aria-label="إجراءات سريعة">
                    <button type="button" class="ms-action-button ms-action-button-primary" data-tab-target="add-property">إضافة عقار</button>
                    <button type="button" class="ms-action-button" data-tab-target="properties">عرض العقارات</button>
                    <button type="button" class="ms-action-button" data-tab-target="invoices">مراجعة الفواتير</button>
                    <button type="button" class="ms-action-button" data-tab-target="maintenance">متابعة الصيانة</button>
                </div>
            </div>

            <?php echo do_shortcode('[ms_action_center_owner]'); ?>

            <?php if (empty($properties)) : ?>
                <div class="ms-guided-empty-state">
                    <div class="ms-guided-empty-state__icon" aria-hidden="true">🏘️</div>
                    <div>
                        <h2>ابدأ بإدارة أول عقار</h2>
                        <p>لا توجد عقارات مرتبطة بحسابك حاليًا. أضف عقارًا أو اطلب من مدير النظام ربط العقارات الموجودة بحسابك.</p>
                    </div>
                    <button type="button" class="ms-action-button ms-action-button-primary" data-tab-target="add-property">إضافة أول عقار</button>
                </div>
            <?php endif; ?>

            <div class="ms-tab-content active" id="overview">
                <div class="ms-owner-grid">
                    <div class="ms-card"><h3>عقاراتي</h3><div id="ms-props-count" class="ms-amount"><?php echo intval(count($properties)); ?></div></div>
                    <div class="ms-card"><h3>الفواتير المرتبطة بالعقارات</h3><div id="ms-invoices-count" class="ms-amount"><?php echo intval(count($invoices)); ?></div>
                        <div style="margin-top:8px;font-size:13px;color:#666">مدفوعة: <span id="ms-invoices-paid"><?php echo function_exists('ms_get_owner_invoices_count_by_status') ? ms_get_owner_invoices_count_by_status($user->ID,'paid') : 0; ?></span> — معلقة: <span id="ms-invoices-pending"><?php echo function_exists('ms_get_owner_invoices_count_by_status') ? ms_get_owner_invoices_count_by_status($user->ID,'pending') : 0; ?></span></div>
                    </div>
<div class="ms-card"><h3>رصيد المحفظة</h3><div id="ms-wallet-balance" class="ms-amount"><?php echo 'ج.م ' . number_format_i18n($wallet,2); ?></div></div>
<div class="ms-card"><h3>إجمالي الإيرادات</h3><div class="ms-amount">ج.م <span id="ms-owner-total-paid"><?php echo number_format_i18n($owner_summary['total_paid'],2); ?></span></div>
<div style="margin-top:8px;font-size:13px;color:#666">المستحق: ج.م <span id="ms-owner-total-due"><?php echo number_format_i18n($owner_summary['total_due'],2); ?></span> — متأخرة: <span id="ms-owner-overdue"><?php echo function_exists('ms_get_owner_overdue_count') ? ms_get_owner_overdue_count($user->ID) : 0; ?></span></div>
                        <div style="margin-top:6px;font-size:13px;color:#444">القادم: <span id="ms-owner-next-due"><?php echo !empty($owner_summary['next_due']->due_date) ? esc_html($owner_summary['next_due']->due_date) : '—'; ?></span></div>
                    </div>
                </div>

                <div class="ms-card" style="margin-top: 20px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                        <h3 style="margin:0;">الإشعارات الأخيرة</h3>
                        <?php if ($owner_unread_count > 0) : ?>
                            <span style="background:#ef4444;color:#fff;padding:3px 10px;border-radius:999px;font-size:0.78rem;font-weight:700;"><?php echo intval($owner_unread_count); ?> غير مقروء</span>
                        <?php endif; ?>
                        <?php if (!empty($owner_notifications)) : ?>
                            <button type="button" id="ms-mark-all-notifications-read" style="background:none;border:1px solid #d1d5db;padding:6px 14px;border-radius:8px;cursor:pointer;font-size:0.82rem;color:#4b5563;">تعليم الكل كمقروء</button>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($owner_notifications)) : ?>
                        <div style="display:flex;flex-direction:column;gap:10px;">
                            <?php foreach ($owner_notifications as $note) : ?>
                                <div style="padding:14px 16px;border-radius:12px;background:<?php echo !empty($note->is_read) ? '#f9fafb' : '#eff6ff'; ?>;border:1px solid <?php echo !empty($note->is_read) ? '#e5e7eb' : '#bfdbfe'; ?>;">
                                    <div class="ms-notification-message" style="font-size:14px;color:#111;line-height:1.6;"><?php echo esc_html(rawurldecode((string) ($note->message ?? ''))); ?></div>
                                    <div style="margin-top:6px;font-size:12px;color:#6b7280;"><?php echo esc_html(date_i18n('Y-m-d H:i', strtotime($note->created_at ?? $note->created_on ?? ''))); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <p style="padding:12px;margin:0;">لا توجد إشعارات جديدة.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="ms-tab-content" id="add-property">
                <div class="ms-card">
                    <h3>إضافة عقار جديد</h3>
                    <p style="color:#64748b;margin-bottom:16px;">أكمل بيانات العقار داخل هذه اللوحة. تُحفظ المسودة تلقائيًا ويمكنك العودة إليها لاحقًا.</p>
                    <?php echo do_shortcode('[ms_inline_add_property]'); ?>
                </div>
            </div>

            <?php
            // Owner properties tab (Houzez-style listing)
            global $properties_query, $delete_properties_nonce;
            $delete_properties_nonce = wp_create_nonce('delete_properties_nonce');

            $paged = get_query_var('paged') ?: get_query_var('page') ?: 1;
            $property_ids = array();
            if (function_exists('ms_get_properties_by_owner')) {
                $owned_properties = ms_get_properties_by_owner($user->ID);
                foreach ($owned_properties as $owned_property) {
                    // If property is Houzez post-based, use its post ID
                    if (isset($owned_property->type) && $owned_property->type === 'houzez') {
                        if (isset($owned_property->id)) {
                            $property_ids[] = intval($owned_property->id);
                            continue;
                        } elseif (isset($owned_property->ID)) {
                            $property_ids[] = intval($owned_property->ID);
                            continue;
                        }
                    }

                    // For unit/other types, attempt to use linked WP post id if present
                    if (!empty($owned_property->wp_post_id)) {
                        $property_ids[] = intval($owned_property->wp_post_id);
                        continue;
                    }
                    if (!empty($owned_property->post_id)) {
                        $property_ids[] = intval($owned_property->post_id);
                        continue;
                    }

                    // If the property has a nested unit object, try its wp_post_id
                    if (!empty($owned_property->unit) && is_object($owned_property->unit) && !empty($owned_property->unit->wp_post_id)) {
                        $property_ids[] = intval($owned_property->unit->wp_post_id);
                        continue;
                    }
                }
            }
            $property_ids = array_values(array_unique(array_filter($property_ids, 'absint')));

            $args = array(
                'post_type' => 'property',
                'paged' => $paged,
                'posts_per_page' => 10,
                'suppress_filters' => false,
                'post_status' => array('publish', 'pending', 'draft', 'expired', 'houzez_sold', 'disapproved', 'on_hold', 'private', 'future'),
            );

            if (!empty($property_ids)) {
                $args['post__in'] = $property_ids;
                $args['orderby'] = 'post__in';
            } else {
                $args['author'] = $user->ID;
            }

            $properties_query = new WP_Query($args);
            $properties_count = intval($properties_query->found_posts);

            global $wpdb;
            $owner_unit_display = array();
            $owner_unit_rows = $wpdb->get_results($wpdb->prepare(
                "SELECT u.id, u.building_id, u.unit_number, u.status, u.tenant_id, b.title AS building_title, tenant.display_name AS tenant_name
                 FROM {$wpdb->prefix}ms_units u
                 LEFT JOIN {$wpdb->prefix}ms_buildings b ON b.id = u.building_id
                 LEFT JOIN {$wpdb->users} tenant ON tenant.ID = u.tenant_id
                 WHERE u.owner_id = %d",
                $user->ID
            ));
            foreach ((array) $owner_unit_rows as $owner_unit_row) {
                $owner_unit_display[absint($owner_unit_row->id)] = $owner_unit_row;
            }

            $invoice_groups = array(
                'property' => array(),
                'building' => array(),
            );
            foreach ((array) $invoices as $invoice_item) {
                $unit_id_for_invoice = absint($invoice_item->unit_id ?? 0);
                if ($unit_id_for_invoice && isset($owner_unit_display[$unit_id_for_invoice])) {
                    $unit_row = $owner_unit_display[$unit_id_for_invoice];
                    $invoice_item->owner_unit_label = ($unit_row->unit_number ? 'الشقة ' . $unit_row->unit_number : 'الوحدة #' . $unit_row->id) . ($unit_row->building_title ? ' — ' . $unit_row->building_title : '');
                    $invoice_item->owner_tenant_label = ($unit_row->status === 'rented' && absint($unit_row->tenant_id)) ? ('المستأجر: ' . ($unit_row->tenant_name ?: 'مرتبط بالوحدة')) : 'المالك: العقار شاغر/معروض للبيع';
                    $invoice_item->owner_rent_state = ($unit_row->status === 'rented' && absint($unit_row->tenant_id)) ? 'occupied' : 'vacant';
                }
                $invoice_category = isset($invoice_item->invoice_category) ? sanitize_key($invoice_item->invoice_category) : '';
                $invoice_type = isset($invoice_item->invoice_type) ? sanitize_key($invoice_item->invoice_type) : '';
                $is_building_invoice = in_array($invoice_category, array('building-maintenance', 'building-facilities', 'building-utilities', 'maintenance', 'facility', 'utilities'), true)
                    || in_array($invoice_type, array('building-maintenance', 'building-facilities', 'building-utilities', 'maintenance', 'facility', 'utilities'), true);
                $invoice_groups[$is_building_invoice ? 'building' : 'property'][] = $invoice_item;
            }
            ?>

            <div class="ms-tab-content" id="properties">
                <div class="ms-properties-header" style="display:flex;justify-content:space-between;align-items:center;gap:14px;margin-bottom:18px;">
                    <div><h2 style="margin:0;color:#0f172a;">العقارات</h2><p style="margin:6px 0 0;color:#64748b;font-size:14px;">إدارة عقاراتك ومتابعة حالتها وإجراءاتها من مكان واحد.</p></div>
                    <button type="button" class="ms-action-button ms-action-button-primary" data-tab-target="add-property">إضافة جديدة</button>
                </div>
                <?php if ( isset($properties_query) && $properties_query->have_posts() ): ?>
                    <?php get_template_part('template-parts/dashboard/property/tabs'); ?>
                    <div class="houzez-data-content">
                        <?php get_template_part('template-parts/dashboard/property/filters'); ?>

                        <div class="ms-properties-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:20px;margin-top:20px;">
                            <?php global $ms_dashboard_context; $ms_dashboard_context = 'owner'; ?>
                            <?php while ( $properties_query->have_posts() ): $properties_query->the_post(); ?>
                                <?php include MOSTAAGER_ENTERPRISE_PATH . 'templates/partials/property-card.php'; ?>
                            <?php endwhile; wp_reset_postdata(); unset($ms_dashboard_context); ?>
                        </div>

                        <?php get_template_part('template-parts/dashboard/property/pagination'); ?>
                    </div>
                <?php else: ?>
                    <div class="ms-guided-empty-state" style="grid-template-columns:auto 1fr auto;">
                        <div class="ms-guided-empty-state__icon" aria-hidden="true">🏠</div>
                        <div><h2>لم يتم العثور على أي عقارات</h2><p>ابدأ بإضافة أول عقار ليظهر هنا مع حالته وصورته وإجراءات التعديل والحذف.</p></div>
                        <button type="button" class="ms-action-button ms-action-button-primary" data-tab-target="add-property">إنشاء قائمة</button>
                    </div>
                <?php endif; ?>
            </div>

            <div class="ms-tab-content" id="invoices">
                <div class="ms-card"><h3>الفواتير</h3>
                <p style="color:#6b7280;font-size:14px;margin-top:8px;">تعرض هذه الصفحة فواتير العقارات التي تملكها وفواتير البناء والصيانة المرتبطة بها، مع فصل النوعين لتسهيل المراجعة والدفع.</p>
                <?php if(!empty($invoices)): ?>
                    <div class="ms-invoice-subtabs" style="margin-top:18px;display:flex;gap:8px;flex-wrap:wrap;">
                        <button class="ms-invoice-subtab active" data-subtab="invoices-property" style="padding:10px 20px;border-radius:999px;border:1px solid #e5e7eb;background:#2563eb;color:#fff;cursor:pointer;font-weight:600;">فواتير العقار (إيجار/بيع)</button>
                        <button class="ms-invoice-subtab" data-subtab="invoices-building" style="padding:10px 20px;border-radius:999px;border:1px solid #e5e7eb;background:#fff;color:#0f172a;cursor:pointer;font-weight:600;">فواتير البناء والصيانة</button>
                    </div>
                    <div class="ms-invoice-subpanel active" id="invoices-property" style="margin-top:16px;">
                        <?php if (!empty($invoice_groups['property'])): ?>
                            <table class="widefat fixed striped" style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                                <thead>
                                    <tr>
                                        <th style="padding: 12px; text-align: right;">#</th>
                                        <th style="padding: 12px; text-align: right;">الوصف</th>
                                        <th style="padding: 12px; text-align: right;">المبنى</th>
                                        <th style="padding: 12px; text-align: right;">المستأجر / الملزم بالدفع</th>
                                        <th style="padding: 12px; text-align: right;">المبلغ</th>
                                        <th style="padding: 12px; text-align: right;">الحالة</th>
                                        <th style="padding: 12px; text-align: right;">تاريخ الاستحقاق</th>
                                        <th style="padding: 12px; text-align: right;">تاريخ الإنشاء</th>
                                        <th style="padding: 12px; text-align: right;">إجراء</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $invoice_index = 1; foreach($invoice_groups['property'] as $inv): ?>
                                        <?php
                                            $description = isset($inv->description) ? $inv->description : (isset($inv->invoice_type) ? $inv->invoice_type : 'فاتورة');
                                            $building_name = '—';
                                            if (isset($inv->building_id) && $inv->building_id) {
                                                $building_post = get_post($inv->building_id);
                                                if ($building_post) {
                                                    $building_name = $building_post->post_title;
                                                }
                                            }
                                            $amount = isset($inv->amount) ? number_format_i18n(floatval($inv->amount), 2) : '0.00';
                                            $status = isset($inv->status) ? strtolower(trim($inv->status)) : 'unknown';
                                            $due_date = isset($inv->due_date) ? $inv->due_date : '—';
                                            $created_at = isset($inv->created_at) ? $inv->created_at : (isset($inv->created_on) ? $inv->created_on : '—');
                                            $is_paid = $status === 'paid';
                                            $is_canceled = $status === 'canceled';
                                            $status_label = '';
                                            if ($is_paid) {
                                                $status_label = '<span style="color:#10b981;font-weight:600">مدفوع</span>';
                                            } elseif ($is_canceled) {
                                                $status_label = '<span style="color:#ef4444;font-weight:600">ملغي</span>';
                                            } elseif ($status === 'pending') {
                                                $status_label = 'معلقة';
                                            } elseif ($status === 'overdue') {
                                                $status_label = '<span style="color:#f59e0b;font-weight:600">متأخرة</span>';
                                            } else {
                                                $status_label = esc_html($status);
                                            }
                                        ?>
                                        <tr>
                                            <td style="padding: 12px; text-align: center;"><?php echo intval($invoice_index++); ?></td>
                                            <td style="padding: 12px;"><?php echo esc_html($description); ?></td>
                                            <td style="padding: 12px;"><?php echo esc_html($building_name); ?></td>
                                            <td style="padding: 12px;"><strong><?php echo esc_html($inv->owner_tenant_label ?? '—'); ?></strong><?php if (($inv->owner_rent_state ?? '') === 'occupied' && in_array(strtolower((string) ($inv->invoice_type ?? '')), array('rent','property-rent','property-monthly'), true)) : ?><br><small style="color:#64748b">حالة الإيجار: بانتظار السداد أو تم السداد حسب حالة الفاتورة</small><?php elseif (($inv->owner_rent_state ?? '') === 'vacant') : ?><br><small style="color:#64748b">رسوم المرافق على المالك لأن الوحدة شاغرة</small><?php endif; ?></td>
                                            <td style="padding: 12px;">ج.م <?php echo esc_html($amount); ?></td>
                                            <td style="padding: 12px;"><?php echo $status_label; ?></td>
                                            <td style="padding: 12px;"><?php echo esc_html($due_date); ?></td>
                                            <td style="padding: 12px;"><?php echo esc_html($created_at); ?></td>
                                            <td style="padding: 12px;">
                                                <?php if (!$is_paid && (!isset($inv->source) || $inv->source !== 'legacy')): ?>
                                                    <button class="ms-pay-now-btn" data-invoice-id="<?php echo intval($inv->id); ?>" data-nonce="<?php echo wp_create_nonce('ms_pay_invoice_' . $inv->id); ?>" style="padding: 6px 12px; background: #2563eb; color: white; border: none; border-radius: 4px; cursor: pointer;">ادفع الآن</button>
                                                <?php elseif ($is_paid): ?>
                                                    <span style="color: #10b981; font-size: 12px;">✓ تم الدفع</span>
                                                <?php else: ?>
                                                    <span style="color: #6b7280;">غير متاح للمدفوعات القديمة</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p style="padding: 12px; margin:0;">لا توجد فواتير إيجار أو بيع.</p>
                        <?php endif; ?>
                    </div>
                    <div class="ms-invoice-subpanel" id="invoices-building" style="display:none;margin-top:16px;">
                        <?php if (!empty($invoice_groups['building'])): ?>
                            <table class="widefat fixed striped" style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                                <thead>
                                    <tr>
                                        <th style="padding: 12px; text-align: right;">#</th>
                                        <th style="padding: 12px; text-align: right;">الوصف</th>
                                        <th style="padding: 12px; text-align: right;">المبنى</th>
                                        <th style="padding: 12px; text-align: right;">المستأجر / الملزم بالدفع</th>
                                        <th style="padding: 12px; text-align: right;">المبلغ</th>
                                        <th style="padding: 12px; text-align: right;">الحالة</th>
                                        <th style="padding: 12px; text-align: right;">تاريخ الاستحقاق</th>
                                        <th style="padding: 12px; text-align: right;">تاريخ الإنشاء</th>
                                        <th style="padding: 12px; text-align: right;">إجراء</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $invoice_index = 1; foreach($invoice_groups['building'] as $inv): ?>
                                        <?php
                                            $description = isset($inv->description) ? $inv->description : (isset($inv->invoice_type) ? $inv->invoice_type : 'فاتورة');
                                            $building_name = '—';
                                            if (isset($inv->building_id) && $inv->building_id) {
                                                $building_post = get_post($inv->building_id);
                                                if ($building_post) {
                                                    $building_name = $building_post->post_title;
                                                }
                                            }
                                            $amount = isset($inv->amount) ? number_format_i18n(floatval($inv->amount), 2) : '0.00';
                                            $status = isset($inv->status) ? strtolower(trim($inv->status)) : 'unknown';
                                            $due_date = isset($inv->due_date) ? $inv->due_date : '—';
                                            $created_at = isset($inv->created_at) ? $inv->created_at : (isset($inv->created_on) ? $inv->created_on : '—');
                                            $is_paid = $status === 'paid';
                                            $is_canceled = $status === 'canceled';
                                            $status_label = '';
                                            if ($is_paid) {
                                                $status_label = '<span style="color:#10b981;font-weight:600">مدفوع</span>';
                                            } elseif ($is_canceled) {
                                                $status_label = '<span style="color:#ef4444;font-weight:600">ملغي</span>';
                                            } elseif ($status === 'pending') {
                                                $status_label = 'معلقة';
                                            } elseif ($status === 'overdue') {
                                                $status_label = '<span style="color:#f59e0b;font-weight:600">متأخرة</span>';
                                            } else {
                                                $status_label = esc_html($status);
                                            }
                                        ?>
                                        <tr>
                                            <td style="padding: 12px; text-align: center;"><?php echo intval($invoice_index++); ?></td>
                                            <td style="padding: 12px;"><?php echo esc_html($description); ?></td>
                                            <td style="padding: 12px;"><?php echo esc_html($building_name); ?></td>
                                            <td style="padding: 12px;"><strong><?php echo esc_html($inv->owner_tenant_label ?? '—'); ?></strong><?php if (($inv->owner_rent_state ?? '') === 'occupied' && in_array(strtolower((string) ($inv->invoice_type ?? '')), array('rent','property-rent','property-monthly'), true)) : ?><br><small style="color:#64748b">حالة الإيجار: بانتظار السداد أو تم السداد حسب حالة الفاتورة</small><?php elseif (($inv->owner_rent_state ?? '') === 'vacant') : ?><br><small style="color:#64748b">رسوم المرافق على المالك لأن الوحدة شاغرة</small><?php endif; ?></td>
                                            <td style="padding: 12px;">ج.م <?php echo esc_html($amount); ?></td>
                                            <td style="padding: 12px;"><?php echo $status_label; ?></td>
                                            <td style="padding: 12px;"><?php echo esc_html($due_date); ?></td>
                                            <td style="padding: 12px;"><?php echo esc_html($created_at); ?></td>
                                            <td style="padding: 12px;">
                                                <?php if (!$is_paid && (!isset($inv->source) || $inv->source !== 'legacy')): ?>
                                                    <button class="ms-pay-now-btn" data-invoice-id="<?php echo intval($inv->id); ?>" data-nonce="<?php echo wp_create_nonce('ms_pay_invoice_' . $inv->id); ?>" style="padding: 6px 12px; background: #2563eb; color: white; border: none; border-radius: 4px; cursor: pointer;">ادفع الآن</button>
                                                <?php elseif ($is_paid): ?>
                                                    <span style="color: #10b981; font-size: 12px;">✓ تم الدفع</span>
                                                <?php else: ?>
                                                    <span style="color: #6b7280;">غير متاح للمدفوعات القديمة</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p style="padding: 12px; margin:0;">لا توجد فواتير بناء أو إدارة مرافق.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p style="padding: 12px;">لا توجد فواتير</p>
                <?php endif; ?>
                </div>
            </div>

            <div class="ms-tab-content" id="maintenance">
                <div class="ms-card"><h3>الصيانة</h3>
                <p style="color:#6b7280;font-size:14px;margin-top:8px;">هذه طلبات الصيانة المتعلقة بالبناء لمبانيك.</p>
                    <?php if (!empty($owner_maintenance_requests)) : ?>
                        <table style="width:100%;border-collapse:collapse;margin-top:10px;">
                            <thead>
                                <tr style="background:#f3f4f6;">
                                    <th style="padding:12px;text-align:right;border-bottom:2px solid #e5e7eb;">العنوان</th>
                                    <th style="padding:12px;text-align:right;border-bottom:2px solid #e5e7eb;">النوع</th>
                                    <th style="padding:12px;text-align:right;border-bottom:2px solid #e5e7eb;">التكلفة</th>
                                    <th style="padding:12px;text-align:right;border-bottom:2px solid #e5e7eb;">الحالة</th>
                                    <th style="padding:12px;text-align:right;border-bottom:2px solid #e5e7eb;">تاريخ الإنشاء</th>
                                    <th style="padding:12px;text-align:right;border-bottom:2px solid #e5e7eb;">إجراء</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($owner_maintenance_requests as $req) : ?>
                                    <tr style="border-bottom:1px solid #e5e7eb;">
                                        <td style="padding:12px;"><?php echo esc_html($req->title ?? 'بدون عنوان'); ?></td>
                                        <td style="padding:12px;"><?php echo esc_html($req->maintenance_type ?? ($req->type ?? '—')); ?></td>
                                        <td style="padding:12px;">ج.م <?php echo isset($req->cost) ? number_format_i18n(floatval($req->cost),2) : '0.00'; ?></td>
                                        <td style="padding:12px;"><?php echo esc_html($req->status ?? '—'); ?></td>
                                        <td style="padding:12px;"><?php echo esc_html($req->created_at ?? $req->created_on ?? '—'); ?></td>
                                        <td style="padding:12px;">
                                            <?php if (isset($req->status) && $req->status === 'pending' && isset($req->cost) && floatval($req->cost) > 0) : ?>
                                                <button class="ms-pay-maintenance-btn" data-request-id="<?php echo intval($req->id); ?>" data-amount="<?php echo floatval($req->cost); ?>" style="padding:6px 12px;background:#2563eb;color:#fff;border:0;border-radius:6px;cursor:pointer;font-size:13px;">الدفع الآن</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="padding:12px;">لا توجد طلبات صيانة مرتبطة بعقاراتك.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ms-tab-content" id="wallet">
                <div class="ms-card"><h3>محفظتي</h3>
                    <div style="margin-top:8px;font-size:16px;font-weight:700">الرصيد: <?php echo 'ج.م ' . number_format_i18n($wallet,2); ?></div>
                    <div style="margin-top:16px;">
                        <button id="ms-recharge-wallet-btn" style="padding:12px 24px;background:#2563eb;color:#fff;border:0;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600;">شحن المحفظة</button>
                    </div>
                    <?php if (!empty($owner_wallet_transactions)) : ?>
                        <div class="ms-table-wrap" style="margin-top:12px;">
                            <table class="ms-table" style="width:100%;border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th>الوصف</th>
                                        <th>المبلغ</th>
                                        <th>النوع</th>
                                        <th>التاريخ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($owner_wallet_transactions as $tx) : ?>
                                        <tr>
                                            <td><?php echo esc_html($tx->description ?? $tx->note ?? 'معاملة'); ?></td>
                                            <td>ج.م <?php echo isset($tx->amount) ? number_format_i18n(floatval($tx->amount), 2) : '0.00'; ?></td>
                                            <td><?php echo esc_html($tx->type ?? '—'); ?></td>
                                            <td><?php echo esc_html($tx->created_at ?? $tx->date ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else : ?>
                        <p style="margin-top:12px;">لا توجد معاملات محفظة حديثة.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ms-tab-content" id="discussions">
                <div class="ms-card">
                    <h3>المناقشات</h3>
                    <?php if (empty($building_ids)): ?>
                        <p style="color:#666;margin-top:8px">لم يتم العثور على معرف مبنى صالح مرتبط بعقاراتك. لا يمكن تحميل مواضيع المناقشات.</p>
                    <?php else: ?>
                        <div id="owner-discussions" data-building-id="<?php echo intval($building_ids[0]); ?>">
                            <div class="ms-discussions-layout" style="display:flex;gap:12px;align-items:flex-start;">
                                <div class="ms-discussions-list" style="width:36%;min-width:220px;border-right:1px solid #eee;padding-right:12px;">
                                    <h4 style="margin-top:0">المواضيع</h4>
                                    <ul class="ms-discussions-list-ul" style="list-style:none;padding:0;margin:0;max-height:420px;overflow:auto;"></ul>
                                </div>
                                <div class="ms-discussion-detail" style="flex:1;min-width:320px;">
                                    <div class="ms-discussion-empty" style="color:#666">اختر موضوعاً لعرض التفاصيل</div>
                                    <div class="ms-discussion-messages" style="margin-top:12px;max-height:360px;overflow:auto;border:1px solid #f3f4f6;padding:12px;background:#fff;"></div>

                                    <form class="ms-discussion-reply-form" style="margin-top:12px;display:none;">
                                        <textarea name="reply" rows="4" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;"></textarea>
                                        <div style="margin-top:8px;text-align:left;">
                                            <button type="submit" class="ms-discussion-reply-submit" style="padding:8px 12px;background:#2563eb;color:#fff;border:none;border-radius:4px;">إرسال الرد</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ms-tab-content" id="deposits">
                <div class="ms-card">
                    <h3>تأمينات الأمانة</h3>
                    <?php
                    // Get security deposits for owner's properties
                    $owner_deposits = array();
                    if (!empty($building_ids)) {
                        $ids_csv = implode(',', array_map('intval', $building_ids));
                        $deposits_table = $wpdb->prefix . 'ms_security_deposits';
                        $owner_deposits = $wpdb->get_results("SELECT * FROM {$deposits_table} WHERE building_id IN ({$ids_csv}) ORDER BY created_at DESC");
                    }
                    
                    if (empty($owner_deposits)) {
                        echo '<p style="color:#6b7280;padding:20px;">لا توجد تأمينات أمانة لعقاراتك.</p>';
                    } else {
                        ?>
                        <table style="width:100%;border-collapse:collapse;margin-top:20px;">
                            <thead>
                                <tr style="background:#f3f4f6;">
                                    <th style="padding:12px;text-align:right;border-bottom:2px solid #e5e7eb;">الوحدة</th>
                                    <th style="padding:12px;text-align:right;border-bottom:2px solid #e5e7eb;">المستأجر</th>
                                    <th style="padding:12px;text-align:right;border-bottom:2px solid #e5e7eb;">المبلغ</th>
                                    <th style="padding:12px;text-align:right;border-bottom:2px solid #e5e7eb;">الحالة</th>
                                    <th style="padding:12px;text-align:right;border-bottom:2px solid #e5e7eb;">تاريخ الدفع</th>
                                    <th style="padding:12px;text-align:right;border-bottom:2px solid #e5e7eb;">إجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($owner_deposits as $deposit) : ?>
                                    <?php
                                    $tenant = get_userdata(intval($deposit->tenant_id));
                                    $tenant_name = $tenant ? $tenant->display_name : 'غير معروف';
                                    
                                    $status_labels = array(
                                        'pending' => 'معلقة',
                                        'frozen' => 'مجمدة',
                                        'released' => 'مُطلقة',
                                    );
                                    $status_colors = array(
                                        'pending' => '#f59e0b',
                                        'frozen' => '#3b82f6',
                                        'released' => '#10b981',
                                    );
                                    $status_label = isset($status_labels[$deposit->status]) ? $status_labels[$deposit->status] : $deposit->status;
                                    $status_color = isset($status_colors[$deposit->status]) ? $status_colors[$deposit->status] : '#6b7280';
                                    ?>
                                    <tr style="border-bottom:1px solid #e5e7eb;">
                                        <td style="padding:12px;">#<?php echo intval($deposit->unit_id); ?></td>
                                        <td style="padding:12px;"><?php echo esc_html($tenant_name); ?></td>
                                        <td style="padding:12px;">ج.م <?php echo number_format_i18n($deposit->amount, 2); ?></td>
                                        <td style="padding:12px;">
                                            <span style="padding:4px 10px;border-radius:999px;font-size:12px;font-weight:600;background:#f3f4f6;color:<?php echo $status_color; ?>;">
                                                <?php echo $status_label; ?>
                                            </span>
                                        </td>
                                        <td style="padding:12px;"><?php echo esc_html($deposit->created_at); ?></td>
                                        <td style="padding:12px;">
                                            <?php if ($deposit->status === 'frozen') : ?>
                                                <button class="ms-release-deposit-btn" data-deposit-id="<?php echo intval($deposit->id); ?>" data-amount="<?php echo floatval($deposit->amount); ?>" style="padding:6px 12px;background:#10b981;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:12px;">إطلاق التأمين</button>
                                            <?php elseif ($deposit->status === 'released') : ?>
                                                <span style="color:#10b981;font-size:12px;">✓ تم الإطلاق</span>
                                            <?php else : ?>
                                                <span style="color:#6b7280;font-size:12px;">في الانتظار</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php
                    }
                    ?>
                </div>
            </div>

            <div class="ms-tab-content" id="profile">
                <div class="ms-card">
                    <h3>البروفايل الشخصي</h3>
                    <?php echo do_shortcode('[ms_notification_preferences]'); ?>
                    <?php echo do_shortcode('[ms_unified_profile]'); ?>
                    <form id="ms-profile-form" method="post" enctype="multipart/form-data" style="display:none!important;margin-top:20px;">
                        <?php wp_nonce_field('ms_update_profile', 'ms_profile_nonce'); ?>
                        <input type="hidden" name="action" value="ms_update_profile">
                        
                        <div style="display:flex;align-items:center;gap:20px;margin-bottom:24px;">
                            <div style="width:100px;height:100px;border-radius:50%;overflow:hidden;background:#f3f4f6;display:flex;align-items:center;justify-content:center;">
                                <?php 
                                $avatar_url = get_avatar_url($user->ID, array('size' => 100));
                                if ($avatar_url) : ?>
                                    <img src="<?php echo esc_url($avatar_url); ?>" alt="Avatar" style="width:100%;height:100%;object-fit:cover;">
                                <?php else : ?>
                                    <span style="font-size:40px;">👤</span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div style="font-weight:700;font-size:18px;"><?php echo esc_html($user->display_name); ?></div>
                                <div style="color:#6b7280;"><?php echo esc_html($user->user_email); ?></div>
                                <button type="button" id="ms-change-avatar-btn" style="margin-top:8px;padding:6px 12px;background:#2563eb;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px;">تغيير الصورة</button>
                                <input type="file" name="avatar" id="ms-avatar-input" accept="image/*" style="display:none;">
                            </div>
                        </div>

                        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:20px;">
                            <div>
                                <label style="display:block;margin-bottom:8px;font-weight:600;">الاسم الأول</label>
                                <input type="text" name="first_name" value="<?php echo esc_attr($user->first_name); ?>" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                            </div>
                            <div>
                                <label style="display:block;margin-bottom:8px;font-weight:600;">اسم العائلة</label>
                                <input type="text" name="last_name" value="<?php echo esc_attr($user->last_name); ?>" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                            </div>
                            <div>
                                <label style="display:block;margin-bottom:8px;font-weight:600;">الاسم المعروض</label>
                                <input type="text" name="display_name" value="<?php echo esc_attr($user->display_name); ?>" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                            </div>
                            <div>
                                <label style="display:block;margin-bottom:8px;font-weight:600;">البريد الإلكتروني</label>
                                <input type="email" name="email" value="<?php echo esc_attr($user->user_email); ?>" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                            </div>
                            <div>
                                <label style="display:block;margin-bottom:8px;font-weight:600;">رقم الهاتف</label>
                                <input type="tel" name="phone" value="<?php echo esc_attr(get_user_meta($user->ID, 'billing_phone', true)); ?>" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                            </div>
                            <div>
                                <label style="display:block;margin-bottom:8px;font-weight:600;">العنوان</label>
                                <input type="text" name="address" value="<?php echo esc_attr(get_user_meta($user->ID, 'billing_address_1', true)); ?>" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                            </div>
                        </div>

                        <div style="margin-top:20px;">
                            <label style="display:block;margin-bottom:8px;font-weight:600;">نبذة عني</label>
                            <textarea name="description" rows="4" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;"><?php echo esc_textarea($user->description); ?></textarea>
                        </div>

                        <div style="margin-top:24px;">
                            <button type="submit" style="padding:12px 24px;background:#2563eb;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:600;">حفظ التغييرات</button>
                        </div>
                    </form>
                </div>
            </div>

        </main>

    </div>

    <script>
    (function() {
        // Invoice subtab switching
        var subtabs = document.querySelectorAll('.ms-invoice-subtab');
        var subpanels = document.querySelectorAll('.ms-invoice-subpanel');
        
        subtabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                var targetSubtab = this.getAttribute('data-subtab');
                
                // Update active tab styling
                subtabs.forEach(function(t) {
                    t.style.background = '#fff';
                    t.style.color = '#0f172a';
                });
                this.style.background = '#2563eb';
                this.style.color = '#fff';
                
                // Show/hide panels
                subpanels.forEach(function(panel) {
                    panel.style.display = 'none';
                    panel.classList.remove('active');
                });
                var targetPanel = document.getElementById(targetSubtab);
                if (targetPanel) {
                    targetPanel.style.display = 'block';
                    targetPanel.classList.add('active');
                }
            });
        });

        // Security deposit release
        var releaseBtns = document.querySelectorAll('.ms-release-deposit-btn');
        releaseBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var depositId = this.getAttribute('data-deposit-id');
                var amount = parseFloat(this.getAttribute('data-amount'));
                
                var deductionAmount = prompt('مبلغ الخصم من التأمين (ج.م) - اترك 0 للإطلاق الكامل:', '0');
                if (deductionAmount === null) return;
                
                deductionAmount = parseFloat(deductionAmount);
                if (isNaN(deductionAmount) || deductionAmount < 0 || deductionAmount > amount) {
                    alert('مبلغ الخصم غير صالح');
                    return;
                }
                
                var deductionReason = '';
                if (deductionAmount > 0) {
                    deductionReason = prompt('سبب الخصم:', '');
                    if (deductionReason === null) return;
                }
                
                if (confirm('هل أنت متأكد من إطلاق التأمين؟\nالمبلغ المُرجع: ج.م ' + (amount - deductionAmount).toFixed(2) + '\nالمبلغ المخصوم: ج.م ' + deductionAmount.toFixed(2))) {
                    var formData = new FormData();
                    formData.append('action', 'ms_release_deposit');
                    formData.append('security', '<?php echo wp_create_nonce('ms_release_deposit_nonce'); ?>');
                    formData.append('deposit_id', depositId);
                    formData.append('deduction_amount', deductionAmount);
                    formData.append('deduction_reason', deductionReason);
                    
                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    })
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        if (data.success) {
                            alert(data.data.message || 'تم إطلاق التأمين بنجاح');
                            location.reload();
                        } else {
                            alert(data.data && data.data.message ? data.data.message : 'حدث خطأ في معالجة الطلب');
                        }
                    })
                    .catch(function(error) {
                        console.error('Error:', error);
                        alert('حدث خطأ في الاتصال');
                    });
                }
            });
        });

        // Wallet recharge
        var rechargeBtn = document.getElementById('ms-recharge-wallet-btn');
        if (rechargeBtn) {
            rechargeBtn.addEventListener('click', function() {
                var amount = prompt('يرجى إدخال مبلغ الشحن (ج.م):');
                if (amount && !isNaN(parseFloat(amount)) && parseFloat(amount) > 0) {
                    if (typeof MostaagerAjax !== 'undefined' && MostaagerAjax.ajax_url) {
                        var formData = new FormData();
                        formData.append('action', 'ms_create_wallet_recharge');
                        formData.append('amount', amount);
                        formData.append('security', MostaagerAjax.nonce);

                        fetch(MostaagerAjax.ajax_url, {
                            method: 'POST',
                            credentials: 'same-origin',
                            body: formData
                        })
                        .then(function(response) { return response.json(); })
                        .then(function(data) {
                            if (data.success && data.data && data.data.payment_url) {
                                window.location.href = data.data.payment_url;
                            } else {
                                var errorMsg = 'حدث خطأ في معالجة الطلب';
                                if (data.data && typeof data.data === 'string') {
                                    errorMsg = data.data;
                                } else if (data.data && data.data.message) {
                                    errorMsg = data.data.message;
                                } else if (data.data) {
                                    errorMsg = JSON.stringify(data.data);
                                }
                                alert(errorMsg);
                            }
                        })
                        .catch(function(error) {
                            console.error('Error:', error);
                            alert('حدث خطأ في الاتصال: ' + error.message);
                        });
                    } else {
                        alert('نظام الدفع غير متاح حالياً');
                    }
                }
            });
        }

        // Maintenance payment
        var payMaintenanceBtns = document.querySelectorAll('.ms-pay-maintenance-btn');
        payMaintenanceBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var requestId = this.getAttribute('data-request-id');
                var amount = this.getAttribute('data-amount');
                
                if (confirm('هل تريد دفع ج.م ' + amount + ' لطلب الصيانة #' + requestId + '؟')) {
                    if (typeof MostaagerAjax !== 'undefined' && MostaagerAjax.ajax_url) {
                        var formData = new FormData();
                        formData.append('action', 'ms_pay_maintenance_invoice');
                        formData.append('request_id', requestId);
                        formData.append('amount', amount);
                        formData.append('security', MostaagerAjax.nonce);

                        fetch(MostaagerAjax.ajax_url, {
                            method: 'POST',
                            credentials: 'same-origin',
                            body: formData
                        })
                        .then(function(response) { return response.json(); })
                        .then(function(data) {
                            if (data.success) {
                                alert(data.data.message || 'تم الدفع بنجاح');
                                location.reload();
                            } else {
                                var errorMsg = 'حدث خطأ في معالجة الطلب';
                                if (data.data && typeof data.data === 'string') {
                                    errorMsg = data.data;
                                } else if (data.data && data.data.message) {
                                    errorMsg = data.data.message;
                                } else if (data.data) {
                                    errorMsg = JSON.stringify(data.data);
                                }
                                alert(errorMsg);
                            }
                        })
                        .catch(function(error) {
                            console.error('Error:', error);
                            alert('حدث خطأ في الاتصال: ' + error.message);
                        });
                    } else {
                        alert('نظام الدفع غير متاح حالياً');
                    }
                }
            });
        });

        // Profile form handling
        var profileForm = document.getElementById('ms-profile-form');
        if (profileForm) {
            profileForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                var formData = new FormData(this);
                formData.append('action', 'ms_update_profile');
                
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    if (data.success) {
                        alert('تم تحديث البروفايل بنجاح');
                        location.reload();
                    } else {
                        alert('خطأ: ' + (data.data.message || 'حدث خطأ أثناء التحديث'));
                    }
                })
                .catch(function(error) {
                    console.error('Error:', error);
                    alert('حدث خطأ أثناء الاتصال بالخادم');
                });
            });
        }

        // Avatar upload button
        var avatarBtn = document.getElementById('ms-change-avatar-btn');
        var avatarInput = document.getElementById('ms-avatar-input');
        if (avatarBtn && avatarInput) {
            avatarBtn.addEventListener('click', function() {
                avatarInput.click();
            });
            avatarInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    // Auto submit form when avatar is selected
                    if (profileForm) {
                        profileForm.dispatchEvent(new Event('submit'));
                    }
                }
            });
        }
    })();
    </script>

    <?php

    return ob_get_clean();

});
