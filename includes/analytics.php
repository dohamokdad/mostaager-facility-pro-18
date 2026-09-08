<?php
if (!defined('ABSPATH')) exit;

function ms_allowed_analytics_events()
{
    return array('dashboard_tab_open', 'notification_open', 'invoice_payment_start', 'invoice_payment_complete', 'property_create_start', 'property_create_complete', 'agent_form_step', 'maintenance_filter_apply');
}

function ms_track_analytics_event($event_name, $context = '', $metadata = array(), $user_id = 0)
{
    $event_name = sanitize_key($event_name);
    if (!in_array($event_name, ms_allowed_analytics_events(), true)) return false;
    global $wpdb;
    $table = $wpdb->prefix . 'ms_analytics_events';
    $inserted = $wpdb->insert($table, array(
        'user_id' => absint($user_id ?: get_current_user_id()),
        'event_name' => $event_name,
        'context' => sanitize_text_field($context),
        'metadata' => wp_json_encode(array_slice((array) $metadata, 0, 10)),
        'created_at' => current_time('mysql'),
    ), array('%d', '%s', '%s', '%s', '%s'));
    return (bool) $inserted;
}

add_action('wp_ajax_ms_track_event', function () {
    if (!is_user_logged_in()) wp_send_json_error('not_logged_in', 401);
    check_ajax_referer('mostaager-ajax-nonce', 'security');
    $metadata = isset($_POST['metadata']) ? json_decode(wp_unslash($_POST['metadata']), true) : array();
    ms_track_analytics_event($_POST['event_name'] ?? '', $_POST['context'] ?? '', is_array($metadata) ? $metadata : array());
    wp_send_json_success(array('tracked' => true));
});

function ms_get_analytics_summary($days = 30)
{
    global $wpdb;
    $days = min(365, max(1, absint($days)));
    $table = $wpdb->prefix . 'ms_analytics_events';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT event_name, COUNT(*) AS total FROM {$table} WHERE created_at >= DATE_SUB(%s, INTERVAL %d DAY) GROUP BY event_name ORDER BY total DESC",
        current_time('mysql'), $days
    ), ARRAY_A);
}
