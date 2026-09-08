<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', function () {
    $cap = 'manage_options';
    // Removed main Mostaager menu page - duplicate functionality
    // add_menu_page('Mostaager', 'Mostaager', $cap, 'mostaager-admin', function () {
    //     echo '<div class="wrap"><h1>Mostaager</h1><p>Manage Mostaager data tables.</p></div>';
    // }, 'dashicons-building', 58);

    // Removed duplicate pages - these exist in separate tabs
    // add_submenu_page('mostaager-admin', 'Buildings', 'Buildings', $cap, 'mostaager-buildings', 'ms_admin_buildings_page');
    // add_submenu_page('mostaager-admin', 'Units', 'Units', $cap, 'mostaager-units', 'ms_admin_units_page');
    // add_submenu_page('mostaager-admin', 'Invoices', 'Invoices', $cap, 'mostaager-invoices', 'ms_admin_invoices_page');
    // add_submenu_page('mostaager-admin', 'Maintenance', 'Maintenance', $cap, 'mostaager-maintenance', 'ms_admin_maintenance_page');
    // add_submenu_page('mostaager-admin', 'Migrations', 'Migrations', $cap, 'mostaager-migrations', 'ms_admin_migrations_page');

    // New grouped pages per plan (separate handlers to avoid conflict with legacy pages)
    // add_submenu_page('mostaager-admin', 'المستأجرون', 'المستأجرون', $cap, 'mostaager-tenants', 'ms_admin_tenants_page');
    // add_submenu_page('mostaager-admin', 'الفواتير (MS)', 'الفواتير', $cap, 'mostaager-ms-invoices', 'ms_admin_ms_invoices_page');
    // add_submenu_page('mostaager-admin', 'التحويلات (MS)', 'التحويلات', $cap, 'mostaager-ms-transfers', 'ms_admin_ms_transfers_page');

    // Removed duplicate menu pages - commented out
    // add_menu_page('فواتير المرافق', 'فواتير المرافق', $cap, 'mostaager-all-invoices', 'ms_admin_all_invoices_page', 'dashicons-media-spreadsheet', 30);
    // add_submenu_page('mostaager-all-invoices', 'طلبات التحويل', 'طلبات التحويل', $cap, 'mostaager-transfers', 'ms_admin_transfers_page');

    // Wallet report page (plan A5)
    add_menu_page('تقرير المحفظة', 'تقرير المحفظة', $cap, 'mostaager-wallet-report', 'ms_admin_wallet_report_page', 'dashicons-chart-pie', 31);

    // Scenario demo runner page
    add_menu_page('سيناريو التشغيل', 'سيناريو التشغيل', $cap, 'mostaager-scenario-runner', 'ms_admin_scenario_runner_page', 'dashicons-play', 32);

    // Services overview page
    add_menu_page('خدمات الإضافة', 'خدمات الإضافة', $cap, 'mostaager-services', 'ms_admin_services_page', 'dashicons-list', 34);

    // New feature pages
    add_menu_page('التوقيع الإلكتروني', 'التوقيع الإلكتروني', $cap, 'msfp-esignature', 'msfp_admin_esignature_page', 'dashicons-sticky', 35);
    add_menu_page('الأتمتة الذكية', 'الأتمتة الذكية', $cap, 'msfp-smart-automation', 'msfp_admin_smart_automation_page', 'dashicons-rocket', 36);
    add_menu_page('واتساب', 'واتساب', $cap, 'mostager-whatsapp', 'ms_admin_whatsapp_page', 'dashicons-whatsapp', 37);
});

// Retire the legacy reports and import management screens.
add_action('admin_menu', function () {
    foreach (array(
        'ms-houzez-reports',
        'ms-import-dashboard',
        'ms-import-new',
        'ms-import-templates',
        'ms-import-logs',
        'ms-import-settings',
        'ms-import-automation',
        'ms-import-monitoring',
        'ms-import-reports',
        'mostaager-import-data',
    ) as $page_slug) {
        remove_menu_page($page_slug);
        remove_submenu_page('ms-import-dashboard', $page_slug);
    }
}, 999);

// Handler functions for new feature pages
function msfp_admin_esignature_page()
{
    if (!current_user_can('manage_options')) {
        wp_die('غير مصرح لك بالوصول إلى هذه الصفحة.');
    }

    $notice = '';
    if (isset($_POST['msfp_signed_contract_upload'], $_POST['property_id']) && check_admin_referer('msfp_signed_contract_upload')) {
        $property_id = absint($_POST['property_id']);
        $contract_type = sanitize_key($_POST['contract_type'] ?? '');
        $file = $_FILES['signed_contract_file'] ?? array();
        $allowed_types = array('rent', 'sale');
        $allowed_extensions = array('pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx');
        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));

        if (!$property_id || !in_array($contract_type, $allowed_types, true) || empty($file['name'])) {
            $notice = '<div class="notice notice-error"><p>يرجى اختيار العقار ونوع العقد ورفع الملف.</p></div>';
        } elseif (!in_array($extension, $allowed_extensions, true) || intval($file['size'] ?? 0) > 10 * 1024 * 1024) {
            $notice = '<div class="notice notice-error"><p>نوع الملف غير مدعوم أو يتجاوز حجمه 10MB.</p></div>';
        } else {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $attachment_id = media_handle_upload('signed_contract_file', $property_id, array(), array('test_form' => false));
            if (is_wp_error($attachment_id)) {
                $notice = '<div class="notice notice-error"><p>' . esc_html($attachment_id->get_error_message()) . '</p></div>';
            } else {
                $signed_url = wp_get_attachment_url($attachment_id);
                $meta_prefix = $contract_type === 'sale' ? 'ms_sale_contract' : 'ms_rent_contract';
                update_post_meta($property_id, 'ms_property_contract_original_url', get_post_meta($property_id, 'ms_property_contract_url', true));
                update_post_meta($property_id, 'ms_property_contract_url', $signed_url);
                update_post_meta($property_id, $meta_prefix . '_url', $signed_url);
                update_post_meta($property_id, 'ms_property_contract_signed_file_id', $attachment_id);
                update_post_meta($property_id, 'ms_property_contract_signature_status', 'signed_by_both');
                update_post_meta($property_id, 'ms_property_contract_signed_at', current_time('mysql'));
                update_post_meta($property_id, 'ms_property_contract_type', $contract_type);
                $notice = '<div class="notice notice-success"><p>تم حفظ النسخة الموقعة من الطرفين بنجاح.</p></div>';
            }
        }
    }

    $contracts = get_posts(array(
        'post_type' => 'property',
        'post_status' => array('publish', 'pending', 'draft', 'private'),
        'posts_per_page' => 200,
        'meta_query' => array(
            array('key' => 'ms_property_contract_url', 'compare' => 'EXISTS'),
        ),
        'orderby' => 'modified',
        'order' => 'DESC',
    ));

    echo '<div class="wrap" dir="rtl"><h1>التوقيع الإلكتروني للعقود</h1>';
    echo '<p>ارفع هنا النسخة النهائية بعد توقيع عقد الإيجار أو البيع من الطرفين. يبقى العقد الأصلي محفوظًا، وتُسجل النسخة الموقعة وتاريخ اعتمادها.</p>' . $notice;
    echo '<table class="widefat fixed striped"><thead><tr><th>العقار</th><th>نوع العقد</th><th>الحالة</th><th>الملف الحالي</th><th>رفع النسخة الموقعة</th></tr></thead><tbody>';
    if (empty($contracts)) {
        echo '<tr><td colspan="5">لا توجد عقود مرفوعة حاليًا.</td></tr>';
    }
    foreach ($contracts as $property) {
        $type = sanitize_key(get_post_meta($property->ID, 'ms_property_contract_type', true));
        $status = sanitize_key(get_post_meta($property->ID, 'ms_property_contract_signature_status', true));
        $label = $type === 'sale' ? 'بيع وشراء' : 'إيجار';
        $status_label = $status === 'signed_by_both' ? 'موقع من الطرفين' : 'بانتظار توقيع الطرفين';
        $url = get_post_meta($property->ID, 'ms_property_contract_url', true);
        echo '<tr><td>' . esc_html($property->post_title) . '</td><td>' . esc_html($label) . '</td><td>' . esc_html($status_label) . '</td><td>';
        if ($url) { echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">فتح العقد</a>'; } else { echo '—'; }
        echo '</td><td><form method="post" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">';
        wp_nonce_field('msfp_signed_contract_upload');
        echo '<input type="hidden" name="msfp_signed_contract_upload" value="1"><input type="hidden" name="property_id" value="' . absint($property->ID) . '"><select name="contract_type"><option value="rent"' . selected($type, 'rent', false) . '>إيجار</option><option value="sale"' . selected($type, 'sale', false) . '>بيع وشراء</option></select><input type="file" name="signed_contract_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required><button class="button button-primary">اعتماد النسخة الموقعة</button></form></td></tr>';
    }
    echo '</tbody></table></div>';
}

function msfp_admin_smart_automation_page()
{
    echo '<div class="wrap"><h1>الأتمتة الذكية</h1><p>ميزة الأتمتة الذكية قيد التطوير.</p></div>';
}

function ms_admin_whatsapp_page()
{
    if (!current_user_can('manage_options')) { wp_die('غير مصرح لك بالوصول إلى هذه الصفحة.'); }
    $defaults = array(
        'test_mode' => 1, 'enabled' => 0, 'provider' => 'meta_cloud', 'phone' => '',
        'maintenance' => 1, 'rent_reminder' => 1, 'transfer' => 1, 'subscription' => 1,
        'quiet_hours' => '22:00-07:00', 'last_test' => '',
    );
    $settings = wp_parse_args((array)get_option('ms_whatsapp_settings', array()), $defaults);
    $notice = '';
    if (isset($_POST['ms_whatsapp_save']) && check_admin_referer('ms_whatsapp_settings')) {
        $settings['test_mode'] = !empty($_POST['test_mode']) ? 1 : 0;
        $settings['enabled'] = !empty($_POST['enabled']) ? 1 : 0;
        $settings['provider'] = sanitize_key($_POST['provider'] ?? 'meta_cloud');
        $settings['phone'] = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
        foreach (array('maintenance','rent_reminder','transfer','subscription') as $key) { $settings[$key] = !empty($_POST[$key]) ? 1 : 0; }
        $settings['quiet_hours'] = sanitize_text_field(wp_unslash($_POST['quiet_hours'] ?? '22:00-07:00'));
        update_option('ms_whatsapp_settings', $settings, false);
        $notice = '<div class="notice notice-success"><p>تم حفظ إعدادات WhatsApp. وضع التجربة لا يرسل رسالة حقيقية.</p></div>';
    }
    if (isset($_POST['ms_whatsapp_preview']) && check_admin_referer('ms_whatsapp_settings')) {
        $event = sanitize_key($_POST['preview_event'] ?? 'maintenance');
        $templates = array(
            'maintenance' => "مرحبًا {اسم المستأجر}، تم تحديث طلب الصيانة #{رقم الطلب} إلى الحالة: {الحالة}. التفاصيل: {الرابط الآمن}",
            'rent_reminder' => "تذكير: موعد دفع الإيجار القادم للعقار {العقار} هو {التاريخ}. المبلغ: {المبلغ}.",
            'transfer' => "تم تقديم طلب تحويل مبلغ الصيانة للمبنى {المبنى} بقيمة {المبلغ}، وحالته الآن قيد المراجعة.",
            'subscription' => "تم تحديث اشتراكك إلى باقة {الباقة}. تاريخ الانتهاء: {تاريخ الانتهاء}.",
        );
        $settings['last_test'] = current_time('mysql') . ' — ' . ($templates[$event] ?? $templates['maintenance']);
        update_option('ms_whatsapp_settings', $settings, false);
        $notice = '<div class="notice notice-info"><p>تم إنشاء معاينة تجريبية فقط. لم يتم الاتصال بواجهة WhatsApp ولم تُرسل رسالة.</p></div>';
    }
    $settings = wp_parse_args((array)get_option('ms_whatsapp_settings', array()), $defaults);
    $templates = array('maintenance'=>'تغيير حالة الصيانة','rent_reminder'=>'تذكير الإيجار قبل 7 أيام','transfer'=>'طلب تحويل مبلغ صيانة','subscription'=>'تفعيل أو تجديد الاشتراك');
    echo '<div class="wrap" dir="rtl"><h1>إعدادات WhatsApp — وضع تجريبي</h1>'.$notice;
    echo '<div style="background:#fff8e1;border-right:4px solid #dba617;padding:14px;margin:16px 0"><strong>الوضع الآمن مفعّل:</strong> المعاينة وتسجيل الأحداث لا يرسلان أي رسالة حقيقية. لا تفعل الإرسال الحقيقي إلا بعد إدخال بيانات Meta أو Twilio واختبارها على رقم مخصص.</div>';
    echo '<form method="post">'.wp_nonce_field('ms_whatsapp_settings').'<table class="form-table"><tr><th>وضع التجربة</th><td><label><input type="checkbox" name="test_mode" value="1" '.checked($settings['test_mode'],1,false).'> معاينة فقط دون إرسال</label></td></tr><tr><th>الإرسال الحقيقي</th><td><label><input type="checkbox" name="enabled" value="1" '.checked($settings['enabled'],1,false).'> السماح بالإرسال بعد إعداد المزود</label></td></tr><tr><th>المزود</th><td><select name="provider"><option value="meta_cloud" '.selected($settings['provider'],'meta_cloud',false).'>Meta WhatsApp Cloud API</option><option value="twilio" '.selected($settings['provider'],'twilio',false).'>Twilio WhatsApp</option></select></td></tr><tr><th>رقم الاختبار</th><td><input class="regular-text" name="phone" value="'.esc_attr($settings['phone']).'" placeholder="+966..."><p class="description">يُستخدم للتوثيق فقط في وضع التجربة.</p></td></tr><tr><th>ساعات عدم الإرسال</th><td><input class="regular-text" name="quiet_hours" value="'.esc_attr($settings['quiet_hours']).'"><p class="description">مثال: 22:00-07:00. لا تُرسل التنبيهات غير العاجلة خلالها.</p></td></tr></table><h2>الأحداث المفعّلة</h2><p><label><input type="checkbox" name="maintenance" value="1" '.checked($settings['maintenance'],1,false).'> تغيير حالة طلب الصيانة</label> &nbsp; <label><input type="checkbox" name="rent_reminder" value="1" '.checked($settings['rent_reminder'],1,false).'> تذكير الإيجار قبل 7 أيام</label> &nbsp; <label><input type="checkbox" name="transfer" value="1" '.checked($settings['transfer'],1,false).'> قبول أو رفض طلب التحويل</label> &nbsp; <label><input type="checkbox" name="subscription" value="1" '.checked($settings['subscription'],1,false).'> تفعيل أو تجديد الاشتراك</label></p><p><button class="button button-primary" name="ms_whatsapp_save" value="1">حفظ الإعدادات</button></p></form>';
    echo '<hr><h2>معاينة الإشعارات وتوقيت الإرسال</h2><table class="widefat striped"><thead><tr><th>الحدث</th><th>متى يتم الإرسال</th><th>مثال الرسالة</th></tr></thead><tbody><tr><td>تغيير الصيانة</td><td>بعد تغيير الحالة مباشرة، إذا كانت القناة مفعّلة</td><td>مرحبًا {اسم المستأجر}، تم تحديث طلب الصيانة #{رقم الطلب} إلى الحالة {الحالة}.</td></tr><tr><td>تذكير الإيجار</td><td>قبل موعد الاستحقاق بـ7 أيام، مرة واحدة لكل فاتورة</td><td>تذكير: موعد دفع الإيجار القادم هو {التاريخ} والمبلغ {المبلغ}.</td></tr><tr><td>التحويل</td><td>عند تقديم الطلب ثم عند القبول أو الرفض</td><td>حالة طلب تحويل مبلغ الصيانة: {الحالة}.</td></tr><tr><td>الاشتراك</td><td>بعد Processing أو Completed أو التجديد</td><td>تم تحديث اشتراكك إلى باقة {الباقة}.</td></tr></tbody></table><form method="post" style="margin-top:16px">'.wp_nonce_field('ms_whatsapp_settings').'<select name="preview_event">';foreach($templates as $key=>$label)echo '<option value="'.esc_attr($key).'">'.esc_html($label).'</option>';echo '</select> <button class="button" name="ms_whatsapp_preview" value="1">محاكاة إشعار الآن</button></form>';
    if (!empty($settings['last_test'])) echo '<p><strong>آخر محاكاة:</strong> '.esc_html($settings['last_test']).'</p>';
    echo '</div>';
}

