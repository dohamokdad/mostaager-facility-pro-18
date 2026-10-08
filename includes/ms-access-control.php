<?php
/**
 * Mostaager Facility PRO — Unified Access Control Layer
 * ------------------------------------------------------
 * ضعه في: includes/ms-access-control.php
 * ثم أضف في mostaager-facility-pro.php (داخل plugins_loaded، قبل تحميل core/ajax.php):
 *     require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/ms-access-control.php';
 *
 * الهدف: توحيد فحوصات الصلاحيات المبعثرة في 154 مكان بالبلجن في مكان واحد.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * هل يملك المستخدم صلاحية الوصول لمبنى معيّن؟
 * يغطي: الأدمن، مدير المبنى، الوسيط المعيّن، المالك، المستأجر.
 */
function ms_user_can_access_building($user_id, $building_id) {
    global $wpdb;

    $user_id     = absint($user_id);
    $building_id = absint($building_id);

    if (!$user_id || !$building_id) {
        return false;
    }

    if (user_can($user_id, 'manage_options')) {
        return true;
    }

    // كاش للطلب الواحد — تجنّب تكرار نفس الاستعلام عشرات المرات في صفحة واحدة
    static $cache = array();
    $key = $user_id . ':' . $building_id;
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $allowed = false;

    // 1) مدير المبنى
    if (function_exists('ms_get_buildings_by_manager')) {
        foreach ((array) ms_get_buildings_by_manager($user_id) as $b) {
            if (absint($b->id ?? $b->ID ?? 0) === $building_id) {
                $allowed = true;
                break;
            }
        }
    }

    // 2) وسيط معيّن على المبنى (جدول الإسناد أو عبر الوحدات)
    if (!$allowed) {
        $assign = $wpdb->prefix . 'ms_building_agents';
        $units  = $wpdb->prefix . 'ms_units';

        $allowed = (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$assign} WHERE agent_id = %d AND building_id = %d AND status = 'active' LIMIT 1",
            $user_id,
            $building_id
        ));

        if (!$allowed) {
            $allowed = (bool) $wpdb->get_var($wpdb->prepare(
                "SELECT 1 FROM {$units} WHERE agent_id = %d AND building_id = %d LIMIT 1",
                $user_id,
                $building_id
            ));
        }
    }

    // 3) مالك أو مستأجر لوحدة داخل المبنى
    if (!$allowed) {
        $units = $wpdb->prefix . 'ms_units';
        $allowed = (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$units} WHERE building_id = %d AND (owner_id = %d OR tenant_id = %d) LIMIT 1",
            $building_id,
            $user_id,
            $user_id
        ));
    }

    $cache[$key] = $allowed;
    return $allowed;
}

/**
 * هل يملك المستخدم صلاحية الوصول لفاتورة معيّنة؟
 * ملاحظة: نستخدم == مع absint لأن $wpdb يرجّع كل الحقول كـ string،
 * والمقارنة الصارمة === بين string و int ترجع false دائماً (باغ موجود حالياً).
 */
function ms_user_can_access_invoice($user_id, $invoice_id) {
    $user_id    = absint($user_id);
    $invoice_id = absint($invoice_id);

    if (!$user_id || !$invoice_id) {
        return false;
    }

    if (user_can($user_id, 'manage_options')) {
        return true;
    }

    if (!function_exists('ms_get_invoice_by_id')) {
        return false;
    }

    $invoice = ms_get_invoice_by_id($invoice_id);
    if (!$invoice) {
        return false;
    }

    // صاحب الفاتورة المباشر
    if (absint($invoice->user_id ?? 0) === $user_id) {
        return true;
    }

    // المالك المرتبط بالفاتورة
    if (function_exists('ms_invoice_belongs_to_owner') && ms_invoice_belongs_to_owner($invoice_id, $user_id)) {
        return true;
    }

    // مدير/وسيط المبنى الذي صدرت عنه الفاتورة
    $building_id = absint($invoice->building_id ?? 0);
    if ($building_id && ms_user_can_access_building($user_id, $building_id)) {
        return true;
    }

    return false;
}

/**
 * هل يملك الوسيط/المالك صلاحية على عقار (post) معيّن؟
 */
function ms_user_can_access_property($user_id, $property_id) {
    $user_id     = absint($user_id);
    $property_id = absint($property_id);

    if (!$user_id || !$property_id) {
        return false;
    }

    if (user_can($user_id, 'manage_options')) {
        return true;
    }

    $post = get_post($property_id);
    if (!$post) {
        return false;
    }

    if (absint($post->post_author) === $user_id) {
        return true;
    }

    // Houzez: الوسيط المعيّن على العقار
    $agent_meta = absint(get_post_meta($property_id, 'fave_agents', true));
    if ($agent_meta && $agent_meta === $user_id) {
        return true;
    }

    $agent_id = absint(get_post_meta($property_id, 'agent_id', true));
    if ($agent_id && $agent_id === $user_id) {
        return true;
    }

    // عبر المبنى المرتبط
    $building_id = absint(get_post_meta($property_id, '_ms_building_id', true));
    if ($building_id && ms_user_can_access_building($user_id, $building_id)) {
        return true;
    }

    return false;
}

/**
 * حارس موحّد لطلبات AJAX: يفحص تسجيل الدخول + الـ nonce معاً.
 * استخدامه: ms_ajax_guard('ms_dashboard_nonce');
 */
function ms_ajax_guard($nonce_action, $nonce_field = 'security') {
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 401);
    }

    $nonce = isset($_POST[$nonce_field]) ? sanitize_text_field(wp_unslash($_POST[$nonce_field])) : '';
    if (!$nonce || !wp_verify_nonce($nonce, $nonce_action)) {
        wp_send_json_error('security_failed', 403);
    }

    return get_current_user_id();
}

/**
 * تحقق آمن من نوع الملف المرفوع — لا تثق أبداً بـ $_FILES['x']['type']
 * فهو يأتي من المتصفح ويمكن تزويره.
 */
function ms_validate_upload($file, $allowed_exts = array('pdf', 'jpg', 'jpeg', 'png')) {
    if (!is_array($file) || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return new WP_Error('upload_error', 'خطأ في رفع الملف.');
    }

    $max_size = 10 * 1024 * 1024; // 10MB
    if (!empty($file['size']) && $file['size'] > $max_size) {
        return new WP_Error('file_too_large', 'حجم الملف يتجاوز 10 ميجابايت.');
    }

    $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
    $ext   = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (empty($check['ext']) || !in_array(strtolower($check['ext']), $allowed_exts, true)) {
        return new WP_Error('invalid_file_type', 'نوع الملف غير مدعوم.');
    }

    if (!in_array($ext, $allowed_exts, true)) {
        return new WP_Error('invalid_file_type', 'امتداد الملف غير مدعوم.');
    }

    return true;
}
