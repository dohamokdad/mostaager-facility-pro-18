<?php
/**
 * Houzez user-dashboard AJAX endpoints (maintenance request + wallet top-up).
 *
 * ملاحظة: كان هذا الملف يضيف تبويبات وقوائم داخل لوحة Houzez عبر
 * houzez_dashboard_tabs و houzez_user_dashboard_menu — وكلاهما غير موجود في
 * Houzez 4.3.5، فكان الكود ميتاً بالكامل. الدمج الفعلي يتم الآن في
 * includes/houzez/class-houzez-dashboard-bridge.php.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_ms_create_maintenance_request', 'ms_houzez_ajax_create_maintenance_request');

function ms_houzez_ajax_create_maintenance_request() {
    // يقبل nonce النموذج القديم أو nonce اللوحة الموحّد (MostaagerAjax.nonce)
    if (!check_ajax_referer('ms_maintenance_nonce', 'ms_maintenance_nonce', false)
        && !check_ajax_referer('mostaager-ajax-nonce', 'security', false)) {
        wp_send_json_error(array('message' => 'security_failed'), 403);
    }
    
    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error(['message' => 'يجب تسجيل الدخول']);
    }
    
    $title = sanitize_text_field($_POST['title']);
    $description = sanitize_textarea_field($_POST['description']);
    $priority = sanitize_text_field($_POST['priority']);
    
    if (empty($title) || empty($description)) {
        wp_send_json_error(['message' => 'جميع الحقول مطلوبة']);
    }
    
    // Get user's current unit/property
    $unit_id = get_user_meta($user_id, 'current_unit_id', true);
    $building_id = get_user_meta($user_id, 'current_building_id', true);
    
    // Create maintenance request
    if (function_exists('ms_create_maintenance_request')) {
        $result = ms_create_maintenance_request([
            'user_id' => $user_id,
            'building_id' => $building_id,
            'unit_id' => $unit_id,
            'title' => $title,
            'description' => $description,
            'priority' => $priority,
        ]);
        
        if ($result) {
            wp_send_json_success(['message' => 'تم إرسال طلب الصيانة بنجاح']);
        } else {
            wp_send_json_error(['message' => 'فشل إرسال الطلب']);
        }
    } else {
        // Fallback: create directly in database
        global $wpdb;
        $table = $wpdb->prefix . 'ms_maintenance_requests';
        
        $wpdb->insert($table, [
            'user_id' => $user_id,
            'building_id' => $building_id,
            'unit_id' => $unit_id,
            'title' => $title,
            'description' => $description,
            'priority' => $priority,
            'status' => 'pending',
            'created_at' => current_time('mysql'),
        ]);
        
        wp_send_json_success(['message' => 'تم إرسال طلب الصيانة بنجاح']);
    }
}

// AJAX handler for wallet top-up
add_action('wp_ajax_ms_wallet_topup', 'ms_houzez_ajax_wallet_topup');

function ms_houzez_ajax_wallet_topup() {
    if (!check_ajax_referer('ms_wallet_topup', 'ms_wallet_topup_nonce', false)
        && !check_ajax_referer('mostaager-ajax-nonce', 'security', false)) {
        wp_send_json_error(array('message' => 'security_failed'), 403);
    }

    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error(array('message' => 'يجب تسجيل الدخول'), 401);
    }

    $amount = floatval($_POST['amount'] ?? 0);
    if ($amount <= 0) {
        wp_send_json_error(array('message' => 'المبلغ يجب أن يكون أكبر من صفر'), 422);
    }

    // نفس مسار الشحن الموحّد (طلب ووكومرس موسوم) بدل السلة والجلسة
    if (!function_exists('ms_create_woo_order_for_wallet_recharge')) {
        wp_send_json_error(array('message' => 'نظام الدفع غير متاح حالياً'), 500);
    }

    $payment_url = ms_create_woo_order_for_wallet_recharge($user_id, $amount);
    if (is_wp_error($payment_url)) {
        wp_send_json_error(array('code' => $payment_url->get_error_code(), 'message' => $payment_url->get_error_message()), 422);
    }
    if (!is_string($payment_url) || $payment_url === '') {
        wp_send_json_error(array('message' => 'تعذّر إنشاء طلب الشحن'), 500);
    }

    wp_send_json_success(array('payment_url' => $payment_url));
}

// Add Mostaager styles to houzez user dashboard
// كان يحمّل assets/css/houzez-user-dashboard.css (غير موجود في البلجن) على قالب
// باسم 'user-dashboard.php' (اسم قالب غير صحيح في Houzez). تنسيق اللوحة يأتي الآن
// من assets/css/ms-houzez-shell.css عبر الجسر.