function ms_admin_scenario_runner_page()
{
    if (!current_user_can('manage_options')) {
        wp_die('غير مصرح لك بالوصول إلى هذه الصفحة.');
    }

    $message = '';
    $details = array();

    if (isset($_POST['ms_run_scenario']) && check_admin_referer('ms_run_scenario')) {
        $result = ms_run_role_scenario_demo();
        $message = $result['message'];
        $details = $result['details'];
    } elseif (isset($_POST['ms_reset_scenario']) && check_admin_referer('ms_reset_scenario')) {
        $result = ms_reset_role_scenario_demo();
        $message = $result['message'];
        $details = $result['details'];
    }

    echo '<div class="wrap">';
    echo '<h1>سيناريو التشغيل المتكامل</h1>';
    echo '<p>يقوم هذا المشغل بإنشاء بيانات حقيقية عبر مالك، مستأجر، وسيط، ومدير مبنى، ثم يربطها بالوحدات والفواتير والصيانة والمحفظة.</p>';

    if ($message) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    if (!empty($details)) {
        echo '<ul>';
        foreach ($details as $line) {
            echo '<li>' . esc_html($line) . '</li>';
        }
        echo '</ul>';
    }

    echo '<form method="post">';
    wp_nonce_field('ms_run_scenario');
    echo '<input type="hidden" name="ms_run_scenario" value="1">';
    echo '<p><button type="submit" class="button button-primary" onclick="return confirm(\'سيؤدي هذا إلى إنشاء بيانات تجريبية جديدة. هل تريد المتابعة؟\')">تشغيل السيناريو</button></p>';
    echo '</form>';

    echo '<form method="post" style="margin-top:8px;">';
    wp_nonce_field('ms_reset_scenario');
    echo '<input type="hidden" name="ms_reset_scenario" value="1">';
    echo '<p><button type="submit" class="button button-secondary" onclick="return confirm(\'سيؤدي هذا إلى حذف بيانات السيناريو التجريبية الحالية. هل تريد المتابعة؟\')">إعادة تعيين السيناريو</button></p>';
    echo '</form>';

    echo '<h2>ما الذي سينشئه المشغل</h2>';
    echo '<ul><li>مستخدمين بأدوار مالك، مستأجر، وسيط، ومدير مبنى</li><li>مبنى ووحدات مرتبطة به</li><li>ربط مستأجر بالوحدة وإضافة فاتورة إيجار</li><li>إنشاء طلب صيانة وتحديثه</li><li>إضافة رصيد لمحفظة المستخدم وملاحظة مالية</li></ul>';
    echo '</div>';
}

function ms_run_role_scenario_demo()
{
    global $wpdb;

    $details = array();

    if (!function_exists('ms_get_or_create_user_wallet')) {
        return array('message' => 'تعذر تشغيل السيناريو لأن وظائف المحفظة غير متوفرة.', 'details' => array());
    }

    $owner_email = 'demo-owner-' . wp_generate_password(4, false) . '@example.com';
    $tenant_email = 'demo-tenant-' . wp_generate_password(4, false) . '@example.com';
    $agent_email = 'demo-agent-' . wp_generate_password(4, false) . '@example.com';
    $manager_email = 'demo-manager-' . wp_generate_password(4, false) . '@example.com';

    $owner_id = username_exists('demo_owner') ? get_user_by('login', 'demo_owner')->ID : wp_insert_user(array(
        'user_login' => 'demo_owner',
        'user_pass' => wp_generate_password(16),
        'user_email' => $owner_email,
        'display_name' => 'مالك تجريبي',
        'role' => 'owner',
    ));
    $tenant_id = username_exists('demo_tenant') ? get_user_by('login', 'demo_tenant')->ID : wp_insert_user(array(
        'user_login' => 'demo_tenant',
        'user_pass' => wp_generate_password(16),
        'user_email' => $tenant_email,
        'display_name' => 'مستأجر تجريبي',
        'role' => 'tenant',
    ));
    $agent_id = username_exists('demo_agent') ? get_user_by('login', 'demo_agent')->ID : wp_insert_user(array(
        'user_login' => 'demo_agent',
        'user_pass' => wp_generate_password(16),
        'user_email' => $agent_email,
        'display_name' => 'وسيط تجريبي',
        'role' => 'agent',
    ));
    $manager_id = username_exists('demo_manager') ? get_user_by('login', 'demo_manager')->ID : wp_insert_user(array(
        'user_login' => 'demo_manager',
        'user_pass' => wp_generate_password(16),
        'user_email' => $manager_email,
        'display_name' => 'مدير مبنى تجريبي',
        'role' => 'building_manager',
    ));

    foreach (array($owner_id, $tenant_id, $agent_id, $manager_id) as $user_id) {
        if (is_wp_error($user_id) || !$user_id) {
            continue;
        }
        wp_update_user(array('ID' => $user_id, 'role' => 'owner' === get_userdata($user_id)->roles[0] ? 'owner' : get_userdata($user_id)->roles[0]));
    }

    $building_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}ms_buildings WHERE title = 'مبنى تجريبي' LIMIT 1");
    if (!$building_id) {
        $wpdb->insert($wpdb->prefix . 'ms_buildings', array(
            'title' => 'مبنى تجريبي',
            'manager_id' => $manager_id,
            'wp_post_id' => 0,
            'created_at' => current_time('mysql'),
        ), array('%s', '%d', '%d', '%s'));
        $building_id = intval($wpdb->insert_id);
    }
    $details[] = 'تم إنشاء/تحديث المبنى ' . intval($building_id);

    $unit_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}ms_units WHERE building_id = %d LIMIT 1", $building_id));
    if (!$unit_id) {
        $wpdb->insert($wpdb->prefix . 'ms_units', array(
            'building_id' => $building_id,
            'owner_id' => $owner_id,
            'tenant_id' => $tenant_id,
            'agent_id' => $agent_id,
            'status' => 'rented',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ), array('%d', '%d', '%d', '%d', '%s', '%s', '%s'));
        $unit_id = intval($wpdb->insert_id);
    }
    $details[] = 'تم إنشاء/تحديث الوحدة ' . intval($unit_id);

    if ($tenant_id && $unit_id) {
        ms_link_tenant_to_unit($tenant_id, $unit_id, $building_id, date('Y-m-d', strtotime('-1 month')));
        $wpdb->update($wpdb->prefix . 'ms_units', array('tenant_id' => $tenant_id, 'status' => 'rented'), array('id' => $unit_id), array('%d', '%s'), array('%d'));
        $details[] = 'تم ربط المستأجر بالوحدة';
    }

    if ($tenant_id) {
        $invoice_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}ms_invoices WHERE user_id = %d AND invoice_type = %s ORDER BY id DESC LIMIT 1", $tenant_id, 'rent'));
        if (!$invoice_id) {
            $wpdb->insert($wpdb->prefix . 'ms_invoices', array(
                'user_id' => $tenant_id,
                'building_id' => $building_id,
                'unit_id' => $unit_id,
                'description' => 'فاتورة إيجار تجريبية',
                'amount' => 5500.00,
                'status' => 'pending',
                'due_date' => date('Y-m-d', strtotime('+7 days')),
                'invoice_type' => 'rent',
                'invoice_category' => 'property-rent',
                'payer_type' => 'tenant',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ), array('%d', '%d', '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s'));
            $invoice_id = intval($wpdb->insert_id);
        }
        $details[] = 'تم إنشاء/تحديث فاتورة الإيجار #' . intval($invoice_id);
    }

    if ($building_id) {
        $maintenance_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}ms_maintenance_requests WHERE building_id = %d ORDER BY id DESC LIMIT 1", $building_id));
        if (!$maintenance_id) {
            $wpdb->insert($wpdb->prefix . 'ms_maintenance_requests', array(
                'building_id' => $building_id,
                'unit_id' => $unit_id,
                'title' => 'طلب صيانة تجريبي',
                'description' => 'تم إنشاء طلب صيانة من خلال سيناريو التشغيل.',
                'cost' => 320.00,
                'status' => 'open',
                'priority' => 'medium',
                'maintenance_type' => 'emergency',
                'manager_id' => $manager_id,
                'assigned_to' => $manager_id,
                'payer_type' => 'owner',
                'start_date' => current_time('Y-m-d'),
                'due_date' => date('Y-m-d', strtotime('+3 days')),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ), array('%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s'));
            $maintenance_id = intval($wpdb->insert_id);
        }
        $details[] = 'تم إنشاء/تحديث طلب الصيانة #' . intval($maintenance_id);
    }

    if ($tenant_id) {
        ms_add_user_wallet_balance($tenant_id, 3000.00, 'تجربة محفظة مستأجر');
        ms_add_notification($tenant_id, 'scenario_demo', 'تم إنشاء سيناريو تجريبي للمستأجر', $building_id, $tenant_id);
        $details[] = 'تم إضافة رصيد لمحفظة المستأجر';
    }

    if ($owner_id) {
        ms_add_user_wallet_balance($owner_id, 10000.00, 'تجربة محفظة مالك');
        ms_add_notification($owner_id, 'scenario_demo', 'تم إنشاء سيناريو تجريبي للمالك', $building_id, $owner_id);
        $details[] = 'تم إضافة رصيد لمحفظة المالك';
    }

    if ($manager_id) {
        ms_add_notification($manager_id, 'scenario_demo', 'تم إنشاء سيناريو تجريبي لمدير المبنى', $building_id, $building_id);
        $details[] = 'تم إرسال إشعار لمدير المبنى';
    }

    return array(
        'message' => 'تم تشغيل السيناريو بنجاح. يمكنك الآن مراجعة اللوحات الخاصة بالمالك والمستأجر ومدير المبنى.',
        'details' => $details,
    );
}

function ms_reset_role_scenario_demo()
{
    global $wpdb;

    $details = array();
    $demo_user_logins = array('demo_owner', 'demo_tenant', 'demo_agent', 'demo_manager');
    $demo_user_ids = array();

    foreach ($demo_user_logins as $login) {
        $user = get_user_by('login', $login);
        if ($user) {
            $demo_user_ids[] = intval($user->ID);
        }
    }

    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}ms_invoices WHERE description = %s", 'فاتورة إيجار تجريبية'));
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}ms_maintenance_requests WHERE title = %s", 'طلب صيانة تجريبي'));
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}ms_notifications WHERE type = %s", 'scenario_demo'));

    if (!empty($demo_user_ids)) {
        $placeholders = implode(',', array_fill(0, count($demo_user_ids), '%d'));
        $tenant_query = $wpdb->prepare("DELETE FROM {$wpdb->prefix}ms_unit_tenants WHERE tenant_id IN ($placeholders)", $demo_user_ids);
        $wpdb->query($tenant_query);

        $user_ids_sql = implode(',', array_map('intval', $demo_user_ids));
        $wpdb->query("DELETE FROM {$wpdb->prefix}ms_user_wallet WHERE user_id IN ({$user_ids_sql})");
        $wpdb->query("DELETE FROM {$wpdb->prefix}ms_wallet_transactions WHERE user_id IN ({$user_ids_sql})");
    }

    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}ms_units WHERE building_id IN (SELECT id FROM {$wpdb->prefix}ms_buildings WHERE title = %s)", 'مبنى تجريبي'));
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}ms_buildings WHERE title = %s", 'مبنى تجريبي'));

    $details[] = 'تم حذف بيانات السيناريو التجريبية من الجداول الأساسية.';

    return array(
        'message' => 'تمت إعادة تعيين بيانات السيناريو التجريبية بنجاح.',
        'details' => $details,
    );
}

