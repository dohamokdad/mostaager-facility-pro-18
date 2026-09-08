<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Houzez Wallet Integration
 * Integrates Mostaager wallet system with WordPress user meta
 */

// Wallet balance user meta key
define('MS_WALLET_BALANCE_META', 'ms_wallet_balance');
define('MS_WALLET_CURRENCY_META', 'ms_wallet_currency');

// Get user wallet balance
if (!function_exists('ms_get_wallet_balance')) {
    function ms_get_wallet_balance($user_id) {
        $balance = get_user_meta($user_id, MS_WALLET_BALANCE_META, true);
        return floatval($balance ?: 0);
    }
}

// Set user wallet balance
if (!function_exists('ms_set_wallet_balance')) {
    function ms_set_wallet_balance($user_id, $amount) {
        update_user_meta($user_id, MS_WALLET_BALANCE_META, floatval($amount));
    }
}

// Add to wallet balance
if (!function_exists('ms_add_to_wallet')) {
    function ms_add_to_wallet($user_id, $amount, $description = '', $type = 'credit') {
        $current_balance = ms_get_wallet_balance($user_id);
        $new_balance = $current_balance + floatval($amount);
        
        if ($new_balance < 0) {
            return false; // Prevent negative balance
        }
        
        ms_set_wallet_balance($user_id, $new_balance);
        
        // Record transaction
        ms_record_wallet_transaction($user_id, $amount, $type, $description);
        
        return $new_balance;
    }
}

// Deduct from wallet balance
if (!function_exists('ms_deduct_from_wallet')) {
    function ms_deduct_from_wallet($user_id, $amount, $description = '') {
        return ms_add_to_wallet($user_id, -$amount, $description, 'debit');
    }
}

// Record wallet transaction
if (!function_exists('ms_record_wallet_transaction')) {
    function ms_record_wallet_transaction($user_id, $amount, $type, $description, $reference_id = 0) {
        global $wpdb;
        
        // Try to use ms_wallet_transactions table if exists
        $table = $wpdb->prefix . 'ms_wallet_transactions';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
        
        if ($table_exists) {
            $wpdb->insert($table, [
                'user_id' => $user_id,
                'amount' => floatval($amount),
                'type' => $type,
                'description' => $description,
                'reference_id' => $reference_id,
                'created_at' => current_time('mysql'),
            ]);
            
            return $wpdb->insert_id;
        }
        
        // Fallback: store in user meta as array
        $transactions = get_user_meta($user_id, 'ms_wallet_transactions', true);
        if (!is_array($transactions)) {
            $transactions = [];
        }
        
        $transaction = [
            'id' => uniqid('txn_'),
            'amount' => floatval($amount),
            'type' => $type,
            'description' => $description,
            'reference_id' => $reference_id,
            'created_at' => current_time('mysql'),
            'balance_after' => ms_get_wallet_balance($user_id),
        ];
        
        array_unshift($transactions, $transaction);
        
        // Keep only last 500 transactions
        if (count($transactions) > 500) {
            $transactions = array_slice($transactions, 0, 500);
        }
        
        update_user_meta($user_id, 'ms_wallet_transactions', $transactions);
        
        return $transaction['id'];
    }
}

// Get wallet transactions
if (!function_exists('ms_get_wallet_transactions')) {
    function ms_get_wallet_transactions($user_id, $limit = 50) {
        global $wpdb;
        
        // Try to use ms_wallet_transactions table if exists
        $table = $wpdb->prefix . 'ms_wallet_transactions';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
        
        if ($table_exists) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
                $user_id,
                $limit
            ));
        }
        
        // Fallback: get from user meta
        $transactions = get_user_meta($user_id, 'ms_wallet_transactions', true);
        
        if (!is_array($transactions)) {
            return [];
        }
        
        return array_slice($transactions, 0, $limit);
    }
}

// Add wallet field to user profile
add_action('show_user_profile', 'ms_show_wallet_field');
add_action('edit_user_profile', 'ms_show_wallet_field');

