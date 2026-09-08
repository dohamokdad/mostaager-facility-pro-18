<?php
if (!defined('ABSPATH')) {
    exit;
}

function ms_get_notification_preferences($user_id = 0)
{
    $user_id = absint($user_id ?: get_current_user_id());
    $defaults = array(
        'in_app' => 1,
        'email' => 0,
        'whatsapp' => 0,
        'push' => 0,
        'rent_reminder' => 1,
    );
    $saved = get_user_meta($user_id, 'ms_notification_preferences', true);
    return array_merge($defaults, is_array($saved) ? array_map('absint', $saved) : array());
}

function ms_save_notification_preferences($user_id, $preferences)
{
    $user_id = absint($user_id);
    $allowed = array('in_app', 'email', 'whatsapp', 'push', 'rent_reminder');
    $clean = array();
    foreach ($allowed as $key) {
        $clean[$key] = !empty($preferences[$key]) ? 1 : 0;
    }
    update_user_meta($user_id, 'ms_notification_preferences', $clean);
    return $clean;
}

function ms_notification_preference_phone($user_id)
{
    $user = get_userdata(absint($user_id));
    if (!$user) return '';
    foreach (array('phone', 'fave_author_phone', 'mobile', 'fave_author_mobile', 'billing_phone', 'user_phone', 'fave_author_whatsapp') as $key) {
        $value = get_user_meta($user->ID, $key, true);
        if ($value) return sanitize_text_field($value);
    }
    return '';
}

add_action('ms_notification_created', function ($notification_id, $user_id, $payload) {
    $preferences = ms_get_notification_preferences($user_id);
    $message = wp_strip_all_tags($payload['message'] ?? '');
    $user = get_userdata(absint($user_id));
    if (!$user || !$message) return;

    $event_type = sanitize_key($payload['type'] ?? '');
    $subject = 'إشعار جديد من منصة إيجار';
    if (in_array($event_type, array('maintenance_withdrawal_approved', 'transfer_approved'), true)) $subject = 'تمت الموافقة على طلب سحب الصيانة';
    if (in_array($event_type, array('maintenance_withdrawal_rejected', 'transfer_rejected'), true)) $subject = 'تم رفض طلب سحب الصيانة';
    if (!empty($preferences['email']) && is_email($user->user_email)) {
        $html = '<div dir="rtl" style="font-family:Arial,sans-serif;line-height:1.8"><h2>' . esc_html($subject) . '</h2><p>' . nl2br(esc_html($message)) . '</p></div>';
        $sent = wp_mail($user->user_email, $subject, $html, array('Content-Type: text/html; charset=UTF-8'));
        if (!$sent) error_log('[Mostaager] Withdrawal email notification failed for user #' . absint($user_id));
    }

    if (!empty($preferences['whatsapp'])) {
        $phone = ms_notification_preference_phone($user_id);
        if ($phone) {
            do_action('ms_send_notification_whatsapp', $phone, $message, $payload, $user_id);
        }
    }
}, 20, 3);

add_action('ms_send_notification_whatsapp', function ($phone, $message, $payload, $user_id) {
    if (!class_exists('Mostager_WhatsApp_Integration')) return;
    $whatsapp = new Mostager_WhatsApp_Integration();
    if (method_exists($whatsapp, 'is_enabled') && !$whatsapp->is_enabled()) return;
    $event_type = sanitize_key($payload['type'] ?? '');
    $status = in_array($event_type, array('maintenance_withdrawal_approved', 'transfer_approved'), true) ? 'approved' : (in_array($event_type, array('maintenance_withdrawal_rejected', 'transfer_rejected'), true) ? 'rejected' : '');
        if ($status && method_exists($whatsapp, 'send_withdrawal_update')) {
        global $wpdb;
        $request = null;
        $request_id = absint($payload['related_id'] ?? 0);
        if ($request_id) {
            $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ms_transfer_requests WHERE id = %d", $request_id));
        }
        $building_name = $request ? ('المبنى #' . absint($request->building_id)) : 'المبنى';
        $note = $request && !empty($request->notes) ? sanitize_text_field($request->notes) : $message;
        $result = $whatsapp->send_withdrawal_update($phone, $status, array(
            'manager_name' => get_userdata(absint($user_id)) ? get_userdata(absint($user_id))->display_name : '',
            'building_name' => $building_name,
            'amount' => $request ? (float) $request->amount : 0,
            'note' => $note,
        ));
        if (is_wp_error($result)) error_log('[Mostaager] WhatsApp withdrawal hook error: ' . $result->get_error_message());
        return;
    }
    if (method_exists($whatsapp, 'send_message')) {
        $result = $whatsapp->send_message($phone, $message);
        if (is_wp_error($result)) error_log('[Mostaager] WhatsApp notification error: ' . $result->get_error_message());
    }
}, 10, 4);

add_shortcode('ms_notification_preferences', function () {
    if (!is_user_logged_in()) return '';
    $prefs = ms_get_notification_preferences();
    ob_start();
    ?>
    <section class="ms-notification-preferences" data-ms-notification-preferences>
        <div class="ms-preferences-header">
            <div><p class="ms-eyebrow">التحكم في التنبيهات</p><h3>تفضيلات الإشعارات</h3><p>اختر القنوات التي تفضلها لتحديثات الصيانة والفواتير.</p></div>
            <span class="ms-preferences-status" data-preferences-status aria-live="polite"></span>
        </div>
        <form data-ms-notification-preferences-form>
            <?php foreach (array('in_app' => 'داخل لوحة التحكم', 'email' => 'البريد الإلكتروني', 'whatsapp' => 'WhatsApp', 'push' => 'إشعارات المتصفح / Firebase', 'rent_reminder' => 'تذكير موعد دفع الإيجار') as $key => $label) : ?>
                <label class="ms-preference-row"><span><strong><?php echo esc_html($label); ?></strong><small><?php echo esc_html($key === 'in_app' ? 'يظهر التنبيه فورًا داخل اللوحة' : 'يمكن تغييره لاحقًا من نفس المكان'); ?></small></span><input type="checkbox" name="<?php echo esc_attr($key); ?>" value="1" <?php checked(!empty($prefs[$key])); ?>></label>
            <?php endforeach; ?>
            <button type="submit" class="ms-action-button ms-action-button-primary">حفظ التفضيلات</button>
        </form>
    </section>
    <?php
    return ob_get_clean();
});