function ms_admin_import_data_page()
{
    if (!current_user_can('manage_options')) {
        wp_die('غير مصرح لك بالوصول إلى هذه الصفحة.');
    }

    $message = '';
    $results = array();
    $errors = array();

    if (isset($_POST['ms_import_xml']) && check_admin_referer('ms_import_xml')) {
        if (isset($_FILES['xml_file']) && $_FILES['xml_file']['error'] === UPLOAD_ERR_OK) {
            $uploaded_file = $_FILES['xml_file']['tmp_name'];
            $xml_file_path = plugin_dir_path(__FILE__) . '../demo-data.xml';
            
            // نسخ الملف المرفوع
            if (move_uploaded_file($uploaded_file, $xml_file_path)) {
                if (file_exists(plugin_dir_path(__FILE__) . 'import-xml.php')) {
                    require_once plugin_dir_path(__FILE__) . 'import-xml.php';
                    $result = ms_import_demo_data_from_xml($xml_file_path);
                    $message = $result['message'];
                    $results = $result['results'] ?? array();
                    $errors = $result['errors'] ?? array();
                } else {
                    $message = 'ملف الاستيراد غير موجود';
                }
            } else {
                $message = 'فشل رفع الملف';
            }
        } else {
            $message = 'يرجى اختيار ملف XML صالح';
        }
    } elseif (isset($_POST['ms_import_default']) && check_admin_referer('ms_import_default')) {
        $xml_file_path = plugin_dir_path(__FILE__) . '../demo-data.xml';
        if (file_exists($xml_file_path)) {
            require_once plugin_dir_path(__FILE__) . 'import-xml.php';
            $result = ms_import_demo_data_from_xml($xml_file_path);
            $message = $result['message'];
            $results = $result['results'] ?? array();
            $errors = $result['errors'] ?? array();
        } else {
            $message = 'ملف demo-data.xml غير موجود في مجلد الإضافة';
        }
    } elseif (isset($_POST['ms_delete_demo']) && check_admin_referer('ms_delete_demo')) {
        require_once plugin_dir_path(__FILE__) . 'import-xml.php';
        $result = ms_delete_demo_data();
        $message = $result['message'];
        $results = $result['results'] ?? array();
    }

    echo '<div class="wrap">';
    echo '<h1>استيراد البيانات التجريبية</h1>';

    if ($message) {
        $notice_class = !empty($errors) ? 'notice-warning' : 'notice-success';
        echo '<div class="notice ' . $notice_class . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    if (!empty($results)) {
        echo '<div class="notice notice-info is-dismissible"><h3>نتائج الاستيراد:</h3><ul>';
        foreach ($results as $result) {
            echo '<li>' . esc_html($result) . '</li>';
        }
        echo '</ul></div>';
    }

    if (!empty($errors)) {
        echo '<div class="notice notice-error is-dismissible"><h3>الأخطاء:</h3><ul>';
        foreach ($errors as $error) {
            echo '<li>' . esc_html($error) . '</li>';
        }
        echo '</ul></div>';
    }

    echo '<div class="card" style="max-width:600px;margin-top:20px">';
    echo '<h2>استيراد من ملف XML الافتراضي</h2>';
    echo '<p>سيتم استيراد البيانات من ملف demo-data.xml الموجود في مجلد الإضافة.</p>';
    echo '<form method="post">';
    wp_nonce_field('ms_import_default');
    echo '<input type="hidden" name="ms_import_default" value="1">';
    echo '<p><button type="submit" class="button button-primary">استيراد البيانات الافتراضية</button></p>';
    echo '</form>';
    echo '</div>';

    echo '<div class="card" style="max-width:600px;margin-top:20px">';
    echo '<h2>رفع ملف XML مخصص</h2>';
    echo '<p>يمكنك رفع ملف XML مخصص لاستيراد البيانات.</p>';
    echo '<form method="post" enctype="multipart/form-data">';
    wp_nonce_field('ms_import_xml');
    echo '<input type="hidden" name="ms_import_xml" value="1">';
    echo '<p><input type="file" name="xml_file" accept=".xml" required></p>';
    echo '<p><button type="submit" class="button button-secondary">رفع واستيراد</button></p>';
    echo '</form>';
    echo '</div>';

    echo '<div class="card" style="max-width:600px;margin-top:20px">';
    echo '<h2>حذف البيانات التجريبية</h2>';
    echo '<p>سيتم حذف جميع البيانات التجريبية من قاعدة البيانات.</p>';
    echo '<form method="post">';
    wp_nonce_field('ms_delete_demo');
    echo '<input type="hidden" name="ms_delete_demo" value="1">';
    echo '<p><button type="submit" class="button button-link-delete" onclick="return confirm(\'هل أنت متأكد من حذف جميع البيانات التجريبية؟\')">حذف البيانات التجريبية</button></p>';
    echo '</form>';
    echo '</div>';

    echo '</div>';
}

function ms_admin_services_page()
{
    if (!current_user_can('manage_options')) {
        wp_die('غير مصرح لك بالوصول إلى هذه الصفحة.');
    }

    echo '<div class="wrap">';
    echo '<h1>خدمات الإضافة - Mostaager Facility Pro</h1>';
    echo '<p>نظرة شاملة على جميع الخدمات والميزات المتوفرة في الإضافة.</p>';

    $services = array(
        array(
            'name' => 'نظام إدارة المباني',
            'icon' => 'dashicons-building',
            'description' => 'إدارة المباني والوحدات السكنية مع تعيين مديري المباني',
            'status' => 'نشط',
            'features' => array('إنشاء المباني', 'إدارة الوحدات', 'تعيين المديرين', 'ربط مع WordPress Posts')
        ),
        array(
            'name' => 'نظام إدارة الوحدات',
            'icon' => 'dashicons-admin-home',
            'description' => 'إدارة الوحدات السكنية وحالاتها (متاحة، مؤجرة، مباعة)',
            'status' => 'نشط',
            'features' => array('تتبع حالة الوحدة', 'ربط المالك والمستأجر', 'تعيين الوسيط')
        ),
        array(
            'name' => 'نظام الفواتير',
            'icon' => 'dashicons-media-spreadsheet',
            'description' => 'إنشاء وإدارة فواتير الإيجار والصيانة والخدمات',
            'status' => 'نشط',
            'features' => array('فواتير الإيجار', 'فواتير الصيانة', 'فواتير الخدمات', 'تتبع الدفعات')
        ),
        array(
            'name' => 'نظام الصيانة',
            'icon' => 'dashicons-hammer',
            'description' => 'إدارة طلبات الصيانة وتعيين الفنيين وتتبع الحالة',
            'status' => 'نشط',
            'features' => array('طلبات الصيانة', 'تعيين الفنيين', 'تتبع الحالة', 'الصيانة المتكررة')
        ),
        array(
            'name' => 'نظام المحفظة',
            'icon' => 'dashicons-money-alt',
            'description' => 'محفظة إلكترونية لجميع المستخدمين مع إمكانية التعبئة والتحويل',
            'status' => 'نشط',
            'features' => array('محفظة المستخدم', 'محفظة المبنى', 'تعبئة الرصيد', 'حركات المحفظة')
        ),
        array(
            'name' => 'نظام التأمين',
            'icon' => 'dashicons-lock',
            'description' => 'إدارة ودائع التأمين للعقود مع قيود السحب',
            'status' => 'نشط',
            'features' => array('إيداع التأمين', 'حجز المبلغ', 'إطلاق التأمين', 'الخصم والاسترداد')
        ),
        array(
            'name' => 'نظام الموافقات',
            'icon' => 'dashicons-yes',
            'description' => 'نظام موافقة الوسطاء على تغييرات حالة العقارات',
            'status' => 'نشط',
            'features' => array('طلب تغيير الحالة', 'موافقة الوسيط', 'إشعارات', 'تتبع الطلبات')
        ),
        array(
            'name' => 'نظام الإشعارات',
            'icon' => 'dashicons-bell',
            'description' => 'إرسال إشعارات للمستخدمين حول الأحداث المهمة',
            'status' => 'نشط',
            'features' => array('إشعارات النظام', 'إشعارات الفواتير', 'إشعارات الصيانة', 'إشعارات المحفظة')
        ),
        array(
            'name' => 'لوحات التحكم',
            'icon' => 'dashicons-dashboard',
            'description' => 'لوحات تحكم مخصصة لكل دور (مالك، مستأجر، وسيط، مدير مبنى)',
            'status' => 'نشط',
            'features' => array('لوحة المالك', 'لوحة المستأجر', 'لوحة الوسيط', 'لوحة مدير المبنى')
        ),
        array(
            'name' => 'نظام الاشتراكات',
            'icon' => 'dashicons-star-filled',
            'description' => 'إدارة اشتراكات الوسطاء والرسوم الشهرية',
            'status' => 'نشط',
            'features' => array('خطط الاشتراك', 'تتبع الرسوم', 'حدود العقارات', 'تجديد الاشتراك')
        ),
        array(
            'name' => 'التوقيع الإلكتروني',
            'icon' => 'dashicons-sticky',
            'description' => 'ميزة التوقيع الإلكتروني للعقود (قيد التطوير)',
            'status' => 'قيد التطوير',
            'features' => array('توقيع العقود', 'التحقق من الهوية', 'الأرشفة الرقمية')
        ),
        array(
            'name' => 'الأتمتة الذكية',
            'icon' => 'dashicons-rocket',
            'description' => 'أتمتة العمليات المتكررة (قيد التطوير)',
            'status' => 'قيد التطوير',
            'features' => array('تذكير الإيجار', 'إنشاء الفواتير التلقائي', 'إشعارات الصيانة')
        ),
        array(
            'name' => 'تكامل WhatsApp',
            'icon' => 'dashicons-whatsapp',
            'description' => 'إرسال إشعارات عبر WhatsApp (وضع تجريبي)',
            'status' => 'تجريبي',
            'features' => array('إشعارات الصيانة', 'تذكير الإيجار', 'إشعارات التحويل', 'إشعارات الاشتراك')
        ),
        array(
            'name' => 'نظام المرافق',
            'icon' => 'dashicons-lightbulb',
            'description' => 'إدارة فواتير الخدمات التشغيلية (الكهرباء، الماء، الغاز)',
            'status' => 'نشط',
            'features' => array('فواتير الخدمات', 'توزيع التكاليف', 'ربط بالوحدات')
        ),
        array(
            'name' => 'التقييمات',
            'icon' => 'dashicons-star-half',
            'description' => 'نظام تقييم الوحدات من قبل المستأجرين',
            'status' => 'نشط',
            'features' => array('تقييم الوحدة', 'التعليقات', 'الموافقة على التقييمات')
        ),
        array(
            'name' => 'التقارير والإحصائيات',
            'icon' => 'dashicons-chart-bar',
            'description' => 'تقارير مالية وإحصائيات شاملة',
            'status' => 'نشط',
            'features' => array('تقرير المحفظة', 'إحصائيات المبنى', 'تقارير الإيرادات', 'تحليلات الأداء')
        ),
        array(
            'name' => 'API REST',
            'icon' => 'dashicons-rest-api',
            'description' => 'واجهة برمجة التطبيقات للتكامل مع التطبيقات الخارجية',
            'status' => 'نشط',
            'features' => array('REST API v1', 'REST API v2', 'توثيق API', 'المصادقة')
        ),
        array(
            'name' => 'استيراد/تصدير البيانات',
            'icon' => 'dashicons-upload',
            'description' => 'استيراد وتصدير البيانات عبر ملفات XML',
            'status' => 'نشط',
            'features' => array('استيراد XML', 'تصدير XML', 'بيانات تجريبية', 'النسخ الاحتياطي')
        )
    );

    echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(350px,1fr));gap:20px;margin-top:30px">';

    foreach ($services as $service) {
        $status_class = $service['status'] === 'نشط' ? 'success' : ($service['status'] === 'قيد التطوير' ? 'warning' : 'info');
        $status_color = $service['status'] === 'نشط' ? '#22c55e' : ($service['status'] === 'قيد التطوير' ? '#f59e0b' : '#3b82f6');
        
        echo '<div class="card" style="margin:0">';
        echo '<div style="display:flex;align-items:center;gap:10px;margin-bottom:15px">';
        echo '<span class="dashicons ' . esc_attr($service['icon']) . '" style="font-size:32px;color:' . esc_attr($status_color) . '"></span>';
        echo '<h3 style="margin:0">' . esc_html($service['name']) . '</h3>';
        echo '<span style="margin-left:auto;background:' . esc_attr($status_color) . ';color:#fff;padding:4px 12px;border-radius:12px;font-size:12px">' . esc_html($service['status']) . '</span>';
        echo '</div>';
        echo '<p style="color:#666;margin-bottom:15px">' . esc_html($service['description']) . '</p>';
        echo '<h4 style="margin:0 0 10px 0;font-size:14px">الميزات:</h4>';
        echo '<ul style="margin:0;padding-right:20px;font-size:13px;color:#555">';
        foreach ($service['features'] as $feature) {
            echo '<li>' . esc_html($feature) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    echo '</div>';
    echo '</div>';
}

function ms_table_list_output($rows, $cols)
{
    echo '<table class="widefat fixed striped"><thead><tr>';
    foreach ($cols as $c) {
        echo '<th>' . esc_html($c) . '</th>';
    }
    echo '<th>Actions</th></tr></thead><tbody>';
    if (empty($rows)) {
        echo '<tr><td colspan="' . (count($cols) + 1) . '">No records found.</td></tr>';
    } else {
        foreach ($rows as $r) {
            echo '<tr>';
            foreach ($cols as $key => $label) {
                // allow numeric keys for direct property access
                $val = is_int($key) ? (isset($r->$label) ? $r->$label : '') : (isset($r->$key) ? $r->$key : '');
                echo '<td>' . esc_html($val) . '</td>';
            }

            $id = intval($r->id ?? $r->ID ?? 0);
            $type = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
            // map page to short type
            $map = array(
                'mostaager-buildings' => 'building',
                'mostaager-units' => 'unit',
                'mostaager-invoices' => 'invoice',
                'mostaager-maintenance' => 'maintenance',
                'mostaager-transfers' => 'transfer',
            );
            $short = isset($map[$type]) ? $map[$type] : '';
            $delete_url = wp_nonce_url(admin_url('admin-post.php?action=ms_delete_item&type=' . $short . '&id=' . $id), 'ms_delete_item');

            echo '<td><a href="' . esc_url($delete_url) . '" onclick="return confirm(\'Delete record?\')">Delete</a></td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table>';
}

function ms_admin_get_building_label($building_id)
{
    global $wpdb;
    $building_id = intval($building_id);
    if (!$building_id) {
        return '—';
    }

    $table = $wpdb->prefix . 'ms_buildings';
    $row = $wpdb->get_row($wpdb->prepare("SELECT title, wp_post_id FROM {$table} WHERE id = %d LIMIT 1", $building_id));
    if ($row) {
        if (!empty($row->title)) {
            return $row->title;
        }
        if (!empty($row->wp_post_id)) {
            $linked_title = get_the_title(intval($row->wp_post_id));
            if ($linked_title) {
                return $linked_title;
            }
        }
    }

    if (function_exists('ms_get_linked_wp_post_id_for_building')) {
        $linked_id = ms_get_linked_wp_post_id_for_building($building_id);
        if ($linked_id) {
            $linked_title = get_the_title(intval($linked_id));
            if ($linked_title) {
                return $linked_title;
            }
        }
    }

    $legacy_title = get_the_title($building_id);
    if ($legacy_title) {
        return $legacy_title;
    }

    return 'Building #' . $building_id;
}

function ms_admin_buildings_page()
{
    global $wpdb;
    $tbl      = $wpdb->prefix . 'ms_buildings';
    $unit_tbl = $wpdb->prefix . 'ms_units';
    $rows = $wpdb->get_results("SELECT b.*, COUNT(u.id) AS unit_count FROM {$tbl} b LEFT JOIN {$unit_tbl} u ON u.building_id = b.id GROUP BY b.id ORDER BY b.id DESC LIMIT 200");

    // fetch WP posts that represent buildings for linking
    $wp_buildings = array();
    $posts = get_posts(array('post_type' => array('building', 'ms_building'), 'posts_per_page' => -1, 'post_status' => 'publish', 'fields' => 'ids'));
    if (!empty($posts)) {
        foreach ($posts as $pid) {
            $p = get_post($pid);
            if ($p) {
                $wp_buildings[$p->ID] = $p->post_title;
            }
        }
    }

    // جلب مستخدمي building_manager لقائمة التعيين
    $all_managers = get_users(array(
        'role__in' => array('building_manager'),
        'orderby'  => 'display_name',
        'order'    => 'ASC',
        'number'   => -1,
        'fields'   => array('ID', 'display_name', 'user_login'),
    ));

    $message = isset($_GET['ms_building_message']) ? sanitize_text_field($_GET['ms_building_message']) : '';

    echo '<div class="wrap"><h1>Buildings</h1>';
    if ($message === 'linked') {
        echo '<div class="notice notice-success is-dismissible"><p>تم ربط المبنى بمنشور WordPress بنجاح.</p></div>';
    } elseif ($message === 'manager_assigned') {
        echo '<div class="notice notice-success is-dismissible"><p>✅ تم تعيين مدير المبنى بنجاح.</p></div>';
    } elseif ($message === 'manager_error') {
        echo '<div class="notice notice-error is-dismissible"><p>❌ حدث خطأ أثناء تعيين المدير. يرجى المحاولة مرة أخرى.</p></div>';
    } elseif ($message === 'conflict') {
        $conflict_title = isset($_GET['conflict_title']) ? esc_html(urldecode(sanitize_text_field($_GET['conflict_title']))) : '';
        echo '<div class="notice notice-warning is-dismissible"><p>المنشور المختار مرتبط بمبنى آخر' . ($conflict_title ? ': ' . $conflict_title : '') . '.</p></div>';
    }

    echo '<table class="widefat fixed striped"><thead><tr>';
    echo '<th style="width:50px">ID</th><th>اسم المبنى</th><th>مدير المبنى الحالي</th><th style="width:60px">وحدات</th><th>منشور WordPress</th><th>تاريخ الإنشاء</th><th>إجراءات</th>';
    echo '</tr></thead><tbody>';

    if (empty($rows)) {
        echo '<tr><td colspan="7">لا توجد سجلات.</td></tr>';
    } else {
        $row_index = 0;
        foreach ($rows as $row) {
            $manager      = get_userdata(intval($row->manager_id));
            $manager_name = $manager
                ? esc_html($manager->display_name . ' (@' . $manager->user_login . ')')
                : ($row->manager_id ? 'User #' . intval($row->manager_id) : '<em style="color:#6b7280">— بدون مدير —</em>');

            $delete_url    = wp_nonce_url(admin_url('admin-post.php?action=ms_delete_item&type=building&id=' . intval($row->id)), 'ms_delete_item');
            $linked_wp_id  = intval($row->wp_post_id ?? 0);

            echo '<tr>';
            echo '<td>' . intval($row->id) . '</td>';
            echo '<td><strong>' . esc_html($row->title) . '</strong></td>';

            // عمود المدير الحالي
            echo '<td>' . $manager_name . '</td>';
            echo '<td style="text-align:center">' . intval($row->unit_count) . '</td>';

            // عمود WP post
            if ($linked_wp_id && isset($wp_buildings[$linked_wp_id])) {
                $post_edit_url = admin_url('post.php?post=' . $linked_wp_id . '&action=edit');
                echo '<td><a href="' . esc_url($post_edit_url) . '">' . esc_html($wp_buildings[$linked_wp_id]) . '</a></td>';
            } else {
                echo '<td><span style="color:#6b7280">غير مرتبط</span></td>';
            }

            echo '<td>' . esc_html($row->created_at ?? '') . '</td>';

            // عمود الإجراءات: نموذجان — ربط WP post + تعيين مدير
            echo '<td style="min-width:280px">';

            // ── نموذج 1: ربط WP post ──
            if (!empty($wp_buildings)) {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-bottom:6px">';
                wp_nonce_field('ms_link_building_post');
                echo '<input type="hidden" name="action" value="ms_link_building_post">';
                echo '<input type="hidden" name="building_id" value="' . intval($row->id) . '">';
                echo '<select name="wp_post_id" style="min-width:160px;font-size:12px">';
                echo '<option value="0">— اختر منشور WordPress —</option>';
                foreach ($wp_buildings as $pid => $title) {
                    $sel = ($pid === $linked_wp_id) ? ' selected' : '';
                    echo '<option value="' . intval($pid) . '"' . $sel . '>' . esc_html($title) . ' (#' . intval($pid) . ')</option>';
                }
                echo '</select> ';
                echo '<button type="submit" class="button button-small">🔗 ربط</button>';
                echo '</form>';
            }

            // ── لوحة AJAX: تعيين مدير المبنى ──
            ms_render_manager_cell($row, $row_index);
            // ── لوحة AJAX: تعيين عدة وسطاء للمبنى ──
            ms_render_agents_cell($row, $row_index);
            $row_index++;

            // زر الحذف
            echo '<div style="margin-top:4px"><a href="' . esc_url($delete_url) . '" onclick="return confirm(\'حذف المبنى؟\')" class="button button-small button-link-delete">🗑 حذف</a></div>';

            echo '</td>';
            echo '</tr>';
        }
    }

    echo '</tbody></table>';
    ms_building_manager_js();
    echo '</div>';
}

function ms_render_manager_cell($building, $index = 0)
{
    $managers = get_users(array(
        'role__in' => array('building_manager'),
        'orderby' => 'display_name',
        'order' => 'ASC',
        'number' => -1,
        'fields' => array('ID', 'display_name', 'user_login'),
    ));

    if (empty($managers)) {
        echo '<p style="color:#999;font-size:12px;margin:4px 0">لا يوجد مديرو مباني مسجلون.<br><a href="' . esc_url(admin_url('user-new.php')) . '">أضف مستخدماً</a> بدور <em>مدير مبنى</em></p>';
        return;
    }

    $current_manager_id = intval($building->manager_id ?? 0);
    $nonce = wp_create_nonce('ms_building_manager_action');
    $select_id = 'ms-manager-select-' . intval($index);
    $button_id = 'ms-manager-save-btn-' . intval($index);

    echo '<div style="display:flex;gap:4px;align-items:center">';
    echo '<select id="' . esc_attr($select_id) . '" style="min-width:160px;font-size:12px">';
    echo '<option value="0">— بدون مدير —</option>';
    foreach ($managers as $mgr) {
        $sel = ($current_manager_id === intval($mgr->ID)) ? ' selected' : '';
        echo '<option value="' . intval($mgr->ID) . '"' . $sel . '>' . esc_html($mgr->display_name . ' (@' . $mgr->user_login . ')') . '</option>';
    }
    echo '</select> ';
    echo '<button type="button" id="' . esc_attr($button_id) . '" class="button button-small button-primary" onclick="msSaveManager(' . intval($building->id) . ', ' . intval($index) . ', \'' . esc_js($nonce) . '\')">👤 حفظ</button>';
    echo '</div>';
}

function ms_render_agents_cell($building, $index = 0)
{
    global $wpdb;
    $agents = get_users(array('role__in' => array('agent', 'houzez_agent'), 'orderby' => 'display_name', 'order' => 'ASC', 'number' => -1, 'fields' => array('ID', 'display_name', 'user_login')));
    $assigned = $wpdb->get_col($wpdb->prepare("SELECT agent_id FROM {$wpdb->prefix}ms_building_agents WHERE building_id = %d AND status = 'active'", intval($building->id)));
    $assigned = array_map('intval', (array) $assigned);
    $select_id = 'ms-agents-select-' . intval($index);
    $nonce = wp_create_nonce('ms_building_agents_action');
    echo '<div style="margin-top:8px;padding-top:8px;border-top:1px solid #eee">';
    echo '<label style="display:block;font-weight:600;font-size:12px;margin-bottom:3px">الوسطاء المعيّنون</label>';
    echo '<select id="' . esc_attr($select_id) . '" multiple size="4" style="min-width:210px;font-size:12px">';
    foreach ($agents as $agent) {
        $selected = in_array(intval($agent->ID), $assigned, true) ? ' selected' : '';
        echo '<option value="' . intval($agent->ID) . '"' . $selected . '>' . esc_html($agent->display_name . ' (@' . $agent->user_login . ')') . '</option>';
    }
    echo '</select> ';
    echo '<button type="button" class="button button-small" data-building="' . intval($building->id) . '" data-select="' . esc_attr($select_id) . '" data-nonce="' . esc_attr($nonce) . '" onclick="msSaveAgentsFromButton(this)">💼 حفظ الوسطاء</button>';
    echo '<small style="display:block;color:#6b7280;margin-top:3px">يمكن تحديد أكثر من وسيط باستخدام Ctrl/⌘.</small>';
    echo '</div>';
}

function ms_building_manager_js()
{
    ?>
    <script>
    (function () {
        var ajaxUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';

        window.msSaveAgentsFromButton = function (button) {
            var buildingId = button.getAttribute('data-building');
            var selectId = button.getAttribute('data-select');
            var nonce = button.getAttribute('data-nonce');
            var select = document.getElementById(selectId);
            if (!select) return;
            var data = new URLSearchParams();
            data.append('action', 'ms_update_building_agents');
            data.append('building_id', buildingId);
            data.append('nonce', nonce);
            Array.prototype.forEach.call(select.options, function (option) {
                if (option.selected && option.value) data.append('agent_ids[]', option.value);
            });
            fetch(ajaxUrl, {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'}, body:data.toString()})
                .then(function(response){ return response.json(); })
                .then(function(json){ alert(json && json.success ? 'تم حفظ الوسطاء.' : ((json.data && json.data.message) || 'تعذر حفظ الوسطاء.')); })
                .catch(function(){ alert('تعذر الاتصال بالخادم.'); });
        };

        window.msSaveManager = function (buildingId, index, nonce) {
            var select = document.getElementById('ms-manager-select-' + index);
            var button = document.getElementById('ms-manager-save-btn-' + index);
            if (!select || !button) {
                return;
            }

            var managerId = select.value;
            button.textContent = 'جاري...';
            button.disabled = true;

            var data = new URLSearchParams();
            data.append('action', 'ms_update_building_manager');
            data.append('building_id', buildingId);
            data.append('manager_id', managerId);
            data.append('nonce', nonce);

            fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
                body: data.toString(),
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (json) {
                    if (json && json.success) {
                        button.textContent = '✓ تم';
                    } else {
                        button.textContent = '✗ خطأ';
                    }
                })
                .catch(function () {
                    button.textContent = '✗ خطأ';
                })
                .finally(function () {
                    setTimeout(function () {
                        button.textContent = '👤 حفظ';
                        button.disabled = false;
                    }, 2000);
                });
        };
    })();
    </script>
    <?php
}

add_action('wp_ajax_ms_update_building_manager', 'ms_ajax_update_building_manager');
add_action('wp_ajax_ms_update_building_agents', 'ms_ajax_update_building_agents');

function ms_ajax_update_building_agents()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'غير مصرح لك بتنفيذ هذا الإجراء.'));
    }
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!$nonce || !wp_verify_nonce($nonce, 'ms_building_agents_action')) {
        wp_send_json_error(array('message' => 'فشل التحقق من الأمان.'));
    }
    $building_id = isset($_POST['building_id']) ? absint($_POST['building_id']) : 0;
    if (!$building_id) {
        wp_send_json_error(array('message' => 'مبنى غير صالح.'));
    }
    $agent_ids = isset($_POST['agent_ids']) ? array_map('absint', (array) wp_unslash($_POST['agent_ids'])) : array();
    $agent_ids = array_values(array_unique(array_filter($agent_ids)));
    foreach ($agent_ids as $agent_id) {
        $agent = get_userdata($agent_id);
        $roles = $agent ? array_map('sanitize_key', (array) $agent->roles) : array();
        if (!$agent || !array_intersect(array('agent', 'houzez_agent'), $roles)) {
            wp_send_json_error(array('message' => 'توجد هوية وسيط غير صالحة.'));
        }
    }
    global $wpdb;
    $table = $wpdb->prefix . 'ms_building_agents';
    $wpdb->delete($table, array('building_id' => $building_id), array('%d'));
    foreach ($agent_ids as $agent_id) {
        $wpdb->insert($table, array('building_id' => $building_id, 'agent_id' => $agent_id, 'assigned_by' => get_current_user_id(), 'status' => 'active'), array('%d', '%d', '%d', '%s'));
    }
    wp_send_json_success(array('count' => count($agent_ids), 'message' => 'تم حفظ الوسطاء.'));
}

