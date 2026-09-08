<?php
if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('manager_dashboard_v4', function () {
    error_log('Building dashboard shortcode called');
    
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
    
    // Check permissions: only site admin or building manager can access
    if (!current_user_can('manage_options') && !function_exists('ms_user_has_role')) {
        return '<p>غير مصرح لك بالوصول إلى هذه الصفحة.</p>';
    }
    
    if (!current_user_can('manage_options') && !ms_user_has_role($user->ID, 'building_manager')) {
        return '<p>غير مصرح لك بالوصول إلى هذه الصفحة. هذه الصفحة مخصصة لمديري الأبنية فقط.</p>';
    }

    ob_start();

    $buildings = function_exists('ms_get_buildings_by_manager') ? ms_get_buildings_by_manager($user->ID) : [];
    $building_ids = array_map(function ($building) {
        return intval($building->id ?? $building->ID ?? 0);
    }, $buildings);

    $requested_building_id = isset($_GET['building_id']) ? absint($_GET['building_id']) : 0;
    $selected_building_id = $requested_building_id;
    $is_site_admin = current_user_can('manage_options');
    if ($selected_building_id && !in_array($selected_building_id, $building_ids, true)) {
        foreach ($buildings as $building) {
            $internal_id = absint($building->id ?? 0);
            $wp_post_id = absint($building->wp_post_id ?? 0);
            if ($wp_post_id === $selected_building_id && $internal_id) {
                $selected_building_id = $internal_id;
                break;
            }
        }
    }
    $selected_building_is_valid = !$selected_building_id || in_array($selected_building_id, $building_ids, true);
    if (!$selected_building_is_valid) {
        $selected_building_id = 0;
    }
    if (!$requested_building_id && !$selected_building_id && !empty($building_ids)) {
        $selected_building_id = $building_ids[0];
    }

    $get_display_phone = function($payer_id) {
        if (!$payer_id) {
            return '';
        }
        $billing_phone = trim(get_user_meta($payer_id, 'billing_phone', true));
        return $billing_phone ? $billing_phone : '';
    };

    $building_expenses = array();
    if ($selected_building_id) {
        if (function_exists('ms_get_legacy_expense_posts_by_building')) {
            $building_expenses = ms_get_legacy_expense_posts_by_building($selected_building_id);
        } else {
            $building_expenses = get_posts(array(
                'post_type' => 'expenses',
                'posts_per_page' => 100,
                'post_status' => 'publish',
                'meta_query' => array(
                    array(
                        'key' => 'building_id',
                        'value' => $selected_building_id,
                        'compare' => '=',
                    ),
                ),
                'orderby' => 'date',
                'order' => 'DESC',
            ));
        }
    }

    // Get active tab - default to overview (JavaScript will handle hash-based switching)
    $active_tab = 'overview';

    // Load all invoices for the selected building; actual invoice types are building-maintenance, building-facilities, and building-utilities.
    $invoices = function_exists('ms_get_manager_invoices') ? ms_get_manager_invoices($user->ID, 100, '', $selected_building_id) : [];
    
    // Group invoices by category
    $invoice_groups = [
        'property' => [], // property-rent, property-monthly, property-sale
        'building' => [], // building-maintenance, building-facilities, building-utilities
    ];
    foreach ($invoices as $invoice) {
        $category = isset($invoice->invoice_category) ? $invoice->invoice_category : 'building-maintenance';
        if (in_array($category, ['property-rent', 'property-monthly', 'property-sale'])) {
            $invoice_groups['property'][] = $invoice;
        } else {
            $invoice_groups['building'][] = $invoice;
        }
    }
    
    $notifications = function_exists('ms_get_notifications_by_user') ? ms_get_notifications_by_user($user->ID, 20) : [];
    $unread_notifications = function_exists('ms_get_unread_notifications_count') ? ms_get_unread_notifications_count($user->ID) : 0;
    $wallet = $selected_building_id && function_exists('ms_get_building_wallet') ? ms_get_building_wallet($selected_building_id) : null;
    $wallet_transactions = $selected_building_id && function_exists('ms_get_building_wallet_transactions') ? ms_get_building_wallet_transactions($selected_building_id, 20) : [];
    $units_count = function_exists('ms_get_units_count_by_manager') ? ms_get_units_count_by_manager($user->ID) : 0;
    $selected_building_units_count = 0;
    if ($selected_building_id) {
        global $wpdb;
        $units_table = $wpdb->prefix . 'ms_units';
        $selected_building_units_count = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$units_table} WHERE building_id = %d",
            $selected_building_id
        )));
        // Houzez properties are the fallback source when the custom units table is empty.
        if ($selected_building_units_count === 0) {
            $selected_building_units_count = count(get_posts(array(
                'post_type' => 'property',
                'post_status' => array('publish', 'pending', 'draft'),
                'posts_per_page' => 100,
                'fields' => 'ids',
                'meta_query' => array(
                    'relation' => 'OR',
                    array('key' => '_ms_building_id', 'value' => $selected_building_id, 'compare' => '='),
                    array('key' => 'ms_building_id', 'value' => $selected_building_id, 'compare' => '='),
                    array('key' => 'building_id', 'value' => $selected_building_id, 'compare' => '='),
                ),
            )));
        }
    }
    $active_maintenance = 0;
    if ($selected_building_id) {
        global $wpdb;
        $work_orders_table = $wpdb->prefix . 'ms_work_orders';
        $active_maintenance = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$work_orders_table} WHERE building_id = %d AND status NOT IN ('completed','closed','cancelled','canceled')",
            $selected_building_id
        )));
    } elseif (function_exists('ms_get_active_maintenance_by_manager')) {
        $active_maintenance = ms_get_active_maintenance_by_manager($user->ID);
    }
        $paid_invoices_count = function_exists('ms_get_paid_invoices_count_for_manager') ? ms_get_paid_invoices_count_for_manager($user->ID) : 0;
    if ($selected_building_id) {
        $paid_invoices_count = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ms_invoices WHERE building_id = %d AND status = 'paid'",
            $selected_building_id
        )));
    }
    $collection_stats = function_exists('ms_get_collection_stats_by_manager') ? ms_get_collection_stats_by_manager($user->ID) : array('percent' => 0, 'total_collected' => 0);

    $display_units_count = $selected_building_id ? $selected_building_units_count : $units_count;

    // If a building is selected, compute building-specific invoice collection stats
    $building_collection = array('total_invoiced' => 0, 'total_collected' => 0, 'total_pending' => 0, 'percent' => 0);
    if ($selected_building_id && !empty($selected_building_id)) {
        global $wpdb;
        $inv_table = $wpdb->prefix . 'ms_invoices';
        $row = $wpdb->get_row($wpdb->prepare("SELECT COALESCE(SUM(amount),0) AS total_invoiced, COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END),0) AS total_collected, COALESCE(SUM(CASE WHEN status != 'paid' AND status != 'canceled' THEN amount ELSE 0 END),0) AS total_pending FROM {$inv_table} WHERE building_id = %d", $selected_building_id));
        if ($row) {
            $building_collection['total_invoiced'] = floatval($row->total_invoiced);
            $building_collection['total_collected'] = floatval($row->total_collected);
            $building_collection['total_pending'] = floatval($row->total_pending);
            $building_collection['percent'] = $building_collection['total_invoiced'] > 0 ? round(($building_collection['total_collected'] / $building_collection['total_invoiced']) * 100, 2) : 0;
        }
    }
    if ($selected_building_id) {
        $collection_stats = array(
            'percent' => $building_collection['percent'],
            'total_collected' => $building_collection['total_collected'],
        );
    }

    ?>

    <style>
        .ms-invoice-action {
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid;
            background: white;
        }
        
        .ms-invoice-action-view {
            background: #eff6ff;
            color: #2563eb;
            border-color: #bfdbfe;
        }
        
        .ms-invoice-action-view:hover {
            background: #dbeafe;
        }
        
        .ms-invoice-action-pay {
            background: #dcfce7;
            color: #166534;
            border-color: #86efac;
        }
        
        .ms-invoice-action-pay:hover {
            background: #bbf7d0;
        }
        
        .ms-invoice-action-download {
            background: #f3f4f6;
            color: #475569;
            border-color: #e5e7eb;
        }
        
        .ms-invoice-action-download:hover {
            background: #e5e7eb;
        }
    </style>

    <div class="ms-dashboard">

        <?php
        $ms_dashboard_menu_items = array(
            array('label' => 'نظرة عامة', 'data_tab' => 'overview', 'icon' => '🏢'),
            array('label' => 'المباني', 'data_tab' => 'buildings', 'icon' => '🏘️'),
            array('label' => 'العقارات', 'data_tab' => 'units', 'icon' => '🏠'),
            array('label' => 'الصيانة', 'data_tab' => 'maintenance', 'icon' => '🛠️'),
            array('label' => 'محفظة', 'data_tab' => 'wallet', 'icon' => '💰'),
            array('label' => 'المناقشات', 'data_tab' => 'discussions', 'icon' => '💬'),
            array('label' => 'الفواتير', 'data_tab' => 'invoices', 'icon' => '🧾'),
            array('label' => 'البروفايل', 'data_tab' => 'profile', 'icon' => '👤'),
            array('href' => wp_logout_url(), 'label' => 'تسجيل الخروج', 'external' => true, 'icon' => '🚪'),
        );
        ms_load_dashboard_sidebar($ms_dashboard_menu_items);
        ?>

        <main class="ms-content">
            <div class="ms-card" style="margin-bottom:20px;">
                <h3>اختر المبنى</h3>
                <?php if (!$selected_building_is_valid && $requested_building_id) : ?>
                    <p style="color:#b91c1c;">المبنى المطلوب غير مرتبط بحسابك أو أن معرفه غير صحيح. اختر مبنى من القائمة.</p>
                <?php elseif (empty($buildings)) : ?>
                    <p>لا توجد أبنية مصرح لك بإدارتها.</p>
                <?php else : ?>
                    <?php if ($is_site_admin) : ?>
                        <p style="margin-bottom:12px;color:#475569;font-size:14px;">أنت مسؤول الموقع ويمكنك عرض وإدارة جميع الأبنية.</p>
                    <?php endif; ?>
                    <div class="ms-building-selector-row" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
                        <select id="ms-building-selector" class="ms-input" style="flex:1;min-width:220px;">
                        <?php foreach ($buildings as $building) : ?>
                            <?php $building_id = intval($building->id ?? $building->ID ?? 0); ?>
                            <option value="<?php echo esc_attr($building_id); ?>" <?php selected($building_id, $selected_building_id); ?>><?php echo esc_html($building->title ?? $building->post_title ?? 'مبنى #' . $building_id); ?></option>
                        <?php endforeach; ?>
                        </select>
                        <div class="ms-building-counts" style="display:flex;gap:8px;flex-wrap:wrap;">
                            <span class="ms-count-badge">عدد الأبنية: <strong id="ms-buildings-count-inline"><?php echo intval(count($buildings)); ?></strong></span>
                            <span class="ms-count-badge">شقق البناء: <strong id="ms-selected-building-units-count"><?php echo intval($selected_building_units_count); ?></strong></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="ms-tab-content active" id="overview">
                <div class="ms-grid">
                    <div class="ms-card"><h3>عدد الأبنية</h3><div id="ms-buildings-count" class="ms-number"><?php echo intval(count($buildings)); ?></div></div>
                    <div class="ms-card"><h3>الشقق المسجلة</h3><div id="ms-units-count" class="ms-number"><?php echo intval($display_units_count); ?></div>
                        <div style="margin-top:8px;font-size:13px;color:#666">مدفوعة: <span id="ms-units-paid"><?php echo esc_html('--'); ?></span> — غير مدفوعة: <span id="ms-units-unpaid"><?php echo esc_html('--'); ?></span></div>
                    </div>
                    <div class="ms-card"><h3>الفواتير المدفوعة</h3><div id="ms-paid-invoices" class="ms-number"><?php echo intval($paid_invoices_count); ?></div></div>
                    <div class="ms-card"><h3>الصيانة النشطة</h3><div id="ms-active-maintenance" class="ms-number"><?php echo intval($active_maintenance); ?></div></div>
                </div>

                <div class="ms-grid" style="margin-top:20px">
                    <div class="ms-card"><h3>نسبة التحصيل</h3><div id="ms-collection-percent" class="ms-number"><?php echo esc_html($collection_stats['percent']); ?>%</div></div>
                    <div class="ms-card"><h3>المجموع المحصل</h3><div id="ms-collection-total" class="ms-number">ج.م <?php echo number_format_i18n(floatval($collection_stats['total_collected']), 2); ?></div></div>
                </div>

                <?php if (!empty($notifications)) : ?>
                    <div class="ms-card" style="margin-top:20px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                            <h3 style="margin:0;">الإشعارات الأخيرة</h3>
                            <?php if ($unread_notifications > 0) : ?>
                                <span style="background:#ef4444;color:#fff;padding:3px 10px;border-radius:999px;font-size:0.78rem;font-weight:700;"><?php echo intval($unread_notifications); ?> غير مقروء</span>
                            <?php endif; ?>
                            <?php if (!empty($notifications)) : ?>
                                <button type="button" id="ms-mark-all-notifications-read" style="background:none;border:1px solid #d1d5db;padding:6px 14px;border-radius:8px;cursor:pointer;font-size:0.82rem;color:#4b5563;">تعليم الكل كمقروء</button>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($notifications)) : ?>
                            <div style="display:flex;flex-direction:column;gap:10px;">
                                <?php foreach ($notifications as $note) : ?>
                                    <div style="padding:14px 16px;border-radius:12px;background:<?php echo !empty($note->is_read) ? '#f9fafb' : '#eff6ff'; ?>;border:1px solid <?php echo !empty($note->is_read) ? '#e5e7eb' : '#bfdbfe'; ?>;">
                                        <div style="font-size:14px;color:#111;line-height:1.6;"><?php echo esc_html($note->message ?? ''); ?></div>
                                        <div style="margin-top:6px;font-size:12px;color:#6b7280;"><?php echo esc_html(date_i18n('Y-m-d H:i', strtotime($note->created_at ?? $note->created_on ?? ''))); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <p style="padding:12px;margin:0;">لا توجد إشعارات جديدة.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="ms-tab-content" id="maintenance">
                <?php echo function_exists('msfp_render_maintenance_pro') ? msfp_render_maintenance_pro($selected_building_id) : '<div class="ms-card"><h3>الصيانة</h3><p>وحدة الصيانة المتقدمة غير متوفرة.</p></div>'; ?>
            </div>

            <div class="ms-tab-content" id="buildings">
                <div class="ms-card">
                    <h3>المباني التي أديرها</h3>
                    <?php if (empty($buildings)) : ?>
                        <p>لا توجد أبنية مصرح لك بإدارتها.</p>
                    <?php else : ?>
                        <table style="width:100%;border-collapse:collapse;margin-top:15px;">
                            <thead>
                                <tr style="background:#f3f4f6;text-align:right;">
                                    <th style="padding:12px;">#</th>
                                    <th style="padding:12px;">اسم المبنى</th>
                                    <th style="padding:12px;">العنوان</th>
                                    <th style="padding:12px;">عدد الوحدات</th>
                                    <th style="padding:12px;">الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($buildings as $building) : ?>
                                    <?php 
                                    $b_id = intval($building->id ?? $building->ID ?? 0);
                                    $b_title = esc_html($building->title ?? $building->post_title ?? 'مبنى #' . $b_id);
                                    // ms_buildings.id is an internal id; public URLs use the linked WP building post.
                                    $b_wp_post_id = !empty($building->wp_post_id) ? absint($building->wp_post_id) : 0;
                                    if (!$b_wp_post_id && function_exists('ms_get_linked_wp_post_id_for_building')) {
                                        $b_wp_post_id = absint(ms_get_linked_wp_post_id_for_building($b_id));
                                    }
                                    $b_public_url = $b_wp_post_id ? get_permalink($b_wp_post_id) : '';
                                    $b_address = esc_html($building->address ?? 'غير محدد');
                                    global $wpdb;
                                    $units_count = 0;
                                    if (function_exists('ms_get_properties_by_building')) {
                                        $units_count = count(ms_get_properties_by_building($b_id));
                                    } else {
                                        $units_count = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ms_units WHERE building_id = %d", $b_id)));
                                    }
                                    $b_status = esc_html($building->status ?? 'active');
                                    ?>
                                    <tr role="button" class="ms-building-row" data-building-id="<?php echo esc_attr($b_id); ?>" style="border-top:1px solid #e5e7eb;cursor:pointer;">
                                        <td style="padding:12px;"><?php echo $b_id; ?></td>
                                        <td style="padding:12px;">
                                            <strong>
                                                <?php if ($b_public_url) : ?>
                                                    <a class="ms-building-public-link" href="<?php echo esc_url($b_public_url); ?>" style="color:#2563eb;text-decoration:none;">
                                                        <?php echo $b_title; ?>
                                                    </a>
                                                <?php else : ?>
                                                    <?php echo $b_title; ?>
                                                <?php endif; ?>
                                            </strong>
                                            <br>
                                            <a href="<?php echo esc_url(home_url('/building-dashboard/?building_id=' . $b_id . '#maintenance')); ?>" style="font-size:12px;color:#64748b;text-decoration:none;">
                                                🛠️ الصيانات
                                            </a>
                                            <span style="color:#e5e7eb;margin:0 4px;">|</span>
                                            <a href="<?php echo esc_url(home_url('/building-dashboard/?building_id=' . $b_id . '#facilities')); ?>" style="font-size:12px;color:#64748b;text-decoration:none;">
                                                🏗️ المرافق
                                            </a>
                                        </td>
                                        <td style="padding:12px;"><?php echo $b_address; ?></td>
                                        <td style="padding:12px;"><?php echo intval($units_count); ?></td>
                                        <td style="padding:12px;">
                                            <span style="padding:4px 10px;border-radius:999px;font-size:12px;font-weight:600;background:<?php echo $b_status === 'active' ? '#dcfce7' : '#fee2e2'; ?>;color:<?php echo $b_status === 'active' ? '#166534' : '#991b1b'; ?>;">
                                                <?php echo $b_status === 'active' ? 'نشط' : 'غير نشط'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ms-tab-content" id="units">
                <div class="ms-card">
                    <h3>العقارات في المبنى</h3>
                    <?php if (!$selected_building_id) : ?>
                        <p>يرجى اختيار مبنى أولاً لعرض العقارات المرتبطة.</p>
                    <?php else : ?>
                        <?php
                        $apartments = function_exists('ms_get_properties_by_building') ? ms_get_properties_by_building($selected_building_id, 100) : array();
                        if (empty($apartments)) {
                            $meta_queries = array('relation' => 'OR',
                                array(
                                    'key' => '_ms_building_id',
                                    'value' => $selected_building_id,
                                    'compare' => '=',
                                ),
                                array(
                                    'key' => 'ms_building_id',
                                    'value' => $selected_building_id,
                                    'compare' => '=',
                                ),
                                array(
                                    'key' => 'building_id',
                                    'value' => $selected_building_id,
                                    'compare' => '=',
                                ),
                            );

                            $apartments = get_posts(array(
                                'post_type' => 'property',
                                'posts_per_page' => -1,
                                'post_status' => 'publish',
                                'meta_query' => $meta_queries,
                                'orderby' => 'title',
                                'order' => 'ASC',
                            ));
                        }
                        ?>
                        <?php if (empty($apartments)) : ?>
                            <p>لا توجد شقق مرتبطة بهذا المبنى.</p>
                        <?php else : ?>
                            <table style="width:100%;border-collapse:collapse;margin-top:15px;">
                                <thead>
                                    <tr style="background:#f3f4f6;text-align:right;">
                                        <th style="padding:12px;">#</th>
                                        <th style="padding:12px;">عنوان الشقة</th>
                                        <th style="padding:12px;">السعر</th>
                                        <th style="padding:12px;">النوع</th>
                                        <th style="padding:12px;">الحالة</th>
                                        <th style="padding:12px;">إجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($apartments as $apartment) : ?>
                                        <?php 
                                        $apt_id = $apartment->ID;
                                        $apt_title = get_the_title($apt_id);
                                        $apt_price = get_post_meta($apt_id, 'fave_property_price', true);
                                        $apt_type = get_post_meta($apt_id, 'fave_property_type', true);
                                        $apt_type = function_exists('ms_translate_property_status_label') ? ms_translate_property_status_label($apt_type) : $apt_type;
                                        $apt_status = get_post_meta($apt_id, 'fave_property_status', true);
                                        $apt_status = function_exists('ms_translate_property_status_label') ? ms_translate_property_status_label($apt_status) : $apt_status;
                                        $apt_permalink = get_permalink($apt_id);
                                        ?>
                                        <tr style="border-top:1px solid #e5e7eb;">
                                            <td style="padding:12px;"><?php echo $apt_id; ?></td>
                                            <td style="padding:12px;">
                                                <strong><a href="<?php echo esc_url($apt_permalink); ?>" target="_blank"><?php echo esc_html($apt_title); ?></a></strong>
                                            </td>
                                            <td style="padding:12px;">
                                                <?php if ($apt_price) : ?>
                                                    ج.م <?php echo number_format_i18n(floatval($apt_price), 2); ?>
                                                <?php else : ?>
                                                    —
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding:12px;"><?php echo esc_html($apt_type ?? 'غير محدد'); ?></td>
                                            <td style="padding:12px;">
                                                <span style="padding:4px 10px;border-radius:999px;font-size:12px;font-weight:600;background:#f3f4f6;color:#475569;">
                                                    <?php echo esc_html($apt_status ?: 'غير محدد'); ?>
                                                </span>
                                            </td>
                                            <td style="padding:12px;">
                                                <a href="<?php echo esc_url($apt_permalink); ?>" target="_blank" style="padding:6px 12px;background:#2563eb;color:#fff;border:0;border-radius:6px;cursor:pointer;font-size:12px;text-decoration:none;">عرض</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ms-tab-content" id="wallet">
                <div class="ms-card"><h3>محفظة المبنى والمركز المالي</h3>
                    <?php if (!$selected_building_id) : ?>
                        <p>يرجى اختيار مبنى أولاً.</p>
                    <?php else : ?>
                        <div style="margin-top:8px;font-size:16px;font-weight:700">الرصيد المتاح: <?php echo 'ج.م ' . number_format_i18n(is_object($wallet) ? (isset($wallet->balance) ? floatval($wallet->balance) : 0) : floatval($wallet ?? 0), 2); ?></div>
                        <div style="margin-top:16px;">
                            <button id="ms-recharge-wallet-btn" style="padding:12px 24px;background:#2563eb;color:#fff;border:0;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600;">شحن المحفظة</button>
                        </div>
                        
                        <!-- إحصائيات التحصيل -->
                        <div style="margin-top:24px;">
                            <h4 style="margin-bottom:12px;">إحصائيات التحصيل</h4>
                            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">
                                <div style="padding:12px;border:1px solid #e5e7eb;border-radius:10px;background:#fff;">
                                    <div style="font-size:13px;color:#6b7280;">إجمالي الفواتير</div>
                                    <div style="font-size:20px;font-weight:700;">ج.م <?php echo number_format_i18n(floatval($building_collection['total_invoiced']), 2); ?></div>
                                </div>
                                <div style="padding:12px;border:1px solid #e5e7eb;border-radius:10px;background:#fff;">
                                    <div style="font-size:13px;color:#6b7280;">المحصّل</div>
                                    <div style="font-size:20px;font-weight:700;">ج.م <?php echo number_format_i18n(floatval($building_collection['total_collected']), 2); ?></div>
                                </div>
                                <div style="padding:12px;border:1px solid #e5e7eb;border-radius:10px;background:#fff;">
                                    <div style="font-size:13px;color:#6b7280;">المتبقي</div>
                                    <div style="font-size:20px;font-weight:700;">ج.م <?php echo number_format_i18n(floatval($building_collection['total_pending']), 2); ?></div>
                                </div>
                                <div style="padding:12px;border:1px solid #e5e7eb;border-radius:10px;background:#fff;">
                                    <div style="font-size:13px;color:#6b7280;">نسبة التحصيل</div>
                                    <div style="font-size:20px;font-weight:700;"><?php echo esc_html($building_collection['percent']); ?>%</div>
                                </div>
                            </div>
                        </div>

                        <!-- المصروفات -->
                        <div style="margin-top:24px;">
                            <h4 style="margin-bottom:12px;">المصروفات</h4>
                            <?php if (!empty($building_expenses)) : ?>
                                <div style="overflow-x:auto;border:1px solid #e5e7eb;border-radius:12px;">
                                    <table class="widefat fixed striped" style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="padding:12px;text-align:right;">#</th>
                                                <th style="padding:12px;text-align:right;">الوصف</th>
                                                <th style="padding:12px;text-align:right;">المبلغ</th>
                                                <th style="padding:12px;text-align:right;">التاريخ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($building_expenses as $index => $expense) : ?>
                                                <?php
                                                $expense_title = get_the_title($expense->ID);
                                                $expense_amount = get_post_meta($expense->ID, 'expense_amount', true);
                                                $expense_date = get_the_date('Y-m-d', $expense->ID);
                                                ?>
                                                <tr>
                                                    <td style="padding:12px;text-align:center;"><?php echo intval($index + 1); ?></td>
                                                    <td style="padding:12px;"><?php echo esc_html($expense_title); ?></td>
                                                    <td style="padding:12px;">ج.م <?php echo number_format_i18n(floatval($expense_amount), 2); ?></td>
                                                    <td style="padding:12px;"><?php echo esc_html($expense_date); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else : ?>
                                <p style="color:#64748b;">لا توجد مصروفات مسجلة لهذا المبنى.</p>
                            <?php endif; ?>
                        </div>

                        <!-- معاملات المحفظة -->
                        <div style="margin-top:24px;">
                            <h4 style="margin-bottom:12px;">معاملات المحفظة</h4>
                            <?php if (!empty($wallet_transactions)) : ?>
                                <div style="overflow-x:auto;border:1px solid #e5e7eb;border-radius:12px;">
                                    <table class="widefat fixed striped" style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="padding:12px;text-align:right;">الوصف</th>
                                                <th style="padding:12px;text-align:right;">المبلغ</th>
                                                <th style="padding:12px;text-align:right;">النوع</th>
                                                <th style="padding:12px;text-align:right;">التاريخ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($wallet_transactions as $t) : ?>
                                                <tr>
                                                    <td style="padding:12px;"><?php echo esc_html($t->description ?? $t->note ?? 'معاملة'); ?></td>
                                                    <td style="padding:12px;">ج.م <?php echo isset($t->amount) ? number_format_i18n(floatval($t->amount), 2) : '0.00'; ?></td>
                                                    <td style="padding:12px;"><?php echo esc_html($t->type ?? '—'); ?></td>
                                                    <td style="padding:12px;"><?php echo esc_html($t->created_at ?? $t->date ?? '-'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else : ?>
                                <p style="color:#64748b;">لا توجد معاملات محفظة حديثة.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (false) : ?><div class="ms-tab-content" id="facilities">
                <div class="ms-card">
                    <h3>🏗️ المرافق</h3>
                    <?php if (!$selected_building_id) : ?>
                        <p>يرجى اختيار مبنى أولاً.</p>
                    <?php else : ?>
                        <?php
                        global $wpdb;
                        $company = ms_get_company_clause('f');
                        $facilities_table = $wpdb->prefix . 'ms_facilities';
                        $types_table = $wpdb->prefix . 'ms_facility_types';
                        
                        $sql = "SELECT f.*, ft.name AS facility_type_name
                            FROM {$facilities_table} f
                            LEFT JOIN {$types_table} ft ON f.facility_type_id = ft.id
                            WHERE f.building_id = %d AND {$company['clause']}
                            ORDER BY f.status ASC, f.created_at DESC";
                        
                        $params = array($selected_building_id);
                        if ($company['value'] !== null && strpos($company['clause'], '%') !== false) {
                            $params[] = $company['value'];
                        }

                        $facilities = $wpdb->get_results(
                            $wpdb->prepare($sql, ...$params)
                        );
                        
                        // Count facilities by status
                        $working_count = 0;
                        $maintenance_count = 0;
                        foreach ($facilities as $f) {
                            if ($f->status === 'working') $working_count++;
                            else $maintenance_count++;
                        }
                        ?>
                        
                        <!-- حالة المرافق الإجمالية -->
                        <div style="margin-bottom:20px;padding:16px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;">
                            <h4 style="margin-top:0;margin-bottom:12px;">حالة المرافق الإجمالية</h4>
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;">
                                <div style="padding:12px;background:#fff;border-radius:8px;border:1px solid #e5e7eb;">
                                    <div style="font-size:12px;color:#64748b;margin-bottom:4px;">إجمالي المرافق</div>
                                    <div style="font-size:18px;font-weight:bold;color:#0f172a;"><?php echo intval(count($facilities)); ?></div>
                                </div>
                                <div style="padding:12px;background:#fff;border-radius:8px;border:1px solid #e5e7eb;">
                                    <div style="font-size:12px;color:#64748b;margin-bottom:4px;">تعمل</div>
                                    <div style="font-size:18px;font-weight:bold;color:#10b981;"><?php echo intval($working_count); ?></div>
                                </div>
                                <div style="padding:12px;background:#fff;border-radius:8px;border:1px solid #e5e7eb;">
                                    <div style="font-size:12px;color:#64748b;margin-bottom:4px;">تحت صيانة</div>
                                    <div style="font-size:18px;font-weight:bold;color:#f59e0b;"><?php echo intval($maintenance_count); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="ms-facility-filters" style="display:flex;gap:8px;flex-wrap:wrap;margin:0 0 16px;">
                            <button type="button" class="ms-facility-filter active" data-facility-status="all" style="padding:8px 14px;border:1px solid #dbe3ee;border-radius:999px;background:#2563eb;color:#fff;cursor:pointer;">الكل</button>
                            <button type="button" class="ms-facility-filter" data-facility-status="working" style="padding:8px 14px;border:1px solid #dbe3ee;border-radius:999px;background:#fff;color:#334155;cursor:pointer;">تعمل</button>
                            <button type="button" class="ms-facility-filter" data-facility-status="under_maintenance" style="padding:8px 14px;border:1px solid #dbe3ee;border-radius:999px;background:#fff;color:#334155;cursor:pointer;">تحت صيانة</button>
                        </div>

                        <!-- نموذج إضافة مرفق -->
                        <div style="margin-bottom:20px;padding:16px;background:#f0f9ff;border:1px solid #bfdbfe;border-radius:12px;">
                            <h4 style="margin-top:0;margin-bottom:12px;">إضافة مرفق جديد</h4>
                            <form id="ms-add-facility-form" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;align-items:end;">
                                <input type="hidden" name="action" value="ms_add_building_facility">
                                <input type="hidden" name="security" value="<?php echo esc_attr(wp_create_nonce('ms_facility_nonce')); ?>">
                                <input type="hidden" name="building_id" value="<?php echo esc_attr($selected_building_id); ?>">
                                <label>
                                    <span style="display:block;margin-bottom:4px;font-size:13px;color:#475569;">اسم المرفق</span>
                                    <input type="text" name="facility_name" required style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;" placeholder="مثال: مصعد، باب حديد">
                                </label>
                                <label>
                                    <span style="display:block;margin-bottom:4px;font-size:13px;color:#475569;">نوع المرفق</span>
                                    <select name="facility_type" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                                        <option value="">اختر النوع</option>
                                        <?php
                                        $type_has_company = (bool) $wpdb->get_var($wpdb->prepare(
                                            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'company_id'",
                                            $types_table
                                        ));
                                        $type_sql = "SELECT * FROM {$types_table}";
                                        $type_params = array();
                                        if ($type_has_company && $company['value'] !== null && strpos($company['clause'], '%') !== false) {
                                            $type_sql .= " WHERE company_id = %d";
                                            $type_params[] = $company['value'];
                                        }
                                        $type_sql .= ' ORDER BY name ASC';
                                        $facility_types = !empty($type_params) ? $wpdb->get_results($wpdb->prepare($type_sql, ...$type_params)) : $wpdb->get_results($type_sql);
                                        if (empty($facility_types)) {
                                            echo '<option value="" disabled>لا توجد أنواع مرافق متاحة</option>';
                                        }
                                        foreach ($facility_types as $ft) {
                                            echo '<option value="' . esc_attr($ft->id) . '">' . esc_html($ft->name) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </label>
                                <label>
                                    <span style="display:block;margin-bottom:4px;font-size:13px;color:#475569;">الحالة</span>
                                    <select name="facility_status" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                                        <option value="working">تعمل</option>
                                        <option value="under_maintenance">تحت صيانة</option>
                                    </select>
                                </label>
                                <button type="submit" style="padding:10px 16px;background:#2563eb;color:#fff;border:0;border-radius:8px;cursor:pointer;font-weight:600;">إضافة</button>
                            </form>
                            <div id="ms-facility-message" style="display:none;margin-top:12px;padding:8px 12px;border-radius:6px;font-size:14px;"></div>
                        </div>

                        <!-- جدول المرافق التفصيلي -->
                        <div style="margin-bottom:20px;">
                            <h4 style="margin-bottom:12px;">قائمة المرافق</h4>
                            <?php if (empty($facilities)) : ?>
                                <p style="color:#64748b;">لا توجد مرافق مسجلة لهذا المبنى.</p>
                            <?php else : ?>
                                <div style="overflow-x:auto;border:1px solid #e5e7eb;border-radius:12px;">
                                    <table class="widefat fixed striped" style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="padding:12px;text-align:right;">#</th>
                                                <th style="padding:12px;text-align:right;">اسم المرفق</th>
                                                <th style="padding:12px;text-align:right;">النوع</th>
                                                <th style="padding:12px;text-align:right;">الحالة</th>
                                                <th style="padding:12px;text-align:right;">آخر تحديث</th>
                                                <th style="padding:12px;text-align:right;">إجراءات</th>
                                            </tr>
                                        </thead>
                                        <tbody>
<?php foreach ($facilities as $index => $f) : ?>
                                                <tr class="ms-facility-row" data-facility-status="<?php echo esc_attr($f->status === 'working' ? 'working' : 'under_maintenance'); ?>">
                                                    <td style="padding:12px;text-align:center;"><?php echo intval($index + 1); ?></td>
                                                    <td style="padding:12px;"><?php echo esc_html($f->name ?? 'بدون اسم'); ?></td>
                                                    <td style="padding:12px;"><?php echo esc_html($f->facility_type_name ?? 'غير محدد'); ?></td>
                                                    <td style="padding:12px;">
                                                        <?php if ($f->status === 'working') : ?>
                                                            <span style="color:#10b981;font-weight:600;">تعمل</span>
                                                        <?php else : ?>
                                                            <span style="color:#f59e0b;font-weight:600;">تحت صيانة</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="padding:12px;"><?php echo esc_html($f->updated_at ?? $f->created_at ?? '—'); ?></td>
                                                    <td style="padding:12px;">
                                                        <button type="button" class="ms-edit-facility-btn" data-facility-id="<?php echo intval($f->id); ?>" data-name="<?php echo esc_attr($f->name); ?>" data-type="<?php echo esc_attr($f->facility_type_id); ?>" data-status="<?php echo esc_attr($f->status); ?>" style="padding:6px 12px;background:#2563eb;color:#fff;border:0;border-radius:6px;cursor:pointer;font-size:12px;margin-left:4px;">تعديل</button>
                                                        <button type="button" class="ms-delete-facility-btn" data-facility-id="<?php echo intval($f->id); ?>" style="padding:6px 12px;background:#ef4444;color:#fff;border:0;border-radius:6px;cursor:pointer;font-size:12px;">حذف</button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php endif; ?>

            <div class="ms-tab-content" id="discussions">
                <div class="ms-card">
                    <h3>مناقشات المبنى</h3>
                    <?php if (!$selected_building_id) : ?>
                        <p>يرجى اختيار مبنى لعرض المناقشات.</p>
                    <?php else : ?>
                        <!-- نموذج إنشاء موضوع جديد -->
                        <div style="margin-bottom:20px;padding:16px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;">
                            <h4 style="margin-top:0;margin-bottom:12px;">إنشاء موضوع جديد</h4>
                            <form class="ms-discussion-create-form" style="display:flex;flex-direction:column;gap:12px;">
                                <input type="hidden" name="building_id" value="<?php echo esc_attr($selected_building_id); ?>">
                                <input type="text" name="title" placeholder="عنوان الموضوع" required 
                                       style="padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px;">
                                <textarea name="content" rows="3" placeholder="محتوى الموضوع" required 
                                          style="padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px;resize:vertical;"></textarea>
                                <button type="submit" style="padding:10px 14px;background:#2563eb;color:#fff;border:none;border-radius:6px;cursor:pointer;font-weight:600;">
                                    إنشاء الموضوع
                                </button>
                            </form>
                        </div>
                        
                        <div id="building-discussions" class="ms-discussions-panel" data-building-id="<?php echo esc_attr($selected_building_id); ?>" style="display:flex;gap:20px;flex-wrap:wrap;">
                            <div style="flex:1 1 320px;min-width:320px;">
                                <h4>المواضيع</h4>
                                <ul class="ms-discussions-list-ul" style="list-style:none;margin:0;padding:0;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;"></ul>
                            </div>
                            <div style="flex:2 1 420px;min-width:320px;display:flex;flex-direction:column;gap:12px;">
                                <div class="ms-discussion-messages" style="min-height:260px;padding:12px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:auto;"></div>
                                <form class="ms-discussion-reply-form" style="display:none;flex-direction:column;gap:12px;">
                                    <textarea name="reply" rows="4" placeholder="اكتب ردك هنا..." style="width:100%;padding:12px;border:1px solid #ddd;border-radius:8px"></textarea>
                                    <button type="submit" style="padding:10px 18px;background:#2563eb;color:#fff;border:none;border-radius:8px;cursor:pointer">إرسال الرد</button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ms-tab-content" id="invoices">
                <div class="ms-card">
                    <h3>إدارة الفواتير</h3>
                    
                    <?php if (!$selected_building_id) : ?>
                        <p>يرجى اختيار مبنى أولاً لعرض الفواتير.</p>
                    <?php else : ?>
                        <!-- Invoice Statistics Cards -->
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px;">
                            <?php 
                            $building_invoices = isset($invoice_groups['building']) && is_array($invoice_groups['building']) ? $invoice_groups['building'] : array();
                            
                            // Calculate paid count
                            $paid_count = 0;
                            foreach ($building_invoices as $inv) {
                                if (strtolower(trim($inv->status ?? '')) === 'paid') {
                                    $paid_count++;
                                }
                            }
                            
                            // Calculate pending count
                            $pending_count = 0;
                            foreach ($building_invoices as $inv) {
                                $status = strtolower(trim($inv->status ?? ''));
                                if ($status !== 'paid' && $status !== 'canceled') {
                                    $pending_count++;
                                }
                            }
                            
                            // Calculate canceled count
                            $canceled_count = 0;
                            foreach ($building_invoices as $inv) {
                                if (strtolower(trim($inv->status ?? '')) === 'canceled') {
                                    $canceled_count++;
                                }
                            }
                            
                            // Calculate paid amount
                            $paid_amount = 0;
                            foreach ($building_invoices as $inv) {
                                if (strtolower(trim($inv->status ?? '')) === 'paid') {
                                    $paid_amount += floatval($inv->amount);
                                }
                            }
                            
                            // Calculate pending amount
                            $pending_amount = 0;
                            foreach ($building_invoices as $inv) {
                                $status = strtolower(trim($inv->status ?? ''));
                                if ($status !== 'paid' && $status !== 'canceled') {
                                    $pending_amount += floatval($inv->amount);
                                }
                            }
                            
                            // Calculate canceled amount
                            $canceled_amount = 0;
                            foreach ($building_invoices as $inv) {
                                if (strtolower(trim($inv->status ?? '')) === 'canceled') {
                                    $canceled_amount += floatval($inv->amount);
                                }
                            }
                            ?>
                            <div style="padding:20px;background:linear-gradient(135deg,#10b981 0%,#059669 100%);border-radius:12px;color:white;">
                                <div style="font-size:0.9rem;opacity:0.9;margin-bottom:8px;">إجمالي الفواتير المدفوعة</div>
                                <div style="font-size:2rem;font-weight:800;"><?php echo $paid_count; ?></div>
                                <div style="font-size:0.85rem;opacity:0.8;margin-top:8px;">
                                    ج.م <?php echo number_format($paid_amount, 2); ?>
                                </div>
                            </div>
                            <div style="padding:20px;background:linear-gradient(135deg,#f59e0b 0%,#d97706 100%);border-radius:12px;color:white;">
                                <div style="font-size:0.9rem;opacity:0.9;margin-bottom:8px;">الفواتير المعلقة</div>
                                <div style="font-size:2rem;font-weight:800;"><?php echo $pending_count; ?></div>
                                <div style="font-size:0.85rem;opacity:0.8;margin-top:8px;">
                                    ج.م <?php echo number_format($pending_amount, 2); ?>
                                </div>
                            </div>
                            <div style="padding:20px;background:linear-gradient(135deg,#ef4444 0%,#dc2626 100%);border-radius:12px;color:white;">
                                <div style="font-size:0.9rem;opacity:0.9;margin-bottom:8px;">الفواتير الملغاة</div>
                                <div style="font-size:2rem;font-weight:800;"><?php echo $canceled_count; ?></div>
                                <div style="font-size:0.85rem;opacity:0.8;margin-top:8px;">
                                    ج.م <?php echo number_format($canceled_amount, 2); ?>
                                </div>
                            </div>
                            <div style="padding:20px;background:linear-gradient(135deg,#2563eb 0%,#1d4ed8 100%);border-radius:12px;color:white;">
                                <div style="font-size:0.9rem;opacity:0.9;margin-bottom:8px;">نسبة التحصيل</div>
                                <div style="font-size:2rem;font-weight:800;"><?php echo isset($building_collection['percent']) ? $building_collection['percent'] : 0; ?>%</div>
                                <div style="font-size:0.85rem;opacity:0.8;margin-top:8px;">
                                    من ج.م <?php echo number_format(isset($building_collection['total_invoiced']) ? $building_collection['total_invoiced'] : 0, 2); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Filter and Search -->
                        <div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
                            <div style="flex:1;min-width:200px;">
                                <input type="text" id="ms-invoice-search" placeholder="بحث في الفواتير..." style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                            </div>
                            <div style="min-width:150px;">
                                <select id="ms-invoice-status-filter" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                                    <option value="">جميع الحالات</option>
                                    <option value="pending">معلقة</option>
                                    <option value="paid">مدفوعة</option>
                                    <option value="canceled">ملغاة</option>
                                </select>
                            </div>
                            <div style="min-width:150px;">
                                <select id="ms-invoice-type-filter" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                                    <option value="">جميع الأنواع</option>
                                    <option value="building-maintenance">صيانة المبنى</option>
                                    <option value="building-facilities">مرافق المبنى</option>
                                    <option value="building-utilities">مرافق عامة</option>
                                </select>
                            </div>
                            <button id="ms-create-invoice-btn" style="padding:10px 20px;background:#2563eb;color:white;border:none;border-radius:8px;cursor:pointer;font-weight:600;">
                                + إنشاء فاتورة
                            </button>
                        </div>

                        <!-- Invoice Tabs -->
                        <div style="display:flex;gap:8px;margin-bottom:20px;border-bottom:1px solid #e5e7eb;padding-bottom:12px;">
                            <button class="ms-invoice-tab active" data-tab="all" style="padding:8px 16px;background:#2563eb;color:white;border:none;border-radius:6px;cursor:pointer;font-weight:600;">الكل</button>
                            <button class="ms-invoice-tab" data-tab="pending" style="padding:8px 16px;background:#f3f4f6;color:#475569;border:none;border-radius:6px;cursor:pointer;font-weight:600;">معلقة</button>
                            <button class="ms-invoice-tab" data-tab="paid" style="padding:8px 16px;background:#f3f4f6;color:#475569;border:none;border-radius:6px;cursor:pointer;font-weight:600;">مدفوعة</button>
                            <button class="ms-invoice-tab" data-tab="overdue" style="padding:8px 16px;background:#f3f4f6;color:#475569;border:none;border-radius:6px;cursor:pointer;font-weight:600;">متأخرة</button>
                        </div>

                        <!-- Invoice Table -->
                        <div style="overflow-x:auto;border:1px solid #e5e7eb;border-radius:12px;">
                            <table style="width:100%;border-collapse:collapse;">
                                <thead>
                                    <tr style="background:#f8fafc;">
                                        <th style="padding:12px;text-align:right;">#</th>
                                        <th style="padding:12px;text-align:right;">رقم الفاتورة</th>
                                        <th style="padding:12px;text-align:right;">النوع</th>
                                        <th style="padding:12px;text-align:right;">المستفيد</th>
                                        <th style="padding:12px;text-align:right;">المبلغ</th>
                                        <th style="padding:12px;text-align:right;">الحالة</th>
                                        <th style="padding:12px;text-align:right;">تاريخ الاستحقاق</th>
                                        <th style="padding:12px;text-align:right;">إجراءات</th>
                                    </tr>
                                </thead>
                                <tbody id="ms-invoices-table-body">
                                    <?php 
                                    $filtered_invoices = isset($invoice_groups['building']) && is_array($invoice_groups['building']) ? $invoice_groups['building'] : array();
                                    if (!empty($filtered_invoices)):
                                        foreach ($filtered_invoices as $invoice): 
                                            $payer_id = intval($invoice->user_id ?? $invoice->payer_id ?? 0);
                                            $payer = $payer_id ? get_userdata($payer_id) : null;
                                            $payer_name = $payer ? $payer->display_name : esc_html($invoice->payer_name ?? '--');
                                            $payer_email = $payer ? $payer->user_email : esc_html($invoice->payer_email ?? '');
                                            $payer_phone = $get_display_phone($payer_id);
                                            $payer_type_label = 'غير معروف';
                                            $payer_type_map = array(
                                                'owner' => 'مالك',
                                                'tenant' => 'مستأجر',
                                                'agent' => 'وسيط',
                                                'owner_or_tenant' => 'مالك/مستأجر',
                                            );
                                            $payer_type_label = isset($payer_type_map[strtolower(trim($invoice->payer_type ?? ''))]) ? $payer_type_map[strtolower(trim($invoice->payer_type ?? ''))] : $payer_type_label;
                                            $status = strtolower(trim($invoice->status ?? 'pending'));
                                            $status_badge = '';
                                            switch($status) {
                                                case 'paid':
                                                    $status_badge = '<span style="padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;background:#dcfce7;color:#166534;">مدفوعة</span>';
                                                    break;
                                                case 'pending':
                                                    $status_badge = '<span style="padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;background:#fef3c7;color:#92400e;">معلقة</span>';
                                                    break;
                                                case 'canceled':
                                                    $status_badge = '<span style="padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;background:#fee2e2;color:#991b1b;">ملغاة</span>';
                                                    break;
                                                default:
                                                    $status_badge = '<span style="padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;background:#f3f4f6;color:#475569;">' . $status . '</span>';
                                            }
                                            $invoice_type = isset($invoice->invoice_category) ? $invoice->invoice_category : 'building-maintenance';
                                            $type_label = 'صيانة المبنى';
                                            if ($invoice_type === 'building-facilities') $type_label = 'مرافق المبنى';
                                            elseif ($invoice_type === 'building-utilities') $type_label = 'مرافق عامة';
                                            ?>
                                            <tr style="border-top:1px solid #e5e7eb;" data-status="<?php echo esc_attr($status); ?>" data-type="<?php echo esc_attr($invoice_type); ?>">
                                                <td style="padding:12px;"><?php echo esc_html($invoice->id); ?></td>
                                                <td style="padding:12px;">
                                                    <span style="font-weight:600;color:#2563eb;">INV-<?php echo str_pad($invoice->id, 6, '0', STR_PAD_LEFT); ?></span>
                                                </td>
                                                <td style="padding:12px;"><?php echo esc_html($type_label); ?></td>
                                                <td style="padding:12px;">
                                                    <div style="font-weight:600;"><?php echo esc_html($payer_name); ?></div>
                                                    <div style="font-size:12px;color:#64748b;"><?php echo esc_html($payer_type_label); ?></div>
                                                    <?php if (!empty($payer_phone)): ?>
                                                        <div style="font-size:12px;color:#64748b;"><?php echo esc_html($payer_phone); ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="padding:12px;">
                                                    <span style="font-weight:700;font-size:1.1rem;">ج.م <?php echo number_format(floatval($invoice->amount), 2); ?></span>
                                                </td>
                                                <td style="padding:12px;"><?php echo $status_badge; ?></td>
                                                <td style="padding:12px;">
                                                    <?php 
                                                    $due_date = !empty($invoice->due_date) ? date('Y-m-d', strtotime($invoice->due_date)) : '';
                                                    if ($due_date) {
                                                        $is_overdue = strtotime($due_date) < time() && $status !== 'paid';
                                                        if ($is_overdue) {
                                                            echo '<span style="color:#ef4444;font-weight:600;">' . esc_html($due_date) . ' (متأخرة)</span>';
                                                        } else {
                                                            echo esc_html($due_date);
                                                        }
                                                    } else {
                                                        echo '—';
                                                    }
                                                    ?>
                                                </td>
                                                <td style="padding:12px;">
                                                    <div style="display:flex;gap:8px;">
                                                        <button class="ms-invoice-action ms-invoice-action-view" data-action="view" data-id="<?php echo esc_attr($invoice->id); ?>">عرض</button>
                                                        <?php if ($status === 'pending'): ?>
                                                            <button class="ms-invoice-action ms-invoice-action-pay" data-action="pay" data-id="<?php echo esc_attr($invoice->id); ?>">دفع</button>
                                                        <?php endif; ?>
                                                        <button class="ms-invoice-action ms-invoice-action-download" data-action="download" data-id="<?php echo esc_attr($invoice->id); ?>">تحميل</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if (empty($filtered_invoices)): ?>
                            <div style="padding:40px;text-align:center;color:#64748b;">
                                <div style="font-size:3rem;margin-bottom:16px;">📄</div>
                                <p>لا توجد فواتير حالياً</p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (false) : ?><div class="ms-tab-content" id="notifications">
                <div class="ms-card">
                    <h3>الإشعارات</h3>
                    <?php if (empty($notifications)) : ?>
                        <p>لا توجد إشعارات جديدة.</p>
                    <?php else : ?>
                        <ul style="list-style:none;margin:0;padding:0;">
                            <?php foreach ($notifications as $notification) : ?>
                                <li style="padding:12px;border-bottom:1px solid #f3f4f6;">
                                    <div style="font-size:14px;color:#111;">
                                        <?php echo esc_html($notification->message ?? ''); ?>
                                    </div>
                                    <div style="font-size:12px;color:#6b7280;margin-top:6px;">
                                        <?php echo esc_html($notification->created_at ?? ''); ?>
                                        <?php if (isset($notification->is_read) && intval($notification->is_read) === 0) : ?>
                                            <span style="background:#2563eb;color:#fff;padding:2px 8px;border-radius:999px;margin-left:8px;font-size:11px;">جديد</span>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <?php endif; ?>

            <?php if (false) : ?><div class="ms-tab-content" id="analytics">
                <?php
                $analytics_path = ms_get_dashboard_template_path('templates/analytics-dashboard.php');
                if (file_exists($analytics_path)) {
                    include $analytics_path;
                } else {
                    echo '<div class="ms-card"><p>لوحة التحليلات غير متاحة حالياً</p></div>';
                }
                ?>
            </div>

            <?php endif; ?>

            <?php if (false) : ?><div class="ms-tab-content" id="users">
                <?php
                if (class_exists('MS_Advanced_User_Management')) {
                    $user_management = new MS_Advanced_User_Management();
                    echo $user_management->render_user_management_dashboard();
                } else {
                    echo '<div class="ms-card"><p>نظام إدارة المستخدمين غير متاح حالياً</p></div>';
                }
                ?>
            </div><?php endif; ?>

            <div class="ms-tab-content" id="profile">
                <div class="ms-card">
                    <h3>البروفايل الشخصي</h3>
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

                        <!-- Houzez-style Profile Fields -->
                        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:20px;">
                            <div>
                                <label style="display:block;margin-bottom:8px;font-weight:600;">الاسم الأول *</label>
                                <input type="text" name="first_name" value="<?php echo esc_attr($user->first_name); ?>" required style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                            </div>
                            <div>
                                <label style="display:block;margin-bottom:8px;font-weight:600;">اسم العائلة *</label>
                                <input type="text" name="last_name" value="<?php echo esc_attr($user->last_name); ?>" required style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                            </div>
                            <div>
                                <label style="display:block;margin-bottom:8px;font-weight:600;">الاسم المعروض *</label>
                                <input type="text" name="display_name" value="<?php echo esc_attr($user->display_name); ?>" required style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                            </div>
                            <div>
                                <label style="display:block;margin-bottom:8px;font-weight:600;">البريد الإلكتروني *</label>
                                <input type="email" name="email" value="<?php echo esc_attr($user->user_email); ?>" required style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                            </div>
                            <div>
                                <label style="display:block;margin-bottom:8px;font-weight:600;">رقم الهاتف</label>
                                <input type="tel" name="phone" value="<?php echo esc_attr(get_user_meta($user->ID, 'billing_phone', true)); ?>" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                            </div>
                            <div>
                                <label style="display:block;margin-bottom:8px;font-weight:600;">رقم الهاتف البديل</label>
                                <input type="tel" name="phone_alternative" value="<?php echo esc_attr(get_user_meta($user->ID, 'billing_phone_alternative', true)); ?>" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                            </div>
                            <div>
                                <label style="display:block;margin-bottom:8px;font-weight:600;">الدولة</label>
                                <input type="text" name="country" value="<?php echo esc_attr(get_user_meta($user->ID, 'billing_country', true)); ?>" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                            </div>
                            <div>
                                <label style="display:block;margin-bottom:8px;font-weight:600;">المدينة</label>
                                <input type="text" name="city" value="<?php echo esc_attr(get_user_meta($user->ID, 'billing_city', true)); ?>" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                            </div>
                            <div>
                                <label style="display:block;margin-bottom:8px;font-weight:600;">العنوان</label>
                                <input type="text" name="address" value="<?php echo esc_attr(get_user_meta($user->ID, 'billing_address_1', true)); ?>" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                            </div>
                            <div>
                                <label style="display:block;margin-bottom:8px;font-weight:600;">الرمز البريدي</label>
                                <input type="text" name="postcode" value="<?php echo esc_attr(get_user_meta($user->ID, 'billing_postcode', true)); ?>" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <label style="display:block;margin-bottom:8px;font-weight:600;">العنوان التفصيلي</label>
                                <input type="text" name="address_2" value="<?php echo esc_attr(get_user_meta($user->ID, 'billing_address_2', true)); ?>" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <label style="display:block;margin-bottom:8px;font-weight:600;">نبذة عني</label>
                                <textarea name="description" rows="4" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;"><?php echo esc_textarea($user->description); ?></textarea>
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <label style="display:block;margin-bottom:8px;font-weight:600;">روابط التواصل الاجتماعي</label>
                                <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;">
                                    <div>
                                        <label style="display:block;margin-bottom:4px;font-size:13px;color:#64748b;">فيسبوك</label>
                                        <input type="url" name="facebook" value="<?php echo esc_attr(get_user_meta($user->ID, 'facebook', true)); ?>" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:4px;font-size:13px;color:#64748b;">تويتر</label>
                                        <input type="url" name="twitter" value="<?php echo esc_attr(get_user_meta($user->ID, 'twitter', true)); ?>" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:4px;font-size:13px;color:#64748b;">لينكد إن</label>
                                        <input type="url" name="linkedin" value="<?php echo esc_attr(get_user_meta($user->ID, 'linkedin', true)); ?>" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:4px;font-size:13px;color:#64748b;">إنستغرام</label>
                                        <input type="url" name="instagram" value="<?php echo esc_attr(get_user_meta($user->ID, 'instagram', true)); ?>" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                                    </div>
                                </div>
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <label style="display:block;margin-bottom:8px;font-weight:600;">إعدادات الإشعارات</label>
                                <div style="display:flex;flex-direction:column;gap:12px;padding:16px;background:#f8fafc;border-radius:8px;">
                                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                        <input type="checkbox" name="notify_email" value="1" <?php checked(get_user_meta($user->ID, 'notify_email', true), 1); ?>>
                                        <span>إشعارات البريد الإلكتروني</span>
                                    </label>
                                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                        <input type="checkbox" name="notify_sms" value="1" <?php checked(get_user_meta($user->ID, 'notify_sms', true), 1); ?>>
                                        <span>إشعارات الرسائل النصية</span>
                                    </label>
                                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                        <input type="checkbox" name="notify_push" value="1" <?php checked(get_user_meta($user->ID, 'notify_push', true), 1); ?>>
                                        <span>إشعارات التطبيق</span>
                                    </label>
                                </div>
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <label style="display:block;margin-bottom:8px;font-weight:600;">اللغة المفضلة</label>
                                <select name="language" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                                    <option value="ar" <?php selected(get_user_meta($user->ID, 'language', true), 'ar'); ?>>العربية</option>
                                    <option value="en" <?php selected(get_user_meta($user->ID, 'language', true), 'en'); ?>>English</option>
                                </select>
                            </div>
                        </div>

                        <div style="margin-top:24px;">
                            <button type="submit" style="padding:12px 24px;background:#2563eb;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:600;">حفظ التغييرات</button>
                        </div>
                    </form>
                </div>
            </div>

        </main>

    </div>

    <script type="text/javascript">
        console.log('Building dashboard script tag loaded');
        document.addEventListener('DOMContentLoaded', function(){
            console.log('Building dashboard DOMContentLoaded fired');
            
            // Tab switching functionality
            var tabLinks = document.querySelectorAll('.ms-tab-link');
            var tabContents = document.querySelectorAll('.ms-tab-content');
            
            console.log('Found ' + tabLinks.length + ' tab links');
            console.log('Found ' + tabContents.length + ' tab contents');
            
            // Log all tab links and contents
            tabLinks.forEach(function(link, index) {
                console.log('Tab link ' + index + ':', link.getAttribute('data-tab'), link.textContent.trim());
            });
            
            tabContents.forEach(function(content, index) {
                console.log('Tab content ' + index + ':', content.id, content.classList.contains('active'));
            });
            
            tabLinks.forEach(function(link) {
                link.addEventListener('click', function(e) {
                    var tab = this.getAttribute('data-tab');
                    console.log('Tab clicked: ' + tab);
                    
                    if (!tab || this.getAttribute('data-external') === 'true') {
                        return;
                    }
                    
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Update URL hash
                    window.location.hash = tab;
                    
                    // Update active states
                    tabLinks.forEach(function(l) {
                        l.classList.remove('active');
                        l.parentElement.classList.remove('active');
                    });
                    this.classList.add('active');
                    this.parentElement.classList.add('active');
                    
                    // Show corresponding content
                    tabContents.forEach(function(content) {
                        content.classList.remove('active');
                        if (content.id === tab) {
                            content.classList.add('active');
                            console.log('Activated tab content: ' + tab);
                        }
                    });
                    
                    console.log('After click - active contents:', document.querySelectorAll('.ms-tab-content.active').length);
                });
            });
            
            // Activate tab based on URL hash on load
            var hash = window.location.hash.replace('#', '');
            console.log('URL hash: ' + hash);
            
            if (hash) {
                var targetLink = document.querySelector('.ms-tab-link[data-tab="' + hash + '"]');
                var targetContent = document.getElementById(hash);
                
                console.log('Target link found: ' + (targetLink ? 'yes' : 'no'));
                console.log('Target content found: ' + (targetContent ? 'yes' : 'no'));
                
                if (targetLink) {
                    tabLinks.forEach(function(l) {
                        l.classList.remove('active');
                        l.parentElement.classList.remove('active');
                    });
                    targetLink.classList.add('active');
                    targetLink.parentElement.classList.add('active');
                }
                
                if (targetContent) {
                    tabContents.forEach(function(content) {
                        content.classList.remove('active');
                    });
                    targetContent.classList.add('active');
                    console.log('Activated tab content from hash: ' + hash);
                }
            } else {
                // Default to overview if no hash
                console.log('No hash, defaulting to overview');
                var overviewLink = document.querySelector('.ms-tab-link[data-tab="overview"]');
                var overviewContent = document.getElementById('overview');
                
                if (overviewLink) {
                    overviewLink.classList.add('active');
                    overviewLink.parentElement.classList.add('active');
                }
                if (overviewContent) {
                    overviewContent.classList.add('active');
                }
            }
            
            function buildBuildingUrl(buildingId) {
                var params = new URLSearchParams(window.location.search);
                params.set('building_id', buildingId);
                var query = params.toString();

                var activeTab = '';
                var activeTabLink = document.querySelector('.ms-tab-link.active');
                if (activeTabLink) {
                    activeTab = activeTabLink.getAttribute('data-tab') || '';
                }
                if (!activeTab && window.location.hash) {
                    activeTab = window.location.hash.replace('#', '');
                }

                var hashPart = activeTab ? '#' + activeTab : '';
                return window.location.pathname + (query ? '?' + query : '') + hashPart;
            }

            var selector = document.getElementById('ms-building-selector');
            if (selector) {
                selector.addEventListener('change', function() {
                    var value = this.value;
                    if (!value) return;
                    window.location.href = buildBuildingUrl(value);
                });
            }

            // Links inside a building row must retain their own destination.
            document.querySelectorAll('.ms-building-row a').forEach(function(link) {
                link.addEventListener('click', function(event) {
                    event.stopPropagation();
                }, true);
            });

            var buildingRows = document.querySelectorAll('.ms-building-row');
            buildingRows.forEach(function(row) {
                row.addEventListener('click', function(event) {
                    // Preserve direct links inside the row, especially the public building page.
                    if (event.target && event.target.closest('a')) return;
                    var buildingId = this.getAttribute('data-building-id');
                    if (!buildingId) return;
                    window.location.href = buildBuildingUrl(buildingId);
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

            // Facilities derived status + utility bills loader
            var loadBtn = document.getElementById('ms-load-utility-bills');
            var buildingId = document.querySelector('#facilities[data-building-id]') ? document.querySelector('#facilities').getAttribute('data-building-id') : null;
            var derivedWrap = document.getElementById('ms-facilities-derived-status');

            var inferredBuildingId = <?php echo intval($selected_building_id); ?>;
            buildingId = buildingId ? buildingId : inferredBuildingId;

            function apiGet(path) {
                return fetch(path, { credentials: 'same-origin' }).then(r => r.json());
            }

            if (buildingId && derivedWrap) {
                apiGet('/wp-json/mostager/v1/facilities/status?building_id=' + encodeURIComponent(buildingId))
                    .then(function(json){
                        if (!json || !json.data) {
                            derivedWrap.textContent = 'لم يتم العثور على مرافق للمبنى.';
                            return;
                        }
                        var data = json.data;
                        if (!data.length) {
                            derivedWrap.textContent = 'لا توجد مرافق.';
                            return;
                        }
                        var lines = data.map(function(item){
                            var dot = item.color ? item.color : '#10b981';
                            var label = item.facility_name || item.facility_type_name || ('Facility #' + item.facility_id);
                            return '<div style="display:flex;align-items:center;gap:10px;margin:6px 0">' +
                                '<span style="width:10px;height:10px;border-radius:999px;background:' + dot + '"></span>' +
                                '<span>' + (label || '') + '</span>' +
                                '<span style="color:#64748b;font-size:12px">(' + (item.derived_status || '') + ')</span>' +
                            '</div>';
                        });
                        derivedWrap.innerHTML = lines.join('');
                    })
                    .catch(function(){
                        derivedWrap.textContent = 'فشل تحميل بيانات المرافق.';
                    });
            }

            // Facility add form
            var facilityForm = document.getElementById('ms-add-facility-form');
            var facilityMessage = document.getElementById('ms-facility-message');
            if (facilityForm && facilityMessage) {
                facilityForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    facilityMessage.style.display = 'block';
                    facilityMessage.style.background = '#dbeafe';
                    facilityMessage.style.color = '#1e40af';
                    facilityMessage.textContent = 'جاري الإضافة...';
                    
                    fetch('<?php echo esc_js(admin_url('admin-ajax.php')); ?>', {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: new FormData(facilityForm)
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) {
                            facilityMessage.style.background = '#dcfce7';
                            facilityMessage.style.color = '#166534';
                            facilityMessage.textContent = 'تمت الإضافة بنجاح';
                            setTimeout(function() { location.reload(); }, 1000);
                        } else {
                            facilityMessage.style.background = '#fee2e2';
                            facilityMessage.style.color = '#991b1b';
                            facilityMessage.textContent = res.data && res.data.message ? res.data.message : 'حدث خطأ';
                        }
                    })
                    .catch(function() {
                        facilityMessage.style.background = '#fee2e2';
                        facilityMessage.style.color = '#991b1b';
                        facilityMessage.textContent = 'حدث خطأ في الاتصال';
                    });
                });
            }

            // Discussion form handling
            var discussionCreateForm = document.querySelector('.ms-discussion-create-form');
            if (discussionCreateForm) {
                discussionCreateForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    var formData = new FormData(this);
                    formData.append('action', 'ms_create_discussion');
                    formData.append('security', '<?php echo esc_js(wp_create_nonce('ms_discussion_nonce')); ?>');
                    
                    fetch('<?php echo esc_js(admin_url('admin-ajax.php')); ?>', {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: formData
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) {
                            alert('تم إنشاء الموضوع بنجاح');
                            discussionCreateForm.reset();
                            // Reload discussions list
                            if (typeof loadDiscussions === 'function') {
                                loadDiscussions();
                            }
                        } else {
                            alert('خطأ: ' + (res.data && res.data.message ? res.data.message : 'حدث خطأ أثناء الإنشاء'));
                        }
                    })
                    .catch(function() {
                        alert('حدث خطأ في الاتصال');
                    });
                });
            }
            
            // Invoice filtering and search
            var invoiceSearch = document.getElementById('ms-invoice-search');
            var invoiceStatusFilter = document.getElementById('ms-invoice-status-filter');
            var invoiceTypeFilter = document.getElementById('ms-invoice-type-filter');
            var invoiceTabs = document.querySelectorAll('.ms-invoice-tab');
            var invoiceTableBody = document.getElementById('ms-invoices-table-body');
            
            function filterInvoices() {
                if (!invoiceTableBody) return;
                
                var searchTerm = invoiceSearch ? invoiceSearch.value.toLowerCase() : '';
                var statusFilter = invoiceStatusFilter ? invoiceStatusFilter.value : '';
                var typeFilter = invoiceTypeFilter ? invoiceTypeFilter.value : '';
                var activeTab = document.querySelector('.ms-invoice-tab.active');
                var tabFilter = activeTab ? activeTab.getAttribute('data-tab') : 'all';
                
                var rows = invoiceTableBody.querySelectorAll('tr');
                rows.forEach(function(row) {
                    var rowText = row.textContent.toLowerCase();
                    var rowStatus = row.getAttribute('data-status') || '';
                    var rowType = row.getAttribute('data-type') || '';
                    
                    var matchesSearch = searchTerm === '' || rowText.includes(searchTerm);
                    var matchesStatus = statusFilter === '' || rowStatus === statusFilter;
                    var matchesType = typeFilter === '' || rowType === typeFilter;
                    var matchesTab = tabFilter === 'all' || rowStatus === tabFilter;
                    
                    if (matchesSearch && matchesStatus && matchesType && matchesTab) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }
            
            if (invoiceSearch) {
                invoiceSearch.addEventListener('input', filterInvoices);
            }
            
            if (invoiceStatusFilter) {
                invoiceStatusFilter.addEventListener('change', filterInvoices);
            }
            
            if (invoiceTypeFilter) {
                invoiceTypeFilter.addEventListener('change', filterInvoices);
            }
            
            invoiceTabs.forEach(function(tab) {
                tab.addEventListener('click', function() {
                    invoiceTabs.forEach(function(t) {
                        t.classList.remove('active');
                        t.style.background = '#f3f4f6';
                        t.style.color = '#475569';
                    });
                    this.classList.add('active');
                    this.style.background = '#2563eb';
                    this.style.color = 'white';
                    filterInvoices();
                });
            });
            
            // Invoice action buttons
            var invoiceActions = document.querySelectorAll('.ms-invoice-action');
            invoiceActions.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var action = this.getAttribute('data-action');
                    var invoiceId = this.getAttribute('data-id');
                    
                    if (action === 'view') {
                        // Open invoice modal or redirect
                        window.open('<?php echo admin_url('admin.php?page=ms_invoice_view&id='); ?>' + invoiceId, '_blank');
                    } else if (action === 'pay') {
                        // Redirect to payment page
                        window.location.href = '<?php echo home_url('/payment/?invoice_id='); ?>' + invoiceId;
                    } else if (action === 'download') {
                        // Download invoice PDF
                        window.location.href = '<?php echo admin_url('admin-ajax.php?action=ms_download_invoice&id='); ?>' + invoiceId;
                    }
                });
            });
            
            // Create invoice button
            var createInvoiceBtn = document.getElementById('ms-create-invoice-btn');
            if (createInvoiceBtn) {
                createInvoiceBtn.addEventListener('click', function() {
                    // Redirect to invoice creation page
                    window.location.href = '<?php echo admin_url('admin.php?page=ms_create_invoice&building_id='); ?>' + <?php echo intval($selected_building_id); ?>;
                });
            }
            
            // Discussion reply form handling
            var discussionReplyForm = document.querySelector('.ms-discussion-reply-form');
            if (discussionReplyForm) {
                discussionReplyForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    var formData = new FormData(this);
                    formData.append('action', 'ms_reply_discussion');
                    formData.append('security', '<?php echo esc_js(wp_create_nonce('ms_discussion_nonce')); ?>');
                    
                    fetch('<?php echo esc_js(admin_url('admin-ajax.php')); ?>', {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: formData
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) {
                            alert('تم إرسال الرد بنجاح');
                            discussionReplyForm.reset();
                            discussionReplyForm.style.display = 'none';
                            // Reload discussion messages
                            if (typeof loadDiscussionMessages === 'function') {
                                loadDiscussionMessages();
                            }
                        } else {
                            alert('خطأ: ' + (res.data && res.data.message ? res.data.message : 'حدث خطأ أثناء الإرسال'));
                        }
                    })
                    .catch(function() {
                        alert('حدث خطأ في الاتصال');
                    });
                });
            }
            
            const deleteBtns = document.querySelectorAll('.ms-delete-facility-btn');

            // Facility delete buttons
            deleteBtns.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var facilityId = this.getAttribute('data-facility-id');
                    if (!confirm('هل أنت متأكد من حذف هذا المرفق؟')) return;
                    
                    var formData = new FormData();
                    formData.append('action', 'ms_delete_building_facility');
                    formData.append('security', '<?php echo esc_js(wp_create_nonce('ms_facility_nonce')); ?>');
                    formData.append('facility_id', facilityId);
                    
                    fetch('<?php echo esc_js(admin_url('admin-ajax.php')); ?>', {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: formData
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) {
                            alert('تم الحذف بنجاح');
                            location.reload();
                        } else {
                            alert(res.data && res.data.message ? res.data.message : 'حدث خطأ');
                        }
                    })
                    .catch(function() {
                        alert('حدث خطأ في الاتصال');
                    });
                });
            });

            if (loadBtn) {
                loadBtn.addEventListener('click', function(){
                    var out = document.getElementById('ms-utility-bills-wrap');
                    if (!out) return;
                    out.textContent = 'جاري التحميل...';
                    apiGet('/wp-json/mostager/v1/utility-bills?building_id=' + encodeURIComponent(buildingId))
                        .then(function(json){
                            if (!json || !json.data) {
                                out.textContent = 'لا توجد فواتير للمبنى أو فشل تحميل البيانات.';
                                return;
                            }
                            var bills = json.data;
                            if (!bills.length) {
                                out.textContent = 'لا توجد فواتير للمبنى.';
                                return;
                            }
                            var html = '<div style="display:flex;flex-direction:column;gap:10px">';
                            html += bills.map(function(b){
                                return '<div style="padding:12px;border:1px solid #e5e7eb;border-radius:12px;background:#fff">' +
                                    '<div style="font-weight:700">' + (b.title || 'Utility Bill') + '</div>' +
                                    '<div style="color:#475569;font-size:13px;margin-top:6px">' +
                                    'الحالة: ' + (b.status || '') + ' | ' +
                                    'المبلغ: ' + (b.total_amount || 0) + '</div>' +
                                    '</div>';
                            }).join('');
                            html += '</div>';
                            out.innerHTML = html;
                        })
                        .catch(function(){
                            out.textContent = 'فشل تحميل فواتير الخدمات.';
                        });
                });
            }

            // Mark all notifications as read
            var markAllReadBtn = document.getElementById('ms-mark-all-notifications-read');
            if (markAllReadBtn) {
                markAllReadBtn.addEventListener('click', function() {
                    if (!confirm('هل أنت متأكد من تعليم جميع الإشعارات كمقروءة؟')) return;
                    
                    var formData = new FormData();
                    formData.append('action', 'ms_mark_all_notifications_read');
                    formData.append('security', '<?php echo esc_js(wp_create_nonce('mostaager-ajax-nonce')); ?>');
                    
                    fetch('<?php echo esc_js(admin_url('admin-ajax.php')); ?>', {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: formData
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) {
                            alert('تم تعليم جميع الإشعارات كمقروءة');
                            location.reload();
                        } else {
                            alert(res.data && res.data.message ? res.data.message : 'حدث خطأ');
                        }
                    })
                    .catch(function() {
                        alert('حدث خطأ في الاتصال');
                    });
                });
            }
        });
    </script>

    <?php
    return ob_get_clean();

});

