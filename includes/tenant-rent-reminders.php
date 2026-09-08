<?php
if (!defined('ABSPATH')) exit;

function msfp_send_tenant_rent_due_reminders() {
    global $wpdb;
    $target_date = date('Y-m-d', current_time('timestamp') + (7 * DAY_IN_SECONDS));
    $table = $wpdb->prefix . 'ms_invoices';
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE due_date=%s AND status NOT IN ('paid','canceled','cancelled') AND (invoice_type='rent' OR invoice_category='property-rent')", $target_date));
    foreach ((array) $rows as $invoice) {
        $user_id = absint($invoice->user_id ?? 0);
        if (!$user_id) continue;
        $prefs = function_exists('ms_get_notification_preferences') ? ms_get_notification_preferences($user_id) : array('in_app'=>1,'email'=>0,'whatsapp'=>0,'push'=>0,'rent_reminder'=>1);
        if (isset($prefs['rent_reminder']) && empty($prefs['rent_reminder'])) continue;
        $marker = 'ms_rent_reminder_' . $target_date;
        if (get_user_meta($user_id, $marker . '_' . absint($invoice->id), true)) continue;
        $message = sprintf('تذكير: موعد دفع الإيجار القادم هو %s، والمبلغ المستحق %s ج.م. يرجى إتمام الدفع قبل الموعد.', $target_date, number_format_i18n((float) ($invoice->amount ?? 0), 2));
        $payload = array('type'=>'rent_due_reminder','message'=>$message,'related_id'=>absint($invoice->id),'building_id'=>absint($invoice->building_id ?? 0));
        if (!empty($prefs['in_app']) && function_exists('ms_add_notification')) ms_add_notification($user_id, 'rent_due_reminder', $message, absint($invoice->building_id ?? 0), absint($invoice->id));
        $user = get_userdata($user_id);
        if (!empty($prefs['email']) && $user && is_email($user->user_email)) {
            wp_mail($user->user_email, 'تذكير بموعد دفع الإيجار', '<div dir="rtl" style="font-family:Arial;line-height:1.8"><h2>تذكير بدفع الإيجار</h2><p>' . esc_html($message) . '</p></div>', array('Content-Type: text/html; charset=UTF-8'));
        }
        if (!empty($prefs['whatsapp']) && function_exists('ms_notification_preference_phone')) {
            $phone = ms_notification_preference_phone($user_id);
            if ($phone) do_action('ms_send_notification_whatsapp', $phone, $message, $payload, $user_id);
        }
        if (!empty($prefs['push']) && class_exists('MS_Firebase_FCM_Provider') && method_exists('MS_Firebase_FCM_Provider', 'send_to_user')) {
            MS_Firebase_FCM_Provider::send_to_user($user_id, array('title'=>'تذكير بدفع الإيجار','body'=>$message,'data'=>array('invoice_id'=>absint($invoice->id))));
        }
        update_user_meta($user_id, $marker . '_' . absint($invoice->id), current_time('mysql'));
    }
}

add_action('init', function () {
    if (!wp_next_scheduled('ms_tenant_rent_due_reminder_cron')) wp_schedule_event(time() + 300, 'daily', 'ms_tenant_rent_due_reminder_cron');
});
add_action('ms_tenant_rent_due_reminder_cron', 'msfp_send_tenant_rent_due_reminders');