function ms_ajax_update_building_manager()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'غير مصرح لك بتنفيذ هذا الإجراء.'));
    }

    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ms_building_manager_action')) {
        wp_send_json_error(array('message' => 'فشل التحقق من الأمان.'));
    }

    $building_id = isset($_POST['building_id']) ? intval($_POST['building_id']) : 0;
    $manager_id  = isset($_POST['manager_id']) ? intval($_POST['manager_id']) : 0;

    if (!$building_id) {
        wp_send_json_error(array('message' => 'فشل التحديث.'));
    }

    if ($manager_id !== 0) {
        $manager = get_userdata($manager_id);
        if (!$manager || !in_array('building_manager', (array) $manager->roles, true)) {
            wp_send_json_error(array('message' => 'مدير غير صالح.'));
        }
    }

    global $wpdb;
    $tbl = $wpdb->prefix . 'ms_buildings';

    $updated = $wpdb->update(
        $tbl,
        array('manager_id' => $manager_id),
        array('id' => $building_id),
        array('%d'),
        array('%d')
    );

    if ($updated === false) {
        wp_send_json_error(array('message' => 'فشل التحديث.'));
    }

    $wp_post_id = intval($wpdb->get_var($wpdb->prepare(
        "SELECT wp_post_id FROM {$tbl} WHERE id = %d LIMIT 1",
        $building_id
    )));
    if ($wp_post_id) {
        if ($manager_id === 0) {
            delete_post_meta($wp_post_id, 'ms_building_manager_id');
        } else {
            update_post_meta($wp_post_id, 'ms_building_manager_id', $manager_id);
        }
    }

    wp_send_json_success(array('message' => 'تم تحديث المدير بنجاح'));
}

// Handler for linking a Mostaager building row to a WordPress post
add_action('admin_post_ms_link_building_post', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Forbidden', '403');
    }

    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field($_POST['_wpnonce']), 'ms_link_building_post')) {
        wp_die('Invalid nonce', '400');
    }

    $building_id = isset($_POST['building_id']) ? intval($_POST['building_id']) : 0;
    $wp_post_id = isset($_POST['wp_post_id']) ? intval($_POST['wp_post_id']) : 0;
    global $wpdb;
    $tbl = $wpdb->prefix . 'ms_buildings';

    if (!$building_id) {
        wp_die('Invalid building id', '400');
    }

    if ($wp_post_id > 0) {
        $conflict = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tbl} WHERE wp_post_id = %d AND id != %d LIMIT 1", $wp_post_id, $building_id));
        if ($conflict) {
            $post = get_post($wp_post_id);
            $title = $post ? rawurlencode($post->post_title) : '';
            $redirect = add_query_arg(array('ms_building_message' => 'conflict', 'conflict_title' => $title), wp_get_referer() ? wp_get_referer() : admin_url('admin.php?page=mostaager-buildings'));
            wp_safe_redirect($redirect);
            exit;
        }
    }

    $updated = $wpdb->update($tbl, array('wp_post_id' => $wp_post_id), array('id' => $building_id), array('%d'), array('%d'));
    $redirect = add_query_arg('ms_building_message', 'linked', wp_get_referer() ? wp_get_referer() : admin_url('admin.php?page=mostaager-buildings'));
    wp_safe_redirect($redirect);
    exit;
});

add_action('admin_post_ms_cancel_invoice', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Forbidden', '403');
    }

    $invoice_id = isset($_GET['invoice_id']) ? intval($_GET['invoice_id']) : 0;
    if (!$invoice_id || !isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field($_GET['_wpnonce']), 'ms_cancel_invoice_' . $invoice_id)) {
        wp_die('Invalid request', '400');
    }

    if (!function_exists('ms_cancel_invoice') || !function_exists('ms_get_invoice_by_id')) {
        wp_die('Function not available', '500');
    }

    $inv = ms_get_invoice_by_id($invoice_id);
    if (!$inv) {
        wp_die('Invoice not found', '404');
    }
    if (strtolower(trim($inv->status ?? '')) !== 'pending') {
        wp_die('Invalid invoice state', '400');
    }

    ms_cancel_invoice($invoice_id);
    $redirect = add_query_arg('ms_message', 'invoice_canceled', wp_get_referer() ? wp_get_referer() : admin_url('admin.php?page=mostaager-invoices'));
    wp_safe_redirect($redirect);
    exit;
});

function ms_admin_units_page()
{
    global $wpdb;
    $tbl = $wpdb->prefix . 'ms_units';
    $rows = $wpdb->get_results("SELECT * FROM {$tbl} ORDER BY id DESC LIMIT 200");
    echo '<div class="wrap"><h1>Units</h1>';
    ms_table_list_output($rows, array('id' => 'ID', 'building_id' => 'Building ID', 'owner_id' => 'Owner', 'tenant_id' => 'Tenant', 'status' => 'Status', 'created_at' => 'Created'));
    echo '</div>';
}

function ms_admin_all_invoices_page()
{
    if (!current_user_can('manage_options')) {
        wp_die('غير مصرح لك بالوصول إلى هذه الصفحة.');
    }

    global $wpdb;
    $paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
    $per_page = 20;

    $args = array(
        'post_type' => 'invoices',
        'posts_per_page' => $per_page,
        'paged' => $paged,
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC'
    );

    $invoices_query = new WP_Query($args);
    $cpt_rows = array();
    if ($invoices_query->have_posts()) {
        while ($invoices_query->have_posts()) {
            $invoices_query->the_post();
            $invoice_id = get_the_ID();
            $building_id = get_post_meta($invoice_id, 'building_id', true);
            $property_id = get_post_meta($invoice_id, 'property_id', true);
            $amount = get_post_meta($invoice_id, 'amount_due', true);
            $status = get_post_meta($invoice_id, 'status', true);
            $created_at = get_post_meta($invoice_id, 'created_at', true) ?: get_the_date('Y-m-d H:i:s');
            $cpt_rows[] = (object) array(
                'id' => $invoice_id,
                'building_id' => intval($building_id),
                'property_id' => intval($property_id),
                'amount' => floatval($amount),
                'status' => $status,
                'created_at' => $created_at,
                'source' => 'legacy_cpt',
            );
        }
        wp_reset_postdata();
    }

    $legacy_rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ms_invoices ORDER BY created_at DESC");
    $rows = array_merge($cpt_rows, $legacy_rows);
    usort($rows, function ($a, $b) {
        $a_date = strtotime($a->created_at ?? ($a->post_date ?? ''));
        $b_date = strtotime($b->created_at ?? ($b->post_date ?? ''));
        return $b_date <=> $a_date;
    });

    echo '<div class="wrap">';
    echo '<h1>جميع فواتير المرافق في الموقع</h1>';
    echo '<p>إجمالي السجلات: ' . intval(count($rows)) . '</p>';

    if (empty($rows)) {
        echo '<p>لا توجد فواتير.</p>';
    } else {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>رقم الفاتورة</th>';
        echo '<th>المبنى</th>';
        echo '<th>الشقة</th>';
        echo '<th>المبلغ</th>';
        echo '<th>الحالة</th>';
        echo '<th>تاريخ الإنشاء</th>';
        echo '<th>المصدر</th>';
        echo '<th>إجراءات</th>';
        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $invoice_id = intval($row->id ?? $row->ID ?? 0);
            $building_title = $row->building_id ? get_the_title(intval($row->building_id)) : '—';
            $property_title = $row->property_id ? get_the_title(intval($row->property_id)) : '—';
            $amount_label = isset($row->amount) ? number_format_i18n(floatval($row->amount), 2) . ' جنيه' : '—';
            $status_label = isset($row->status) ? esc_html($row->status) : '—';
            $created_at = esc_html($row->created_at ?? '—');
            $source = esc_html($row->source ?? '—');
            $edit_url = $invoice_id ? get_edit_post_link($invoice_id) : '#';

            echo '<tr>';
            echo '<td>' . $invoice_id . '</td>';
            echo '<td>' . esc_html($building_title) . '</td>';
            echo '<td>' . esc_html($property_title) . '</td>';
            echo '<td>' . esc_html($amount_label) . '</td>';
            echo '<td>' . $status_label . '</td>';
            echo '<td>' . $created_at . '</td>';
            echo '<td>' . $source . '</td>';
            echo '<td>' . ($edit_url ? '<a href="' . esc_url($edit_url) . '" target="_blank">عرض</a>' : '—') . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    echo '</div>';
}


function ms_admin_invoices_page()
{
    global $wpdb;
    $tbl = $wpdb->prefix . 'ms_invoices';
    $status_filter = isset($_GET['ms_status']) ? sanitize_text_field($_GET['ms_status']) : '';
    $sql = "SELECT * FROM {$tbl}";
    if (!empty($status_filter)) {
        $sql = $wpdb->prepare("SELECT * FROM {$tbl} WHERE status = %s", $status_filter);
    }
    $sql .= " ORDER BY id DESC LIMIT 200";
    $rows = $wpdb->get_results($sql);

    $message = isset($_GET['ms_message']) ? sanitize_text_field($_GET['ms_message']) : '';
    echo '<div class="wrap">';
    echo '<h1>Invoices</h1>';
    if ($message === 'invoice_paid') {
        echo '<div class="notice notice-success"><p>تم وضع الفاتورة كمدفوعة بنجاح.</p></div>';
    } elseif ($message === 'invoice_canceled') {
        echo '<div class="notice notice-success"><p>تم إلغاء الفاتورة بنجاح.</p></div>';
    }

    // Filter form
    echo '<form method="get" style="margin-bottom:12px">';
    echo '<input type="hidden" name="page" value="mostaager-invoices">';
    echo '<label style="margin-right:8px">الحالة: </label>';
    echo '<select name="ms_status">';
    $opts = array('' => 'الكل', 'pending' => 'معلقة', 'paid' => 'مدفوع', 'canceled' => 'ملغي');
    foreach ($opts as $k => $v) {
        $sel = ($k === $status_filter) ? ' selected' : '';
        echo '<option value="' . esc_attr($k) . '"' . $sel . '>' . esc_html($v) . '</option>';
    }
    echo '</select> ';
    echo '<button class="button">تصفية</button>';
    echo '</form>';
    echo '<table class="widefat fixed striped"><thead><tr>';
    echo '<th>ID</th><th>نوع الدافع</th><th>اسم الشخص</th><th>المبلغ</th><th>الحالة</th><th>تاريخ الاستحقاق</th><th>أنشئت بتاريخ</th><th>Actions</th>';
    echo '</tr></thead><tbody>';

    if (empty($rows)) {
        echo '<tr><td colspan="8">No invoices found.</td></tr>';
    } else {
        foreach ($rows as $invoice) {
            $payer_type_map = array(
                'owner' => 'مالك',
                'tenant' => 'مستأجر',
                'agent' => 'وسيط',
                'owner_or_tenant' => 'مالك/مستأجر',
            );
            $payer_type = strtolower(trim($invoice->payer_type ?? ''));
            $payer_type_label = isset($payer_type_map[$payer_type]) ? $payer_type_map[$payer_type] : 'غير معروف';

            $user = get_userdata(intval($invoice->user_id));
            $payer_name = $user ? $user->display_name : esc_html($invoice->payer_name ?? '--');

            $status_norm = strtolower(trim($invoice->status ?? ''));
            $paid_label = ($status_norm === 'paid') ? '<span style="color:#10b981;font-weight:600">مدفوع</span>' : ( $status_norm === 'canceled' ? '<span style="color:#ef4444;font-weight:600">ملغي</span>' : esc_html($invoice->status) );
            $action = '';
            if ($status_norm === 'pending') {
                $action .= '<a href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=ms_mark_invoice_paid&invoice_id=' . intval($invoice->id)), 'ms_mark_invoice_paid_' . intval($invoice->id))) . '" class="button button-secondary">تحديد كمدفوع</a>';
                $action .= ' ' . '<a href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=ms_cancel_invoice&invoice_id=' . intval($invoice->id)), 'ms_cancel_invoice_' . intval($invoice->id))) . '" class="button button-link-delete" onclick="return confirm(\'Are you sure you want to cancel this invoice?\')">إلغاء</a>';
            }

            echo '<tr>';
            echo '<td>' . intval($invoice->id) . '</td>';
            echo '<td>' . esc_html($payer_type_label) . '</td>';
            echo '<td>' . esc_html($payer_name) . '</td>';
            echo '<td>ج.م ' . number_format_i18n(floatval($invoice->amount), 2) . '</td>';
            echo '<td>' . $paid_label . '</td>';
            echo '<td>' . esc_html($invoice->due_date) . '</td>';
            echo '<td>' . esc_html($invoice->created_at ?? '') . '</td>';
            echo '<td>' . $action . '</td>';
            echo '</tr>';
        }
    }

    echo '</tbody></table>';
    echo '</div>';
}

add_action('admin_post_ms_mark_invoice_paid', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Forbidden', '403');
    }

    $invoice_id = isset($_GET['invoice_id']) ? intval($_GET['invoice_id']) : 0;
    if (!$invoice_id || !isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field($_GET['_wpnonce']), 'ms_mark_invoice_paid_' . $invoice_id)) {
        wp_die('Invalid request', '400');
    }

    if (!function_exists('ms_mark_invoice_paid') || !function_exists('ms_get_invoice_by_id')) {
        wp_die('Function not available', '500');
    }

    $inv = ms_get_invoice_by_id($invoice_id);
    if (!$inv) {
        wp_die('Invoice not found', '404');
    }
    if (strtolower(trim($inv->status ?? '')) !== 'pending') {
        wp_die('Invalid invoice state', '400');
    }

    ms_mark_invoice_paid($invoice_id);
    $redirect = add_query_arg('ms_message', 'invoice_paid', wp_get_referer() ? wp_get_referer() : admin_url('admin.php?page=mostaager-invoices'));
    wp_safe_redirect($redirect);
    exit;
});

