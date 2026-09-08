<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * houzez User Dashboard Integration
 * Integrates Mostaager features with houzez user dashboard (tenant dashboard)
 */

// Add Mostaager tabs to houzez user dashboard
add_filter('houzez_dashboard_tabs', 'ms_houzez_add_user_dashboard_tabs', 10, 2);

function ms_houzez_add_user_dashboard_tabs($tabs, $user_id) {
    // Only add for tenants
    if (!function_exists('ms_user_has_role') || !ms_user_has_role($user_id, 'tenant')) {
        return $tabs;
    }
    
    $tabs['ms_invoices'] = [
        'label' => 'فواتيري',
        'icon' => '🧾',
        'content' => 'ms_houzez_user_invoices_tab',
    ];
    
    $tabs['ms_maintenance'] = [
        'label' => 'طلبات الصيانة',
        'icon' => '🛠️',
        'content' => 'ms_houzez_user_maintenance_tab',
    ];
    
    $tabs['ms_wallet'] = [
        'label' => 'محفظتي',
        'icon' => '💰',
        'content' => 'ms_houzez_user_wallet_tab',
    ];
    
    $tabs['ms_notifications'] = [
        'label' => 'الإشعارات',
        'icon' => '🔔',
        'content' => 'ms_houzez_user_notifications_tab',
    ];
    
    return $tabs;
}

// User invoices tab content
function ms_houzez_user_invoices_tab($user_id) {
    $invoices = function_exists('ms_get_user_invoices') ? ms_get_user_invoices($user_id, 20) : [];
    
    ob_start();
    ?>
    <div class="ms-user-invoices">
        <h3>فواتيري</h3>
        
        <?php if (empty($invoices)): ?>
            <p>لا توجد فواتير.</p>
        <?php else: ?>
            <table class="houzez-data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>المبلغ</th>
                        <th>الحالة</th>
                        <th>تاريخ الاستحقاق</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td><?php echo esc_html($invoice->id); ?></td>
                            <td><?php echo number_format_i18n($invoice->amount, 2); ?> ج.م</td>
                            <td>
                                <?php if ($invoice->status === 'paid'): ?>
                                    <span style="color: #10b981; font-weight: bold;">مدفوع</span>
                                <?php elseif ($invoice->status === 'canceled'): ?>
                                    <span style="color: #ef4444; font-weight: bold;">ملغي</span>
                                <?php else: ?>
                                    <span style="color: #f59e0b; font-weight: bold;">معلق</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($invoice->due_date); ?></td>
                            <td>
                                <?php if ($invoice->status !== 'paid' && $invoice->status !== 'canceled'): ?>
                                    <a href="<?php echo esc_url(add_query_arg(['invoice_id' => $invoice->id, 'action' => 'pay'], houzez_get_permalink('invoice_payment'))); ?>" class="btn btn-primary btn-sm">دفع</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

// User maintenance tab content
function ms_houzez_user_maintenance_tab($user_id) {
    $requests = function_exists('ms_get_user_maintenance_requests') ? ms_get_user_maintenance_requests($user_id, 20) : [];
    
    ob_start();
    ?>
    <div class="ms-user-maintenance">
        <h3>طلبات الصيانة</h3>
        
        <button class="btn btn-primary" onclick="ms_show_maintenance_form()">+ طلب صيانة جديد</button>
        
        <div id="ms-maintenance-form" style="display: none; margin-top: 20px; padding: 20px; background: #f8fafc; border-radius: 8px;">
            <form id="ms-create-maintenance-form">
                <?php wp_nonce_field('ms_maintenance_nonce', 'ms_maintenance_nonce'); ?>
                <input type="hidden" name="action" value="ms_create_maintenance_request">
                
                <div class="form-group">
                    <label>العنوان</label>
                    <input type="text" name="title" required class="form-control">
                </div>
                
                <div class="form-group">
                    <label>الوصف</label>
                    <textarea name="description" required class="form-control"></textarea>
                </div>
                
                <div class="form-group">
                    <label>الأولوية</label>
                    <select name="priority" class="form-control">
                        <option value="low">منخفضة</option>
                        <option value="medium" selected>متوسطة</option>
                        <option value="high">عالية</option>
                        <option value="urgent">عاجلة</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">إرسال الطلب</button>
                <button type="button" class="btn btn-secondary" onclick="ms_hide_maintenance_form()">إلغاء</button>
            </form>
        </div>
        
        <?php if (empty($requests)): ?>
            <p style="margin-top: 20px;">لا توجد طلبات صيانة.</p>
        <?php else: ?>
            <table class="houzez-data-table" style="margin-top: 20px;">
                <thead>
                    <tr>
                        <th>العنوان</th>
                        <th>الأولوية</th>
                        <th>الحالة</th>
                        <th>التاريخ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $req): ?>
                        <tr>
                            <td><?php echo esc_html($req->title); ?></td>
                            <td><?php echo esc_html($req->priority); ?></td>
                            <td><?php echo esc_html($req->status); ?></td>
                            <td><?php echo esc_html($req->created_at); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <script>
    function ms_show_maintenance_form() {
        document.getElementById('ms-maintenance-form').style.display = 'block';
    }
    function ms_hide_maintenance_form() {
        document.getElementById('ms-maintenance-form').style.display = 'none';
    }
    </script>
    <?php
    return ob_get_clean();
}