function ms_show_wallet_field($user) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $balance = ms_get_wallet_balance($user->ID);
    $currency = get_user_meta($user->ID, MS_WALLET_CURRENCY_META, true) ?: 'EGP';
    
    ?>
    <h2>💰 المحفظة</h2>
    <table class="form-table">
        <tr>
            <th><label for="ms_wallet_balance">رصيد المحفظة:</label></th>
            <td>
                <input type="number" id="ms_wallet_balance" name="ms_wallet_balance" value="<?php echo esc_attr($balance); ?>" step="0.01" style="width:100px;">
                <span><?php echo esc_html($currency); ?></span>
                <p class="description">تعديل رصيد محفظة المستخدم يدوياً</p>
            </td>
        </tr>
        <tr>
            <th><label for="ms_wallet_currency">عملة المحفظة:</label></th>
            <td>
                <input type="text" id="ms_wallet_currency" name="ms_wallet_currency" value="<?php echo esc_attr($currency); ?>" style="width:100px;">
            </td>
        </tr>
    </table>
    <?php
}

// Save wallet field
add_action('personal_options_update', 'ms_save_wallet_field');
add_action('edit_user_profile_update', 'ms_save_wallet_field');

function ms_save_wallet_field($user_id) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    if (isset($_POST['ms_wallet_balance'])) {
        $old_balance = ms_get_wallet_balance($user_id);
        $new_balance = floatval($_POST['ms_wallet_balance']);
        
        if ($old_balance !== $new_balance) {
            $difference = $new_balance - $old_balance;
            $type = $difference > 0 ? 'credit' : 'debit';
            $description = 'تعديل يدوي من قبل الإدارة';
            
            ms_set_wallet_balance($user_id, $new_balance);
            ms_record_wallet_transaction($user_id, $difference, $type, $description);
        }
    }
    
    if (isset($_POST['ms_wallet_currency'])) {
        update_user_meta($user_id, MS_WALLET_CURRENCY_META, sanitize_text_field($_POST['ms_wallet_currency']));
    }
}

// Add wallet balance to admin users list
add_filter('manage_users_columns', 'ms_add_wallet_column');

function ms_add_wallet_column($columns) {
    $columns['wallet_balance'] = 'رصيد المحفظة';
    return $columns;
}

add_filter('manage_users_custom_column', 'ms_show_wallet_column', 10, 3);

function ms_show_wallet_column($value, $column_name, $user_id) {
    if ($column_name === 'wallet_balance') {
        $balance = ms_get_wallet_balance($user_id);
        $currency = get_user_meta($user_id, MS_WALLET_CURRENCY_META, true) ?: 'EGP';
        return number_format_i18n($balance, 2) . ' ' . $currency;
    }
    return $value;
}

// WooCommerce integration for wallet top-up
// This hook is a filter. Registering it as an action can leave WooCommerce's
// gateway collection null when another callback returns no value.
add_filter('woocommerce_payment_gateways', 'ms_add_wallet_gateway', 20, 1);

function ms_add_wallet_gateway($gateways) {
    // Add wallet as a payment method if WooCommerce is active
    if (class_exists('WC_Payment_Gateway')) {
        class MS_Wallet_Gateway extends WC_Payment_Gateway {
            public function __construct() {
                $this->id = 'ms_wallet';
                $this->icon = '';
                $this->has_fields = false;
                $this->method_title = 'محفظة Mostaager';
                $this->method_description = 'الدفع باستخدام رصيد المحفظة';
                
                $this->init_form_fields();
                $this->init_settings();
                
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
            }
            
            public function init_form_fields() {
                $this->form_fields = [
                    'enabled' => [
                        'title' => 'تفعيل/تعطيل',
                        'type' => 'checkbox',
                        'label' => 'تفعيل دفع المحفظة',
                        'default' => 'yes',
                    ],
                ];
            }
            
            public function process_payment($order_id) {
                $order = wc_get_order($order_id);
                $user_id = $order->get_user_id();
                $amount = $order->get_total();
                
                if (ms_get_wallet_balance($user_id) < $amount) {
                    wc_add_notice('رصيد المحفظة غير كافٍ', 'error');
                    return;
                }
                
                ms_deduct_from_wallet($user_id, $amount, 'دفع طلب #' . $order_id);
                
                $order->payment_complete();
                $order->add_order_note('تم الدفع باستخدام محفظة Mostaager');
                
                return [
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order),
                ];
            }
        }
        
        $gateways[] = new MS_Wallet_Gateway();
    }
    
    return $gateways;
}