function ms_admin_maintenance_page()
{
    global $wpdb;
    $tbl = $wpdb->prefix . 'ms_maintenance_requests';
    $rows = $wpdb->get_results("SELECT m.*, b.title AS building_title FROM {$tbl} m LEFT JOIN {$wpdb->prefix}ms_buildings b ON b.id = m.building_id ORDER BY m.id DESC LIMIT 200");
    echo '<div class="wrap"><h1>Maintenance / Expenses</h1>';
    echo '<table class="widefat fixed striped"><thead><tr>';
    echo '<th>ID</th><th>Building</th><th>Unit</th><th>Title</th><th>Cost</th><th>نوع الدافع</th><th>Manager</th><th>Type</th><th>Status</th><th>Due Date</th><th>Created</th><th>Actions</th>';
    echo '</tr></thead><tbody>';

    if (empty($rows)) {
        echo '<tr><td colspan="12">No records found.</td></tr>';
    } else {
        foreach ($rows as $row) {
            $manager = get_userdata(intval($row->manager_id));
            $manager_name = $manager ? $manager->display_name : ($row->manager_id ? 'User #' . intval($row->manager_id) : '--');
            $payer_type_map = array(
                'owner' => 'مالك',
                'tenant' => 'مستأجر',
                'agent' => 'وسيط',
                'owner_or_tenant' => 'مالك/مستأجر',
            );
            $payer_type = strtolower(trim($row->payer_type ?? 'owner'));
            $payer_type_label = isset($payer_type_map[$payer_type]) ? $payer_type_map[$payer_type] : esc_html($payer_type);
            $delete_url = wp_nonce_url(admin_url('admin-post.php?action=ms_delete_item&type=maintenance&id=' . intval($row->id)), 'ms_delete_item');
            echo '<tr>';
            echo '<td>' . intval($row->id) . '</td>';
            echo '<td>' . esc_html($row->building_title ?: 'Building #' . intval($row->building_id)) . '</td>';
            echo '<td>' . ($row->unit_id ? 'Unit #' . intval($row->unit_id) : '--') . '</td>';
            echo '<td>' . esc_html($row->title) . '</td>';
            echo '<td>ج.م ' . number_format_i18n(floatval($row->cost), 2) . '</td>';
            echo '<td>' . esc_html($payer_type_label) . '</td>';
            echo '<td>' . esc_html($manager_name) . '</td>';
            echo '<td>' . esc_html($row->maintenance_type) . '</td>';
            echo '<td>' . esc_html($row->status) . '</td>';
            echo '<td>' . esc_html($row->due_date ?? '') . '</td>';
            echo '<td>' . esc_html($row->created_at ?? '') . '</td>';
            echo '<td><a href="' . esc_url($delete_url) . '" onclick="return confirm(\'Delete maintenance record?\')">Delete</a></td>';
            echo '</tr>';
        }
    }

    echo '</tbody></table>';
    echo '</div>';
}

function ms_admin_transfers_page()
{
    if (!current_user_can('manage_options')) {
        wp_die('غير مصرح لك بالوصول إلى هذه الصفحة.');
    }

    if (isset($_POST['approve_transfer']) && isset($_POST['transfer_action_nonce']) && wp_verify_nonce($_POST['transfer_action_nonce'], 'transfer_action')) {
        $transfer_id = intval($_POST['transfer_id']);
        update_post_meta($transfer_id, 'status', 'approved');
        update_post_meta($transfer_id, 'approved_by', get_current_user_id());
        update_post_meta($transfer_id, 'approved_date', current_time('mysql'));

        $building_id = get_post_meta($transfer_id, 'building_id', true);
        $manager_id = $building_id ? get_post_meta($building_id, 'manager_id', true) : 0;
        if ($manager_id) {
            if (function_exists('mostaager_add_internal_notification')) {
                mostaager_add_internal_notification($manager_id, 'تمت الموافقة على طلب التحويل', 'تمت الموافقة على طلب التحويل المالي الخاص بك.', $transfer_id);
            }
        }
    }

    if (isset($_POST['reject_transfer']) && isset($_POST['transfer_action_nonce']) && wp_verify_nonce($_POST['transfer_action_nonce'], 'transfer_action')) {
        $transfer_id = intval($_POST['transfer_id']);
        update_post_meta($transfer_id, 'status', 'rejected');
        update_post_meta($transfer_id, 'rejected_by', get_current_user_id());
        update_post_meta($transfer_id, 'rejected_date', current_time('mysql'));

        $building_id = get_post_meta($transfer_id, 'building_id', true);
        $manager_id = $building_id ? get_post_meta($building_id, 'manager_id', true) : 0;
        if ($manager_id) {
            if (function_exists('mostaager_add_internal_notification')) {
                mostaager_add_internal_notification($manager_id, 'تم رفض طلب التحويل', 'تم رفض طلب التحويل المالي الخاص بك.', $transfer_id);
            }
        }
    }

    $paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
    $per_page = 20;
    $args = array(
        'post_type' => 'transfers',
        'posts_per_page' => $per_page,
        'paged' => $paged,
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC'
    );

    $transfers_query = new WP_Query($args);
    $total_transfers = $transfers_query->found_posts;
    $total_pages = ceil($total_transfers / $per_page);

    echo '<div class="wrap">';
    echo '<h1>طلبات التحويل المالي</h1>';
    echo '<p>إجمالي الطلبات: ' . intval($total_transfers) . '</p>';

    if ($transfers_query->have_posts()) {
        echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
        echo '<th>رقم الطلب</th>';
        echo '<th>المبنى</th>';
        echo '<th>المبلغ</th>';
        echo '<th>الحالة</th>';
        echo '<th>تاريخ الطلب</th>';
        echo '<th>الإجراءات</th>';
        echo '</tr></thead><tbody>';

        while ($transfers_query->have_posts()) {
            $transfers_query->the_post();
            $transfer_id = get_the_ID();
            $building_id = get_post_meta($transfer_id, 'building_id', true);
            $amount = get_post_meta($transfer_id, 'amount', true);
            $status = get_post_meta($transfer_id, 'status', true);
            $status_label = '';
            switch ($status) {
                case 'pending': $status_label = 'في الانتظار'; break;
                case 'approved': $status_label = 'معتمد'; break;
                case 'rejected': $status_label = 'مرفوض'; break;
                default: $status_label = 'غير محدد'; break;
            }

            echo '<tr>';
            echo '<td>' . $transfer_id . '</td>';
            echo '<td>' . esc_html($building_id ? get_the_title(intval($building_id)) : '—') . '</td>';
            echo '<td>' . ($amount ? number_format(floatval($amount), 2) . ' جنيه' : '—') . '</td>';
            echo '<td>' . $status_label . '</td>';
            echo '<td>' . get_the_date() . '</td>';
            echo '<td>';
            if ($status === 'pending') {
                echo '<form method="post" style="display:inline;">';
                wp_nonce_field('transfer_action', 'transfer_action_nonce');
                echo '<input type="hidden" name="transfer_id" value="' . $transfer_id . '">';
                echo '<button type="submit" name="approve_transfer" class="button button-primary">موافقة</button> ';
                echo '<button type="submit" name="reject_transfer" class="button button-secondary">رفض</button>';
                echo '</form>';
            }
            echo ' <a href="' . esc_url(get_edit_post_link($transfer_id)) . '" target="_blank">عرض</a>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        if ($total_pages > 1) {
            echo '<div class="tablenav bottom"><div class="tablenav-pages">';
            echo paginate_links(array(
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => __('&laquo;'),
                'next_text' => __('&raquo;'),
                'total' => $total_pages,
                'current' => $paged
            ));
            echo '</div></div>';
        }
    } else {
        echo '<p>لا توجد طلبات تحويل.</p>';
    }

    wp_reset_postdata();
    echo '</div>';
}

/**
 * Admin page: manage tenants bindings
 */
function ms_admin_tenants_page()
{
    if (!current_user_can('manage_options') && !(function_exists('ms_user_has_role') && ms_user_has_role(get_current_user_id(), 'building_manager'))) {
        wp_die('غير مصرح لك بالوصول إلى هذه الصفحة.');
    }

    global $wpdb;
    $ten_table = $wpdb->prefix . 'ms_unit_tenants';

    // Handle saving new binding
    if (isset($_POST['ms_save_tenant_binding']) && wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ms_save_tenant_binding')) {
        $unit_id = isset($_POST['unit_id']) ? intval($_POST['unit_id']) : 0;
        $building_id = isset($_POST['building_id']) ? intval($_POST['building_id']) : 0;
        $tenant_user_id = isset($_POST['tenant_id']) ? intval($_POST['tenant_id']) : 0;
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';

        if (!$unit_id || !$building_id || !$tenant_user_id || empty($start_date)) {
            echo '<div class="notice notice-error"><p>الرجاء ملء جميع الحقول المطلوبة.</p></div>';
        } else {
            $new_id = false;
            if (function_exists('ms_link_tenant_to_unit')) {
                $new_id = ms_link_tenant_to_unit($tenant_user_id, $unit_id, $building_id, $start_date);
            } else {
                $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$ten_table} WHERE unit_id = %d AND (end_date IS NULL OR end_date = '') LIMIT 1", $unit_id));
                if ($existing) {
                    $wpdb->update($ten_table, array('end_date' => current_time('Y-m-d')), array('id' => intval($existing->id)), array('%s'), array('%d'));
                }
                $inserted = $wpdb->insert($ten_table, array(
                    'building_id' => $building_id,
                    'unit_id' => $unit_id,
                    'tenant_id' => $tenant_user_id,
                    'start_date' => $start_date,
                    'end_date' => null,
                    'status' => 'active',
                    'created_at' => current_time('mysql'),
                ), array('%d','%d','%d','%s','%s','%s','%s'));
                if ($inserted !== false) {
                    $new_id = intval($wpdb->insert_id);
                }
            }

            if ($new_id) {
                if (function_exists('ms_add_notification')) {
                    ms_add_notification($tenant_user_id, 'tenant_linked', "تم ربطك بالوحدة #{$unit_id} في المبنى.", $building_id, $new_id);
                }
                echo '<div class="notice notice-success"><p>تم حفظ الربط بنجاح.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>فشل حفظ الربط.</p></div>';
            }
        }
    }

    // Handle ending binding
    if (isset($_POST['ms_end_tenant_binding']) && wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ms_end_tenant_binding')) {
        $binding_id = isset($_POST['binding_id']) ? intval($_POST['binding_id']) : 0;
        if ($binding_id) {
            $updated = $wpdb->update($ten_table, array('end_date' => current_time('Y-m-d')), array('id' => $binding_id), array('%s'), array('%d'));
            if ($updated !== false) {
                echo '<div class="notice notice-success"><p>تم إنهاء الربط بنجاح.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>فشل إنهاء الربط.</p></div>';
            }
        }
    }

    // Display page
    $current_user = wp_get_current_user();
    $is_admin = current_user_can('manage_options');
    $building_id = isset($_GET['building_id']) ? intval($_GET['building_id']) : 0;

    // get buildings list
    if ($is_admin) {
        $buildings = function_exists('ms_get_buildings_by_manager') ? ms_get_buildings_by_manager(0) : array();
    } else {
        $buildings = function_exists('ms_get_buildings_by_manager') ? ms_get_buildings_by_manager($current_user->ID) : array();
    }

    echo '<div class="wrap"><h1>إدارة المستأجرين</h1>';
    echo '<form method="get" style="margin-bottom:12px">';
    echo '<input type="hidden" name="page" value="mostaager-tenants">';
    echo '<label style="margin-right:8px">اختر المبنى: </label>';
    echo '<select name="building_id">';
    echo '<option value="0">-- اختر مبنى --</option>';
    foreach ($buildings as $b) {
        $bid = intval($b->id ?? $b->ID ?? 0);
        $title = esc_html($b->title ?? $b->post_title ?? 'Building #' . $bid);
        $sel = ($bid === $building_id) ? ' selected' : '';
        echo '<option value="' . $bid . '"' . $sel . '>' . $title . ' (#' . $bid . ')</option>';
    }
    echo '</select> <button class="button">تصفية</button>';
    echo '</form>';

    if (!$building_id) {
        echo '<p>اختر مبنى لعرض الوحدات.</p>';
        echo '</div>';
        return;
    }

    // units
    $units = function_exists('ms_get_units_by_building') ? ms_get_units_by_building($building_id) : array();
    echo '<table class="widefat fixed striped"><thead><tr><th>الوحدة</th><th>المستأجر الحالي</th><th>تاريخ البداية</th><th>إجراء</th></tr></thead><tbody>';
    $tenant_users = get_users(array(
        'role' => 'tenant',
        'orderby' => 'display_name',
        'order' => 'ASC',
        'fields' => array('ID', 'display_name'),
    ));

    if (empty($units)) {
        echo '<tr><td colspan="4">لا توجد وحدات لهذا المبنى.</td></tr>';
    } else {
        foreach ($units as $unit) {
            $unit_id = intval($unit->id ?? $unit->ID ?? 0);
            $binding = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$ten_table} WHERE unit_id = %d AND (end_date IS NULL OR end_date = '') LIMIT 1", $unit_id));
            echo '<tr>';
            echo '<td>الوحدة #' . $unit_id . '</td>';
            if ($binding) {
                $tenant_user = get_userdata(intval($binding->tenant_id ?? $binding->tenant_user_id));
                $tenant_name = $tenant_user ? $tenant_user->display_name : ('User #' . intval($binding->tenant_id ?? $binding->tenant_user_id));
                echo '<td>' . esc_html($tenant_name) . '</td>';
                echo '<td>' . esc_html($binding->start_date ?? '') . '</td>';
                echo '<td>';
                echo '<form method="post" style="display:inline">';
                wp_nonce_field('ms_end_tenant_binding');
                echo '<input type="hidden" name="binding_id" value="' . intval($binding->id) . '">';
                echo '<button type="submit" name="ms_end_tenant_binding" class="button button-secondary">إنهاء الربط</button>';
                echo '</form>';
                echo '</td>';
            } else {
                echo '<td>—</td>';
                echo '<td>—</td>';
                echo '<td>';
                echo '<form method="post" style="display:flex;gap:8px;align-items:center">';
                wp_nonce_field('ms_save_tenant_binding');
                echo '<input type="hidden" name="building_id" value="' . intval($building_id) . '">';
                echo '<input type="hidden" name="unit_id" value="' . $unit_id . '">';
                echo '<select name="tenant_id" style="width:180px;padding:6px">';
                echo '<option value="0">-- اختر مستأجراً --</option>';
                foreach ($tenant_users as $tenant_user) {
                    echo '<option value="' . intval($tenant_user->ID) . '">' . esc_html($tenant_user->display_name) . ' (#' . intval($tenant_user->ID) . ')</option>';
                }
                echo '</select>';
                echo '<input type="date" name="start_date" style="padding:6px">';
                echo '<button type="submit" name="ms_save_tenant_binding" class="button button-primary">حفظ الربط</button>';
                echo '</form>';
                echo '</td>';
            }
            echo '</tr>';
        }
    }
    echo '</tbody></table>';
    echo '</div>';
}

/**
 * Admin page: improved MS invoices listing with building/unit/date filters
 */