// User wallet tab content
function ms_houzez_user_wallet_tab($user_id) {
    $balance = function_exists('ms_get_wallet_balance') ? ms_get_wallet_balance($user_id) : 0;
    $currency = get_user_meta($user_id, MS_WALLET_CURRENCY_META, true) ?: 'EGP';
    $transactions = function_exists('ms_get_wallet_transactions') ? ms_get_wallet_transactions($user_id, 20) : [];
    
    ob_start();
    ?>
    <div class="ms-user-wallet">
        <h3>محفظتي</h3>
        
        <div class="ms-wallet-balance" style="padding: 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 16px; color: #fff; margin-bottom: 20px;">
            <div style="font-size: 14px; opacity: 0.9;">الرصيد الحالي</div>
            <div style="font-size: 48px; font-weight: bold; margin: 10px 0;">
                <?php echo number_format_i18n($balance, 2); ?> <?php echo esc_html($currency); ?>
            </div>
            <button class="btn btn-light" onclick="ms_show_topup_form()">شحن المحفظة</button>
        </div>
        
        <div id="ms-topup-form" style="display: none; margin-bottom: 20px; padding: 20px; background: #f8fafc; border-radius: 8px;">
            <h4>شحن المحفظة</h4>
            <form id="ms-topup-form-inner">
                <?php wp_nonce_field('ms_wallet_topup', 'ms_wallet_topup_nonce'); ?>
                <input type="hidden" name="action" value="ms_wallet_topup">
                
                <div class="form-group">
                    <label>مبلغ الشحن</label>
                    <input type="number" name="amount" min="10" step="0.01" required class="form-control">
                </div>
                
                <button type="submit" class="btn btn-primary">شحن</button>
                <button type="button" class="btn btn-secondary" onclick="ms_hide_topup_form()">إلغاء</button>
            </form>
        </div>
        
        <h4>المعاملات الأخيرة</h4>
        
        <?php if (empty($transactions)): ?>
            <p>لا توجد معاملات.</p>
        <?php else: ?>
            <table class="houzez-data-table">
                <thead>
                    <tr>
                        <th>النوع</th>
                        <th>المبلغ</th>
                        <th>الوصف</th>
                        <th>التاريخ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $txn): ?>
                        <tr>
                            <td>
                                <?php if ($txn->type === 'credit'): ?>
                                    <span style="color: #10b981;">إضافة</span>
                                <?php else: ?>
                                    <span style="color: #ef4444;">خصم</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo number_format_i18n($txn->amount, 2); ?> <?php echo esc_html($currency); ?></td>
                            <td><?php echo esc_html($txn->description); ?></td>
                            <td><?php echo esc_html($txn->created_at); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <script>
    function ms_show_topup_form() {
        document.getElementById('ms-topup-form').style.display = 'block';
    }
    function ms_hide_topup_form() {
        document.getElementById('ms-topup-form').style.display = 'none';
    }
    </script>
    <?php
    return ob_get_clean();
}

