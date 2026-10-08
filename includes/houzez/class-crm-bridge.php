<?php
/**
 * Mostaager ↔ Houzez CRM Bridge (Phase 3)
 *
 * يستبدل ثلاث نسخ ميتة كانت تضيف صندوقاً في أنواع منشورات غير موجودة
 * (houzez_crm / houzez_lead / fave_property_inquiry). الـ CRM في Houzez 4.x
 * يعمل بكلاسات وجداول (Houzez_Leads / Houzez_Deals) وليس بمنشورات.
 *
 * ما يفعله: زر «تحويل لمستأجر» على صفوف الصفقات في صفحة CRM داخل لوحة Houzez.
 * الزر يُحقن بالـ JS اعتماداً على <tr data-id="deal_id"> التي يطبعها قالب Houzez.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class MS_Houzez_CRM_Bridge {

    public static function init() {
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue'), 20);
        add_action('wp_ajax_ms_crm_convert_deal', array(__CLASS__, 'ajax_convert'));

        // التحويل التلقائي (معطّل افتراضياً): خيار ms_crm_auto_convert = 1
        // CRM لا يطلق أي hook عند تغيير حالة الصفقة، لذلك نجدول مسحة قصيرة بعد
        // أي إضافة/تعديل صفقة، مع مسحة احتياطية كل ساعة.
        add_action('wp_ajax_houzez_crm_add_deal', array(__CLASS__, 'schedule_scan'), 1);
        add_action('wp_ajax_crm_set_deal_status', array(__CLASS__, 'schedule_scan'), 1);
        add_action('ms_crm_scan_won_deals', array(__CLASS__, 'scan_won_deals'));
        if (get_option('ms_crm_auto_convert') === '1' && !wp_next_scheduled('ms_crm_scan_won_deals')) {
            wp_schedule_event(time() + 600, 'hourly', 'ms_crm_scan_won_deals');
        }
    }

    public static function auto_convert_enabled() {
        return apply_filters('ms_crm_auto_convert', get_option('ms_crm_auto_convert') === '1');
    }

    public static function schedule_scan() {
        if (self::auto_convert_enabled() && !wp_next_scheduled('ms_crm_scan_won_deals_once')) {
            wp_schedule_single_event(time() + 60, 'ms_crm_scan_won_deals');
        }
    }

    /**
     * يحوّل الصفقات الرابحة (deal_group = won) التي لم تُحوَّل بعد.
     * كل صفقة تُعلَّم بـ user meta حتى لا تتكرر.
     */
    public static function scan_won_deals() {
        global $wpdb;
        if (!self::auto_convert_enabled()) {
            return;
        }
        $table = self::table('deals');
        if (!$table) {
            return;
        }
        $deals = $wpdb->get_results("SELECT * FROM {$table} WHERE deal_group = 'won' ORDER BY deal_id DESC LIMIT 50");
        foreach ((array) $deals as $deal) {
            $existing = get_users(array(
                'meta_key'   => '_ms_source_deal_id',
                'meta_value' => absint($deal->deal_id),
                'fields'     => 'ID',
                'number'     => 1,
            ));
            if ($existing) {
                continue;
            }
            $lead = empty($deal->lead_id) ? null : self::get_lead(absint($deal->lead_id));
            if (!$lead) {
                continue;
            }
            $result = self::convert_deal($deal, $lead);
            if (is_wp_error($result) && defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[MS CRM] deal ' . $deal->deal_id . ': ' . $result->get_error_message());
            }
        }
    }

    public static function crm_available() {
        return class_exists('Houzez_Leads') || self::table('deals') !== '';
    }

    /**
     * جداول Houzez CRM 1.5.0 (مؤكدة من houzez-crm.php):
     *   {prefix}houzez_crm_deals · {prefix}houzez_crm_leads
     */
    private static function table($what) {
        global $wpdb;
        static $cache = array();
        if (isset($cache[$what])) {
            return $cache[$what];
        }
        $table = $wpdb->prefix . ($what === 'deals' ? 'houzez_crm_deals' : 'houzez_crm_leads');
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
        return $cache[$what] = $exists ? $table : '';
    }

    public static function enqueue() {
        if (!is_user_logged_in() || !is_page_template('template/user_dashboard_crm.php') || !self::crm_available()) {
            return;
        }
        $user_id = get_current_user_id();
        $allowed = current_user_can('manage_options')
            || (function_exists('ms_user_has_role') && (ms_user_has_role($user_id, 'agent') || ms_user_has_role($user_id, 'building_manager')));
        if (!$allowed) {
            return;
        }
        $file = MOSTAAGER_ENTERPRISE_PATH . 'assets/js/ms-crm-bridge.js';
        if (!file_exists($file)) {
            return;
        }
        wp_enqueue_script('ms-crm-bridge', MOSTAAGER_ENTERPRISE_URL . 'assets/js/ms-crm-bridge.js', array(), filemtime($file), true);
        wp_localize_script('ms-crm-bridge', 'MSCrmBridge', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('ms_crm_convert_deal'),
            'label'   => 'تحويل لمستأجر',
            'confirm' => 'سيتم إنشاء حساب مستأجر من بيانات هذه الصفقة. متابعة؟',
        ));
    }

    /* ------------------------------------------------------------------ *
     * قراءة الصفقة والعميل من CRM
     * ------------------------------------------------------------------ */

    private static function get_deal($deal_id) {
        global $wpdb;
        $table = self::table('deals');
        if (!$table) {
            return null;
        }
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE deal_id = %d", $deal_id));
    }

    /**
     * Houzez_Leads::get_lead() تقيّد النتيجة بـ user_id للمستخدم الحالي،
     * لذلك نقرأ من الجدول مباشرة حتى يعمل الأدمن أيضاً.
     */
    private static function get_lead($lead_id) {
        global $wpdb;
        $table = self::table('leads');
        if (!$table) {
            return class_exists('Houzez_Leads') ? (Houzez_Leads::get_lead($lead_id) ?: null) : null;
        }
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE lead_id = %d", $lead_id));
    }

    /** الوحدة المرتبطة بعقار الصفقة (listing_id) إن وُجدت */
    private static function unit_for_listing($listing_id) {
        global $wpdb;
        $listing_id = absint($listing_id);
        if (!$listing_id) {
            return null;
        }
        $units = $wpdb->prefix . 'ms_units';

        // العمود property_id يُضاف لاحقاً بواسطة msfp_add_missing_column — نجرّب ونتجاهل الخطأ
        $suppress = $wpdb->suppress_errors(true);
        $unit = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$units} WHERE property_id = %d LIMIT 1", $listing_id));
        $wpdb->suppress_errors($suppress);
        if ($unit) {
            return $unit;
        }
        $unit_id = absint(get_post_meta($listing_id, 'unit_id', true));
        if ($unit_id) {
            return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$units} WHERE id = %d", $unit_id));
        }
        return null;
    }

    /* ------------------------------------------------------------------ */

    public static function ajax_convert() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'not_logged_in'), 401);
        }
        $nonce = isset($_POST['security']) ? sanitize_text_field(wp_unslash($_POST['security'])) : '';
        if (!wp_verify_nonce($nonce, 'ms_crm_convert_deal')) {
            wp_send_json_error(array('message' => 'security_failed'), 403);
        }

        $user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');
        if (!$is_admin && (!function_exists('ms_user_has_role')
            || (!ms_user_has_role($user_id, 'agent') && !ms_user_has_role($user_id, 'building_manager')))) {
            wp_send_json_error(array('message' => 'forbidden'), 403);
        }

        $deal_id = isset($_POST['deal_id']) ? absint($_POST['deal_id']) : 0;
        $deal    = $deal_id ? self::get_deal($deal_id) : null;
        if (!$deal) {
            wp_send_json_error(array('message' => 'تعذّر العثور على الصفقة في CRM.'), 404);
        }

        // الوسيط يحوّل صفقاته فقط
        // جدول الصفقات يحفظ user_id (صاحب السجل) و agent_id (الوكيل المسؤول)
        $owns = in_array($user_id, array(absint($deal->user_id ?? 0), absint($deal->agent_id ?? 0)), true);
        if (!$is_admin && !$owns) {
            wp_send_json_error(array('message' => 'هذه الصفقة ليست ضمن صفقاتك.'), 403);
        }

        $lead = isset($deal->lead_id) ? self::get_lead(absint($deal->lead_id)) : null;
        if (!$lead) {
            wp_send_json_error(array('message' => 'الصفقة غير مرتبطة بعميل في CRM.'), 404);
        }

        $result = self::convert_deal($deal, $lead);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 400);
        }

        wp_send_json_success(array(

            'message'   => $result['message'],
            'linked'    => $result['linked'],
            'tenant_id' => $result['tenant_id'],
            'next_url'  => $result['next_url'],
        ));
    }

    /**
     * جوهر التحويل: عميل CRM ← حساب مستأجر (+ ربط بالوحدة إن أمكن).
     * تستخدمه نقطة AJAX والتحويل التلقائي معاً.
     *
     * @return array|WP_Error
     */
    public static function convert_deal($deal, $lead) {
        $email = isset($lead->email) ? sanitize_email($lead->email) : '';
        if (!is_email($email)) {
            return new WP_Error('no_email', 'العميل بدون بريد إلكتروني صالح — أضفه في CRM أولاً.');
        }

        $name = trim(
            (isset($lead->display_name) ? $lead->display_name : '')
            ?: trim((isset($lead->first_name) ? $lead->first_name : '') . ' ' . (isset($lead->last_name) ? $lead->last_name : ''))
        );
        $phone = '';
        foreach (array('mobile', 'home_phone', 'work_phone') as $field) {
            if (!empty($lead->$field)) {
                $phone = sanitize_text_field($lead->$field);
                break;
            }
        }

        $tenant = get_user_by('email', $email);
        $created = false;
        if ($tenant) {
            $tenant_id = (int) $tenant->ID;
        } else {
            $login = sanitize_user(current(explode('@', $email)), true);
            if (!$login || username_exists($login)) {
                $login = 'tenant_' . wp_generate_password(6, false, false);
            }
            $tenant_id = wp_create_user($login, wp_generate_password(14), $email);
            if (is_wp_error($tenant_id)) {
                return new WP_Error('user_failed', 'تعذّر إنشاء حساب المستأجر.');
            }
            $created = true;
        }

        // add_role لا يمسّ الأدوار الأخرى (الكود القديم كان يستبدلها كلها)
        $user = new WP_User($tenant_id);
        if (!in_array('tenant', (array) $user->roles, true)) {
            $user->add_role('tenant');
        }
        if (function_exists('ms_sync_user_roles')) {
            ms_sync_user_roles($tenant_id); // يمنحه houzez_buyer
        }

        if ($name) {
            wp_update_user(array('ID' => $tenant_id, 'display_name' => $name, 'first_name' => $lead->first_name ?? '', 'last_name' => $lead->last_name ?? ''));
        }
        if ($phone) {
            update_user_meta($tenant_id, 'phone', $phone);
            update_user_meta($tenant_id, 'billing_phone', $phone);
        }
        update_user_meta($tenant_id, '_ms_source_deal_id', absint($deal->deal_id ?? 0));
        update_user_meta($tenant_id, '_ms_source_lead_id', absint($deal->lead_id));

        // ربط تلقائي بالوحدة عندما تكون الصفقة مرتبطة بعقار له وحدة معروفة
        $linked_unit = self::unit_for_listing($deal->listing_id ?? 0);
        $linked = false;
        if ($linked_unit) {
            global $wpdb;
            $leases = $wpdb->prefix . 'ms_unit_tenants';
            $already = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$leases} WHERE unit_id = %d AND tenant_id = %d AND status = 'active'",
                absint($linked_unit->id),
                $tenant_id
            ));
            if (!$already) {
                $linked = (bool) $wpdb->insert($leases, array(
                    'unit_id'     => absint($linked_unit->id),
                    'tenant_id'   => $tenant_id,
                    'building_id' => absint($linked_unit->building_id),
                    'start_date'  => current_time('Y-m-d'),
                    'status'      => 'active',
                    'created_at'  => current_time('mysql'),
                ), array('%d', '%d', '%d', '%s', '%s', '%s'));
            } else {
                $linked = true;
            }
            if ($linked) {
                $wpdb->update($wpdb->prefix . 'ms_units', array('tenant_id' => $tenant_id, 'status' => 'occupied'), array('id' => absint($linked_unit->id)), array('%d', '%s'), array('%d'));
            }
        }

        do_action('ms_crm_deal_converted_to_tenant', $tenant_id, $deal, $lead);

        // ربط الوحدة يتم يدوياً: العقد يحتاج وحدة وتواريخ وتأميناً — لا نخمّنها
        $next = '';
        if (class_exists('MS_Houzez_Dashboard_Bridge')) {
            $next = MS_Houzez_Dashboard_Bridge::page_url('building_manager', 'units');
            if (!$next) {
                $next = MS_Houzez_Dashboard_Bridge::page_url('owner', 'properties');
            }
        }


        return array(
            'message'   => sprintf(
                '%s %s',
                $created
                    ? sprintf('تم إنشاء حساب مستأجر لـ %s.', $name ?: $email)
                    : sprintf('%s لديه حساب بالفعل وتمت إضافة صفة مستأجر له.', $name ?: $email),
                $linked
                    ? 'وتم ربطه بالوحدة المرتبطة بالصفقة. راجع العقد والتأمين.'
                    : 'الخطوة التالية: اربطه بالوحدة وابدأ العقد.'
            ),
            'linked'    => $linked,
            'tenant_id' => $tenant_id,
            'next_url'  => $next,
        );
    }
}

MS_Houzez_CRM_Bridge::init();