function ms_admin_ms_invoices_page()
{
    if (!current_user_can('manage_options')) {
        wp_die('غير مصرح لك بالوصول إلى هذه الصفحة.');
    }

    global $wpdb;
    $tbl = $wpdb->prefix . 'ms_invoices';
    $building_filter = isset($_GET['ms_building_id']) ? intval($_GET['ms_building_id']) : 0;
    $date_from = isset($_GET['ms_date_from']) ? sanitize_text_field($_GET['ms_date_from']) : '';
    $date_to = isset($_GET['ms_date_to']) ? sanitize_text_field($_GET['ms_date_to']) : '';
    $status_filter = isset($_GET['ms_status']) ? sanitize_text_field($_GET['ms_status']) : '';

    $where = array();
    $params = array();
    if ($building_filter) {
        $where[] = 'building_id = %d';
        $params[] = $building_filter;
    }
    if ($status_filter) {
        $where[] = 'status = %s';
        $params[] = $status_filter;
    }
    if ($date_from && $date_to) {
        $where[] = 'due_date BETWEEN %s AND %s';
        $params[] = $date_from;
        $params[] = $date_to;
    } elseif ($date_from) {
        $where[] = 'due_date >= %s';
        $params[] = $date_from;
    } elseif ($date_to) {
        $where[] = 'due_date <= %s';
        $params[] = $date_to;
    }

    $sql = "SELECT * FROM {$tbl}";
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY id DESC LIMIT 500';

    if (!empty($params)) {
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params));
    } else {
        $rows = $wpdb->get_results($sql);
    }

    // fetch buildings list for filter
    $buildings = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}ms_buildings ORDER BY title ASC");

    echo '<div class="wrap"><h1>الفواتير (MS)</h1>';
    echo '<form method="get" style="margin-bottom:12px">';
    echo '<input type="hidden" name="page" value="mostaager-ms-invoices">';
    echo '<label style="margin-right:8px">المبنى: </label>';
    echo '<select name="ms_building_id">';
    echo '<option value="0">الكل</option>';
    foreach ($buildings as $b) {
        $sel = ($building_filter && intval($b->id) === $building_filter) ? ' selected' : '';
        echo '<option value="' . intval($b->id) . '"' . $sel . '>' . esc_html($b->title) . ' (#' . intval($b->id) . ')</option>';
    }
    echo '</select> ';
    echo '<label style="margin-left:12px;margin-right:8px">من: </label><input type="date" name="ms_date_from" value="' . esc_attr($date_from) . '">';
    echo '<label style="margin-left:8px;margin-right:8px">إلى: </label><input type="date" name="ms_date_to" value="' . esc_attr($date_to) . '">';
    echo '<label style="margin-left:12px;margin-right:8px">الحالة: </label>';
    echo '<select name="ms_status">';
    $opts = array('' => 'الكل', 'pending' => 'معلقة', 'paid' => 'مدفوع', 'canceled' => 'ملغي');
    foreach ($opts as $k => $v) {
        $sel = ($k === $status_filter) ? ' selected' : '';
        echo '<option value="' . esc_attr($k) . '"' . $sel . '>' . esc_html($v) . '</option>';
    }
    echo '</select> ';
    echo '<button class="button">تصفية</button>';
    echo '</form>';

    echo '<table class="widefat fixed striped"><thead><tr>';
    echo '<th>ID</th><th>المبنى</th><th>الوحدة</th><th>نوع الدافع</th><th>اسم الشخص</th><th>المبلغ</th><th>الحالة</th><th>تاريخ الاستحقاق</th><th>أنشئت بتاريخ</th><th>إجراءات</th>';
    echo '</tr></thead><tbody>';

    if (empty($rows)) {
        echo '<tr><td colspan="10">لا توجد فواتير.</td></tr>';
    } else {
        foreach ($rows as $invoice) {
            $invoice_id = intval($invoice->id ?? $invoice->ID ?? 0);
            $building_title = $invoice->building_id ? ms_admin_get_building_label(intval($invoice->building_id)) : '—';
            $unit_label = $invoice->unit_id ? 'Unit #' . intval($invoice->unit_id) : '—';
            $payer_type_map = array('owner' => 'مالك','tenant' => 'مستأجر','agent' => 'وسيط','owner_or_tenant' => 'مالك/مستأجر');
            $payer_type = strtolower(trim($invoice->payer_type ?? ''));
            $payer_type_label = $payer_type_map[$payer_type] ?? 'غير معروف';
            $user = get_userdata(intval($invoice->user_id));
            $payer_name = $user ? $user->display_name : esc_html($invoice->payer_name ?? '--');
            $status_norm = strtolower(trim($invoice->status ?? ''));
            $paid_label = ($status_norm === 'paid') ? '<span style="color:#10b981;font-weight:600">مدفوع</span>' : ($status_norm === 'canceled' ? '<span style="color:#ef4444;font-weight:600">ملغي</span>' : esc_html($invoice->status));
            $action = '';
            if ($status_norm === 'pending') {
                $action .= '<a href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=ms_mark_invoice_paid&invoice_id=' . $invoice_id), 'ms_mark_invoice_paid_' . $invoice_id)) . '" class="button button-secondary">تحديد كمدفوع</a>';
                $action .= ' ' . '<a href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=ms_cancel_invoice&invoice_id=' . $invoice_id), 'ms_cancel_invoice_' . $invoice_id)) . '" class="button button-link-delete" onclick="return confirm(\'Are you sure you want to cancel this invoice?\')">إلغاء</a>';
            }

            echo '<tr>';
            echo '<td>' . $invoice_id . '</td>';
            echo '<td>' . esc_html($building_title) . '</td>';
            echo '<td>' . esc_html($unit_label) . '</td>';
            echo '<td>' . esc_html($payer_type_label) . '</td>';
            echo '<td>' . esc_html($payer_name) . '</td>';
            echo '<td>ج.م ' . number_format_i18n(floatval($invoice->amount), 2) . '</td>';
            echo '<td>' . $paid_label . '</td>';
            echo '<td>' . esc_html($invoice->due_date) . '</td>';
            echo '<td>' . esc_html($invoice->created_at ?? '') . '</td>';
            echo '<td>' . $action . '</td>';
            echo '</tr>';
        }
    }

    echo '</tbody></table></div>';
}

/**
 * Admin page: improved MS transfers with manager name, document link and rejection note
 */
function ms_admin_ms_transfers_page()
{
    if (!current_user_can('manage_options')) {
        wp_die('غير مصرح لك بالوصول إلى هذه الصفحة.');
    }

    global $wpdb;

    // handle approve/reject with rejection note and notifications
    if (isset($_POST['ms_transfer_action']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'ms_ms_transfers_action')) {
        $action = sanitize_text_field($_POST['ms_transfer_action']);
        $transfer_id = isset($_POST['transfer_id']) ? intval($_POST['transfer_id']) : 0;
        if ($transfer_id) {
            if ($action === 'approve') {
                update_post_meta($transfer_id, 'status', 'approved');
                update_post_meta($transfer_id, 'approved_by', get_current_user_id());
                update_post_meta($transfer_id, 'approved_date', current_time('mysql'));
                // notify manager
                $building_id = get_post_meta($transfer_id, 'building_id', true);
                $manager_id = 0;
                if ($building_id) {
                    $manager_id = $wpdb->get_var($wpdb->prepare("SELECT manager_id FROM {$wpdb->prefix}ms_buildings WHERE id = %d", intval($building_id)));
                    if (!$manager_id) {
                        $manager_id = get_post_meta($building_id, 'manager_id', true);
                    }
                }
                if ($manager_id && function_exists('ms_add_notification')) {
                    ms_add_notification(intval($manager_id), 'transfer_approved', 'تمت الموافقة على طلب التحويل المالي الخاص بك.', $building_id, $transfer_id);
                }
            } elseif ($action === 'reject') {
                $note = isset($_POST['rejection_note']) ? sanitize_textarea_field($_POST['rejection_note']) : '';
                update_post_meta($transfer_id, 'status', 'rejected');
                update_post_meta($transfer_id, 'rejected_by', get_current_user_id());
                update_post_meta($transfer_id, 'rejected_date', current_time('mysql'));
                update_post_meta($transfer_id, 'rejection_note', $note);
                // notify manager with note
                $building_id = get_post_meta($transfer_id, 'building_id', true);
                $manager_id = 0;
                if ($building_id) {
                    $manager_id = $wpdb->get_var($wpdb->prepare("SELECT manager_id FROM {$wpdb->prefix}ms_buildings WHERE id = %d", intval($building_id)));
                    if (!$manager_id) {
                        $manager_id = get_post_meta($building_id, 'manager_id', true);
                    }
                }
                if ($manager_id && function_exists('ms_add_notification')) {
                    $msg = 'تم رفض طلب التحويل المالي.' . ($note ? " ملاحظة: {$note}" : '');
                    ms_add_notification(intval($manager_id), 'transfer_rejected', $msg, $building_id, $transfer_id);
                }
            }
        }
    }

    // filters
    $status_filter = isset($_GET['ms_transfer_status']) ? sanitize_text_field($_GET['ms_transfer_status']) : '';
    $args = array('post_type' => 'transfers', 'posts_per_page' => 50, 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'DESC');
    if ($status_filter) {
        $args['meta_query'] = array(array('key' => 'status', 'value' => $status_filter, 'compare' => '='));
    }

    $transfers_query = new WP_Query($args);

    echo '<div class="wrap"><h1>التحويلات (MS)</h1>';
    echo '<form method="get" style="margin-bottom:12px">';
    echo '<input type="hidden" name="page" value="mostaager-ms-transfers">';
    echo '<label style="margin-right:8px">الحالة: </label>';
    echo '<select name="ms_transfer_status">';
    $opts = array('' => 'الكل', 'pending' => 'معلق', 'approved' => 'معتمد', 'rejected' => 'مرفوض');
    foreach ($opts as $k => $v) {
        $sel = ($k === $status_filter) ? ' selected' : '';
        echo '<option value="' . esc_attr($k) . '"' . $sel . '>' . esc_html($v) . '</option>';
    }
    echo '</select> <button class="button">تصفية</button>';
    echo '</form>';

    if ($transfers_query->have_posts()) {
        echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
        echo '<th>رقم الطلب</th><th>المبنى</th><th>المدير</th><th>المبلغ</th><th>الحالة</th><th>المستند</th><th>تاريخ الطلب</th><th>الإجراءات</th>';
        echo '</tr></thead><tbody>';

        while ($transfers_query->have_posts()) {
            $transfers_query->the_post();
            $transfer_id = get_the_ID();
            $building_id = get_post_meta($transfer_id, 'building_id', true);
            $amount = get_post_meta($transfer_id, 'amount', true);
            $status = get_post_meta($transfer_id, 'status', true) ?: 'pending';
            // resolve manager id from ms_buildings then post_meta fallback
            $manager_id = 0;
            if ($building_id) {
                $manager_id = $wpdb->get_var($wpdb->prepare("SELECT manager_id FROM {$wpdb->prefix}ms_buildings WHERE id = %d", intval($building_id)));
                if (!$manager_id) {
                    $manager_id = get_post_meta($building_id, 'manager_id', true);
                }
            }
            $manager_name = $manager_id ? (get_userdata(intval($manager_id))->display_name ?? 'User #' . intval($manager_id)) : '—';

            // document link
            $doc_url = '';
            $invoice_file_id = get_post_meta($transfer_id, 'invoice_file', true);
            if ($invoice_file_id) {
                $doc_url = wp_get_attachment_url(intval($invoice_file_id));
            }
            if (!$doc_url) {
                $doc_url = get_post_meta($transfer_id, 'document_url', true) ?: '';
            }
            if (!$doc_url) {
                $att = get_post_meta($transfer_id, 'attachment_id', true);
                if ($att) {
                    $doc_url = wp_get_attachment_url(intval($att));
                }
            }

            $building_label = $building_id ? ms_admin_get_building_label(intval($building_id)) : '—';

            echo '<tr>';
            echo '<td>' . $transfer_id . '</td>';
            echo '<td>' . esc_html($building_label) . '</td>';
            echo '<td>' . esc_html($manager_name) . '</td>';
            echo '<td>' . ($amount ? number_format(floatval($amount), 2) . ' جنيه' : '—') . '</td>';
            echo '<td>' . esc_html($status) . '</td>';
            echo '<td>' . ($doc_url ? '<a href="' . esc_url($doc_url) . '" target="_blank">عرض المستند</a>' : '—') . '</td>';
            echo '<td>' . get_the_date() . '</td>';
            echo '<td>';
            if ($status === 'pending') {
                echo '<form method="post" style="display:inline">';
                wp_nonce_field('ms_ms_transfers_action');
                echo '<input type="hidden" name="transfer_id" value="' . intval($transfer_id) . '">';
                echo '<button type="submit" name="ms_transfer_action" value="approve" class="button button-primary">موافقة</button> ';
                echo '<button type="button" class="button button-secondary" onclick="document.getElementById(\'reject-wrap-' . $transfer_id . '\').style.display=\'block\'">رفض</button>';
                echo '<div id="reject-wrap-' . $transfer_id . '" style="display:none;margin-top:8px">';
                echo '<textarea name="rejection_note" placeholder="ملاحظة الرفض" style="width:100%;min-height:80px"></textarea><br/>';
                echo '<button type="submit" name="ms_transfer_action" value="reject" class="button button-secondary" style="margin-top:6px">تأكيد الرفض</button>';
                echo '</div>';
                echo '</form>';
            }
            echo ' <a href="' . esc_url(get_edit_post_link($transfer_id)) . '" target="_blank">عرض</a>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    } else {
        echo '<p>لا توجد طلبات تحويل.</p>';
    }

    wp_reset_postdata();
    echo '</div>';
}

function ms_units_table_exists()
{
    global $wpdb;
    $unit_tbl = $wpdb->prefix . 'ms_units';
    return (bool) $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($unit_tbl)));
}

function ms_admin_ensure_ms_units_table()
{
    if (!ms_units_table_exists() && function_exists('ms_create_tables')) {
        ms_create_tables();
    }
    return ms_units_table_exists();
}

function ms_get_property_sync_candidates()
{
    global $wpdb;
    if (!ms_admin_ensure_ms_units_table()) {
        return array();
    }
    $unit_tbl = $wpdb->prefix . 'ms_units';
    return $wpdb->get_results("SELECT * FROM {$unit_tbl} WHERE building_id > 0 ORDER BY id DESC");
}

function ms_get_total_units_count()
{
    global $wpdb;
    if (!ms_admin_ensure_ms_units_table()) {
        return 0;
    }
    $unit_tbl = $wpdb->prefix . 'ms_units';
    return intval($wpdb->get_var("SELECT COUNT(*) FROM {$unit_tbl}"));
}

function ms_get_property_posts_with_building_id()
{
    $args = array(
        'post_type' => 'property',
        'post_status' => array('publish', 'pending', 'draft', 'expired', 'houzez_sold', 'disapproved', 'on_hold', 'private', 'future'),
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => 'building_id',
                'compare' => 'EXISTS',
            ),
        ),
        'fields' => 'all',
    );

    return get_posts($args);
}

function ms_extract_first_meta_id($value)
{
    if (is_array($value)) {
        foreach ($value as $item) {
            $id = ms_extract_first_meta_id($item);
            if ($id) {
                return $id;
            }
        }
        return 0;
    }

    if (is_string($value)) {
        if ($value === '') {
            return 0;
        }

        if (is_serialized($value)) {
            return ms_extract_first_meta_id(maybe_unserialize($value));
        }

        $delimiters = array(',', '|');
        foreach ($delimiters as $delimiter) {
            if (strpos($value, $delimiter) !== false) {
                foreach (explode($delimiter, $value) as $token) {
                    $id = intval(trim($token));
                    if ($id > 0) {
                        return $id;
                    }
                }
            }
        }

        return intval($value);
    }

    return intval($value);
}

function ms_generate_units_from_properties()
{
    global $wpdb;
    if (!ms_admin_ensure_ms_units_table()) {
        return array(
            'created' => 0,
            'skipped' => 0,
            'report' => array('Cannot generate units because the ms_units table is missing and could not be created.'),
        );
    }

    $unit_tbl = $wpdb->prefix . 'ms_units';
    $properties = ms_get_property_posts_with_building_id();
    $created = 0;
    $skipped = 0;
    $report = array();

    foreach ($properties as $property) {
        $building_id = ms_extract_first_meta_id(get_post_meta($property->ID, 'building_id', true));
        if (!$building_id) {
            $report[] = "Property {$property->ID} skipped: missing building_id.";
            $skipped++;
            continue;
        }

        $owner_id = ms_extract_first_meta_id(get_post_meta($property->ID, 'owner_id', true));
        if (!$owner_id) {
            $owner_id = ms_extract_first_meta_id(get_post_meta($property->ID, 'property_owner', true));
        }
        if (!$owner_id) {
            $owner_id = intval($property->post_author);
        }

        $agent_id = ms_extract_first_meta_id(get_post_meta($property->ID, 'fave_agents', true));
        if (!$agent_id) {
            $agent_id = ms_extract_first_meta_id(get_post_meta($property->ID, 'fave_property_agent', true));
        }
        if (!$agent_id) {
            $agent_id = ms_extract_first_meta_id(get_post_meta($property->ID, 'property_agent', true));
        }

        $tenant_id = ms_extract_first_meta_id(get_post_meta($property->ID, 'tenant_id', true));
        $status = $tenant_id ? 'occupied' : 'available';

        $existing = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$unit_tbl} WHERE building_id=%d AND owner_id=%d AND agent_id=%d AND tenant_id=%d", $building_id, $owner_id, $agent_id, $tenant_id));
        if ($existing) {
            $report[] = "Property {$property->ID} skipped: matching unit already exists.";
            $skipped++;
            continue;
        }

        $inserted = $wpdb->insert($unit_tbl, array(
            'building_id' => $building_id,
            'owner_id' => $owner_id,
            'tenant_id' => $tenant_id,
            'agent_id' => $agent_id,
            'status' => $status,
            'created_at' => current_time('mysql'),
        ), array('%d','%d','%d','%d','%s','%s'));

        if ($inserted !== false) {
            $created++;
            $report[] = "Created unit from property {$property->ID} (building {$building_id}).";
        } else {
            $error_message = trim($wpdb->last_error);
            $query = trim($wpdb->last_query);
            $report[] = "Property {$property->ID} failed to create unit." . ($error_message ? " DB error: {$error_message}." : '');
            if ($query) {
                $report[] = "Last query: {$query}";
            }
            $skipped++;
        }
    }

    return array('created' => $created, 'skipped' => $skipped, 'report' => $report);
}

function ms_sync_units_to_houzez_properties()
{
    $report = array();
    $updated = 0;
    $skipped = 0;

    if (!ms_admin_ensure_ms_units_table()) {
        return array(
            'updated' => 0,
            'skipped' => 0,
            'report' => array('Cannot sync units because the ms_units table is missing and could not be created.'),
        );
    }

    $units = ms_get_property_sync_candidates();

    if (empty($units)) {
        return array('updated' => 0, 'skipped' => 0, 'report' => array('No unit rows found for sync.'));
    }

    foreach ($units as $unit) {
        $building_id = intval($unit->building_id);
        if (!$building_id) {
            $report[] = "Unit {$unit->id} skipped: missing building_id.";
            $skipped++;
            continue;
        }

        $props = get_posts(array(
            'post_type' => 'property',
            'post_status' => array('publish', 'pending', 'draft', 'expired', 'houzez_sold', 'disapproved', 'on_hold', 'private', 'future'),
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'building_id',
                    'value' => $building_id,
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ),
            ),
            'fields' => 'all',
        ));

        if (empty($props)) {
            $report[] = "Unit {$unit->id} skipped: no property posts found for building_id {$building_id}.";
            $skipped++;
            continue;
        }

        $property = null;
        if (count($props) === 1) {
            $property = $props[0];
        } else {
            foreach ($props as $p) {
                if ($unit->owner_id && intval($p->post_author) === intval($unit->owner_id)) {
                    $property = $p;
                    break;
                }
                $owner_meta = get_post_meta($p->ID, 'owner_id', true);
                if ($unit->owner_id && intval($owner_meta) === intval($unit->owner_id)) {
                    $property = $p;
                    break;
                }
            }
            if (!$property) {
                $property = $props[0];
            }
        }

        $needs_update = false;
        $property_id = $property->ID;
        $current_owner = get_post_meta($property_id, 'owner_id', true);
        $current_owner_alt = get_post_meta($property_id, 'property_owner', true);
        $current_agent = get_post_meta($property_id, 'fave_agents', true);
        $current_agent_alt = get_post_meta($property_id, 'fave_property_agent', true);
        $current_tenant = get_post_meta($property_id, 'tenant_id', true);

        if ($unit->owner_id && (!$current_owner || intval($current_owner) !== intval($unit->owner_id))) {
            update_post_meta($property_id, 'owner_id', intval($unit->owner_id));
            update_post_meta($property_id, 'property_owner', intval($unit->owner_id));
            $needs_update = true;
        }

        if ($unit->agent_id && (!$current_agent || intval($current_agent) !== intval($unit->agent_id))) {
            update_post_meta($property_id, 'fave_agents', intval($unit->agent_id));
            update_post_meta($property_id, 'fave_property_agent', intval($unit->agent_id));
            $needs_update = true;
        } elseif ($unit->agent_id && !$current_agent_alt) {
            update_post_meta($property_id, 'fave_property_agent', intval($unit->agent_id));
            $needs_update = true;
        }

        if ($unit->tenant_id && (!$current_tenant || intval($current_tenant) !== intval($unit->tenant_id))) {
            update_post_meta($property_id, 'tenant_id', intval($unit->tenant_id));
            $needs_update = true;
        }

        if ($needs_update) {
            $updated++;
            $report[] = "Unit {$unit->id} synced to property {$property_id} (building {$building_id}).";
        } else {
            $skipped++;
            $report[] = "Unit {$unit->id} already in sync for property {$property_id}.";
        }
    }

    return array('updated' => $updated, 'skipped' => $skipped, 'report' => $report);
}

