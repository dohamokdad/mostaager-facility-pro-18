<?php
/**
 * Mostaager — Wallet ⇄ WooCommerce
 *
 * كل شحن للمحفظة يمر عبر طلب WooCommerce: الإنشاء في
 * ms_create_woo_order_for_wallet_recharge()، والإضافة للرصيد في includes/actions.php
 * عند انتقال الطلب إلى processing/completed (مع ختم _ms_wallet_credited لمنع التكرار).
 *
 * هذا الملف يكمل الدورة:
 *  - حدود مبلغ الشحن في مكان واحد لكل نقاط الدخول
 *  - استرجاع الطلب (كلي أو جزئي) يخصم المبلغ من الرصيد بدل أن يبقى للمستخدم
 */

if (!defined('ABSPATH')) {
    exit;
}

/** الحد الأدنى والأقصى لمبلغ الشحن */
function ms_wallet_topup_limits() {
    $limits = array(
        'min' => (float) get_option('ms_wallet_topup_min', 50),
        'max' => (float) get_option('ms_wallet_topup_max', 100000),
    );

    return apply_filters('ms_wallet_topup_limits', $limits);
}

/**
 * تحقق موحّد من مبلغ الشحن — تستدعيه دالة إنشاء الطلب، فيسري على كل النقاط
 * (لوحة المستأجر، لوحة المالك، الـ shortcode، REST، تطبيق الموبايل).
 *
 * @return true|WP_Error
 */
function ms_validate_topup_amount($amount) {
    $amount = (float) $amount;
    $limits = ms_wallet_topup_limits();

    if ($amount <= 0) {
        return new WP_Error('invalid_amount', 'المبلغ غير صالح.');
    }
    if ($amount < $limits['min']) {
        return new WP_Error(
            'amount_below_min',
            sprintf('الحد الأدنى للشحن %s ج.م.', number_format($limits['min'], 2))
        );
    }
    if ($amount > $limits['max']) {
        return new WP_Error(
            'amount_above_max',
            sprintf('الحد الأقصى للشحن %s ج.م.', number_format($limits['max'], 2))
        );
    }

    return true;
}

/* ====================================================================== *
 * الاسترجاع
 * ====================================================================== */

/**
 * استرجاع طلب شحن: يخصم المبلغ المسترجع من رصيد المحفظة.
 * يعمل مع الاسترجاع الكلي (تغيير الحالة) والجزئي (woocommerce_order_refunded).
 */
function ms_wallet_sync_refund($order_id, $refund_id = 0) {
    if (!function_exists('wc_get_order')) {
        return;
    }
    $order = wc_get_order($order_id);
    if (!$order || $order->get_meta('mostaager_wallet_recharge_type') !== 'wallet_recharge') {
        return;
    }
    // لم يُضف للرصيد أصلاً (طلب لم يُدفع) — لا شيء لخصمه
    if (!$order->get_meta('_ms_wallet_credited')) {
        return;
    }

    $refunded_total  = (float) $order->get_total_refunded();
    $already_handled = (float) $order->get_meta('_ms_wallet_refunded_total');
    $delta = round($refunded_total - $already_handled, 2);

    if ($delta <= 0) {
        return;
    }

    $user_id = absint($order->get_meta('mostaager_wallet_recharge_user_id')) ?: absint($order->get_customer_id());
    if (!$user_id) {
        return;
    }

    if (function_exists('ms_deduct_user_wallet_balance')) {
        ms_deduct_user_wallet_balance($user_id, $delta, 'استرجاع شحن محفظة - طلب #' . $order->get_id());
    } elseif (function_exists('ms_deduct_from_wallet')) {
        ms_deduct_from_wallet($user_id, $delta, 'استرجاع شحن محفظة - طلب #' . $order->get_id());
    } else {
        return;
    }

    $order->update_meta_data('_ms_wallet_refunded_total', $refunded_total);
    $order->save();

    do_action('ms_wallet_topup_refunded', $user_id, $delta, $order->get_id());
}

add_action('woocommerce_order_status_refunded', 'ms_wallet_sync_refund', 20, 1);
add_action('woocommerce_order_refunded', 'ms_wallet_sync_refund', 20, 2);

/**
 * استرجاع طلب فاتورة: لا نغيّر حالة الفاتورة تلقائياً (قرار محاسبي)،
 * لكن نسجّل الحدث ونُشعر الإدارة حتى لا يمر بصمت.
 */
