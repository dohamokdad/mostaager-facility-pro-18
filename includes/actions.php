<?php
if (!defined('ABSPATH')) {
    exit;
}

function ms_activate_agent_subscription_from_invoice($invoice, $user_id)
{
    if (!$invoice || empty($user_id) || ($invoice->invoice_type ?? '') !== 'subscription') {
        return false;
    }

    $description = (string) ($invoice->description ?? '');
    $plan_key = '';
    $subscription_mode = 'upgrade';
    if (preg_match('/\\[([a-z0-9_-]+)(?::(renew|upgrade))?\\]/i', $description, $matches)) {
        $plan_key = sanitize_key($matches[1]);
        if (!empty($matches[2])) { $subscription_mode = sanitize_key($matches[2]); }
    }
    $plan_names = array(
        'gold' => 'الباقة الذهبية',
        'silver' => 'الباقة الفضية',
        'bronze' => 'الباقة البرونزية',
    );
    if (!$plan_key) {
        foreach ($plan_names as $key => $name) {
            if (strpos($description, $name) !== false) { $plan_key = $key; break; }
        }
    }
    if (!$plan_key || !isset($plan_names[$plan_key]) || !function_exists('ms_set_agent_subscription')) {
        return false;
    }

    $current = function_exists('ms_get_agent_subscription') ? ms_get_agent_subscription($user_id) : null;
    $today = current_time('Y-m-d');
    $base_date = $today;
    if ($subscription_mode === 'renew' && $current && !empty($current->ends_at) && strtotime($current->ends_at) >= current_time('timestamp')) {
        $base_date = $current->ends_at;
    }
    $started_at = $base_date;
    $ends_at = date('Y-m-d', strtotime($base_date . ' +30 days'));

    $saved = ms_set_agent_subscription(
        $user_id,
        floatval($invoice->amount ?? 0),
        'active',
        $ends_at,
        $plan_key,
        $plan_names[$plan_key],
        $started_at,
        $ends_at
    );

    if ($saved && function_exists('ms_restore_agent_properties_after_subscription')) {
        ms_restore_agent_properties_after_subscription($user_id);
    }

    return $saved;
}

add_action('ms_invoice_paid', function ($invoice_id, $invoice = null) {
    if (!$invoice && function_exists('ms_get_invoice_by_id')) {
        $invoice = ms_get_invoice_by_id(intval($invoice_id));
    }
    if (!$invoice || ($invoice->invoice_type ?? '') !== 'subscription' || empty($invoice->user_id)) {
        return;
    }
    if (function_exists('ms_activate_agent_subscription_from_invoice')) {
        ms_activate_agent_subscription_from_invoice($invoice, intval($invoice->user_id));
    }
}, 20, 2);

/**
 * Process WooCommerce orders bridged to Mostaager invoices.
 * Both processing and completed are approved states for subscription orders.
 */
function ms_process_mostaager_order_status($order_id) {
    if (!function_exists('wc_get_order')) {
        return;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    $user_id = intval($order->get_user_id());
    if (!$user_id) {
        return;
    }

    $invoice_id = intval($order->get_meta('mostaager_invoice_id'));
    if ($invoice_id && function_exists('ms_get_invoice_by_id')) {
        $invoice = ms_get_invoice_by_id($invoice_id);
        if (!$invoice) {
            return;
        }

        $activation_key = '_ms_subscription_activated_invoice_' . $invoice_id;
        $already_activated = $order->get_meta($activation_key, true);

        if (function_exists('ms_mark_invoice_paid')) {
            ms_mark_invoice_paid($invoice_id);
        }

        if (!$already_activated && ($invoice->invoice_type ?? '') === 'subscription' && function_exists('ms_activate_agent_subscription_from_invoice')) {
            ms_activate_agent_subscription_from_invoice($invoice, $user_id);
            $order->update_meta_data($activation_key, current_time('mysql'));
            $order->save();
        }

        if (function_exists('ms_add_notification') && empty($invoice->expense_id)) {
            global $wpdb;
            $building_id = intval($invoice->building_id ?? 0);
            if ($building_id) {
                $manager_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT manager_id FROM {$wpdb->prefix}ms_buildings WHERE id = %d LIMIT 1",
                    $building_id
                ));
                if ($manager_id) {
                    ms_add_notification(
                        intval($manager_id),
                        'payment_received',
                        sprintf('تم استلام دفعة للفاتورة #%d', $invoice_id),
                        $building_id,
                        $invoice_id
                    );
                }
            }
        }
        return;
    }

    // Wallet top-ups are credited for approved orders.
    if (function_exists('ms_add_user_wallet_balance')) {
        ms_add_user_wallet_balance($user_id, floatval($order->get_total()), 'Order approved');
    }
}

add_action('woocommerce_order_status_processing', 'ms_process_mostaager_order_status', 20, 1);
add_action('woocommerce_order_status_completed', 'ms_process_mostaager_order_status', 20, 1);

add_action('woocommerce_before_calculate_totals', function ($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    if (!is_object($cart) || !method_exists($cart, 'get_cart')) {
        return;
    }

    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        $months = isset($cart_item['months']) ? intval($cart_item['months']) : 0;
        if ($months >= 3 && isset($cart_item['data']) && is_object($cart_item['data'])) {
            $unit_price = floatval($cart_item['data']->get_price());
            if ($unit_price > 0) {
                $discount_price = ($months * $unit_price) / ($months + 1);
                $cart_item['data']->set_price($discount_price);
            }
        }
    }
});