function ms_admin_migrations_page()
{
    $status = '';
    $report = array();

    if (isset($_POST['ms_migrate_legacy_buildings']) && check_admin_referer('ms_migrate_legacy_buildings')) {
        $result = ms_admin_migrate_legacy_buildings();
        $status = sprintf('Migrated %d legacy buildings, skipped %d existing.', intval($result['migrated']), intval($result['skipped']));
        $report = $result['report'];
    }

    if (isset($_POST['ms_migrate_legacy_expenses']) && check_admin_referer('ms_migrate_legacy_expenses')) {
        $result = ms_admin_migrate_legacy_expenses();
        $status = sprintf('Migrated %d legacy expenses, skipped %d existing or invalid.', intval($result['migrated']), intval($result['skipped']));
        $report = $result['report'];
    }

    if (isset($_POST['ms_migrate_legacy_invoices']) && check_admin_referer('ms_migrate_legacy_invoices')) {
        $result = ms_admin_migrate_legacy_invoices();
        $status = sprintf('Migrated %d legacy invoices, skipped %d existing or invalid.', intval($result['migrated']), intval($result['skipped']));
        $report = $result['report'];
    }

    if (isset($_POST['ms_migrate_legacy_transfers']) && check_admin_referer('ms_migrate_legacy_transfers')) {
        $result = ms_admin_migrate_legacy_transfers();
        $status = sprintf('Migrated %d legacy transfers, skipped %d existing or invalid.', intval($result['migrated']), intval($result['skipped']));
        $report = $result['report'];
    }

    if (isset($_POST['ms_migrate_legacy_all']) && check_admin_referer('ms_migrate_legacy_all')) {
        $result = ms_admin_migrate_legacy_all();
        $status = sprintf('Migrated %d records across legacy CPTs, skipped %d existing items.', intval($result['migrated']), intval($result['skipped']));
        $report = $result['report'];
    }

    if (isset($_POST['ms_run_unit_generation']) && check_admin_referer('ms_unit_generation')) {
        $result = ms_generate_units_from_properties();
        $status = sprintf('Created %d units, skipped %d properties.', intval($result['created']), intval($result['skipped']));
        $report = $result['report'];
    }

    $legacy_counts = array(
        'building' => ms_get_legacy_cpt_count('building'),
        'expenses' => ms_get_legacy_cpt_count('expenses'),
        'invoices' => ms_get_legacy_cpt_count('invoices'),
        'transfers' => ms_get_legacy_cpt_count('transfers'),
    );
    $legacy_total = array_sum($legacy_counts);

    $units = ms_get_property_sync_candidates();
    $units_count = count($units);
    $total_units = ms_get_total_units_count();
    $properties_with_building = ms_get_property_posts_with_building_id();
    $properties_with_building_count = count($properties_with_building);
    $properties_count = 0;
    if (post_type_exists('property')) {
        $counts = wp_count_posts('property');
        $properties_count = intval($counts->publish) + intval($counts->pending) + intval($counts->draft);
    }
    $can_generate_units = ($properties_with_building_count > 0);

    echo '<div class="wrap"><h1>Mostaager Migrations</h1>';
    echo '<p>Use this page to migrate legacy Mostager CPT content into the current plugin tables.</p>';

    echo '<div style="margin:16px 0;padding:12px;border:1px solid #ccd0d4;background:#f9f9f9;">';
    echo '<strong>Legacy buildings:</strong> ' . intval($legacy_counts['building']) . '<br>';
    echo '<strong>Legacy expenses:</strong> ' . intval($legacy_counts['expenses']) . '<br>';
    echo '<strong>Legacy invoices:</strong> ' . intval($legacy_counts['invoices']) . '<br>';
    echo '<strong>Legacy transfers:</strong> ' . intval($legacy_counts['transfers']) . '<br>';
    echo '<strong>Current ms_units rows:</strong> ' . intval($total_units) . '<br>';
    echo '<strong>Houzez property posts:</strong> ' . intval($properties_count) . '<br>';
    echo '<strong>Property posts with building_id:</strong> ' . intval($properties_with_building_count) . '</div>';

    if ($legacy_total === 0) {
        echo '<div class="notice notice-info inline"><p>No legacy Mostager CPT records were detected. Use the Houzez property generator below to seed <code>ms_units</code> if needed.</p></div>';
    }

    if ($status) {
        echo '<div class="notice notice-success inline"><p>' . esc_html($status) . '</p></div>';
        if (!empty($report)) {
            echo '<div style="margin:16px 0;padding:12px;border:1px solid #e6e6e6;background:#fff;max-height:320px;overflow:auto;"><strong>Migration log:</strong><ul>';
            foreach ($report as $line) {
                echo '<li>' . esc_html($line) . '</li>';
            }
            echo '</ul></div>';
        }
    }

    echo '<h2>Legacy CPT migration</h2>';
    echo '<table class="widefat fixed striped"><thead><tr>';
    echo '<th>Legacy CPT</th><th>Count</th><th>Action</th>';
    echo '</tr></thead><tbody>';

    $legacy_types = array(
        'building' => 'Buildings',
        'expenses' => 'Expenses',
        'invoices' => 'Invoices',
        'transfers' => 'Transfers',
    );

    foreach ($legacy_types as $type => $label) {
        echo '<tr>';
        echo '<td>' . esc_html($label) . '</td>';
        echo '<td>' . intval($legacy_counts[$type]) . '</td>';
        echo '<td>';
        if ($legacy_counts[$type] > 0) {
            echo '<form method="post" style="display:inline-block;margin:0;">';
            wp_nonce_field('ms_migrate_legacy_' . $type);
            echo '<input type="hidden" name="ms_migrate_legacy_' . esc_attr($type) . '" value="1">';
            echo '<button type="submit" class="button button-secondary">Migrate ' . esc_html($label) . '</button>';
            echo '</form>';
        } else {
            echo 'No legacy records';
        }
        echo '</td>';
        echo '</tr>';
    }

    echo '<tr><td colspan="3">';
    echo '<form method="post">';
    wp_nonce_field('ms_migrate_legacy_all');
    echo '<input type="hidden" name="ms_migrate_legacy_all" value="1">';
    echo '<button type="submit" class="button button-primary"' . ($legacy_total === 0 ? ' disabled' : '') . '>Migrate All Legacy CPTs</button>';
    echo '</form>';
    echo '</td></tr>';
    echo '</tbody></table>';

    echo '<h2>Houzez property unit generator</h2>';
    if ($can_generate_units) {
        if ($total_units > 0) {
            echo '<div class="notice notice-warning inline"><p>Existing <code>ms_units</code> rows were detected. This generator will only add missing units for Houzez properties that do not already map to an existing unit.</p></div>';
        }
        $button_label = $total_units === 0 ? 'Seed ms_units from Houzez properties' : 'Generate missing ms_units rows from Houzez properties';
        echo '<form method="post">';
        wp_nonce_field('ms_unit_generation');
        echo '<input type="hidden" name="ms_run_unit_generation" value="1">';
        echo '<p><button type="submit" class="button button-secondary" onclick="return confirm(\'Confirm generation of ms_units rows from Houzez properties?\')">' . esc_html($button_label) . '</button></p>';
        echo '</form>';
    } else {
        echo '<div class="notice notice-warning inline"><p>Generation is unavailable because no Houzez properties are tagged with <code>building_id</code>.</p></div>';
    }

    echo '<h2>Notes</h2>';
    echo '<p>The legacy migration imports <strong>building</strong>, <strong>expenses</strong>, <strong>invoices</strong>, and <strong>transfers</strong> CPTs into the current Mostaager tables.</p>';
    echo '<p>If no legacy CPT records are available, use the Houzez property generator to seed <code>ms_units</code> from existing properties.</p>';
    echo '</div>';
}

function ms_get_legacy_cpt_count($post_type)
{
    global $wpdb;
    return intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status != %s", $post_type, 'auto-draft')));
}

function ms_get_legacy_cpt_posts($post_type)
{
    return get_posts(array(
        'post_type' => $post_type,
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'all',
    ));
}

function ms_admin_get_legacy_meta_value($post_id, $keys, $default = '')
{
    foreach ((array) $keys as $key) {
        $value = get_post_meta($post_id, $key, true);
        if ($value !== '' && $value !== null) {
            return $value;
        }
    }
    return $default;
}

function ms_admin_get_legacy_meta_int($post_id, $keys, $default = 0)
{
    $value = ms_admin_get_legacy_meta_value($post_id, $keys, $default);
    return intval($value);
}

function ms_admin_get_legacy_meta_float($post_id, $keys, $default = 0.0)
{
    $value = ms_admin_get_legacy_meta_value($post_id, $keys, $default);
    return floatval($value);
}

function ms_admin_get_legacy_meta_bool($post_id, $keys, $default = false)
{
    $value = ms_admin_get_legacy_meta_value($post_id, $keys, $default);
    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
}

function ms_admin_sanitize_legacy_payer_type($value)
{
    $allowed = array('owner', 'tenant', 'agent', 'owner_or_tenant');
    $value = strtolower(trim($value));
    return in_array($value, $allowed, true) ? $value : 'owner_or_tenant';
}

function ms_admin_get_legacy_invoice_user_id($property_id, $building_id)
{
    if ($property_id) {
        $contacts = ms_get_property_contacts($property_id);
        if (!empty($contacts['tenant'])) {
            return intval($contacts['tenant']->ID);
        }
        if (!empty($contacts['owner'])) {
            return intval($contacts['owner']->ID);
        }
    }

    if ($building_id) {
        $properties = ms_get_properties_by_building($building_id);
        if (!empty($properties)) {
            $property = reset($properties);
            $contacts = ms_get_property_contacts($property->ID);
            if (!empty($contacts['tenant'])) {
                return intval($contacts['tenant']->ID);
            }
            if (!empty($contacts['owner'])) {
                return intval($contacts['owner']->ID);
            }
        }
    }

    return 0;
}

function ms_admin_migrate_legacy_buildings()
{
    global $wpdb;
    $count = 0;
    $skipped = 0;
    $report = array();
    $posts = ms_get_legacy_cpt_posts('building');
    $tbl = $wpdb->prefix . 'ms_buildings';

    foreach ($posts as $post) {
        $manager_id = ms_admin_get_legacy_meta_int($post->ID, array('manager_id', 'building_manager', 'manager'));
        if (!$manager_id) {
            $manager_id = intval($post->post_author);
        }
        $title = sanitize_text_field($post->post_title ?: 'Building #' . $post->ID);
        $exists = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tbl WHERE title = %s AND manager_id = %d", $title, $manager_id)));
        if ($exists) {
            $skipped++;
            $report[] = "Skipped legacy building {$post->ID}: already migrated.";
            continue;
        }

        $inserted = $wpdb->insert($tbl, array(
            'title' => $title,
            'manager_id' => $manager_id,
            'created_at' => date('Y-m-d H:i:s', strtotime($post->post_date)),
        ), array('%s', '%d', '%s'));

        if ($inserted !== false) {
            $count++;
            $report[] = "Migrated legacy building {$post->ID} as building row.";
        } else {
            $skipped++;
            $report[] = "Failed to migrate legacy building {$post->ID}.";
        }
    }

    return array('migrated' => $count, 'skipped' => $skipped, 'report' => $report);
}

function ms_admin_migrate_legacy_expenses()
{
    global $wpdb;
    $count = 0;
    $skipped = 0;
    $report = array();
    $posts = ms_get_legacy_cpt_posts('expenses');
    $tbl = $wpdb->prefix . 'ms_maintenance_requests';

    foreach ($posts as $post) {
        $building_id = ms_admin_get_legacy_meta_int($post->ID, array('building_id', 'property_id', 'building'));
        if (!$building_id) {
            $report[] = "Skipped legacy expense {$post->ID}: missing building_id.";
            $skipped++;
            continue;
        }

        $title = sanitize_text_field($post->post_title ?: 'Expense #' . $post->ID);
        $description = $post->post_content ?: ms_admin_get_legacy_meta_value($post->ID, array('description', 'desc', 'details'));
        $cost = ms_admin_get_legacy_meta_float($post->ID, array('total_amount', 'amount_due', 'amount', 'cost'), 0);
        if ($cost <= 0) {
            $report[] = "Skipped legacy expense {$post->ID}: invalid cost.";
            $skipped++;
            continue;
        }

        $maintenance_type = strtolower(ms_admin_get_legacy_meta_value($post->ID, array('maintenance_type', 'expense_type', 'type'), 'emergency'));
        $allowed_types = array('monthly', 'emergency', 'capital');
        if (!in_array($maintenance_type, $allowed_types, true)) {
            $maintenance_type = 'emergency';
        }

        $status = strtolower(ms_admin_get_legacy_meta_value($post->ID, array('status'), 'open'));
        if ($status !== 'open' && $status !== 'closed' && $status !== 'completed') {
            $status = 'open';
        }
        if ($status === 'completed') {
            $status = 'closed';
        }

        $is_recurring = ms_admin_get_legacy_meta_bool($post->ID, array('is_recurring', 'recurring', 'repeat'), false) ? 1 : 0;
        $recurrence_day = ms_admin_get_legacy_meta_int($post->ID, array('recurrence_day'), 1);
        if ($recurrence_day < 1 || $recurrence_day > 28) {
            $recurrence_day = 1;
        }

        $manager_id = ms_admin_get_legacy_meta_int($post->ID, array('manager_id', 'created_by', 'author'));
        if (!$manager_id) {
            $manager_id = intval($post->post_author);
        }

        $payer_type = ms_admin_sanitize_legacy_payer_type(ms_admin_get_legacy_meta_value($post->ID, array('payer_type', 'responsible', 'payer', 'pay_type'), 'owner_or_tenant'));
        $start_date = ms_admin_get_legacy_meta_value($post->ID, array('start_date', 'date_created'), null);
        $due_date = ms_admin_get_legacy_meta_value($post->ID, array('due_date', 'date_due', 'due_date'), null);

        $existing = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $tbl WHERE building_id = %d AND title = %s AND cost = %f AND due_date <=> %s",
            $building_id,
            $title,
            $cost,
            $due_date
        )));

        if ($existing) {
            $skipped++;
            $report[] = "Skipped legacy expense {$post->ID}: matching maintenance request already exists.";
            continue;
        }

        $inserted = $wpdb->insert($tbl, array(
            'building_id' => $building_id,
            'unit_id' => ms_admin_get_legacy_meta_int($post->ID, array('unit_id'), 0),
            'title' => $title,
            'description' => $description,
            'cost' => $cost,
            'status' => $status,
            'priority' => ms_admin_get_legacy_meta_value($post->ID, array('priority'), 'medium'),
            'maintenance_type' => $maintenance_type,
            'is_recurring' => $is_recurring,
            'recurrence_day' => $recurrence_day,
            'manager_id' => $manager_id,
            'payer_type' => $payer_type,
            'start_date' => $start_date ?: date('Y-m-d', strtotime($post->post_date)),
            'due_date' => $due_date,
            'created_at' => date('Y-m-d H:i:s', strtotime($post->post_date)),
        ), array('%d','%d','%s','%s','%f','%s','%s','%d','%d','%d','%s','%s','%s','%s'));

        if ($inserted !== false) {
            $count++;
            $report[] = "Migrated legacy expense {$post->ID} as maintenance request.";
        } else {
            $skipped++;
            $report[] = "Failed to migrate legacy expense {$post->ID}.";
        }
    }

    return array('migrated' => $count, 'skipped' => $skipped, 'report' => $report);
}