add_action('woocommerce_order_status_refunded', function ($order_id) {
    if (!function_exists('wc_get_order')) {
        return;
    }
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }
    $invoice_id = absint($order->get_meta('mostaager_invoice_id'));
    if (!$invoice_id) {
        return;
    }

    do_action('ms_invoice_order_refunded', $invoice_id, $order_id, (float) $order->get_total_refunded());

    if (function_exists('ms_add_notification')) {
        $admins = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
        if (!empty($admins[0])) {
            ms_add_notification(
                (int) $admins[0],
                'invoice_refunded',
                sprintf('تم استرجاع طلب الدفع #%d المرتبط بالفاتورة #%d — راجع حالة الفاتورة يدوياً.', $order_id, $invoice_id)
            );
        }
    }
}, 30, 1);

/* ====================================================================== *
 * التحصيل اليدوي (نقدي / تحويل بنكي) — يمر أيضاً عبر WooCommerce
 * ====================================================================== */

/**
 * ينشئ طلب WooCommerce مدفوعاً بطريقة يدوية ("تحصيل نقدي")، حتى لا تدخل أي
 * أموال إلى النظام خارج ووكومرس. كل إضافة للرصيد تصبح لها فاتورة وطلب وسجل.
 *
 * @param array $args amount · customer_id · description · credit_type
 *                    (user_wallet | building_wallet | invoice) · building_id · invoice_id
 * @return WC_Order|WP_Error
 */
function ms_create_offline_paid_order(array $args) {
    if (!function_exists('wc_create_order')) {
        return new WP_Error('woo_missing', 'ووكومرس غير مفعّل.');
    }

    $amount = round((float) ($args['amount'] ?? 0), 2);
    if ($amount <= 0) {
        return new WP_Error('invalid_amount', 'المبلغ غير صالح.');
    }

    $order = wc_create_order(array('customer_id' => absint($args['customer_id'] ?? 0)));
    if (is_wp_error($order)) {
        return $order;
    }

    $fee = new WC_Order_Item_Fee();
    $fee->set_name(!empty($args['description']) ? $args['description'] : 'تحصيل يدوي');
    $fee->set_total($amount);
    $order->add_item($fee);

    $order->set_payment_method('ms_offline');
    $order->set_payment_method_title(!empty($args['method_title']) ? $args['method_title'] : 'تحصيل نقدي / تحويل بنكي');
    $order->update_meta_data('mostaager_credit_type', sanitize_key($args['credit_type'] ?? 'manual'));
    $order->update_meta_data('mostaager_collected_by', get_current_user_id());

    foreach (array('building_id', 'invoice_id') as $key) {
        if (!empty($args[$key])) {
            $order->update_meta_data('mostaager_' . $key, absint($args[$key]));
        }
    }

    $order->calculate_totals();
    $order->save();
    $order->payment_complete(); // يشغّل نفس مسار الترحيل المستخدم مع البوابات

    return $order;
}

/**
 * ترحيل طلب مُحصَّل يدوياً إلى محفظة المبنى (مرة واحدة لكل طلب).
 */
function ms_credit_building_wallet_from_order($order_id) {
    if (!function_exists('wc_get_order')) {
        return;
    }
    $order = wc_get_order($order_id);
    if (!$order || $order->get_meta('mostaager_credit_type') !== 'building_wallet') {
        return;
    }
    if ($order->get_meta('_ms_building_wallet_credited')) {
        return;
    }

    $building_id = absint($order->get_meta('mostaager_building_id'));
    $amount = (float) $order->get_total();
    if (!$building_id || $amount <= 0 || !function_exists('ms_update_building_wallet_balance')) {
        return;
    }

    ms_update_building_wallet_balance($building_id, $amount);
    $order->update_meta_data('_ms_building_wallet_credited', current_time('mysql'));
    $order->save();

    do_action('ms_building_wallet_credited', $building_id, $amount, $order->get_id());
}

add_action('woocommerce_payment_complete', 'ms_credit_building_wallet_from_order', 20, 1);
add_action('woocommerce_order_status_processing', 'ms_credit_building_wallet_from_order', 20, 1);
add_action('woocommerce_order_status_completed', 'ms_credit_building_wallet_from_order', 20, 1);

/** استرجاع طلب تحصيل محفظة مبنى يخصم المبلغ من المحفظة */
add_action('woocommerce_order_status_refunded', function ($order_id) {
    if (!function_exists('wc_get_order')) {
        return;
    }
    $order = wc_get_order($order_id);
    if (!$order || $order->get_meta('mostaager_credit_type') !== 'building_wallet') {
        return;
    }
    if (!$order->get_meta('_ms_building_wallet_credited') || $order->get_meta('_ms_building_wallet_refunded')) {
        return;
    }
    $building_id = absint($order->get_meta('mostaager_building_id'));
    $amount = (float) $order->get_total_refunded();
    if ($building_id && $amount > 0 && function_exists('ms_update_building_wallet_balance')) {
        ms_update_building_wallet_balance($building_id, -$amount);
        $order->update_meta_data('_ms_building_wallet_refunded', current_time('mysql'));
        $order->save();
    }
}, 20, 1);