// Add wallet top-up shortcode
add_shortcode('ms_wallet_topup', 'ms_render_wallet_topup');

function ms_render_wallet_topup($atts) {
    if (!is_user_logged_in()) {
        return '<p>يرجى تسجيل الدخول لشحن المحفظة.</p>';
    }
    
    $user_id = get_current_user_id();
    $balance = ms_get_wallet_balance($user_id);
    $currency = get_user_meta($user_id, MS_WALLET_CURRENCY_META, true) ?: 'EGP';
    
    ob_start();
    ?>
    <div class="ms-wallet-topup">
        <div class="ms-wallet-balance" style="padding:20px;background:#f8fafc;border-radius:8px;margin-bottom:20px;">
            <h3>رصيد المحفظة الحالي</h3>
            <p style="font-size:24px;font-weight:bold;color:#2563eb;">
                <?php echo number_format_i18n($balance, 2); ?> <?php echo esc_html($currency); ?>
            </p>
        </div>
        
        <?php if (class_exists('WooCommerce')) : ?>
            <form method="post" action="">
                <?php wp_nonce_field('ms_wallet_topup', 'ms_wallet_topup_nonce'); ?>
                <div style="margin-bottom:15px;">
                    <label for="topup_amount">مبلغ الشحن:</label>
                    <input type="number" id="topup_amount" name="topup_amount" min="1" step="0.01" required style="width:100%;padding:10px;margin-top:5px;">
                </div>
                <button type="submit" name="ms_wallet_topup_submit" class="button button-primary">شحن المحفظة</button>
            </form>
        <?php else : ?>
            <p>يرجى تثبيت WooCommerce لشحن المحفظة.</p>
        <?php endif; ?>
    </div>
    <?php
    
    return ob_get_clean();
}

// Process wallet top-up
add_action('init', 'ms_process_wallet_topup');

function ms_process_wallet_topup() {
    if (!isset($_POST['ms_wallet_topup_submit'])) {
        return;
    }
    
    if (!isset($_POST['ms_wallet_topup_nonce']) || !wp_verify_nonce($_POST['ms_wallet_topup_nonce'], 'ms_wallet_topup')) {
        return;
    }
    
    if (!is_user_logged_in()) {
        return;
    }
    
    $amount = floatval($_POST['topup_amount']);
    if ($amount <= 0) {
        return;
    }
    
    $user_id = get_current_user_id();
    
    // Create WooCommerce product for top-up
    if (class_exists('WC_Product_Simple')) {
        $product = new WC_Product_Simple();
        $product->set_name('شحن محفظة - ' . number_format_i18n($amount, 2));
        $product->set_price($amount);
        $product->set_virtual(true);
        $product_id = $product->save();
        
        // Add to cart and redirect to checkout
        WC()->cart->empty_cart();
        WC()->cart->add_to_cart($product_id);
        
        // Store user ID in session for post-payment processing
        WC()->session->set('ms_wallet_topup_user_id', $user_id);
        WC()->session->set('ms_wallet_topup_amount', $amount);
        
        wp_redirect(wc_get_checkout_url());
        exit;
    }
}

// Process wallet top-up after WooCommerce payment
// Payment completion receives the order id; it must never participate in the
// payment-gateway filter because its return value is not a gateway list.
add_action('woocommerce_payment_complete', 'ms_process_wallet_topup_after_payment', 999, 1);

function ms_process_wallet_topup_after_payment($order_id) {
    if (!WC()->session) {
        return;
    }
    
    $user_id = WC()->session->get('ms_wallet_topup_user_id');
    $amount = WC()->session->get('ms_wallet_topup_amount');
    
    if ($user_id && $amount) {
        ms_add_to_wallet($user_id, $amount, 'شحن محفظة عبر WooCommerce');
        
        WC()->session->__unset('ms_wallet_topup_user_id');
        WC()->session->__unset('ms_wallet_topup_amount');
    }
}