function ms_admin_migrate_legacy_invoices()
{
    global $wpdb;
    $count = 0;
    $skipped = 0;
    $report = array();
    $posts = ms_get_legacy_cpt_posts('invoices');
    $tbl = $wpdb->prefix . 'ms_invoices';

    foreach ($posts as $post) {
        $property_id = ms_admin_get_legacy_meta_int($post->ID, array('property_id'));
        $building_id = ms_admin_get_legacy_meta_int($post->ID, array('building_id'));
        $amount = ms_admin_get_legacy_meta_float($post->ID, array('amount_due', 'total_amount', 'amount', 'due_amount'), 0);
        if ($amount <= 0) {
            $report[] = "Skipped legacy invoice {$post->ID}: invalid amount.";
            $skipped++;
            continue;
        }

        $status = strtolower(ms_admin_get_legacy_meta_value($post->ID, array('status'), 'pending'));
        if ($status === 'completed') {
            $status = 'paid';
        }
        if (!in_array($status, array('pending', 'paid', 'overdue', 'cancelled', 'failed'), true)) {
            $status = 'pending';
        }

        $payer_type = ms_admin_sanitize_legacy_payer_type(ms_admin_get_legacy_meta_value($post->ID, array('payer_type', 'pay_type', 'responsible'), 'owner_or_tenant'));
        $invoice_type = ms_admin_get_legacy_meta_value($post->ID, array('invoice_type', 'type'), 'legacy');
        $description = $post->post_content ?: ms_admin_get_legacy_meta_value($post->ID, array('description', 'desc', 'details'), $post->post_title ?: 'Invoice #' . $post->ID);
        $due_date = ms_admin_get_legacy_meta_value($post->ID, array('due_date', 'date_due', 'paid_date'), null);
        $user_id = ms_admin_get_legacy_invoice_user_id($property_id, $building_id);

        $existing = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $tbl WHERE building_id = %d AND amount = %f AND due_date <=> %s AND invoice_type = %s",
            $building_id,
            $amount,
            $due_date,
            $invoice_type
        )));

        if ($existing) {
            $skipped++;
            $report[] = "Skipped legacy invoice {$post->ID}: matching invoice already exists.";
            continue;
        }

        $inserted = $wpdb->insert($tbl, array(
            'user_id' => max(0, $user_id),
            'building_id' => $building_id,
            'unit_id' => ms_admin_get_legacy_meta_int($post->ID, array('unit_id'), 0),
            'expense_id' => 0,
            'description' => $description,
            'amount' => $amount,
            'status' => $status,
            'due_date' => $due_date,
            'invoice_type' => $invoice_type,
            'created_at' => date('Y-m-d H:i:s', strtotime($post->post_date)),
            'payer_type' => $payer_type,
        ), array('%d','%d','%d','%d','%s','%f','%s','%s','%s','%s','%s'));

        if ($inserted !== false) {
            $count++;
            $report[] = "Migrated legacy invoice {$post->ID} as ms_invoices record.";
        } else {
            $skipped++;
            $report[] = "Failed to migrate legacy invoice {$post->ID}.";
        }
    }

    return array('migrated' => $count, 'skipped' => $skipped, 'report' => $report);
}

function ms_admin_migrate_legacy_transfers()
{
    global $wpdb;
    $count = 0;
    $skipped = 0;
    $report = array();
    $posts = ms_get_legacy_cpt_posts('transfers');
    $tbl = $wpdb->prefix . 'ms_wallet_transactions';

    foreach ($posts as $post) {
        $user_id = ms_admin_get_legacy_meta_int($post->ID, array('user_id', 'participant_id', 'from_user', 'to_user'));
        $amount = ms_admin_get_legacy_meta_float($post->ID, array('amount', 'transfer_amount', 'total_amount'), 0);
        if ($amount <= 0) {
            $report[] = "Skipped legacy transfer {$post->ID}: invalid amount.";
            $skipped++;
            continue;
        }

        $type = ms_admin_get_legacy_meta_value($post->ID, array('type', 'transfer_type'), 'transfer');
        $meta = array(
            'legacy_post_id' => $post->ID,
            'legacy_post_type' => 'transfers',
        );

        $existing = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tbl WHERE reference_id = %d", $post->ID)));
        if ($existing) {
            $skipped++;
            $report[] = "Skipped legacy transfer {$post->ID}: already migrated.";
            continue;
        }

        $inserted = $wpdb->insert($tbl, array(
            'user_id' => max(0, $user_id),
            'type' => $type,
            'amount' => $amount,
            'meta' => maybe_serialize($meta),
            'reference_id' => $post->ID,
            'created_at' => date('Y-m-d H:i:s', strtotime($post->post_date)),
        ), array('%d','%s','%f','%s','%d','%s'));

        if ($inserted !== false) {
            $count++;
            $report[] = "Migrated legacy transfer {$post->ID} as wallet transaction.";
        } else {
            $skipped++;
            $report[] = "Failed to migrate legacy transfer {$post->ID}.";
        }
    }

    return array('migrated' => $count, 'skipped' => $skipped, 'report' => $report);
}

function ms_admin_migrate_legacy_all()
{
    $report = array();
    $migrated = 0;
    $skipped = 0;

    $building_result = ms_admin_migrate_legacy_buildings();
    $expense_result = ms_admin_migrate_legacy_expenses();
    $invoice_result = ms_admin_migrate_legacy_invoices();
    $transfer_result = ms_admin_migrate_legacy_transfers();

    $report = array_merge($building_result['report'], $expense_result['report'], $invoice_result['report'], $transfer_result['report']);
    $migrated = intval($building_result['migrated']) + intval($expense_result['migrated']) + intval($invoice_result['migrated']) + intval($transfer_result['migrated']);
    $skipped = intval($building_result['skipped']) + intval($expense_result['skipped']) + intval($invoice_result['skipped']) + intval($transfer_result['skipped']);

    return array('migrated' => $migrated, 'skipped' => $skipped, 'report' => $report);
}

// Add property owner meta box to Houzez property edit screen
add_action('add_meta_boxes', function() {
    add_meta_box('ms_property_owner_box', 'Mostaager Property Owner', function($post) {
        wp_nonce_field('ms_property_owner_meta', 'ms_property_owner_meta_nonce');
        $current = get_post_meta($post->ID, 'ms_property_owner_id', true);
        $users = get_users(array('role__in' => array('owner', 'administrator', 'editor'), 'orderby' => 'display_name'));
        echo '<p><label for="ms_property_owner_id">تعيين مالك العقار (Mostaager)</label></p>';
        echo '<select name="ms_property_owner_id" id="ms_property_owner_id" style="width:100%;padding:8px;">';
        echo '<option value="">-- لا شيء --</option>';
        foreach ($users as $u) {
            printf('<option value="%d" %s>%s</option>', $u->ID, selected($u->ID, $current, false), esc_html($u->display_name));
        }
        echo '</select>';
    }, 'property', 'side', 'low');
});

// Save ms_property_owner_id when property saved
add_action('save_post_property', function($post_id, $post, $update) {
    if (!isset($_POST['ms_property_owner_meta_nonce']) || !wp_verify_nonce($_POST['ms_property_owner_meta_nonce'], 'ms_property_owner_meta')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['ms_property_owner_id'])) {
        $val = intval($_POST['ms_property_owner_id']);
        if ($val > 0) {
            update_post_meta($post_id, 'ms_property_owner_id', $val);
        } else {
            delete_post_meta($post_id, 'ms_property_owner_id');
        }
    }
}, 10, 3);

add_action('admin_post_ms_delete_item', function () {
    if (!current_user_can('manage_options')) wp_die('Forbidden');
    check_admin_referer('ms_delete_item');

    $type = isset($_REQUEST['type']) ? sanitize_text_field($_REQUEST['type']) : '';
    $id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
    if (!$id || !$type) wp_redirect(wp_get_referer() ?: admin_url('admin.php?page=mostaager-admin'));

    global $wpdb;
    $mapping = array(
        'building' => $wpdb->prefix . 'ms_buildings',
        'unit' => $wpdb->prefix . 'ms_units',
        'invoice' => $wpdb->prefix . 'ms_invoices',
        'maintenance' => $wpdb->prefix . 'ms_maintenance_requests',
        'transfer' => $wpdb->prefix . 'ms_wallet_transactions',
    );

    if (!isset($mapping[$type])) wp_die('Invalid type');

    $tbl = $mapping[$type];
    $wpdb->delete($tbl, array('id' => $id), array('%d'));

    wp_redirect(wp_get_referer() ?: admin_url('admin.php?page=mostaager-admin'));
    exit;
});



/**
 * Canonical Mostaager admin records.
 * Uses normalized ms_* tables as the source of truth instead of empty legacy CPT screens.
 */
add_action('manage_building_posts_custom_column', function ($column, $post_id) {
    if ($column !== 'ms_thumbnail') { return; }
    $thumb = get_the_post_thumbnail($post_id, array(64, 48), array('style' => 'width:64px;height:48px;object-fit:cover;border-radius:6px;'));
    echo $thumb ? $thumb : '<span style="display:inline-flex;width:64px;height:48px;align-items:center;justify-content:center;background:#f1f5f9;color:#94a3b8;border-radius:6px;font-size:11px;">لا توجد</span>';
}, 10, 2);
add_filter('manage_building_posts_columns', function ($columns) {
    $out = array();
    foreach ($columns as $key => $label) {
        if ($key === 'cb') { $out[$key] = $label; $out['ms_thumbnail'] = 'الصورة'; continue; }
        $out[$key] = $label;
    }
    if (!isset($out['ms_thumbnail'])) { $out['ms_thumbnail'] = 'الصورة'; }
    return $out;
});

add_action('admin_menu', function () {
    if (!current_user_can('manage_options')) { return; }
    add_submenu_page('options-general.php', 'تقرير المحفظة', 'تقرير المحفظة', 'manage_options', 'mostaager-wallet-report', 'ms_admin_wallet_report_page');
    remove_menu_page('mostaager-wallet-report');
    remove_menu_page('mostaager-scenario-runner');
    remove_menu_page('ms-import-dashboard');
    remove_menu_page('ms-unified-settings');
});

add_action('admin_init', function () {
    if (!current_user_can('manage_options')) { return; }
    $post_type = isset($_GET['post_type']) ? sanitize_key($_GET['post_type']) : '';
    $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
    if ($post_type === 'houzez_maintenance') {
        wp_safe_redirect(admin_url('options-general.php'));
        exit;
    }
    if ($post_type === 'houzez_owner') {
        wp_safe_redirect(admin_url('users.php?role=owner'));
        exit;
    }
    if ($post_type === 'houzez_bldg_mgr') {
        wp_safe_redirect(admin_url('users.php?role=building_manager'));
        exit;
    }
    $removed_pages = array('ms-houzez-reports','ms-import-dashboard','ms-import-new','ms-import-templates','ms-import-logs','ms-import-settings','ms-import-automation','ms-import-monitoring','ms-import-reports','mostaager-import-data','mostaager-scenario-runner','ms-canonical-records');
    if (in_array($page, $removed_pages, true)) {
        wp_safe_redirect(admin_url('options-general.php'));
        exit;
    }
});

function ms_admin_canonical_records_page() {
    if (!current_user_can('manage_options')) { wp_die('غير مصرح لك بالوصول إلى هذه الصفحة.'); }
    $record = isset($_GET['record']) ? sanitize_key($_GET['record']) : 'maintenance';
    $allowed = array('maintenance','invoices','discussions','transfers');
    if (!in_array($record, $allowed, true)) { $record = 'maintenance'; }
    echo '<div class="wrap" dir="rtl"><h1>سجلات Mostaager الأساسية</h1><p>هذه الصفحات تعرض البيانات الفعلية من جداول Mostaager، وليس سجلات CPT القديمة الفارغة.</p><nav class="nav-tab-wrapper">';
    foreach (array('maintenance'=>'طلبات الصيانة','invoices'=>'الفواتير','discussions'=>'المناقشات','transfers'=>'طلبات التحويل') as $key=>$label) {
        echo '<a class="nav-tab '.($record===$key?'nav-tab-active':''). '" href="'.esc_url(admin_url('admin.php?page=ms-canonical-records&record='.$key)).'">'.esc_html($label).'</a>';
    }
    echo '</nav><div style="margin-top:20px;">';
    if ($record === 'maintenance') { ms_admin_canonical_maintenance(); }
    elseif ($record === 'invoices') { ms_admin_canonical_invoices(); }
    elseif ($record === 'discussions') { ms_admin_canonical_discussions(); }
    else { ms_admin_canonical_transfers(); }
    echo '</div></div>';
}

function ms_admin_canonical_maintenance() {
    global $wpdb;
    $t = $wpdb->prefix.'ms_maintenance_requests';
    $rows = $wpdb->get_results("SELECT * FROM {$t} ORDER BY id DESC LIMIT 300");
    echo '<table class="widefat fixed striped"><thead><tr><th>#</th><th>الطلب</th><th>المبنى</th><th>الوحدة</th><th>التكلفة</th><th>الحالة</th><th>الأولوية</th><th>التاريخ</th></tr></thead><tbody>';
    if (!$rows) { echo '<tr><td colspan="8">لا توجد طلبات صيانة.</td></tr>'; }
    foreach ((array)$rows as $r) {
        echo '<tr><td>'.intval($r->id).'</td><td>'.esc_html($r->title).'</td><td>#'.intval($r->building_id).'</td><td>#'.intval($r->unit_id).'</td><td>'.esc_html(number_format_i18n((float)$r->cost,2)).' ج.م</td><td>'.esc_html($r->status).'</td><td>'.esc_html($r->priority).'</td><td>'.esc_html($r->created_at).'</td></tr>';
    }
    echo '</tbody></table>';
}

function ms_admin_canonical_invoices() {
    global $wpdb;
    $t = $wpdb->prefix.'ms_invoices';
    $rows = $wpdb->get_results("SELECT * FROM {$t} ORDER BY id DESC LIMIT 300");
    echo '<table class="widefat fixed striped"><thead><tr><th>#</th><th>الوصف</th><th>المستخدم</th><th>المبنى</th><th>المبلغ</th><th>النوع</th><th>الحالة</th><th>الاستحقاق</th></tr></thead><tbody>';
    if (!$rows) { echo '<tr><td colspan="8">لا توجد فواتير في جدول Mostaager.</td></tr>'; }
    foreach ((array)$rows as $r) {
        $u = get_userdata(absint($r->user_id));
        echo '<tr><td>'.intval($r->id).'</td><td>'.esc_html($r->description).'</td><td>'.esc_html($u ? $u->display_name : '#'.intval($r->user_id)).'</td><td>#'.intval($r->building_id).'</td><td>'.esc_html(number_format_i18n((float)$r->amount,2)).' ج.م</td><td>'.esc_html($r->invoice_type).'</td><td>'.esc_html($r->status).'</td><td>'.esc_html($r->due_date).'</td></tr>';
    }
    echo '</tbody></table>';
}

function ms_admin_canonical_discussions() {
    $rows = get_posts(array('post_type'=>array('ms_discussion','discussions'),'post_status'=>array('publish','private','draft'),'posts_per_page'=>300,'orderby'=>'date','order'=>'DESC'));
    echo '<table class="widefat fixed striped"><thead><tr><th>#</th><th>العنوان</th><th>الكاتب</th><th>المبنى</th><th>الحالة</th><th>التاريخ</th></tr></thead><tbody>';
    if (!$rows) { echo '<tr><td colspan="6">لا توجد مناقشات.</td></tr>'; }
    foreach ((array)$rows as $r) {
        echo '<tr><td>'.intval($r->ID).'</td><td>'.esc_html($r->post_title).'</td><td>'.esc_html(get_the_author_meta('display_name', $r->post_author)).'</td><td>#'.intval(get_post_meta($r->ID,'building_id',true)).'</td><td>'.esc_html($r->post_status).'</td><td>'.esc_html($r->post_date).'</td></tr>';
    }
    echo '</tbody></table>';
}

function ms_admin_canonical_transfers() {
    global $wpdb;
    $t = $wpdb->prefix.'ms_transfer_requests';
    $rows = $wpdb->get_results("SELECT * FROM {$t} ORDER BY id DESC LIMIT 300");
    echo '<p>لا يمكن لمدير المبنى إنشاء طلب تحويل إلا بعد دفع جميع فواتير أمر الصيانة. بعد الإرسال يظهر الطلب هنا لمدير الموقع، وتتم الموافقة قبل التحويل البنكي اليدوي إلى حساب مالك المبنى.</p>';
    echo '<table class="widefat fixed striped"><thead><tr><th>#</th><th>أمر العمل</th><th>المبنى</th><th>المبلغ</th><th>طالب الطلب</th><th>الحالة</th><th>المعالجة</th></tr></thead><tbody>';
    if (!$rows) { echo '<tr><td colspan="7">لا توجد طلبات تحويل.</td></tr>'; }
    foreach ((array)$rows as $r) {
        $u = get_userdata(absint($r->requested_by));
        echo '<tr><td>'.intval($r->id).'</td><td>#'.intval($r->work_order_id).'</td><td>#'.intval($r->building_id).'</td><td>'.esc_html(number_format_i18n((float)$r->amount,2)).' ج.م</td><td>'.esc_html($u ? $u->display_name : '#'.intval($r->requested_by)).'</td><td>'.esc_html($r->status).'</td><td>'.esc_html($r->processed_at ?: '—').'</td></tr>';
    }
    echo '</tbody></table>';
}

add_filter('ms_unified_settings_show_import', '__return_false');
add_filter('manage_users_columns', function ($columns) { $columns['ms_owner_status'] = 'صفة المالك'; return $columns; });
add_filter('manage_users_custom_column', function ($output, $column, $user_id) {
    if ($column !== 'ms_owner_status') { return $output; }
    $user = get_userdata($user_id);
    return ($user && in_array('owner', (array)$user->roles, true)) ? '<strong style="color:#0f766e">مالك</strong>' : '—';
}, 10, 3);
add_action('restrict_manage_users', function () {
    if (current_user_can('manage_options')) { echo '<a class="button" style="margin-right:8px" href="'.esc_url(admin_url('users.php?role=owner')).'">عرض المالكين فقط</a>'; }
});
add_filter('user_row_actions', function ($actions, $user) {
    if (in_array('owner', (array)$user->roles, true)) { $actions['ms_owner'] = '<span style="color:#0f766e">مالك</span>'; }
    return $actions;
}, 10, 2);
