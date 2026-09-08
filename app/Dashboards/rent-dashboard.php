<?php
if (!defined('ABSPATH')) exit;

add_shortcode('rent_dashboard_v4', function () {
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
    
    // Check permissions: only site admin or tenant can access
    if (!current_user_can('manage_options') && !function_exists('ms_user_has_role')) {
        return '<p>غير مصرح لك بالوصول إلى هذه الصفحة.</p>';
    }
    
    if (!current_user_can('manage_options') && !ms_user_has_role($user->ID, 'tenant')) {
        return '<p>غير مصرح لك بالوصول إلى هذه الصفحة. هذه الصفحة مخصصة للمستأجرين فقط.</p>';
    }

    ob_start();

    $wallet = function_exists('ms_get_wallet_balance') ? ms_get_wallet_balance($user->ID) : 0;
    $invoices = function_exists('ms_get_tenant_invoices') ? ms_get_tenant_invoices($user->ID, 100) : array();
    
    // Keep property/rent invoices separate from building/facility invoices.
    $invoice_groups = array(
        'property' => array(),
        'building' => array(),
    );
    foreach ((array) $invoices as $invoice) {
        $category = isset($invoice->invoice_category) ? sanitize_key($invoice->invoice_category) : '';
        $type = isset($invoice->invoice_type) ? sanitize_key($invoice->invoice_type) : '';
        $is_building_invoice = in_array($category, array('building-maintenance', 'building-facilities', 'building-utilities', 'maintenance', 'facility', 'utilities'), true)
            || in_array($type, array('building-maintenance', 'building-facilities', 'building-utilities', 'maintenance', 'facility', 'utilities'), true);
        if ($is_building_invoice) {
            $invoice_groups['building'][] = $invoice;
        } else {
            $invoice_groups['property'][] = $invoice;
        }
    }
    
    // Count rent invoices and maintenance invoices separately
    $rent_invoices = [];
    $maintenance_invoices = [];
    foreach ($invoices as $invoice) {
        $category = isset($invoice->invoice_category) ? $invoice->invoice_category : 'property-rent';
        if (in_array($category, ['property-rent', 'property-monthly', 'property-sale'])) {
            $rent_invoices[] = $invoice;
        } else {
            $maintenance_invoices[] = $invoice;
        }
    }
    
    $pending_count = function_exists('ms_get_invoices_count_by_user_and_status') ? ms_get_invoices_count_by_user_and_status($user->ID, 'pending') : 0;
    $overdue_count = function_exists('ms_get_user_overdue_count') ? ms_get_user_overdue_count($user->ID) : 0;
    $next_due = function_exists('ms_get_latest_due_invoice') ? ms_get_latest_due_invoice($user->ID) : null;
    $rent_streak = function_exists('ms_get_rent_streak_badge') ? ms_get_rent_streak_badge($user->ID) : array('label' => 'غير متوفر', 'streak' => 0, 'color' => '#64748b');
    $notifications = function_exists('ms_get_notifications_by_user') ? ms_get_notifications_by_user($user->ID, 20) : array();
    $unread_notifications_count = function_exists('ms_get_unread_notifications_count') ? ms_get_unread_notifications_count($user->ID) : 0;

    // Tenant unit lookup (ms_unit_tenants priority)
    global $wpdb;
    $tenant_unit = null;
    $tenant_building_id = 0;
    $tenant_unit_id = 0;
    if (function_exists('ms_get_tenant_unit')) {
        $tenant_unit = ms_get_tenant_unit($user->ID);
        if ($tenant_unit) {
            $tenant_unit_id = intval($tenant_unit->unit_id ?? 0);
            $tenant_building_id = intval($tenant_unit->building_id ?? 0);
        }
    } else {
        global $wpdb;
        if ($wpdb) {
            $ten_table = $wpdb->prefix . 'ms_unit_tenants';
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$ten_table} WHERE tenant_id = %d AND status = %s AND (end_date IS NULL OR end_date = '' OR end_date >= CURDATE()) ORDER BY start_date DESC LIMIT 1",
                absint($user->ID),
                'active'
            ));
            if ($row) {
                $tenant_unit = $row;
                $tenant_unit_id = intval($row->unit_id ?? 0);
                $tenant_building_id = intval($row->building_id ?? 0);
            }
        }
    }

    // Fallback for legacy/imported records where ms_unit_tenants is missing or incomplete.
    if ((!$tenant_unit_id || !$tenant_building_id) && $wpdb instanceof wpdb) {
        $fallback_unit_table = $wpdb->prefix . 'ms_units';
        $fallback_unit = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$fallback_unit_table} WHERE tenant_id = %d AND status IN ('rented','occupied','active') ORDER BY id DESC LIMIT 1",
            absint($user->ID)
        ));
        if ($fallback_unit) {
            $tenant_unit = $fallback_unit;
            $tenant_unit_id = absint($fallback_unit->id ?? $fallback_unit->unit_id ?? 0);
            $tenant_building_id = absint($fallback_unit->building_id ?? 0);
        }
    }
    $tenant_property_name = 'غير محدد';
    $tenant_unit_label = $tenant_unit_id ? ('وحدة #' . $tenant_unit_id) : 'غير محددة';
    $tenant_building_name = 'غير محدد';
    if ($tenant_unit_id && isset($wpdb) && $wpdb instanceof wpdb) {
        $unit_table = $wpdb->prefix . 'ms_units';
        $unit_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$unit_table} WHERE id = %d LIMIT 1", $tenant_unit_id));
        if ($unit_row) {
            if (!$tenant_building_id) {
                $tenant_building_id = absint($unit_row->building_id ?? 0);
            }
            $unit_number = $unit_row->unit_number ?? $unit_row->number ?? $unit_row->name ?? '';
            if ($unit_number !== '') {
                $tenant_unit_label = 'وحدة ' . sanitize_text_field((string) $unit_number);
            }
            $property_post_id = absint($unit_row->wp_post_id ?? $unit_row->property_id ?? 0);
            if ($property_post_id) {
                $property_post = get_post($property_post_id);
                if ($property_post) {
                    $tenant_property_name = $property_post->post_title;
                }
            }
        }
    }
    if ($tenant_building_id && isset($wpdb) && $wpdb instanceof wpdb) {
        $building_table = $wpdb->prefix . 'ms_buildings';
        $building_row = $wpdb->get_row($wpdb->prepare("SELECT title, wp_post_id FROM {$building_table} WHERE id = %d LIMIT 1", $tenant_building_id));
        if ($building_row) {
            $tenant_building_name = $building_row->title ?: $tenant_building_name;
            if (!empty($building_row->wp_post_id)) {
                $building_post = get_post(absint($building_row->wp_post_id));
                if ($building_post && $building_post->post_title) {
                    $tenant_building_name = $building_post->post_title;
                }
            }
        }
    }
    if ($tenant_property_name === 'غير محدد' && $tenant_unit_id) {
        // Some legacy imports store the Houzez property post ID in unit_id.
        $direct_property = get_post($tenant_unit_id);
        if ($direct_property && $direct_property->post_type === 'property') {
            $tenant_property_name = $direct_property->post_title;
        } else {
            $linked_properties = get_posts(array(
                'post_type' => 'property',
                'post_status' => array('publish', 'pending', 'private'),
                'posts_per_page' => 1,
                'fields' => 'ids',
                'meta_query' => array(
                    'relation' => 'OR',
                    array('key' => 'ms_unit_id', 'value' => $tenant_unit_id, 'compare' => '='),
                    array('key' => 'unit_id', 'value' => $tenant_unit_id, 'compare' => '='),
                ),
            ));
            if (!empty($linked_properties)) {
                $linked_property = get_post(absint($linked_properties[0]));
                if ($linked_property) {
                    $tenant_property_name = $linked_property->post_title;
                }
            }
        }
    }
    if ($tenant_property_name === 'غير محدد' && $tenant_unit_id) {
        $tenant_property_name = $tenant_unit_label;
    }

    // Tenant maintenance requests and wallet transactions.
    $tenant_maintenance_requests = array();
    $tenant_wallet_transactions = array();

    if (!isset($wpdb) || !($wpdb instanceof wpdb)) {
        error_log('[Mostaager] Rent dashboard: wpdb is unavailable.');
    } else {
        // A tenant must see only requests for the unit linked to their account.
        // Filtering by building_id alone would expose other tenants' requests.
        if ($tenant_unit_id > 0) {
            $maint_table = $wpdb->prefix . 'ms_maintenance_requests';
            $invoice_table = $wpdb->prefix . 'ms_invoices';
            // Table names are built only from the trusted WordPress prefix.
            // All dynamic values are passed through prepare().
            $maintenance_sql = $wpdb->prepare(
                "SELECT m.id, m.building_id, m.unit_id, m.title, m.description, m.cost, m.status, m.priority, m.maintenance_type, m.start_date, m.due_date, m.completed_date, m.created_at, m.updated_at, i.id AS payable_invoice_id, i.amount AS payable_invoice_amount, i.status AS payable_invoice_status, i.due_date AS payable_invoice_due_date FROM {$maint_table} AS m LEFT JOIN {$invoice_table} AS i ON i.id = (SELECT MAX(i2.id) FROM {$invoice_table} AS i2 WHERE i2.expense_id = m.id AND i2.user_id = %d) WHERE m.unit_id = %d AND m.building_id = %d ORDER BY m.created_at DESC LIMIT %d",
                absint($user->ID),
                $tenant_unit_id,
                $tenant_building_id,
                50
            );
            $tenant_maintenance_requests = $wpdb->get_results($maintenance_sql);
            if (is_array($tenant_maintenance_requests)) {
                foreach ($tenant_maintenance_requests as $maintenance_row) {
                    $maintenance_row->payable_invoice = !empty($maintenance_row->payable_invoice_id) ? (object) array(
                        'id' => absint($maintenance_row->payable_invoice_id),
                        'amount' => (float) $maintenance_row->payable_invoice_amount,
                        'status' => sanitize_key($maintenance_row->payable_invoice_status),
                        'due_date' => $maintenance_row->payable_invoice_due_date,
                    ) : null;
                    unset(
                        $maintenance_row->payable_invoice_id,
                        $maintenance_row->payable_invoice_amount,
                        $maintenance_row->payable_invoice_status,
                        $maintenance_row->payable_invoice_due_date
                    );
                }
            }

            if ($tenant_maintenance_requests === null && !empty($wpdb->last_error)) {
                error_log('[Mostaager] Rent dashboard maintenance query failed: ' . $wpdb->last_error);
                $tenant_maintenance_requests = array();
            }
        }

        if (function_exists('ms_get_wallet_transactions_for_user')) {
            $tenant_wallet_transactions = ms_get_wallet_transactions_for_user($user->ID, 20);
        } elseif (function_exists('ms_get_wallet_transactions')) {
            $tenant_wallet_transactions = ms_get_wallet_transactions($user->ID, 20);
        } else {
            $tx_table = $wpdb->prefix . 'ms_wallet_transactions';
            $wallet_sql = $wpdb->prepare(
                "SELECT * FROM {$tx_table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
                absint($user->ID),
                20
            );
            $tenant_wallet_transactions = $wpdb->get_results($wallet_sql);

            if ($tenant_wallet_transactions === null && !empty($wpdb->last_error)) {
                error_log('[Mostaager] Rent dashboard wallet query failed: ' . $wpdb->last_error);
                $tenant_wallet_transactions = array();
            }
        }
    }

    ?>

    <div class="ms-dashboard">

        <?php
        $ms_dashboard_menu_items = array(
            array('label' => 'نظرة عامة', 'data_tab' => 'overview', 'icon' => '📋', 'active' => true),
            array('label' => 'الفواتير', 'data_tab' => 'invoices', 'icon' => '🧾', 'badge' => $pending_count),
            array('label' => 'الصيانة', 'data_tab' => 'maintenance', 'icon' => '🛠️'),
            array('label' => 'المحفظة', 'data_tab' => 'wallet', 'icon' => '💳'),
            array('label' => 'التأمين', 'data_tab' => 'deposit', 'icon' => '🔒'),
            array('label' => 'المستندات', 'data_tab' => 'documents', 'icon' => '📁'),
            array('label' => 'العدادات', 'data_tab' => 'meters', 'icon' => '📟'),
            array('label' => 'المناقشات', 'data_tab' => 'discussions', 'icon' => '💬'),
            array('label' => 'البروفايل', 'data_tab' => 'profile', 'icon' => '👤'),
            array('href' => wp_logout_url(), 'label' => 'تسجيل الخروج', 'external' => true, 'icon' => '🚪'),
        );
        ms_load_dashboard_sidebar($ms_dashboard_menu_items);
        ?>

        <main class="ms-content">

            <div class="ms-tab-content active" id="overview">
                <h1>مرحباً، <?php echo esc_html($user->display_name); ?></h1>
                <div class="ms-card ms-tenant-home-card" style="margin:16px 0;">
                    <h3 style="margin-top:0;">العقار المستأجر</h3>
                    <div class="ms-grid" style="margin-top:12px;">
                        <div><span style="display:block;color:#64748b;font-size:13px;">اسم العقار / الوحدة</span><strong><?php echo esc_html($tenant_property_name); ?></strong></div>
                        <div><span style="display:block;color:#64748b;font-size:13px;">المبنى</span><strong><?php echo esc_html($tenant_building_name); ?></strong></div>
                        <div><span style="display:block;color:#64748b;font-size:13px;">رقم الوحدة</span><strong><?php echo esc_html($tenant_unit_label); ?></strong></div>
                    </div>
                </div>
                <div class="ms-grid">
                    <div class="ms-card"><h3>الإيجار القادم</h3><div id="ms-next-rent" class="ms-number"><?php echo $next_due ? 'ج.م ' . number_format_i18n($next_due->amount, 2) : '—'; ?></div>
                        <div style="margin-top:8px;font-size:13px;color:#666">تاريخ الاستحقاق: <span id="ms-next-due-date"><?php echo $next_due ? esc_html($next_due->due_date) : '—'; ?></span></div>
                    </div>
                    <div class="ms-card"><h3>رصيد المحفظة</h3><div id="ms-rent-wallet" class="ms-number"><?php echo 'ج.م ' . number_format_i18n($wallet, 2); ?></div></div>
                    <div class="ms-card"><h3>عدد الفواتير</h3><div id="ms-rent-invoices" class="ms-number"><?php echo intval(count($invoices)); ?></div>
                        <div style="margin-top:8px;font-size:13px;color:#666">فواتير الإيجار: <span id="ms-rent-count"><?php echo intval(count($rent_invoices)); ?></span></div>
                        <div style="margin-top:8px;font-size:13px;color:#666">فواتير الصيانة: <span id="ms-maintenance-count"><?php echo intval(count($maintenance_invoices)); ?></span></div>
                        <div style="margin-top:8px;font-size:13px;color:#666">معلقة: <span id="ms-rent-pending"><?php echo intval($pending_count); ?></span></div>
                        <div style="margin-top:8px;font-size:13px;color:#666">متأخرة: <span id="ms-rent-overdue"><?php echo intval($overdue_count); ?></span></div>
                    </div>
                </div>
                <?php echo do_shortcode('[rent_streak_badge]'); ?>
                <?php if (!empty($notifications)) : ?>
                    <div class="ms-card" style="margin-top:16px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                            <h3 style="margin:0;">الإشعارات الأخيرة</h3>
                            <?php if ($unread_notifications_count > 0) : ?>
                                <span style="background:#ef4444;color:#fff;padding:3px 10px;border-radius:999px;font-size:0.78rem;font-weight:700;"><?php echo intval($unread_notifications_count); ?> غير مقروء</span>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;flex-direction:column;gap:10px;">
                            <?php foreach (array_slice($notifications, 0, 5) as $note) : ?>
                                <div style="padding:14px 16px;border-radius:12px;background:<?php echo !empty($note->is_read) ? '#f9fafb' : '#eff6ff'; ?>;border:1px solid <?php echo !empty($note->is_read) ? '#e5e7eb' : '#bfdbfe'; ?>;">
                                    <div style="font-size:14px;color:#111;line-height:1.6;"><?php echo esc_html($note->message ?? ''); ?></div>
                                    <div style="margin-top:6px;font-size:12px;color:#6b7280;"><?php echo esc_html(date_i18n('Y-m-d H:i', strtotime($note->created_at ?? $note->created_on ?? ''))); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="ms-tab-content" id="invoices">
                <div class="ms-card">
                    <h3>فواتيري</h3>
                    <?php if(!empty($invoices)): ?>
                        <div class="ms-invoice-subtabs" style="margin-top:18px;display:flex;gap:8px;flex-wrap:wrap;">
                            <button class="ms-invoice-subtab active" data-subtab="invoices-property" style="padding:10px 20px;border-radius:999px;border:1px solid #e5e7eb;background:#2563eb;color:#fff;cursor:pointer;font-weight:600;">فواتير العقار (إيجار)</button>
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
                                                } elseif (isset($inv->property_id) && $inv->property_id) {
                                                    $property_post = get_post($inv->property_id);
                                                    if ($property_post) {
                                                        $building_name = $property_post->post_title;
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
                                                } elseif (isset($inv->property_id) && $inv->property_id) {
                                                    $property_post = get_post($inv->property_id);
                                                    if ($property_post) {
                                                        $building_name = $property_post->post_title;
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
                <div class="ms-card ms-tenant-maintenance-card"><h3>طلبات الصيانة</h3>
                    <?php if ($tenant_building_id && !empty($tenant_maintenance_requests)) : ?>
                        <div class="ms-tenant-maintenance-filters" role="tablist" aria-label="تصفية طلبات الصيانة">
                            <button type="button" class="ms-maintenance-filter active" data-status="all">الكل</button>
                            <button type="button" class="ms-maintenance-filter" data-status="active">نشطة</button>
                            <button type="button" class="ms-maintenance-filter" data-status="completed">منتهية</button>
                            <button type="button" class="ms-maintenance-filter" data-status="cancelled">ملغية</button>
                        </div>
                        <div style="overflow-x:auto;margin-top:10px;">
                            <table class="ms-tenant-maintenance-table" style="width:100%;border-collapse:collapse;min-width:760px;">
                                <thead>
                                    <tr style="background:#f3f4f6;">
                                        <th style="padding:12px;text-align:right;border-bottom:2px solid #e5e7eb;">العنوان</th>
                                        <th style="padding:12px;text-align:right;border-bottom:2px solid #e5e7eb;">الحالة</th>
                                        <th style="padding:12px;text-align:right;border-bottom:2px solid #e5e7eb;">الأولوية</th>
                                        <th style="padding:12px;text-align:right;border-bottom:2px solid #e5e7eb;">التكلفة</th>
                                        <th style="padding:12px;text-align:right;border-bottom:2px solid #e5e7eb;">التاريخ</th>
                                        <th style="padding:12px;text-align:right;border-bottom:2px solid #e5e7eb;">الإجراء</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tenant_maintenance_requests as $r) : ?>
                                        <?php
                                        $raw_status = strtolower(sanitize_key($r->status ?? ''));
                                        $status_group = in_array($raw_status, array('completed', 'closed', 'done'), true) ? 'completed' : (in_array($raw_status, array('cancelled', 'canceled'), true) ? 'cancelled' : 'active');
                                        $status_labels = array('completed' => 'منتهية', 'cancelled' => 'ملغية', 'active' => 'نشطة');
                                        $maintenance_invoice = is_object($r->payable_invoice ?? null) ? $r->payable_invoice : null;
                                        $invoice_status = strtolower((string) ($maintenance_invoice->status ?? ''));
                                        ?>
                                        <tr class="ms-tenant-maintenance-row" data-status="<?php echo esc_attr($status_group); ?>" style="border-bottom:1px solid #e5e7eb;">
                                            <td style="padding:12px;"><?php echo esc_html($r->title ?? 'بدون عنوان'); ?></td>
                                            <td style="padding:12px;"><span class="ms-maintenance-status ms-status-<?php echo esc_attr($status_group); ?>"><?php echo esc_html($status_labels[$status_group]); ?></span></td>
                                            <td style="padding:12px;"><?php echo esc_html($r->priority ?? 'متوسط'); ?></td>
                                            <td style="padding:12px;">ج.م <?php echo isset($r->cost) ? number_format_i18n(floatval($r->cost),2) : '0.00'; ?></td>
                                            <td style="padding:12px;"><?php echo esc_html($r->created_at ?? $r->created_on ?? '—'); ?></td>
                                            <td style="padding:12px;">
                                                <?php if ($maintenance_invoice && !in_array($invoice_status, array('paid','cancelled','canceled'), true)) : ?>
                                                    <button type="button" class="ms-tenant-pay-maintenance" data-request-id="<?php echo intval($r->id); ?>" data-amount="<?php echo esc_attr((float) $maintenance_invoice->amount); ?>">دفع ج.م <?php echo esc_html(number_format_i18n((float) $maintenance_invoice->amount, 2)); ?></button>
                                                <?php elseif ($maintenance_invoice && $invoice_status === 'paid') : ?>
                                                    <span class="ms-maintenance-paid">مدفوعة</span>
                                                <?php else : ?>
                                                    <span style="color:#94a3b8;">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php elseif ($tenant_building_id) : ?>
                        <p style="padding:12px;">لا توجد طلبات صيانة مرتبطة بوحدتك حاليًا.</p>
                    <?php else : ?>
                        <p style="padding:12px;">لم يتم ربط حسابك بوحدة أو عقد نشط بعد.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ms-tab-content" id="wallet">
                <div class="ms-card"><h3>محفظتي</h3>
                    <div style="margin-top:8px;font-size:16px;font-weight:700">الرصيد: <?php echo 'ج.م ' . number_format_i18n($wallet,2); ?></div>
                    <div style="margin-top:16px;">
                        <button id="ms-recharge-wallet-btn" style="padding:12px 24px;background:#2563eb;color:#fff;border:0;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600;">شحن المحفظة</button>
                    </div>
                    <?php if (!empty($tenant_wallet_transactions)) : ?>
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
                                    <?php foreach ($tenant_wallet_transactions as $t) : ?>
                                        <tr>
                                            <td><?php echo esc_html($t->description ?? $t->note ?? 'معاملة'); ?></td>
                                            <td>ج.م <?php echo isset($t->amount) ? number_format_i18n(floatval($t->amount),2) : '0.00'; ?></td>
                                            <td><?php echo esc_html($t->type ?? '—'); ?></td>
                                            <td><?php echo esc_html($t->created_at ?? $t->date ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="padding:12px;">لا توجد معاملات محفظة حديثة.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ms-tab-content" id="deposit">
                <div class="ms-card">
                    <h3>تأمين الأمانة</h3>
                    <?php 
                    // Get tenant's security deposit
                    $security_deposit = null;
                    if ($tenant_unit_id && function_exists('ms_get_security_deposit_by_lease')) {
                        // Try to find deposit by unit (using unit_id as lease_id if no lease table exists)
                        $security_deposit = ms_get_security_deposit_by_lease($tenant_unit_id);
                    }
                    
                    if (!$security_deposit) {
                        // Check if tenant has an active lease
                        if ($tenant_unit_id) {
                            ?>
                            <div style="margin-top:20px;padding:20px;background:#f0f9ff;border:1px solid #bfdbfe;border-radius:12px;">
                                <h4 style="margin-top:0;margin-bottom:12px;">دفع تأمين الأمانة</h4>
                                <p style="margin-bottom:16px;color:#475569;">يجب دفع تأمين أمانة يعادل إيجار شهرين قبل بدء عقد الإيجار. هذا المبلغ سيُجمد في المحفظة وسيُرجع لك عند انتهاء العقد بعد فحص العقار.</p>
                                
                                <?php
                                // Calculate deposit amount (2 months rent)
                                $monthly_rent = 0;
                                if ($next_due) {
                                    $monthly_rent = floatval($next_due->amount);
                                }
                                $deposit_amount = $monthly_rent * 2;
                                
                                if ($deposit_amount > 0) {
                                    ?>
                                    <div style="margin-bottom:16px;padding:16px;background:#fff;border-radius:8px;border:1px solid #e5e7eb;">
                                        <div style="font-size:14px;color:#6b7280;margin-bottom:4px;">مبلغ التأمين المطلوب</div>
                                        <div style="font-size:24px;font-weight:700;color:#0f172a;">ج.م <?php echo number_format_i18n($deposit_amount, 2); ?></div>
                                    </div>
                                    
                                    <?php if ($wallet >= $deposit_amount) : ?>
                                        <form id="ms-pay-deposit-form" method="post" style="margin-top:16px;">
                                            <?php wp_nonce_field('ms_pay_deposit', 'ms_deposit_nonce'); ?>
                                            <input type="hidden" name="action" value="ms_pay_deposit">
                                            <input type="hidden" name="unit_id" value="<?php echo intval($tenant_unit_id); ?>">
                                            <input type="hidden" name="building_id" value="<?php echo intval($tenant_building_id); ?>">
                                            <input type="hidden" name="amount" value="<?php echo floatval($deposit_amount); ?>">
                                            <button type="submit" style="padding:12px 24px;background:#10b981;color:#fff;border:0;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600;">دفع التأمين الآن</button>
                                        </form>
                                    <?php else : ?>
                                        <div style="margin-top:16px;padding:12px;background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;">
                                            <p style="margin:0;color:#92400e;">رصيد محفظتك غير كافٍ. يرجى شحن المحفظة أولاً.</p>
                                            <p style="margin:8px 0 0 0;color:#92400e;">الرصيد الحالي: ج.م <?php echo number_format_i18n($wallet, 2); ?></p>
                                        </div>
                                    <?php endif; ?>
                                <?php } else { ?>
                                    <p style="color:#6b7280;">لم يتم تحديد مبلغ الإيجار الشهري بعد.</p>
                                <?php } ?>
                            </div>
                            <?php
                        } else {
                            echo '<p style="color:#6b7280;">لم يتم العثور على وحدة مرتبطة بحسابك.</p>';
                        }
                    } else {
                        // Display existing deposit status
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
                        $status_label = isset($status_labels[$security_deposit->status]) ? $status_labels[$security_deposit->status] : $security_deposit->status;
                        $status_color = isset($status_colors[$security_deposit->status]) ? $status_colors[$security_deposit->status] : '#6b7280';
                        ?>
                        <div style="margin-top:20px;">
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:20px;">
                                <div style="padding:16px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;">
                                    <div style="font-size:13px;color:#64748b;margin-bottom:4px;">مبلغ التأمين</div>
                                    <div style="font-size:20px;font-weight:700;color:#0f172a;">ج.م <?php echo number_format_i18n($security_deposit->amount, 2); ?></div>
                                </div>
                                <div style="padding:16px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;">
                                    <div style="font-size:13px;color:#64748b;margin-bottom:4px;">الحالة</div>
                                    <div style="font-size:20px;font-weight:700;color:<?php echo $status_color; ?>;"><?php echo $status_label; ?></div>
                                </div>
                                <?php if ($security_deposit->status === 'frozen') : ?>
                                    <div style="padding:16px;background:#eff6ff;border-radius:8px;border:1px solid #bfdbfe;">
                                        <div style="font-size:13px;color:#64748b;margin-bottom:4px;">تاريخ التجميد</div>
                                        <div style="font-size:16px;font-weight:600;color:#1e40af;"><?php echo esc_html($security_deposit->frozen_at); ?></div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($security_deposit->status === 'released') : ?>
                                    <div style="padding:16px;background:#f0fdf4;border-radius:8px;border:1px solid #bbf7d0;">
                                        <div style="font-size:13px;color:#64748b;margin-bottom:4px;">تاريخ الإطلاق</div>
                                        <div style="font-size:16px;font-weight:600;color:#166534;"><?php echo esc_html($security_deposit->released_at); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($security_deposit->status === 'released' && $security_deposit->deduction_amount > 0) : ?>
                                <div style="margin-top:20px;padding:16px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;">
                                    <h4 style="margin-top:0;margin-bottom:12px;color:#991b1b;">الخصومات من التأمين</h4>
                                    <div style="margin-bottom:8px;">
                                        <span style="color:#6b7280;">المبلغ المخصوم:</span>
                                        <strong style="color:#991b1b;"> ج.م <?php echo number_format_i18n($security_deposit->deduction_amount, 2); ?></strong>
                                    </div>
                                    <?php if ($security_deposit->deduction_reason) : ?>
                                        <div style="margin-bottom:8px;">
                                            <span style="color:#6b7280;">السبب:</span>
                                            <span style="color:#7f1d1d;"><?php echo esc_html($security_deposit->deduction_reason); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div style="margin-bottom:8px;">
                                        <span style="color:#6b7280;">المبلغ المُرجع:</span>
                                        <strong style="color:#166534;"> ج.م <?php echo number_format_i18n($security_deposit->amount - $security_deposit->deduction_amount, 2); ?></strong>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($security_deposit->status === 'frozen') : ?>
                                <div style="margin-top:20px;padding:16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;">
                                    <p style="margin:0;color:#166534;">✓ تأمين الأمانة مجمد في محفظتك. سيتم إطلاقه عند انتهاء عقد الإيجار بعد فحص العقار من قبل المالك.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>

            <div class="ms-tab-content" id="documents">
                <?php echo function_exists('msfp_render_documents_center') ? msfp_render_documents_center($user->ID) : '<div class="ms-card"><h3>المستندات</h3><p>وحدة المستندات غير محملة.</p></div>'; ?>
            </div>

            <div class="ms-tab-content" id="meters">
                <?php echo function_exists('msfp_render_meter_readings') ? msfp_render_meter_readings($tenant_unit_id) : '<div class="ms-card"><h3>العدادات</h3><p>وحدة قراءات العدادات غير محملة.</p></div>'; ?>
            </div>

            <div class="ms-tab-content" id="discussions">
                <div class="ms-card">
                    <h3>المناقشات</h3>
                    <?php 
                    // Get building name
                    $building_name = '';
                    if ($tenant_building_id) {
                        global $wpdb;
                        $buildings_table = $wpdb->prefix . 'ms_buildings';
                        $building = $wpdb->get_row($wpdb->prepare("SELECT * FROM $buildings_table WHERE id = %d LIMIT 1", $tenant_building_id));
                        if ($building) {
                            $building_name = $building->title ?? '';
                        }
                    }
                    
                    // Get apartment/unit name
                    $apartment_name = '';
                    if ($tenant_unit_id) {
                        $apartment = get_post($tenant_unit_id);
                        if ($apartment) {
                            $apartment_name = $apartment->post_title ?? '';
                        }
                    }
                    
                    if (!$tenant_building_id): ?>
                        <p style="color:#666;margin-top:8px">لم يتم العثور على معرف مبنى صالح مرتبط بوحدتك. لا يمكن تحميل مواضيع المناقشات.</p>
                    <?php else: ?>
                        <div style="margin-bottom:16px;padding:12px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;">
                            <div style="font-size:13px;color:#64748b;">المبنى:</div>
                            <div style="font-size:16px;font-weight:600;color:#0f172a;"><?php echo esc_html($building_name ?: 'غير محدد'); ?></div>
                            <?php if ($apartment_name): ?>
                                <div style="font-size:13px;color:#647487;margin-top:8px;">الشقة:</div>
                                <div style="font-size:16px;font-weight:600;color:#0f172a;"><?php echo esc_html($apartment_name); ?></div>
                            <?php endif; ?>
                        </div>
                        <div id="tenant-discussions" data-building-id="<?php echo intval($tenant_building_id); ?>">
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

        // Security deposit payment
        var depositForm = document.getElementById('ms-pay-deposit-form');
        if (depositForm) {
            depositForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                var formData = new FormData(this);
                
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.success) {
                        alert(data.data.message || 'تم دفع التأمين بنجاح');
                        location.reload();
                    } else {
                        alert(data.data && data.data.message ? data.data.message : 'حدث خطأ في معالجة الطلب');
                    }
                })
                .catch(function(error) {
                    console.error('Error:', error);
                    alert('حدث خطأ في الاتصال');
                });
            });
        }

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