// User notifications tab content
function ms_houzez_user_notifications_tab($user_id) {
    $notifications = function_exists('ms_get_user_notifications') ? ms_get_user_notifications($user_id, 50) : [];
    $unread_count = function_exists('ms_get_unread_notification_count') ? ms_get_unread_notification_count($user_id) : 0;
    
    ob_start();
    ?>
    <div class="ms-user-notifications">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3>الإشعارات</h3>
            <?php if ($unread_count > 0): ?>
                <button class="btn btn-secondary" onclick="ms_mark_all_read()">تعليم الكل كمقروء</button>
            <?php endif; ?>
        </div>
        
        <?php if (empty($notifications)): ?>
            <p>لا توجد إشعارات.</p>
        <?php else: ?>
            <div class="ms-notifications-list">
                <?php foreach ($notifications as $notif): ?>
                    <div class="ms-notification-item" style="padding: 15px; background: <?php echo ($notif->is_read == 0) ? '#dbeafe' : '#f8fafc'; ?>; border-radius: 8px; margin-bottom: 10px; border-left: 4px solid <?php echo ($notif->is_read == 0) ? '#2563eb' : '#94a3b8'; ?>;">
                        <div style="font-weight: 600; margin-bottom: 5px;">
                            <?php echo esc_html($notif->message); ?>
                            <?php if ($notif->is_read == 0): ?>
                                <span style="background: #2563eb; color: #fff; padding: 2px 8px; border-radius: 999px; font-size: 11px; margin-right: 8px;">جديد</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size: 12px; color: #64748b;">
                            <?php echo esc_html($notif->created_at); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
    function ms_mark_all_read() {
        if (!confirm('هل أنت متأكد من تعليم جميع الإشعارات كمقروءة؟')) return;
        
        var formData = new FormData();
        formData.append('action', 'ms_mark_all_notifications_read');
        formData.append('security', '<?php echo wp_create_nonce('mostaager-ajax-nonce'); ?>');
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                location.reload();
            } else {
                alert('حدث خطأ');
            }
        });
    }
    </script>
    <?php
    return ob_get_clean();
}

// Add Mostaager menu items to houzez user dashboard sidebar
add_filter('houzez_user_dashboard_menu', 'ms_houzez_add_user_dashboard_menu');

function ms_houzez_add_user_dashboard_menu($menu_items) {
    $user_id = get_current_user_id();
    
    if (!function_exists('ms_user_has_role') || !ms_user_has_role($user_id, 'tenant')) {
        return $menu_items;
    }
    
    $menu_items[] = [
        'label' => 'فواتيري',
        'icon' => '🧾',
        'url' => '#ms_invoices',
        'badge' => function_exists('ms_get_pending_invoices_count') ? ms_get_pending_invoices_count($user_id) : 0,
    ];
    
    $menu_items[] = [
        'label' => 'طلبات الصيانة',
        'icon' => '🛠️',
        'url' => '#ms_maintenance',
    ];
    
    $menu_items[] = [
        'label' => 'محفظتي',
        'icon' => '💰',
        'url' => '#ms_wallet',
    ];
    
    $menu_items[] = [
        'label' => 'الإشعارات',
        'icon' => '🔔',
        'url' => '#ms_notifications',
        'badge' => function_exists('ms_get_unread_notification_count') ? ms_get_unread_notification_count($user_id) : 0,
    ];
    
    return $menu_items;
}

// AJAX handler for creating maintenance request from user dashboard
add_action('wp_ajax_ms_create_maintenance_request', 'ms_houzez_ajax_create_maintenance_request');
add_action('wp_ajax_nopriv_ms_create_maintenance_request', 'ms_houzez_ajax_create_maintenance_request');

function ms_houzez_ajax_create_maintenance_request() {
    check_ajax_referer('ms_maintenance_nonce', 'ms_maintenance_nonce');
    
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
add_action('wp_ajax_nopriv_ms_wallet_topup', 'ms_houzez_ajax_wallet_topup');

function ms_houzez_ajax_wallet_topup() {
    check_ajax_referer('ms_wallet_topup', 'ms_wallet_topup_nonce');
    
    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error(['message' => 'يجب تسجيل الدخول']);
    }
    
    $amount = floatval($_POST['amount']);
    
    if ($amount <= 0) {
        wp_send_json_error(['message' => 'المبلغ يجب أن يكون أكبر من صفر']);
    }
    
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
        
        // Store user ID in session
        WC()->session->set('ms_wallet_topup_user_id', $user_id);
        WC()->session->set('ms_wallet_topup_amount', $amount);
        
        wp_send_json_success(['redirect' => wc_get_checkout_url()]);
    } else {
        wp_send_json_error(['message' => 'WooCommerce غير متوفر']);
    }
}

// Add Mostaager styles to houzez user dashboard
add_action('wp_enqueue_scripts', 'ms_houzez_enqueue_user_dashboard_styles');

function ms_houzez_enqueue_user_dashboard_styles() {
    if (!is_page_template('user-dashboard.php')) {
        return;
    }
    
    wp_enqueue_style(
        'ms-houzez-user-dashboard',
        MOSTAAGER_ENTERPRISE_URL . 'assets/css/houzez-user-dashboard.css',
        [],
        MOSTAAGER_ENTERPRISE_VERSION
    );
}
