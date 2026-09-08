<?php
if (!defined('ABSPATH')) exit;

// Debugging: capture any unexpected HTML or output generated during AJAX requests.
// Enabled only when WP_DEBUG is true to avoid noise on production.
if (defined('WP_DEBUG') && WP_DEBUG) {
    if (!function_exists('ms_maybe_start_ajax_output_buffer')) {
        function ms_maybe_start_ajax_output_buffer() {
            // only start buffer for admin-ajax.php requests
            if ((defined('DOING_AJAX') && DOING_AJAX) || (isset($_REQUEST['action']) && !empty($_REQUEST['action']))) {
                ob_start();
                register_shutdown_function(function () {
                    $buf = ob_get_clean();
                    if ($buf !== '') {
                        $action = isset($_REQUEST['action']) ? sanitize_text_field($_REQUEST['action']) : 'unknown';
                        // Truncate long output to avoid huge logs
                        $snippet = substr($buf, 0, 4096);
                        error_log("[Mostaager AJAX RAW OUTPUT] action: $action, output_snippet: " . $snippet);
                        // echo original buffer back so response doesn't get lost
                        echo $buf;
                    }
                });
            }
        }
        ms_maybe_start_ajax_output_buffer();
    }
}

// Include agent property actions (property status update + contract upload)
// MOSTAAGER_FACILITY_PRO_PATH may be undefined on some installs; use enterprise path as fallback.
$ms_agent_property_actions_path = (defined('MOSTAAGER_FACILITY_PRO_PATH') ? MOSTAAGER_FACILITY_PRO_PATH : MOSTAAGER_ENTERPRISE_PATH) . 'core/ajax-agent-property.php';
if (file_exists($ms_agent_property_actions_path)) {
    require_once $ms_agent_property_actions_path;
}

// Add facility to building
add_action('wp_ajax_ms_add_building_facility', function () {
    check_ajax_referer('ms_facility_nonce', 'security');
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'يرجى تسجيل الدخول.'), 403);
    }

    $user = wp_get_current_user();
    if (!function_exists('ms_user_can_view_dashboard') || !ms_user_can_view_dashboard($user->ID, 'building_manager')) {
        wp_send_json_error(array('message' => 'غير مصرح'), 403);
    }

    $building_id = isset($_POST['building_id']) ? absint($_POST['building_id']) : 0;
    $facility_name = isset($_POST['facility_name']) ? sanitize_text_field($_POST['facility_name']) : '';
    $facility_type = isset($_POST['facility_type']) ? absint($_POST['facility_type']) : 0;
    $facility_status = isset($_POST['facility_status']) ? sanitize_key($_POST['facility_status']) : 'working';

    if (!$building_id || empty($facility_name)) {
        wp_send_json_error(array('message' => 'بيانات غير صالحة'), 400);
    }

    global $wpdb;
    $facilities_table = $wpdb->prefix . 'ms_facilities';
    
    $inserted = $wpdb->insert(
        $facilities_table,
        array(
            'building_id' => $building_id,
            'facility_type_id' => $facility_type,
            'name' => $facility_name,
            'status' => $facility_status,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ),
        array('%d', '%d', '%s', '%s', '%s', '%s')
    );

    if ($inserted) {
        wp_send_json_success(array('message' => 'تمت الإضافة بنجاح'));
    } else {
        error_log('Facility insertion failed: ' . $wpdb->last_error);
        wp_send_json_error(array('message' => 'فشل الإضافة: ' . $wpdb->last_error));
    }
});

// Delete facility from building
add_action('wp_ajax_ms_delete_building_facility', function () {
    check_ajax_referer('ms_facility_nonce', 'security');
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'يرجى تسجيل الدخول.'), 403);
    }

    $user = wp_get_current_user();
    if (!function_exists('ms_user_can_view_dashboard') || !ms_user_can_view_dashboard($user->ID, 'building_manager')) {
        wp_send_json_error(array('message' => 'غير مصرح'), 403);
    }

    $facility_id = isset($_POST['facility_id']) ? absint($_POST['facility_id']) : 0;
    if (!$facility_id) {
        wp_send_json_error(array('message' => 'معرف المرفق غير صالح'), 400);
    }

    global $wpdb;
    $facilities_table = $wpdb->prefix . 'ms_facilities';
    $company = ms_get_company_clause('f');

    $deleted = $wpdb->delete(
        $facilities_table,
        array('id' => $facility_id, $company['column'] => $company['value']),
        array('%d', '%s')
    );

    if ($deleted) {
        wp_send_json_success(array('message' => 'تم الحذف بنجاح'));
    } else {
        wp_send_json_error(array('message' => 'فشل الحذف'));
    }
});

// Add error logging for debugging
function ms_log_ajax_error($action, $error_code, $message) {
    $context = array(
        'user_id' => get_current_user_id(),
        'request_uri' => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '',
        'site' => is_admin() ? 'admin' : 'front_end',
    );
    error_log(sprintf(
        '[Mostaager AJAX] action=%s code=%s message=%s context=%s',
        sanitize_key($action),
        sanitize_key($error_code),
        sanitize_text_field($message),
        wp_json_encode($context)
    ));
}


// Return owner dashboard summary (requires login)
add_action('wp_ajax_ms_get_owner_dashboard', function () {
    if (!is_user_logged_in()) {
        ms_log_ajax_error('ms_get_owner_dashboard', 'not_logged_in', 'User is not logged in.');
        wp_send_json_error('not_logged_in', 401);
    }

    $user = wp_get_current_user();
    if (!function_exists('ms_user_can_view_dashboard') || !ms_user_can_view_dashboard($user->ID, 'owner')) {
        ms_log_ajax_error('ms_get_owner_dashboard', 'forbidden', 'User does not have permission to view owner dashboard.');
        wp_send_json_error('forbidden', 403);
    }

    try {
        $properties = function_exists('ms_get_properties_by_owner') ? ms_get_properties_by_owner($user->ID) : [];
        $invoices = function_exists('ms_get_user_invoices') ? ms_get_user_invoices($user->ID) : [];
        $wallet = function_exists('ms_get_wallet_balance') ? ms_get_wallet_balance($user->ID) : 0;

        $data = array(
            'properties_count' => intval(count($properties)),
            'invoices_count' => intval(count($invoices)),
            'wallet_balance' => floatval($wallet),
            'invoices_paid' => function_exists('ms_get_invoices_count_by_user_and_status') ? ms_get_invoices_count_by_user_and_status($user->ID, 'paid') : 0,
            'invoices_pending' => function_exists('ms_get_invoices_count_by_user_and_status') ? ms_get_invoices_count_by_user_and_status($user->ID, 'pending') : 0,
            'owner_total_paid' => 0,
            'owner_total_due' => 0,
            'owner_next_due' => null,
            'owner_overdue_count' => function_exists('ms_get_user_overdue_count') ? ms_get_user_overdue_count($user->ID) : 0,
        );

        $summary = function_exists('ms_get_owner_revenue_summary') ? ms_get_owner_revenue_summary($user->ID) : null;
        if ($summary) {
            $data['owner_total_paid'] = $summary['total_paid'];
            $data['owner_total_due'] = $summary['total_due'];
            $data['owner_next_due'] = $summary['next_due'] && isset($summary['next_due']->due_date) ? $summary['next_due']->due_date : null;
        }

        wp_send_json_success($data);
    } catch (Exception $e) {
        ms_log_ajax_error('ms_get_owner_dashboard', 'exception', $e->getMessage());
        wp_send_json_error('server_error', 500);
    }
});

// Building dashboard summary for manager
add_action('wp_ajax_ms_get_building_dashboard', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 401);
    }

    $user = wp_get_current_user();
    if (!function_exists('ms_user_can_view_dashboard') || !ms_user_can_view_dashboard($user->ID, 'building_manager')) {
        wp_send_json_error('forbidden', 403);
    }

    $buildings = function_exists('ms_get_buildings_by_manager') ? ms_get_buildings_by_manager($user->ID) : [];
    $allowed_building_ids = array_values(array_filter(array_map(function ($building) {
        return absint($building->id ?? $building->ID ?? 0);
    }, (array) $buildings)));
    $requested_building_id = isset($_POST['building_id']) ? absint($_POST['building_id']) : 0;
    if ($requested_building_id && !current_user_can('manage_options') && !in_array($requested_building_id, $allowed_building_ids, true)) {
        wp_send_json_error('forbidden_building', 403);
    }
    $selected_building_id = $requested_building_id && in_array($requested_building_id, $allowed_building_ids, true) ? $requested_building_id : 0;
    $selected_units_count = 0;
    if ($selected_building_id) {
        global $wpdb;
        $units_table = $wpdb->prefix . 'ms_units';
        $selected_units_count = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$units_table} WHERE building_id = %d",
            $selected_building_id
        )));
    }

    $data = array(
        'buildings_count' => intval(count($buildings)),
        'selected_building_id' => $selected_building_id,
        'selected_building_units_count' => $selected_units_count,
        'units_count' => $selected_building_id ? $selected_units_count : (function_exists('ms_get_units_count_by_manager') ? ms_get_units_count_by_manager($user->ID) : 0),
        'paid_invoices_count' => function_exists('ms_get_paid_invoices_count_for_manager') ? ms_get_paid_invoices_count_for_manager($user->ID) : 0,
        'active_maintenance_count' => function_exists('ms_get_active_maintenance_by_manager') ? ms_get_active_maintenance_by_manager($user->ID) : 0,
        // extended stats
        'collection' => function_exists('ms_get_collection_stats_by_manager') ? ms_get_collection_stats_by_manager($user->ID) : array('total_due'=>0,'total_collected'=>0,'percent'=>0),
        'units_paid_info' => function_exists('ms_get_paid_unpaid_units_by_manager') ? ms_get_paid_unpaid_units_by_manager($user->ID) : array('total'=>0,'paid'=>0,'unpaid'=>0),
    );

    wp_send_json_success($data);
});

// Agent dashboard summary
add_action('wp_ajax_ms_get_agent_dashboard', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 401);
    }

    $user = wp_get_current_user();
    if (!function_exists('ms_user_can_view_dashboard') || !ms_user_can_view_dashboard($user->ID, 'agent')) {
        wp_send_json_error('forbidden', 403);
    }

    $listings = function_exists('ms_get_properties_by_agent') ? ms_get_properties_by_agent($user->ID) : [];
    if (empty($listings) && function_exists('ms_get_properties_by_owner')) {
        $listings = ms_get_properties_by_owner($user->ID);
    }

    if (empty($listings)) {
        $listing_ids = array();
        $author_listings = get_posts(array(
            'post_type' => 'property',
            'post_status' => array('publish', 'pending', 'draft', 'expired', 'houzez_sold', 'disapproved', 'on_hold', 'private', 'future'),
            'posts_per_page' => -1,
            'author' => $user->ID,
            'fields' => 'ids',
        ));

        foreach ($author_listings as $post_id) {
            if (!in_array($post_id, $listing_ids, true)) {
                $listing_ids[] = $post_id;
            }
        }

        $agent_meta_listings = get_posts(array(
            'post_type' => 'property',
            'post_status' => array('publish', 'pending', 'draft', 'expired', 'houzez_sold', 'disapproved', 'on_hold', 'private', 'future'),
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'fave_agents',
                    'value' => '"' . $user->ID . '"',
                    'compare' => 'LIKE',
                ),
                array(
                    'key' => 'fave_property_agency',
                    'value' => '"' . $user->ID . '"',
                    'compare' => 'LIKE',
                ),
            ),
            'fields' => 'ids',
        ));

        foreach ($agent_meta_listings as $post_id) {
            if (!in_array($post_id, $listing_ids, true)) {
                $listing_ids[] = $post_id;
            }
        }

        $data = array(
            'listings_count' => intval(count($listing_ids)),
        );
    } else {
        $data = array(
            'listings_count' => intval(count($listings)),
        );
    }

    $subscription_status = function_exists('ms_get_agent_subscription_status') ? ms_get_agent_subscription_status($user->ID) : array('monthly_fee' => 0, 'status' => 'unknown', 'due' => 0);
    $data['subscription_status'] = $subscription_status;

    wp_send_json_success($data);
});

// Delete a property from the agent dashboard
add_action('wp_ajax_ms_delete_agent_property', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 401);
    }

    $user = wp_get_current_user();
    $can_view_agent_dashboard = function_exists('ms_user_can_view_dashboard') && ms_user_can_view_dashboard($user->ID, 'agent');
    $can_view_owner_dashboard = function_exists('ms_user_can_view_dashboard') && ms_user_can_view_dashboard($user->ID, 'owner');
    if (!$can_view_agent_dashboard && !$can_view_owner_dashboard) {
        wp_send_json_error('forbidden', 403);
    }

    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'delete_properties_nonce')) {
        wp_send_json_error('security_failed', 403);
    }

    if (empty($_POST['prop_id'])) {
        wp_send_json_error('invalid_property_id', 400);
    }

    $prop_id = absint($_POST['prop_id']);
    if (!$prop_id) {
        wp_send_json_error('invalid_property_id', 400);
    }

    $listing_ids = array();
    $listings = function_exists('ms_get_properties_by_agent') ? ms_get_properties_by_agent($user->ID) : [];
    if (empty($listings) && function_exists('ms_get_properties_by_owner')) {
        $listings = ms_get_properties_by_owner($user->ID);
    }

    if (!empty($listings)) {
        foreach ((array) $listings as $listing) {
            $lid = 0;
            if (is_object($listing)) {
                $lid = intval($listing->ID ?? $listing->id ?? 0);
                if (!$lid && isset($listing->unit_id)) $lid = intval($listing->unit_id);
                if (!$lid && isset($listing->post_id)) $lid = intval($listing->post_id);
            } elseif (is_array($listing)) {
                $lid = intval($listing['ID'] ?? $listing['id'] ?? 0);
                if (!$lid && isset($listing['unit_id'])) $lid = intval($listing['unit_id']);
            } elseif (is_numeric($listing)) {
                $lid = intval($listing);
            }
            if ($lid) $listing_ids[] = $lid;
        }
    }

    if (empty($listing_ids)) {
        $author_listings = get_posts(array(
            'post_type' => 'property',
            'post_status' => array('publish', 'pending', 'draft', 'expired', 'houzez_sold', 'disapproved', 'on_hold', 'private', 'future'),
            'posts_per_page' => -1,
            'author' => $user->ID,
            'fields' => 'ids',
        ));
        $listing_ids = array_merge($listing_ids, array_map('absint', $author_listings));

        $agent_meta_listings = get_posts(array(
            'post_type' => 'property',
            'post_status' => array('publish', 'pending', 'draft', 'expired', 'houzez_sold', 'disapproved', 'on_hold', 'private', 'future'),
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'fave_agents',
                    'value' => '"' . $user->ID . '"',
                    'compare' => 'LIKE',
                ),
                array(
                    'key' => 'fave_property_agency',
                    'value' => '"' . $user->ID . '"',
                    'compare' => 'LIKE',
                ),
            ),
            'fields' => 'ids',
        ));
        $listing_ids = array_merge($listing_ids, array_map('absint', $agent_meta_listings));
    }

    if (!in_array($prop_id, $listing_ids, true)) {
        wp_send_json_error('permission_denied', 403);
    }

    $post = get_post($prop_id);
    if (!$post || $post->post_type !== 'property') {
        wp_send_json_error('property_not_found', 404);
    }

    if (function_exists('houzez_delete_property_attachments_frontend') && get_post_status($prop_id) !== 'draft') {
        houzez_delete_property_attachments_frontend($prop_id);
    }

    $deleted = wp_delete_post($prop_id, true);
    if (!$deleted) {
        wp_send_json_error('delete_failed', 500);
    }

    wp_send_json_success(array('message' => 'تم حذف العقار بنجاح.'));
});

// Rent dashboard summary
add_action('wp_ajax_ms_get_rent_dashboard', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 401);
    }

    $user = wp_get_current_user();
    if (!function_exists('ms_user_can_view_dashboard') || !ms_user_can_view_dashboard($user->ID, 'tenant')) {
        wp_send_json_error('forbidden', 403);
    }

    $wallet = function_exists('ms_get_wallet_balance') ? ms_get_wallet_balance($user->ID) : 0;

    $data = array(
        'wallet_balance' => floatval($wallet),
    );
    $data['invoices_count'] = intval(count(function_exists('ms_get_tenant_invoices') ? ms_get_tenant_invoices($user->ID) : array()));
    $data['overdue_count'] = function_exists('ms_get_user_overdue_count') ? ms_get_user_overdue_count($user->ID) : 0;
    $next = function_exists('ms_get_latest_due_invoice') ? ms_get_latest_due_invoice($user->ID) : null;
    $data['next_due'] = $next ? $next->due_date : null;
    $data['next_due_amount'] = $next ? floatval($next->amount) : 0;
    $rent_streak = function_exists('ms_get_rent_streak_badge') ? ms_get_rent_streak_badge($user->ID) : array('streak'=>0,'label'=>'','color'=>'#64748b');
    $data['rent_streak'] = $rent_streak;

    wp_send_json_success($data);
});

// Mark tenant notifications as read
add_action('wp_ajax_ms_mark_notifications_read', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 401);
    }

    $user = wp_get_current_user();
    $allowed_roles = array('tenant', 'owner', 'agent', 'building_manager');
    $has_access = false;
    if (function_exists('ms_user_has_role')) {
        foreach ($allowed_roles as $role) {
            if (ms_user_has_role($user->ID, $role)) {
                $has_access = true;
                break;
            }
        }
    }
    if (!$has_access) {
        wp_send_json_error('forbidden', 403);
    }

    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'mostaager-ajax-nonce')) {
        wp_send_json_error('security_failed', 403);
    }

    if (!function_exists('ms_mark_notifications_read')) {
        wp_send_json_error('function_not_available', 500);
    }

    $updated = ms_mark_notifications_read($user->ID);
    wp_send_json_success(array('updated' => $updated));
});

// Fetch notifications created after a client-side cursor.
add_action('wp_ajax_ms_get_notifications_since', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 401);
    }

    if (!isset($_POST['security']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['security'])), 'mostaager-ajax-nonce')) {
        wp_send_json_error('security_failed', 403);
    }

    $user_id = get_current_user_id();
    $after_id = isset($_POST['after_id']) ? absint($_POST['after_id']) : 0;
    $rows = function_exists('ms_get_notifications_since')
        ? ms_get_notifications_since($user_id, $after_id, 20)
        : array();

    $payload = array();
    foreach ((array) $rows as $row) {
        $payload[] = array(
            'id' => absint($row->id ?? 0),
            'type' => sanitize_key($row->type ?? ''),
            'message' => wp_strip_all_tags($row->message ?? ''),
            'building_id' => absint($row->building_id ?? 0),
            'related_id' => absint($row->related_id ?? 0),
            'is_read' => !empty($row->is_read),
            'created_at' => sanitize_text_field($row->created_at ?? ''),
        );
    }

    wp_send_json_success(array(
        'notifications' => $payload,
        'last_id' => !empty($payload) ? end($payload)['id'] : $after_id,
        'unread_count' => function_exists('ms_get_unread_notifications_count')
            ? absint(ms_get_unread_notifications_count($user_id))
            : 0,
    ));
});

add_action('wp_ajax_ms_register_push_token', function () {
    if (!is_user_logged_in()) wp_send_json_error('not_logged_in', 401);
    check_ajax_referer('mostaager-ajax-nonce', 'security');
    $token = sanitize_text_field($_POST['token'] ?? '');
    $platform = sanitize_key($_POST['platform'] ?? 'web');
    if (!$token || !class_exists('MS_Firebase_FCM_Provider') || !MS_Firebase_FCM_Provider::register_token(get_current_user_id(), $token, $platform)) {
        wp_send_json_error('invalid_token', 422);
    }
    wp_send_json_success(array('registered' => true));
});

add_action('wp_ajax_ms_unregister_push_token', function () {
    if (!is_user_logged_in()) wp_send_json_error('not_logged_in', 401);
    check_ajax_referer('mostaager-ajax-nonce', 'security');
    $token = sanitize_text_field($_POST['token'] ?? '');
    if ($token && class_exists('MS_Firebase_FCM_Provider')) {
        MS_Firebase_FCM_Provider::unregister_token(get_current_user_id(), $token);
    }
    wp_send_json_success(array('removed' => true));
});

add_action('wp_ajax_ms_save_notification_preferences', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 401);
    }
    $nonce = isset($_POST['security']) ? sanitize_text_field(wp_unslash($_POST['security'])) : '';
    if (!wp_verify_nonce($nonce, 'mostaager-ajax-nonce')) {
        wp_send_json_error('security_failed', 403);
    }
    $values = array();
    foreach (array('in_app', 'email', 'whatsapp', 'push', 'rent_reminder') as $key) {
        $values[$key] = !empty($_POST[$key]) ? 1 : 0;
    }
    $saved = function_exists('ms_save_notification_preferences')
        ? ms_save_notification_preferences(get_current_user_id(), $values)
        : $values;
    wp_send_json_success(array('preferences' => $saved));
});

// Create maintenance request
add_action('wp_ajax_ms_create_maintenance_request', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 401);
    }

    $user = wp_get_current_user();
    if (!function_exists('ms_user_has_role') || !ms_user_has_role($user->ID, 'building_manager')) {
        wp_send_json_error('forbidden', 403);
    }

    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'mostaager-ajax-nonce')) {
        wp_send_json_error('security_failed', 403);
    }

    $building_id = isset($_POST['building_id']) ? intval($_POST['building_id']) : 0;
    $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
    $maintenance_type = isset($_POST['maintenance_type']) ? sanitize_text_field($_POST['maintenance_type']) : 'emergency';
    $cost = isset($_POST['cost']) ? round((float) $_POST['cost'], 2) : 0;
    $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : null;
    $due_date = isset($_POST['due_date']) ? sanitize_text_field($_POST['due_date']) : null;
    $is_recurring = isset($_POST['is_recurring']) ? intval($_POST['is_recurring']) : 0;
    $recurrence_day = isset($_POST['recurrence_day']) ? intval($_POST['recurrence_day']) : 1;

    if (!$building_id || empty($title) || $cost <= 0) {
        wp_send_json_error('invalid_data', 400);
    }
    $max_cost = (float) get_option('ms_max_maintenance_cost', 10000000);
    if ($max_cost > 0 && $cost > $max_cost) {
        wp_send_json_error('maintenance_cost_limit_exceeded', 422);
    }

    $valid_types = array('monthly', 'emergency', 'capital');
    if (!in_array($maintenance_type, $valid_types, true)) {
        wp_send_json_error('invalid_maintenance_type', 400);
    }

    if ($is_recurring && ($recurrence_day < 1 || $recurrence_day > 28)) {
        wp_send_json_error('invalid_recurrence_day', 400);
    }

    // Ensure the current manager actually manages the requested building
    if (!current_user_can('manage_options') && (!function_exists('ms_current_user_manages_building') || !ms_current_user_manages_building($user->ID, $building_id))) {
        wp_send_json_error('forbidden', 403);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'ms_maintenance_requests';

    $payer_type = isset($_POST['payer_type']) ? sanitize_text_field($_POST['payer_type']) : 'owner';
    $allowed_payer_types = array('owner', 'tenant', 'agent');
    if (!in_array($payer_type, $allowed_payer_types, true)) {
        $payer_type = 'owner';
    }

    $wpdb->insert($table, [
        'building_id' => $building_id,
        'title' => $title,
        'maintenance_type' => $maintenance_type,
        'cost' => $cost,
        'start_date' => $start_date,
        'due_date' => $due_date,
        'is_recurring' => $is_recurring,
        'recurrence_day' => $recurrence_day,
        'manager_id' => $user->ID,
        'status' => 'open',
        'payer_type' => $payer_type,
        'created_at' => current_time('mysql'),
    ], ['%d', '%s', '%s', '%f', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s']);

    $new_id = $wpdb->insert_id;
    $allowed_payer_types = array('owner', 'tenant', 'agent');
    if (!in_array($payer_type, $allowed_payer_types, true)) {
        $payer_type = 'owner';
    }
    $invoices_created = function_exists('ms_generate_invoices_from_maintenance') ? ms_generate_invoices_from_maintenance($new_id, $payer_type) : 0;

    // Trigger maintenance created action for integrations
    do_action('ms_new_maintenance_ticket', intval($new_id), array(
        'building_id' => $building_id,
        'title' => $title,
        'maintenance_type' => $maintenance_type,
        'cost' => $cost,
        'start_date' => $start_date,
        'due_date' => $due_date,
        'manager_id' => $user->ID,
        'payer_type' => $payer_type,
    ));

    wp_send_json_success([
        'maintenance_id' => intval($new_id),
        'invoices_created' => $invoices_created,
        'message' => "تم إنشاء {$invoices_created} فاتورة"
    ]);
});

// Request a transfer for a building
add_action('wp_ajax_ms_request_building_transfer', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'not_logged_in'], 401);
    }

    $user = wp_get_current_user();
    if (!current_user_can('manage_options') && (!function_exists('ms_user_has_role') || !ms_user_has_role($user->ID, 'building_manager'))) {
        wp_send_json_error(['message' => 'forbidden'], 403);
    }

    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'mostaager-ajax-nonce')) {
        wp_send_json_error(['message' => 'security_failed'], 403);
    }

    $building_id = isset($_POST['building_id']) ? intval($_POST['building_id']) : 0;
    $expense_id = isset($_POST['expense_id']) ? intval($_POST['expense_id']) : 0;
    $transfer_amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $notes = isset($_POST['note']) ? sanitize_textarea_field($_POST['note']) : '';
    $confirm_paid = isset($_POST['confirm_paid_invoices']) ? 1 : 0;
    $electronic_sig = isset($_POST['electronic_signature']) ? sanitize_text_field($_POST['electronic_signature']) : '';

    $result = ms_handle_transfer_submission($user, $building_id, $expense_id, $transfer_amount, $notes, $confirm_paid, $electronic_sig);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()], 400);
    }

    wp_send_json_success(['transfer_id' => intval($result)]);
});

// Request withdrawal of a fully collected maintenance amount.
add_action('wp_ajax_ms_request_maintenance_withdrawal', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'يجب تسجيل الدخول.'), 401);
    }
    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'mostaager-ajax-nonce')) {
        wp_send_json_error(array('message' => 'فشل التحقق الأمني.'), 403);
    }

    $user = wp_get_current_user();
    $maintenance_id = isset($_POST['maintenance_id']) ? absint($_POST['maintenance_id']) : 0;
    $building_id = isset($_POST['building_id']) ? absint($_POST['building_id']) : 0;
    $note = isset($_POST['note']) ? sanitize_textarea_field($_POST['note']) : '';
    $roles = array_map('sanitize_key', (array) $user->roles);
    $is_association_president = (bool) array_intersect($roles, array('building_manager', 'building-manager', 'owners_association_president', 'owners-association-president', 'president'));
    $can_manage = current_user_can('manage_options') || $is_association_president || (function_exists('ms_user_has_role') && ms_user_has_role($user->ID, 'building_manager'));
    if (!$can_manage || !$maintenance_id || !$building_id) {
        wp_send_json_error(array('message' => 'بيانات غير صالحة أو لا تملك الصلاحية.'), 403);
    }
    if (!current_user_can('manage_options') && function_exists('ms_current_user_manages_building') && !ms_current_user_manages_building($user->ID, $building_id)) {
        wp_send_json_error(array('message' => 'لا تملك صلاحية هذا البناء.'), 403);
    }

    global $wpdb;
    $maintenance_table = $wpdb->prefix . 'ms_maintenance_requests';
    $maintenance = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$maintenance_table} WHERE id = %d AND building_id = %d", $maintenance_id, $building_id));
    if (!$maintenance) {
        wp_send_json_error(array('message' => 'طلب الصيانة غير موجود.'), 404);
    }

    $progress = function_exists('ms_get_maintenance_collection_progress') ? ms_get_maintenance_collection_progress($maintenance_id) : array();
    $total_invoices = intval($progress['total'] ?? 0);
    $paid_invoices = intval($progress['paid'] ?? 0);
    $paid_amount = round(floatval($progress['paid_amount'] ?? 0), 2);
    if ($total_invoices < 1 || $paid_invoices < $total_invoices || $paid_amount <= 0) {
        wp_send_json_error(array('message' => 'لا يمكن طلب السحب قبل اكتمال تحصيل جميع الفواتير.'), 400);
    }

    global $wpdb;
    $transfer_table = $wpdb->prefix . 'ms_transfer_requests';
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$transfer_table} WHERE work_order_id = %d AND building_id = %d AND status IN ('pending','approved') LIMIT 1",
        $maintenance_id,
        $building_id
    ));
    if ($existing) {
        wp_send_json_error(array('message' => 'يوجد طلب سحب قائم لهذا الطلب بالفعل.'), 409);
    }

    $bank_name = sanitize_text_field($_POST['bank_name'] ?? get_user_meta($user->ID, 'ms_bank_name', true) ?: get_user_meta($user->ID, 'bank_name', true));
    $bank_account_name = sanitize_text_field($_POST['bank_account_name'] ?? get_user_meta($user->ID, 'ms_bank_account_name', true) ?: get_user_meta($user->ID, 'bank_account_name', true));
    $bank_account_number = sanitize_text_field($_POST['bank_account_number'] ?? get_user_meta($user->ID, 'ms_bank_account_number', true) ?: get_user_meta($user->ID, 'bank_account_number', true));
    $bank_iban = sanitize_text_field($_POST['bank_iban'] ?? get_user_meta($user->ID, 'ms_bank_iban', true) ?: get_user_meta($user->ID, 'bank_iban', true));
    if (!$bank_name || (!$bank_account_number && !$bank_iban)) {
        wp_send_json_error(array('message' => 'يرجى إكمال اسم البنك ورقم الحساب أو IBAN قبل إرسال طلب التحويل.'), 422);
    }

    $inserted = $wpdb->insert($transfer_table, array(
        'work_order_id' => $maintenance_id,
        'building_id' => $building_id,
        'requested_by' => $user->ID,
        'owner_id' => $user->ID,
        'bank_name' => $bank_name,
        'bank_account_name' => $bank_account_name,
        'bank_account_number' => $bank_account_number,
        'bank_iban' => $bank_iban,
        'amount' => $paid_amount,
        'status' => 'pending',
        'notes' => $note,
        'created_at' => current_time('mysql'),
    ));
    if ($inserted === false) {
        wp_send_json_error(array('message' => 'تعذر حفظ طلب التحويل.'), 500);
    }
    $transfer_request_id = absint($wpdb->insert_id);

    $transfer_id = wp_insert_post(array(
        'post_title' => sprintf('طلب تحويل صيانة #%d - %s', $maintenance_id, current_time('Y-m-d')),
        'post_content' => $note,
        'post_status' => 'publish',
        'post_type' => 'transfers',
        'post_author' => $user->ID,
    ), true);
    if (is_wp_error($transfer_id)) {
        wp_send_json_error(array('message' => 'تعذر إنشاء طلب السحب.'), 500);
    }

    update_post_meta($transfer_id, 'transfer_type', 'maintenance_withdrawal');
    update_post_meta($transfer_id, 'maintenance_id', $maintenance_id);
    update_post_meta($transfer_id, 'building_id', $building_id);
    update_post_meta($transfer_id, 'amount', $paid_amount);
    update_post_meta($transfer_id, 'status', 'pending');
    update_post_meta($transfer_id, 'requested_by', $user->ID);
    update_post_meta($transfer_id, 'requested_date', current_time('mysql'));
    update_post_meta($transfer_id, 'collection_paid_count', $paid_invoices);
    update_post_meta($transfer_id, 'collection_total_count', $total_invoices);
    update_post_meta($transfer_id, '_ms_source_transfer_request_id', $transfer_request_id);
    update_post_meta($transfer_id, 'bank_name', $bank_name);
    update_post_meta($transfer_id, 'bank_account_name', $bank_account_name);
    update_post_meta($transfer_id, 'bank_account_number', $bank_account_number);
    update_post_meta($transfer_id, 'bank_iban', $bank_iban);

    $admins = get_users(array('capability' => 'manage_options', 'fields' => 'ID'));
    foreach ((array) $admins as $admin_id) {
        if (function_exists('ms_add_notification')) {
            ms_add_notification(absint($admin_id), 'maintenance_withdrawal_requested', sprintf('طلب سحب مبلغ صيانة #%d بقيمة %s ج.م', $maintenance_id, number_format_i18n($paid_amount, 2)), $building_id, $transfer_id);
        }
    }

    wp_send_json_success(array('transfer_id' => absint($transfer_id), 'transfer_request_id' => $transfer_request_id, 'amount' => $paid_amount, 'message' => 'تم إرسال طلب التحويل إلى مدير الموقع للمراجعة.'));
});

// Get maintenance requests
add_action('wp_ajax_ms_get_maintenance_requests', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 401);
    }

    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'mostaager-ajax-nonce')) {
        wp_send_json_error('security_failed', 403);
    }

    $building_id = isset($_POST['building_id']) ? intval($_POST['building_id']) : 0;
    if (!$building_id) {
        wp_send_json_error('invalid_building_id', 400);
    }

    if (function_exists('ms_get_maintenance_requests')) {
        $rows = ms_get_maintenance_requests(array('building_id' => $building_id, 'limit' => 0));
    } else {
        global $wpdb;
        $table = $wpdb->prefix . 'ms_maintenance_requests';
        $building_ids = function_exists('ms_get_building_id_values_for_query') ? ms_get_building_id_values_for_query($building_id) : array($building_id);
        $rows = array();
        if (!empty($building_ids)) {
            $placeholders = implode(',', array_fill(0, count($building_ids), '%d'));
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE building_id IN ($placeholders) ORDER BY created_at DESC",
                ...$building_ids
            ));
        }
    }

    $combined = array();
    if (!empty($rows)) {
        foreach ($rows as $row) {
            $row->source = 'new';
            $collection = function_exists('ms_get_maintenance_collection_progress') ? ms_get_maintenance_collection_progress(intval($row->id)) : array();
            $row->collection = $collection;
            $row->invoices_count = intval($collection['total'] ?? 0);
            $row->paid_invoices_count = intval($collection['paid'] ?? 0);
            $row->collection_complete = ($row->invoices_count > 0 && $row->paid_invoices_count >= $row->invoices_count);
            $combined['new:' . intval($row->id)] = $row;
        }
    }

    if (post_type_exists('expenses') && function_exists('ms_get_legacy_expense_posts_by_building')) {
        $legacy_expenses = ms_get_legacy_expense_posts_by_building($building_id);
        if (!empty($legacy_expenses)) {
            foreach ((array) $legacy_expenses as $post) {
                if (!is_object($post)) {
                    continue;
                }

                $key = 'legacy:' . intval($post->ID);
                if (isset($combined[$key])) {
                    continue;
                }

                $combined[$key] = (object) array(
                    'id' => intval($post->ID),
                    'title' => $post->post_title,
                    'cost' => floatval(get_post_meta($post->ID, 'total_amount', true)),
                    'status' => get_post_meta($post->ID, 'status', true) ?: 'open',
                    'maintenance_type' => get_post_meta($post->ID, 'expense_type', true) ?: '',
                    'building_id' => $building_id,
                    'created_at' => $post->post_date,
                    'source' => 'legacy',
                );
            }
        }
    }

    $combined_rows = array_values($combined);
    usort($combined_rows, function ($a, $b) {
        $a_date = strtotime($a->created_at ?? ($a->post_date ?? '0'));
        $b_date = strtotime($b->created_at ?? ($b->post_date ?? '0'));
        return $b_date <=> $a_date;
    });

    wp_send_json_success($combined_rows);
});

function ms_handle_transfer_submission($user, $building_id, $expense_id, $transfer_amount, $notes, $confirm_paid, $electronic_sig)
{
    if (!$user || !$user->ID || !$building_id || !$expense_id || $transfer_amount <= 0 || !$confirm_paid || !$electronic_sig) {
        return new WP_Error('invalid_data', 'invalid_data');
    }

    if (!current_user_can('manage_options') && (!function_exists('ms_current_user_manages_building') || !ms_current_user_manages_building($user->ID, $building_id))) {
        return new WP_Error('forbidden', 'forbidden');
    }

    $expense = get_post($expense_id);
    $expense_building_id = 0;
    $expense_title = '';
    if ($expense && $expense->post_type === 'expenses') {
        $expense_building_id = get_post_meta($expense_id, 'building_id', true);
        $expense_title = $expense->post_title;
        if (!$expense_building_id && function_exists('get_field')) {
            $expense_building_id = get_field('building_id', $expense_id);
            if (is_object($expense_building_id)) {
                $expense_building_id = intval($expense_building_id->ID);
            }
        }
    } else {
        global $wpdb;
        $maintenance_table = $wpdb->prefix . 'ms_maintenance_requests';
        $maintenance = $wpdb->get_row($wpdb->prepare("SELECT title, building_id FROM {$maintenance_table} WHERE id = %d", $expense_id));
        if (!$maintenance) {
            return new WP_Error('invalid_expense', 'invalid_expense');
        }
        $expense_building_id = intval($maintenance->building_id);
        $expense_title = $maintenance->title;
    }

    if (intval($expense_building_id) !== intval($building_id)) {
        return new WP_Error('expense_mismatch', 'expense_mismatch');
    }

    if (empty($_FILES['invoice_file']['name'])) {
        return new WP_Error('missing_invoice_file', 'missing_invoice_file');
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $attachment_id = media_handle_upload('invoice_file', 0);
    if (is_wp_error($attachment_id)) {
        return $attachment_id;
    }

    $transfer_id = wp_insert_post(array(
        'post_title' => 'طلب تحويل - ' . get_the_title($building_id) . ' - ' . date('Y-m-d'),
        'post_content' => $notes,
        'post_status' => 'publish',
        'post_type' => 'transfers',
        'post_author' => $user->ID,
    ));

    if (!$transfer_id || is_wp_error($transfer_id)) {
        return new WP_Error('transfer_create_failed', 'transfer_create_failed');
    }

    update_post_meta($transfer_id, 'building_id', $building_id);
    update_post_meta($transfer_id, 'expense_id', $expense_id);
    update_post_meta($transfer_id, 'maintenance_id', $expense_id);
    update_post_meta($transfer_id, 'maintenance_title', $expense_title);
    update_post_meta($transfer_id, 'amount', $transfer_amount);
    update_post_meta($transfer_id, 'invoice_file', $attachment_id);
    update_post_meta($transfer_id, 'status', 'pending');
    update_post_meta($transfer_id, 'confirm_paid_invoices', $confirm_paid);
    update_post_meta($transfer_id, 'electronic_signature', $electronic_sig);
    update_post_meta($transfer_id, 'signature_date', current_time('mysql'));

    return $transfer_id;
}

// Update maintenance status
add_action('wp_ajax_ms_update_maintenance_status', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 401);
    }

    $user = wp_get_current_user();
    if (!function_exists('ms_user_has_role') || !ms_user_has_role($user->ID, 'building_manager')) {
        wp_send_json_error('forbidden', 403);
    }

    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'mostaager-ajax-nonce')) {
        wp_send_json_error('security_failed', 403);
    }

    $maintenance_id = isset($_POST['maintenance_id']) ? intval($_POST['maintenance_id']) : 0;
    $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

    if (!$maintenance_id || !in_array($status, array('open', 'in_progress', 'completed', 'closed'), true)) {
        wp_send_json_error('invalid_data', 400);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'ms_maintenance_requests';

    // Load the maintenance row and ensure the caller manages the building
    $target = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $maintenance_id));
    if (!$target) {
        wp_send_json_error('not_found', 404);
    }

    $target_building = intval($target->building_id ?? 0);
    if (!current_user_can('manage_options') && (!function_exists('ms_current_user_manages_building') || !ms_current_user_manages_building($user->ID, $target_building))) {
        wp_send_json_error('forbidden', 403);
    }

    $previous_status = sanitize_key($target->status ?? '');
    $updated = $wpdb->update($table, ['status' => $status], ['id' => $maintenance_id], ['%s'], ['%d']);
    if ($updated === false) {
        wp_send_json_error('update_failed', 500);
    }

    if ($previous_status !== $status && function_exists('ms_add_maintenance_timeline_entry')) {
        $status_labels = array(
            'open' => 'مفتوح',
            'in_progress' => 'قيد التنفيذ',
            'completed' => 'مكتمل',
            'closed' => 'مغلق',
        );
        ms_add_maintenance_timeline_entry(
            $maintenance_id,
            $status,
            'تحديث حالة طلب الصيانة',
            sprintf('تم تغيير الحالة من %s إلى %s.', $status_labels[$previous_status] ?? $previous_status, $status_labels[$status] ?? $status),
            $user->ID
        );
        do_action('ms_maintenance_status_changed', $maintenance_id, $status, $previous_status);
    }

    // Notify only the active tenant linked to this maintenance unit and only when
    // the status actually changes. The notification is stored server-side first;
    // the dashboard polls for it without exposing other users' notifications.
    if ($previous_status !== $status && function_exists('ms_get_tenant_by_unit') && function_exists('ms_add_notification')) {
        $tenant_link = ms_get_tenant_by_unit(absint($target->unit_id ?? 0));
        $tenant_id = $tenant_link ? absint($tenant_link->tenant_id ?? 0) : 0;
        if ($tenant_id) {
            $status_labels = array(
                'open' => 'مفتوح',
                'in_progress' => 'قيد التنفيذ',
                'completed' => 'مكتمل',
                'closed' => 'مغلق',
            );
            $label = $status_labels[$status] ?? $status;
            ms_add_notification(
                $tenant_id,
                'maintenance_status_changed',
                sprintf('تم تحديث حالة طلب الصيانة #%d إلى: %s', $maintenance_id, $label),
                absint($target->building_id ?? 0),
                $maintenance_id
            );
        }
    }

    wp_send_json_success(['updated' => true, 'maintenance_id' => $maintenance_id, 'new_status' => $status]);
});

add_action('wp_ajax_upload_receipt', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 401);
    }

    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'mostaager-ajax-nonce')) {
        wp_send_json_error('security_failed', 403);
    }

    $user = wp_get_current_user();
    $invoice_id = isset($_POST['invoice_id']) ? intval($_POST['invoice_id']) : 0;
    if (!$invoice_id) {
        wp_send_json_error('invalid_invoice_id', 400);
    }

    $invoice_post = get_post($invoice_id);
    if (!$invoice_post || $invoice_post->post_type !== 'invoices') {
        wp_send_json_error('invoice_not_found', 404);
    }

    $building_id = get_post_meta($invoice_id, 'building_id', true);
    $property_id = get_post_meta($invoice_id, 'property_id', true);

    if (!$building_id && function_exists('get_field')) {
        $building_id = get_field('building_id', $invoice_id);
        if (is_object($building_id)) {
            $building_id = intval($building_id->ID);
        }
    }

    $allowed = current_user_can('manage_options') || intval($invoice_post->post_author) === $user->ID;
    if (!$allowed && function_exists('ms_current_user_manages_building')) {
        $allowed = ms_current_user_manages_building($user->ID, intval($building_id));
    }

    if (!$allowed && !empty($property_id) && function_exists('ms_get_properties_by_owner')) {
        $owned_properties = ms_get_properties_by_owner($user->ID);
        foreach ((array) $owned_properties as $property) {
            $prop_id = intval($property->id ?? $property->ID ?? 0);
            if ($prop_id === intval($property_id)) {
                $allowed = true;
                break;
            }
        }
    }

    if (!$allowed) {
        wp_send_json_error('forbidden', 403);
    }

    if (empty($_FILES['receipt']['name'])) {
        wp_send_json_error('missing_receipt', 400);
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $attachment_id = media_handle_upload('receipt', 0);
    if (is_wp_error($attachment_id)) {
        wp_send_json_error($attachment_id->get_error_message(), 500);
    }

    update_post_meta($invoice_id, 'receipt_image', $attachment_id);
    update_post_meta($invoice_id, 'status', 'waiting_review');
    if (function_exists('update_field')) {
        update_field('receipt_image', $attachment_id, $invoice_id);
        update_field('status', 'waiting_review', $invoice_id);
    }

    wp_send_json_success(['message' => 'receipt_uploaded']);
});

add_action('wp_ajax_approve_invoice', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 401);
    }

    $user = wp_get_current_user();
    $allowed_role = current_user_can('manage_options') || (function_exists('ms_user_has_role') && ms_user_has_role($user->ID, 'building_manager'));
    if (!$allowed_role) {
        wp_send_json_error('forbidden', 403);
    }

    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'mostaager-ajax-nonce')) {
        wp_send_json_error('security_failed', 403);
    }

    $invoice_id = isset($_POST['invoice_id']) ? intval($_POST['invoice_id']) : 0;
    if (!$invoice_id) {
        wp_send_json_error('invalid_invoice_id', 400);
    }

    $invoice_post = get_post($invoice_id);
    if (!$invoice_post || $invoice_post->post_type !== 'invoices') {
        wp_send_json_error('invoice_not_found', 404);
    }

    $building_id = get_post_meta($invoice_id, 'building_id', true);
    if (!$building_id && function_exists('get_field')) {
        $building_id = get_field('building_id', $invoice_id);
        if (is_object($building_id)) {
            $building_id = intval($building_id->ID);
        }
    }

    if (!current_user_can('manage_options') && (!function_exists('ms_current_user_manages_building') || !ms_current_user_manages_building($user->ID, intval($building_id)))) {
        wp_send_json_error('forbidden', 403);
    }

    $amount = floatval(get_post_meta($invoice_id, 'amount_due', true));
    if (!$amount && function_exists('get_field')) {
        $amount = floatval(get_field('amount_due', $invoice_id));
    }

    if (!$building_id || !$amount) {
        wp_send_json_error('invalid_invoice_data', 400);
    }

    update_post_meta($invoice_id, 'status', 'paid');
    if (function_exists('update_field')) {
        update_field('status', 'paid', $invoice_id);
    }

    if (function_exists('ms_update_building_wallet_balance')) {
        ms_update_building_wallet_balance($building_id, $amount);
    }

    wp_send_json_success(['message' => 'invoice_approved']);
});

add_action('wp_ajax_withdraw_wallet', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 401);
    }

    $user = wp_get_current_user();
    $allowed_role = current_user_can('manage_options') || (function_exists('ms_user_has_role') && ms_user_has_role($user->ID, 'building_manager'));
    if (!$allowed_role) {
        wp_send_json_error('forbidden', 403);
    }

    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'mostaager-ajax-nonce')) {
        wp_send_json_error('security_failed', 403);
    }

    $building_id = isset($_POST['building_id']) ? intval($_POST['building_id']) : 0;
    if (!$building_id) {
        wp_send_json_error('invalid_building_id', 400);
    }

    if (!current_user_can('manage_options') && (!function_exists('ms_current_user_manages_building') || !ms_current_user_manages_building($user->ID, $building_id))) {
        wp_send_json_error('forbidden', 403);
    }

    if (!function_exists('ms_get_building_wallet') || !function_exists('ms_update_building_wallet_balance')) {
        wp_send_json_error('wallet_functions_missing', 500);
    }

    $wallet = ms_get_building_wallet($building_id);
    if (!$wallet || floatval($wallet->balance) < floatval($wallet->target_amount) || floatval($wallet->target_amount) <= 0) {
        wp_send_json_error('insufficient_wallet_balance', 400);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'ms_building_wallet';
    $wpdb->update($table, ['balance' => 0, 'target_amount' => 0, 'status' => 'withdrawn'], ['id' => intval($wallet->id)]);

    wp_send_json_success(['message' => 'wallet_withdrawn']);
});

add_action('wp_ajax_submit_transfer', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 401);
    }

    $user = wp_get_current_user();
    if (!current_user_can('manage_options') && (!function_exists('ms_user_has_role') || !ms_user_has_role($user->ID, 'building_manager'))) {
        wp_send_json_error('forbidden', 403);
    }

    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'mostaager-ajax-nonce')) {
        wp_send_json_error('security_failed', 403);
    }

    $building_id = isset($_POST['building_id']) ? intval($_POST['building_id']) : 0;
    $expense_id = isset($_POST['expense_id']) ? intval($_POST['expense_id']) : 0;
    $transfer_amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $notes = isset($_POST['note']) ? sanitize_textarea_field($_POST['note']) : '';
    $confirm_paid = isset($_POST['confirm_paid_invoices']) ? 1 : 0;
    $electronic_sig = isset($_POST['electronic_signature']) ? sanitize_text_field($_POST['electronic_signature']) : '';

    $result = ms_handle_transfer_submission($user, $building_id, $expense_id, $transfer_amount, $notes, $confirm_paid, $electronic_sig);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()], 400);
    }

    wp_send_json_success(['transfer_id' => intval($result)]);
});

function ms_current_user_manages_building($user_id, $building_id)
{
    if (!$user_id || !$building_id || !function_exists('ms_get_buildings_by_manager')) {
        return false;
    }

    $buildings = ms_get_buildings_by_manager($user_id);
    if (empty($buildings)) {
        return false;
    }

    foreach ($buildings as $building) {
        $id = intval($building->id ?? $building->ID ?? 0);
        if ($id === $building_id) {
            return true;
        }
    }

    return false;
}



function ms_user_can_access_building_discussions($user_id, $building_id)
{
    $user_id = absint($user_id);
    $building_id = absint($building_id);
    if (!$user_id || !$building_id) {
        return false;
    }

    if (user_can($user_id, 'manage_options')) {
        return true;
    }

    if (function_exists('ms_current_user_manages_building') && ms_current_user_manages_building($user_id, $building_id)) {
        return true;
    }

    global $wpdb;
    if ($wpdb) {
        $units_table = $wpdb->prefix . 'ms_units';
        $has_unit_relation = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$units_table} WHERE building_id = %d AND (owner_id = %d OR agent_id = %d OR tenant_id = %d) LIMIT 1",
            $building_id,
            $user_id,
            $user_id,
            $user_id
        ));
        if (intval($has_unit_relation) > 0) {
            return true;
        }

        $tenant_table = $wpdb->prefix . 'ms_unit_tenants';
        $has_tenant_relation = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tenant_table} WHERE building_id = %d AND (tenant_id = %d OR tenant_user_id = %d) AND (end_date IS NULL OR end_date = '' OR end_date >= CURDATE()) LIMIT 1",
            $building_id,
            $user_id,
            $user_id
        ));
        if (intval($has_tenant_relation) > 0) {
            return true;
        }
    }

    if (post_type_exists('property')) {
        $owned_property_ids = get_posts(array(
            'post_type' => 'property',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'author' => $user_id,
            'meta_query' => array(
                array(
                    'key' => 'building_id',
                    'value' => $building_id,
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ),
            ),
        ));
        if (!empty($owned_property_ids)) {
            return true;
        }
    }

    return false;
}

function ms_meta_contains_user_id($meta, $user_id)
{
    $user_id = absint($user_id);
    if (!$user_id) {
        return false;
    }

    if (is_array($meta)) {
        foreach ($meta as $value) {
            if (absint($value) === $user_id) {
                return true;
            }
        }
        return false;
    }

    if (is_numeric($meta) && absint($meta) === $user_id) {
        return true;
    }

    $serialized = maybe_serialize($meta);
    if (strpos($serialized, '"' . $user_id . '"') !== false) {
        return true;
    }

    return preg_match('/\b' . preg_quote($user_id, '/') . '\b/', $serialized) === 1;
}

function ms_current_agent_manages_property($user_id, $property_id)
{
    $user_id = absint($user_id);
    $property_id = absint($property_id);
    if (!$user_id || !$property_id) {
        return false;
    }

    if (!function_exists('ms_user_has_role') || !ms_user_has_role($user_id, 'agent')) {
        return false;
    }

    $property = get_post($property_id);
    if (!$property || $property->post_type !== 'property') {
        return false;
    }

    if (intval($property->post_author) === $user_id) {
        return true;
    }

    $meta_keys = array('fave_agents', 'fave_property_agency', 'owner_id', 'property_owner', 'fave_property_owner', 'tenant_id');
    foreach ($meta_keys as $meta_key) {
        $meta = get_post_meta($property_id, $meta_key, false);
        if (ms_meta_contains_user_id($meta, $user_id)) {
            return true;
        }
    }

    $listing_ids = array();

    if (function_exists('ms_get_properties_by_agent')) {
        $listings = ms_get_properties_by_agent($user_id);
        if (!empty($listings)) {
            foreach ((array) $listings as $listing) {
                $id = 0;
                if (is_object($listing)) {
                    $id = intval($listing->ID ?? $listing->id ?? 0);
                    if (!$id && isset($listing->unit_id)) $id = intval($listing->unit_id);
                } elseif (is_array($listing)) {
                    $id = intval($listing['ID'] ?? $listing['id'] ?? 0);
                    if (!$id && isset($listing['unit_id'])) $id = intval($listing['unit_id']);
                } elseif (is_numeric($listing)) {
                    $id = intval($listing);
                }
                if ($id) {
                    $listing_ids[] = $id;
                }
            }
        }
    }

    if (empty($listing_ids) && function_exists('ms_get_properties_by_owner')) {
        $listings = ms_get_properties_by_owner($user_id);
        if (!empty($listings)) {
            foreach ((array) $listings as $listing) {
                $id = 0;
                if (is_object($listing)) {
                    $id = intval($listing->ID ?? $listing->id ?? 0);
                    if (!$id && isset($listing->unit_id)) $id = intval($listing->unit_id);
                } elseif (is_array($listing)) {
                    $id = intval($listing['ID'] ?? $listing['id'] ?? 0);
                    if (!$id && isset($listing['unit_id'])) $id = intval($listing['unit_id']);
                } elseif (is_numeric($listing)) {
                    $id = intval($listing);
                }
                if ($id) {
                    $listing_ids[] = $id;
                }
            }
        }
    }

    if (in_array($property_id, $listing_ids, true)) {
        return true;
    }

    return false;
}

// REMOVED: Duplicate handler for ms_agent_update_property_status
// The primary handler is in core/ajax-agent-property.php (loaded at line 8)
// Keeping both causes nonce conflicts and duplicate execution.
// See: https://github.com/mostaager/facility-pro/issues/security-nonce-conflict

// REMOVED: Duplicate handler for ms_agent_upload_property_contract
// The primary handler is in core/ajax-agent-property.php (loaded at line 8)
// Keeping both causes nonce conflicts and duplicate execution.
// See: https://github.com/mostaager/facility-pro/issues/security-nonce-conflict

// ============ END OF REMOVED DUPLICATE HANDLER ============

add_action('wp_ajax_ms_get_discussions', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 401);
    }

    $user = wp_get_current_user();
    $building_id = isset($_POST['building_id']) ? intval($_POST['building_id']) : 0;
    if (!$building_id || !ms_user_can_access_building_discussions($user->ID, $building_id)) {
        wp_send_json_error('forbidden', 403);
    }

    $items = ms_get_building_discussions($building_id);
    wp_send_json_success($items);
});

add_action('wp_ajax_ms_get_discussion_replies', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 401);
    }

    $discussion_id = isset($_POST['discussion_id']) ? intval($_POST['discussion_id']) : 0;
    if (!$discussion_id) {
        wp_send_json_error('invalid_discussion', 400);
    }

    $discussion = get_post($discussion_id);
    if (!$discussion || $discussion->post_type !== 'ms_discussion') {
        wp_send_json_error('discussion_not_found', 404);
    }

    $building_id = intval(get_post_meta($discussion_id, 'building_id', true));
    if (!$building_id || !ms_user_can_access_building_discussions(get_current_user_id(), $building_id)) {
        wp_send_json_error('forbidden', 403);
    }

    $replies = ms_get_discussion_replies($discussion_id);
    wp_send_json_success($replies);
});

// Manager: mark invoice paid via AJAX
add_action('wp_ajax_ms_manager_mark_invoice_paid', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'not_logged_in'], 401);
    }

    $user = wp_get_current_user();
    if (!current_user_can('manage_options') && (!function_exists('ms_user_has_role') || !ms_user_has_role($user->ID, 'building_manager'))) {
        wp_send_json_error(['message' => 'forbidden'], 403);
    }

    $invoice_id = isset($_POST['invoice_id']) ? intval($_POST['invoice_id']) : 0;
    $security = isset($_POST['security']) ? sanitize_text_field($_POST['security']) : '';
    $valid_nonce = false;
    if (!empty($security) && (wp_verify_nonce($security, 'mostaager-ajax-nonce') || wp_verify_nonce($security, 'ms-mark-invoice-paid'))) {
        $valid_nonce = true;
    }

    if (!$invoice_id || !$valid_nonce) {
        wp_send_json_error(['message' => 'invalid_request'], 400);
    }

    if (!function_exists('ms_mark_invoice_paid') || !function_exists('ms_get_invoice_by_id')) {
        wp_send_json_error(['message' => 'function_unavailable'], 500);
    }

    $invoice = ms_get_invoice_by_id($invoice_id);
    if (!$invoice || (!current_user_can('manage_options') && !ms_current_user_manages_building($user->ID, intval($invoice->building_id)))) {
        wp_send_json_error(['message' => 'forbidden'], 403);
    }

    if (strtolower(trim($invoice->status ?? '')) !== 'pending') {
        wp_send_json_error(['message' => 'invalid_state'], 400);
    }

    $result = ms_mark_invoice_paid($invoice_id);
    if ($result === false) {
        wp_send_json_error(['message' => 'update_failed'], 500);
    }

    wp_send_json_success(['message' => 'marked_paid']);
});

// Manager: cancel invoice via AJAX
add_action('wp_ajax_ms_manager_cancel_invoice', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'not_logged_in'], 401);
    }

    $user = wp_get_current_user();
    if (!current_user_can('manage_options') && (!function_exists('ms_user_has_role') || !ms_user_has_role($user->ID, 'building_manager'))) {
        wp_send_json_error(['message' => 'forbidden'], 403);
    }

    $invoice_id = isset($_POST['invoice_id']) ? intval($_POST['invoice_id']) : 0;
    $security = isset($_POST['security']) ? sanitize_text_field($_POST['security']) : '';
    $valid_nonce = false;
    if (!empty($security) && wp_verify_nonce($security, 'mostaager-ajax-nonce')) {
        $valid_nonce = true;
    }

    if (!$invoice_id || !$valid_nonce) {
        wp_send_json_error(['message' => 'invalid_request'], 400);
    }

    if (!function_exists('ms_cancel_invoice') || !function_exists('ms_get_invoice_by_id')) {
        wp_send_json_error(['message' => 'function_unavailable'], 500);
    }

    $invoice = ms_get_invoice_by_id($invoice_id);
    if (!$invoice || (!current_user_can('manage_options') && !ms_current_user_manages_building($user->ID, intval($invoice->building_id)))) {
        wp_send_json_error(['message' => 'forbidden'], 403);
    }

    if (strtolower(trim($invoice->status ?? '')) !== 'pending') {
        wp_send_json_error(['message' => 'invalid_state'], 400);
    }

    $result = ms_cancel_invoice($invoice_id);
    if ($result === false) {
        wp_send_json_error(['message' => 'update_failed'], 500);
    }

    wp_send_json_success(['message' => 'canceled']);
});

add_action('wp_ajax_ms_add_discussion_reply', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 401);
    }

    $user = wp_get_current_user();
    $discussion_id = isset($_POST['discussion_id']) ? intval($_POST['discussion_id']) : 0;
    $content = isset($_POST['content']) ? sanitize_textarea_field($_POST['content']) : '';
    $nonce = isset($_POST['security']) ? sanitize_text_field($_POST['security']) : '';

    if (!$discussion_id || empty($content)) {
        wp_send_json_error('invalid_data', 400);
    }

    if (!wp_verify_nonce($nonce, 'mostaager-ajax-nonce')) {
        wp_send_json_error('security_failed', 403);
    }

    $discussion = get_post($discussion_id);
    if (!$discussion || $discussion->post_type !== 'ms_discussion') {
        wp_send_json_error('discussion_not_found', 404);
    }

    $building_id = intval(get_post_meta($discussion_id, 'building_id', true));
    if (!$building_id || !ms_user_can_access_building_discussions($user->ID, $building_id)) {
        wp_send_json_error('forbidden', 403);
    }

    $comment = ms_add_discussion_reply($discussion_id, $user->ID, $content);
    if (!$comment) {
        wp_send_json_error('reply_failed', 500);
    }

    $reply = array(
        'id' => $comment->comment_ID,
        'author_name' => $comment->comment_author,
        'created_at' => get_comment_date('Y-m-d H:i', $comment),
        'content' => wpautop(esc_html($comment->comment_content)),
    );

    $replies = ms_get_discussion_replies($discussion_id);
    wp_send_json_success(array('reply' => $reply, 'replies' => $replies));
});

add_action('wp_ajax_ms_create_discussion', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 401);
    }

    $user = wp_get_current_user();
    $building_id = isset($_POST['building_id']) ? intval($_POST['building_id']) : 0;
    $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
    $content = isset($_POST['content']) ? sanitize_textarea_field($_POST['content']) : '';
    $nonce = isset($_POST['security']) ? sanitize_text_field($_POST['security']) : '';

    if (!$building_id || empty($title) || empty($content)) {
        wp_send_json_error('invalid_data', 400);
    }

    if (!wp_verify_nonce($nonce, 'ms_discussion_nonce')) {
        wp_send_json_error('security_failed', 403);
    }

    if (!ms_current_user_manages_building($user->ID, $building_id)) {
        wp_send_json_error('forbidden', 403);
    }

    $post_id = ms_create_building_discussion($building_id, $title, $content, $user->ID);
    if (!$post_id) {
        wp_send_json_error('discussion_create_failed', 500);
    }

    wp_send_json_success(array(
        'discussion' => array(
            'id' => $post_id,
            'title' => $title,
            'excerpt' => wp_trim_words($content, 20, '...'),
            'created_at' => current_time('mysql'),
        )
    ));
});

// Add AJAX handler for reply discussion
add_action('wp_ajax_ms_reply_discussion', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 401);
    }

    $user = wp_get_current_user();
    $discussion_id = isset($_POST['discussion_id']) ? intval($_POST['discussion_id']) : 0;
    $reply = isset($_POST['reply']) ? sanitize_textarea_field($_POST['reply']) : '';
    $nonce = isset($_POST['security']) ? sanitize_text_field($_POST['security']) : '';

    if (!$discussion_id || empty($reply)) {
        wp_send_json_error('invalid_data', 400);
    }

    if (!wp_verify_nonce($nonce, 'ms_discussion_nonce')) {
        wp_send_json_error('security_failed', 403);
    }

    // Check if user can reply to this discussion
    $discussion = get_post($discussion_id);
    if (!$discussion || $discussion->post_type !== 'discussion') {
        wp_send_json_error('discussion_not_found', 404);
    }

    $comment_id = wp_insert_comment(array(
        'comment_post_ID' => $discussion_id,
        'comment_content' => $reply,
        'user_id' => $user->ID,
        'comment_approved' => 1,
    ));

    if (!$comment_id) {
        wp_send_json_error('reply_failed', 500);
    }

    wp_send_json_success(array(
        'reply' => array(
            'id' => $comment_id,
            'content' => $reply,
            'created_at' => current_time('mysql'),
        )
    ));
});

// Pay invoice - create WooCommerce order and redirect to checkout
add_action('wp_ajax_ms_pay_invoice', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 401);
    }

    $user = wp_get_current_user();

    if (!isset($_POST['invoice_id']) || !isset($_POST['security'])) {
        wp_send_json_error('invalid_request', 400);
    }

    $invoice_id = intval($_POST['invoice_id']);
    $nonce = sanitize_text_field($_POST['security']);

    if (!wp_verify_nonce($nonce, 'ms_pay_invoice_' . $invoice_id)) {
        wp_send_json_error('security_failed', 403);
    }

    if (!function_exists('ms_get_invoice_by_id')) {
        wp_send_json_error('function_not_available', 500);
    }

    $invoice = ms_get_invoice_by_id($invoice_id);
    if (!$invoice) {
        wp_send_json_error('invoice_not_found', 404);
    }

    $can_access_invoice = $invoice->user_id === $user->ID;
    if (!$can_access_invoice && function_exists('ms_invoice_belongs_to_owner')) {
        $can_access_invoice = ms_invoice_belongs_to_owner($invoice_id, $user->ID);
    }

    if (!$can_access_invoice) {
        wp_send_json_error('not_owner', 403);
    }

    if ($invoice->status === 'paid') {
        wp_send_json_error('already_paid', 400);
    }
    if (function_exists('ms_user_can_view_dashboard') && ms_user_can_view_dashboard($user->ID, 'agent')) {
        $is_subscription = (($invoice->invoice_type ?? '') === 'subscription') || (($invoice->invoice_category ?? '') === 'agent-fees') || (stripos((string) ($invoice->description ?? ''), 'اشتراك') !== false);
        if (!$is_subscription) {
            wp_send_json_error('agent_subscription_invoice_only', 403);
        }
    }

    if (!function_exists('ms_create_woo_order_for_invoice')) {
        wp_send_json_error('woo_function_not_available', 500);
    }

    $payment_url = ms_create_woo_order_for_invoice($invoice_id);
    if (!$payment_url) {
        wp_send_json_error(array('code' => 'order_creation_failed', 'message' => function_exists('ms_get_woo_payment_setup_message') && ms_get_woo_payment_setup_message() ? ms_get_woo_payment_setup_message() : 'تعذر إنشاء طلب الدفع. راجع إعدادات WooCommerce وCheckout وبوابة الدفع.'), 500);
    }

    wp_send_json_success(array('payment_url' => $payment_url));
});

add_action('wp_ajax_ms_create_wallet_recharge', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 401);
    }

    $user = wp_get_current_user();
    $nonce = isset($_POST['security']) ? sanitize_text_field($_POST['security']) : '';
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;

    if (!wp_verify_nonce($nonce, 'mostaager-ajax-nonce')) {
        wp_send_json_error('security_failed', 403);
    }

    if ($amount <= 0) {
        wp_send_json_error('invalid_amount', 400);
    }

    if (!function_exists('ms_create_woo_order_for_wallet_recharge')) {
        wp_send_json_error('woo_function_not_available', 500);
    }

    $payment_url = ms_create_woo_order_for_wallet_recharge($user->ID, $amount);
    if (!$payment_url) {
        wp_send_json_error(array('code' => 'order_creation_failed', 'message' => function_exists('ms_get_woo_payment_setup_message') && ms_get_woo_payment_setup_message() ? ms_get_woo_payment_setup_message() : 'تعذر إنشاء طلب الدفع. راجع إعدادات WooCommerce وCheckout وبوابة الدفع.'), 500);
    }

    wp_send_json_success(array('payment_url' => $payment_url));
});



add_action('wp_ajax_ms_purchase_subscription_plan', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 401);
    }

    $user = wp_get_current_user();
    $plan_key = isset($_POST['plan_key']) ? sanitize_key(wp_unslash($_POST['plan_key'])) : '';
    $mode = isset($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : 'upgrade';
    $nonce = isset($_POST['security']) ? sanitize_text_field(wp_unslash($_POST['security'])) : '';

    if (!wp_verify_nonce($nonce, 'mostaager-ajax-nonce')) {
        wp_send_json_error('security_failed', 403);
    }

    $plans = array(
        'gold' => array('name' => 'الباقة الذهبية', 'amount' => 100.00),
        'silver' => array('name' => 'الباقة الفضية', 'amount' => 50.00),
        'bronze' => array('name' => 'الباقة البرونزية', 'amount' => 25.00),
    );

    if (!in_array($mode, array('renew', 'upgrade'), true)) {
        $mode = 'upgrade';
    }
    $current = function_exists('ms_get_agent_subscription_status') ? ms_get_agent_subscription_status($user->ID) : array();
    if ($mode === 'renew' && empty($plan_key)) {
        $plan_key = !empty($current['plan_key']) ? sanitize_key($current['plan_key']) : '';
    }
    if (empty($plan_key) || !isset($plans[$plan_key])) {
        wp_send_json_error('invalid_plan', 400);
    }
    if ($mode === 'upgrade' && !empty($current['plan_key'])) {
        $rank = array('bronze' => 1, 'silver' => 2, 'gold' => 3);
        if (isset($rank[$plan_key], $rank[$current['plan_key']]) && $rank[$plan_key] <= $rank[$current['plan_key']]) {
            wp_send_json_error(array('message' => 'اختر باقة أعلى من باقتك الحالية.'), 422);
        }
    }

    if (!function_exists('ms_create_subscription_package_invoice') || !function_exists('ms_create_woo_order_for_invoice')) {
        wp_send_json_error('payment_not_available', 500);
    }

    $plan = $plans[$plan_key];
    $invoice_id = ms_create_subscription_package_invoice($user->ID, $plan['amount'], $plan['name'], $plan_key, $mode);
    if (!$invoice_id) {
        wp_send_json_error('invoice_create_failed', 500);
    }

    $payment_url = ms_create_woo_order_for_invoice($invoice_id);
    if (!$payment_url) {
        wp_send_json_error(array('code' => 'order_creation_failed', 'message' => function_exists('ms_get_woo_payment_setup_message') && ms_get_woo_payment_setup_message() ? ms_get_woo_payment_setup_message() : 'تعذر إنشاء طلب الدفع. راجع إعدادات WooCommerce وCheckout وبوابة الدفع.'), 500);
    }

    wp_send_json_success(array('payment_url' => $payment_url, 'mode' => $mode, 'plan_key' => $plan_key));
});


add_action('wp_ajax_ms_agent_renewal_action', function () {
    if (!is_user_logged_in()) wp_send_json_error(array('message' => 'يرجى تسجيل الدخول.'), 401);
    $user = wp_get_current_user();
    if (!function_exists('ms_user_has_role') || !ms_user_has_role($user->ID, 'agent')) wp_send_json_error(array('message' => 'غير مصرح.'), 403);
    $nonce = isset($_POST['security']) ? sanitize_text_field(wp_unslash($_POST['security'])) : '';
    if (!wp_verify_nonce($nonce, 'ms_agent_renewal_action')) wp_send_json_error(array('message' => 'فشل التحقق الأمني.'), 403);
    $action = sanitize_key($_POST['renewal_action'] ?? '');
    if (!in_array($action, array('cancel', 'restore'), true)) wp_send_json_error(array('message' => 'إجراء غير صالح.'), 400);
    global $wpdb;
    $key = 'ms_agent_renewal_cancelled';
    if ($action === 'cancel') {
        update_user_meta($user->ID, $key, 1);
        $table = $wpdb->prefix . 'ms_invoices';
        $wpdb->query($wpdb->prepare("UPDATE {$table} SET status = 'canceled' WHERE user_id = %d AND invoice_type = 'subscription' AND status = 'pending' AND description LIKE %s", $user->ID, '%:renew]%'));
        wp_send_json_success(array('state' => 'cancelled', 'message' => 'تم إلغاء التجديد. يبقى اشتراكك فعالًا حتى تاريخ انتهائه.'));
    }
    delete_user_meta($user->ID, $key);
    wp_send_json_success(array('state' => 'active', 'message' => 'تمت إعادة تفعيل إمكانية التجديد.'));
});

add_action('wp_ajax_ms_get_timeline', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in');
    }

    global $wpdb;
    $request_id = intval($_POST['request_id'] ?? 0);

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ms_maintenance_timeline WHERE request_id=%d ORDER BY created_at ASC",
            $request_id
        ),
        ARRAY_A
    );

    wp_send_json_success($rows);
});

// Security Deposit Payment Handler
add_action('wp_ajax_ms_pay_deposit', function () {
    check_ajax_referer('ms_deposit_nonce', 'ms_deposit_nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'يرجى تسجيل الدخول.'), 403);
    }

    $user = wp_get_current_user();
    if (!function_exists('ms_user_has_role') || !ms_user_has_role($user->ID, 'tenant')) {
        wp_send_json_error(array('message' => 'غير مصرح: هذه العملية للمستأجرين فقط.'), 403);
    }

    $unit_id = isset($_POST['unit_id']) ? absint($_POST['unit_id']) : 0;
    $building_id = isset($_POST['building_id']) ? absint($_POST['building_id']) : 0;
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;

    if (!$unit_id || !$building_id || $amount <= 0) {
        wp_send_json_error(array('message' => 'بيانات غير صالحة'), 400);
    }

    // Check if deposit already exists for this unit
    if (function_exists('ms_get_security_deposit_by_lease')) {
        $existing_deposit = ms_get_security_deposit_by_lease($unit_id);
        if ($existing_deposit) {
            wp_send_json_error(array('message' => 'يوجد تأمين أمانة بالفعل لهذه الوحدة.'), 400);
        }
    }

    // Get owner of the unit
    global $wpdb;
    $unit_table = $wpdb->prefix . 'ms_units';
    $unit = $wpdb->get_row($wpdb->prepare("SELECT * FROM $unit_table WHERE id = %d LIMIT 1", $unit_id));
    
    if (!$unit) {
        wp_send_json_error(array('message' => 'الوحدة غير موجودة.'), 404);
    }

    $owner_id = intval($unit->owner_id ?? 0);
    if (!$owner_id) {
        wp_send_json_error(array('message' => 'لم يتم العثور على مالك للوحدة.'), 400);
    }

    // Check tenant wallet balance
    if (!function_exists('ms_get_wallet_balance')) {
        wp_send_json_error(array('message' => 'نظام المحفظة غير متاح.'), 500);
    }

    $wallet_balance = ms_get_wallet_balance($user->ID);
    if ($wallet_balance < $amount) {
        wp_send_json_error(array('message' => 'رصيد المحفظة غير كافٍ.'), 400);
    }

    // Deduct from tenant wallet
    if (!function_exists('ms_update_wallet_balance')) {
        wp_send_json_error(array('message' => 'نظام المحفظة غير متاح.'), 500);
    }

    if (!ms_update_wallet_balance($user->ID, -$amount, 'دفع تأمين أمانة', $unit_id)) {
        wp_send_json_error(array('message' => 'فشل خصم المبلغ من المحفظة.'), 500);
    }

    // Create security deposit record
    if (!function_exists('ms_create_security_deposit')) {
        wp_send_json_error(array('message' => 'نظام التأمين غير متاح.'), 500);
    }

    $deposit_id = ms_create_security_deposit($unit_id, $unit_id, $building_id, $user->ID, $owner_id, $amount);
    if (!$deposit_id) {
        // Refund if deposit creation failed
        ms_update_wallet_balance($user->ID, $amount, 'استرداد تأمين أمانة (فشل الإنشاء)', $unit_id);
        wp_send_json_error(array('message' => 'فشل إنشاء سجل التأمين.'), 500);
    }

    // Freeze the deposit immediately
    if (!function_exists('ms_freeze_security_deposit')) {
        wp_send_json_error(array('message' => 'نظام التجميد غير متاح.'), 500);
    }

    if (!ms_freeze_security_deposit($deposit_id)) {
        wp_send_json_error(array('message' => 'فشل تجميد التأمين.'), 500);
    }

    // Send notification to owner
    if (function_exists('ms_add_notification')) {
        ms_add_notification($owner_id, 'security_deposit', 'تم استلام تأمين أمانة جديد من المستأجر: ' . $user->display_name, $building_id, $unit_id);
    }

    wp_send_json_success(array(
        'message' => 'تم دفع تأمين الأمانة وتجميده بنجاح.',
        'deposit_id' => $deposit_id,
        'amount' => $amount
    ));
});

// Security Deposit Release Handler (Landlord)
add_action('wp_ajax_ms_release_deposit', function () {
    check_ajax_referer('ms_release_deposit_nonce', 'security');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'يرجى تسجيل الدخول.'), 403);
    }

    $user = wp_get_current_user();
    if (!function_exists('ms_user_has_role') || !ms_user_has_role($user->ID, 'owner')) {
        wp_send_json_error(array('message' => 'غير مصرح: هذه العملية للمالكين فقط.'), 403);
    }

    $deposit_id = isset($_POST['deposit_id']) ? absint($_POST['deposit_id']) : 0;
    $deduction_amount = isset($_POST['deduction_amount']) ? floatval($_POST['deduction_amount']) : 0;
    $deduction_reason = isset($_POST['deduction_reason']) ? sanitize_textarea_field($_POST['deduction_reason']) : '';
    $deduction_documents = isset($_POST['deduction_documents']) ? sanitize_textarea_field($_POST['deduction_documents']) : '';

    if (!$deposit_id) {
        wp_send_json_error(array('message' => 'بيانات غير صالحة'), 400);
    }

    // Get deposit details
    global $wpdb;
    $deposits_table = $wpdb->prefix . 'ms_security_deposits';
    $deposit = $wpdb->get_row($wpdb->prepare("SELECT * FROM $deposits_table WHERE id = %d LIMIT 1", $deposit_id));
    
    if (!$deposit) {
        wp_send_json_error(array('message' => 'التأمين غير موجود.'), 404);
    }

    // Verify owner owns this deposit
    if (intval($deposit->owner_id) !== $user->ID) {
        wp_send_json_error(array('message' => 'غير مصرح: هذا التأمين ليس ملكك.'), 403);
    }

    if ($deposit->status !== 'frozen') {
        wp_send_json_error(array('message' => 'لا يمكن إطلاق التأمين إلا إذا كان مجمداً.'), 400);
    }

    // Validate deduction amount
    if ($deduction_amount < 0 || $deduction_amount > $deposit->amount) {
        wp_send_json_error(array('message' => 'مبلغ الخصم غير صالح.'), 400);
    }

    // Release the deposit
    if (!function_exists('ms_release_security_deposit')) {
        wp_send_json_error(array('message' => 'نظام إطلاق التأمين غير متاح.'), 500);
    }

    if (!ms_release_security_deposit($deposit_id, $deduction_amount, $deduction_reason, $deduction_documents)) {
        wp_send_json_error(array('message' => 'فشل إطلاق التأمين.'), 500);
    }

    // Send notification to tenant
    if (function_exists('ms_add_notification')) {
        $message = 'تم إطلاق تأمين الأمانة الخاص بك';
        if ($deduction_amount > 0) {
            $message .= ' (خصم: ' . number_format_i18n($deduction_amount, 2) . ' ج.م)';
        }
        ms_add_notification($deposit->tenant_id, 'security_deposit', $message, $deposit->building_id, $deposit->unit_id);
    }

    wp_send_json_success(array(
        'message' => 'تم إطلاق التأمين بنجاح.',
        'deposit_id' => $deposit_id,
        'released_amount' => $deposit->amount - $deduction_amount,
        'deduction_amount' => $deduction_amount
    ));
});

add_action('wp_ajax_ms_pay_maintenance_invoice', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 401);
    }

    $user = wp_get_current_user();
    $nonce = isset($_POST['security']) ? sanitize_text_field($_POST['security']) : '';
    $request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;

    if (!wp_verify_nonce($nonce, 'mostaager-ajax-nonce')) {
        wp_send_json_error('security_failed', 403);
    }

    if (!$request_id || $amount <= 0) {
        wp_send_json_error('invalid_data', 400);
    }

    global $wpdb;
    $maint_table = $wpdb->prefix . 'ms_maintenance_requests';
    
    // Get maintenance request
    $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$maint_table} WHERE id = %d", $request_id));
    
    if (!$request) {
        wp_send_json_error('request_not_found', 404);
    }

    // A tenant may pay only an invoice explicitly linked to this maintenance request.
    $invoice_table = $wpdb->prefix . 'ms_invoices';
    $invoice = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$invoice_table} WHERE expense_id = %d AND user_id = %d ORDER BY id DESC LIMIT 1",
        $request_id,
        $user->ID
    ));
    if (!$invoice) {
        wp_send_json_error('maintenance_invoice_not_found', 404);
    }
    if (in_array(strtolower((string) ($invoice->status ?? '')), array('paid', 'cancelled', 'canceled'), true)) {
        wp_send_json_error('invoice_not_payable', 400);
    }

    // Ignore client-provided amount and charge the server-side invoice amount.
    $amount = (float) ($invoice->amount ?? 0);
    if ($amount <= 0) {
        wp_send_json_error('invalid_invoice_amount', 400);
    }

    // Create the WooCommerce order for the actual maintenance invoice.
    if (!function_exists('ms_create_woo_order_for_invoice')) {
        wp_send_json_error('woo_function_not_available', 500);
    }

    $payment_url = ms_create_woo_order_for_invoice((int) $invoice->id);
    if (!$payment_url) {
        wp_send_json_error(array('code' => 'order_creation_failed', 'message' => function_exists('ms_get_woo_payment_setup_message') && ms_get_woo_payment_setup_message() ? ms_get_woo_payment_setup_message() : 'تعذر إنشاء طلب الدفع. راجع إعدادات WooCommerce وCheckout وبوابة الدفع.'), 500);
    }

    wp_send_json_success(array('payment_url' => $payment_url, 'invoice_id' => (int) $invoice->id, 'amount' => $amount));
});

// ============================================================
// AJAX Endpoints لنظام الوسيط العقاري
// ============================================================

/**
 * التحقق من حالة اشتراك الوسيط
 */
add_action('wp_ajax_ms_check_agent_subscription', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 401);
    }
    
    $user = wp_get_current_user();
    if (!function_exists('ms_user_has_role') || !ms_user_has_role($user->ID, 'agent')) {
        wp_send_json_error('forbidden', 403);
    }
    
    if (!function_exists('ms_check_agent_subscription_limits')) {
        wp_send_json_error('function_not_available', 500);
    }
    
    $result = ms_check_agent_subscription_limits($user->ID);
    wp_send_json_success($result);
});

/**
 * جلب المالكين حسب المبنى
 */
add_action('wp_ajax_ms_get_owners_by_building', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 401);
    }
    
    $user = wp_get_current_user();
    if (!function_exists('ms_user_has_role') || !ms_user_has_role($user->ID, 'agent')) {
        wp_send_json_error('forbidden', 403);
    }
    
    if (!isset($_POST['building_id'])) {
        wp_send_json_error('missing_building_id', 400);
    }
    
    $building_id = intval($_POST['building_id']);
    
    if (!function_exists('ms_get_owners_by_building')) {
        wp_send_json_error('function_not_available', 500);
    }
    
    $owners = ms_get_owners_by_building($building_id);
    wp_send_json_success(array('owners' => $owners));
});

/**
 * جلب المستأجرين حسب المبنى
 */
add_action('wp_ajax_ms_get_tenants_by_building', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 401);
    }
    
    $user = wp_get_current_user();
    if (!function_exists('ms_user_has_role') || !ms_user_has_role($user->ID, 'agent')) {
        wp_send_json_error('forbidden', 403);
    }
    
    if (!isset($_POST['building_id'])) {
        wp_send_json_error('missing_building_id', 400);
    }
    
    $building_id = intval($_POST['building_id']);
    
    if (!function_exists('ms_get_tenants_by_building')) {
        wp_send_json_error('function_not_available', 500);
    }
    
    $tenants = ms_get_tenants_by_building($building_id);
    wp_send_json_success(array('tenants' => $tenants));
});

/**
 * رفع عقد الإيجار
 */
add_action('wp_ajax_ms_upload_rent_contract', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 401);
    }
    
    $user = wp_get_current_user();
    if (!function_exists('ms_user_has_role') || !ms_user_has_role($user->ID, 'agent')) {
        wp_send_json_error('forbidden', 403);
    }
    
    if (!isset($_POST['property_id']) || !isset($_FILES['contract'])) {
        wp_send_json_error('invalid_data', 400);
    }
    
    $property_id = intval($_POST['property_id']);
    $file = $_FILES['contract'];
    
    // التحقق من الملف
    if ($file['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error('upload_error', 400);
    }
    
    // التحقق من نوع الملف
    $allowed_types = array('application/pdf', 'image/jpeg', 'image/png');
    if (!in_array($file['type'], $allowed_types)) {
        wp_send_json_error('invalid_file_type', 400);
    }
    
    // رفع الملف
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    $upload = wp_handle_upload($file, array('test_form' => false));
    
    if (isset($upload['error'])) {
        wp_send_json_error($upload['error'], 500);
    }
    
    // حفظ معلومات العقد
    $contract_id = wp_insert_post(array(
        'post_type' => 'ms_contract',
        'post_title' => 'عقد إيجار - عقار #' . $property_id,
        'post_content' => $upload['url'],
        'post_status' => 'publish',
        'post_author' => $user->ID
    ));
    
    if ($contract_id) {
        update_post_meta($property_id, '_ms_rent_contract_id', $contract_id);
        update_post_meta($contract_id, '_ms_property_id', $property_id);
        update_post_meta($contract_id, '_ms_contract_type', 'rent');
        update_post_meta($contract_id, '_ms_contract_file', $upload['url']);
        update_post_meta($contract_id, '_ms_contract_signed_date', current_time('mysql'));
        update_post_meta($property_id, 'ms_property_contract_url', esc_url_raw($upload['url']));
        update_post_meta($property_id, 'ms_property_contract_type', 'rent');
        update_post_meta($property_id, 'ms_property_contract_original_url', esc_url_raw($upload['url']));
        update_post_meta($property_id, 'ms_property_contract_signature_status', 'awaiting_signatures');
        
        wp_send_json_success(array(
            'contract_id' => $contract_id,
            'file_url' => $upload['url'],
            'message' => 'تم رفع عقد الإيجار بنجاح.'
        ));
    } else {
        wp_send_json_error('contract_creation_failed', 500);
    }
});

/**
 * رفع عقد البيع
 */
add_action('wp_ajax_ms_upload_sale_contract', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 401);
    }
    
    $user = wp_get_current_user();
    if (!function_exists('ms_user_has_role') || !ms_user_has_role($user->ID, 'agent')) {
        wp_send_json_error('forbidden', 403);
    }
    
    if (!isset($_POST['property_id']) || !isset($_FILES['contract'])) {
        wp_send_json_error('invalid_data', 400);
    }
    
    $property_id = intval($_POST['property_id']);
    $file = $_FILES['contract'];
    
    // التحقق من الملف
    if ($file['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error('upload_error', 400);
    }
    
    // التحقق من نوع الملف
    $allowed_types = array('application/pdf', 'image/jpeg', 'image/png');
    if (!in_array($file['type'], $allowed_types)) {
        wp_send_json_error('invalid_file_type', 400);
    }
    
    // رفع الملف
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    $upload = wp_handle_upload($file, array('test_form' => false));
    
    if (isset($upload['error'])) {
        wp_send_json_error($upload['error'], 500);
    }
    
    // حفظ معلومات العقد
    $contract_id = wp_insert_post(array(
        'post_type' => 'ms_contract',
        'post_title' => 'عقد بيع - عقار #' . $property_id,
        'post_content' => $upload['url'],
        'post_status' => 'publish',
        'post_author' => $user->ID
    ));
    
    if ($contract_id) {
        update_post_meta($property_id, '_ms_sale_contract_id', $contract_id);
        update_post_meta($contract_id, '_ms_property_id', $property_id);
        update_post_meta($contract_id, '_ms_contract_type', 'sale');
        update_post_meta($contract_id, '_ms_contract_file', $upload['url']);
        update_post_meta($contract_id, '_ms_contract_signed_date', current_time('mysql'));
        update_post_meta($property_id, 'ms_property_contract_url', esc_url_raw($upload['url']));
        update_post_meta($property_id, 'ms_property_contract_type', 'sale');
        update_post_meta($property_id, 'ms_property_contract_original_url', esc_url_raw($upload['url']));
        update_post_meta($property_id, 'ms_property_contract_signature_status', 'awaiting_signatures');
        
        wp_send_json_success(array(
            'contract_id' => $contract_id,
            'file_url' => $upload['url'],
            'message' => 'تم رفع عقد البيع بنجاح.'
        ));
    } else {
        wp_send_json_error('contract_creation_failed', 500);
    }
});

// ============================================================
// AJAX endpoints لنظام مبلغ التأمين - Security Deposit System
// ============================================================

/**
 * حجز مبلغ التأمين
 */
add_action('wp_ajax_ms_reserve_security_deposit', function() {
    check_ajax_referer('ms_facility_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 403);
    }
    
    $user = wp_get_current_user();
    $property_id = isset($_POST['property_id']) ? intval($_POST['property_id']) : 0;
    $tenant_id = isset($_POST['tenant_id']) ? intval($_POST['tenant_id']) : 0;
    $owner_id = isset($_POST['owner_id']) ? intval($_POST['owner_id']) : 0;
    $rent_amount = isset($_POST['rent_amount']) ? floatval($_POST['rent_amount']) : 0;
    
    if (!$property_id || !$tenant_id || !$owner_id || !$rent_amount) {
        wp_send_json_error('missing_parameters', 400);
    }
    
    // التحقق من الصلاحيات (المالك أو مدير النظام)
    if (!current_user_can('manage_options') && $user->ID != $owner_id) {
        wp_send_json_error('unauthorized', 403);
    }
    
    if (!function_exists('ms_reserve_security_deposit')) {
        wp_send_json_error('function_not_found', 500);
    }
    
    $result = ms_reserve_security_deposit($property_id, $tenant_id, $owner_id, $rent_amount);
    
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result['message'], 400);
    }
});

/**
 * استرجاع مبلغ التأمين
 */
add_action('wp_ajax_ms_release_security_deposit', function() {
    check_ajax_referer('ms_facility_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 403);
    }
    
    $user = wp_get_current_user();
    $property_id = isset($_POST['property_id']) ? intval($_POST['property_id']) : 0;
    $tenant_id = isset($_POST['tenant_id']) ? intval($_POST['tenant_id']) : 0;
    $owner_id = isset($_POST['owner_id']) ? intval($_POST['owner_id']) : 0;
    
    if (!$property_id || !$tenant_id || !$owner_id) {
        wp_send_json_error('missing_parameters', 400);
    }
    
    // التحقق من الصلاحيات (المالك أو مدير النظام)
    if (!current_user_can('manage_options') && $user->ID != $owner_id) {
        wp_send_json_error('unauthorized', 403);
    }
    
    if (!function_exists('ms_release_security_deposit')) {
        wp_send_json_error('function_not_found', 500);
    }
    
    $result = ms_release_security_deposit($property_id, $tenant_id, $owner_id);
    
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result['message'], 400);
    }
});

/**
 * خصم من مبلغ التأمين للأضرار
 */
add_action('wp_ajax_ms_deduct_from_security_deposit', function() {
    check_ajax_referer('ms_facility_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 403);
    }
    
    $user = wp_get_current_user();
    $property_id = isset($_POST['property_id']) ? intval($_POST['property_id']) : 0;
    $owner_id = isset($_POST['owner_id']) ? intval($_POST['owner_id']) : 0;
    $deduction_amount = isset($_POST['deduction_amount']) ? floatval($_POST['deduction_amount']) : 0;
    $reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : '';
    
    if (!$property_id || !$owner_id || !$deduction_amount) {
        wp_send_json_error('missing_parameters', 400);
    }
    
    // التحقق من الصلاحيات (المالك أو مدير النظام)
    if (!current_user_can('manage_options') && $user->ID != $owner_id) {
        wp_send_json_error('unauthorized', 403);
    }
    
    if (!function_exists('ms_deduct_from_security_deposit')) {
        wp_send_json_error('function_not_found', 500);
    }
    
    $result = ms_deduct_from_security_deposit($property_id, $owner_id, $deduction_amount, $reason);
    
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result['message'], 400);
    }
});

// ============================================================
// AJAX endpoints لنظام موافقة الوسيط - Agent Approval System
// ============================================================

/**
 * إنشاء طلب تغيير حالة العقار
 */
add_action('wp_ajax_ms_create_status_change_request', function() {
    check_ajax_referer('ms_facility_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 403);
    }
    
    $user = wp_get_current_user();
    $property_id = isset($_POST['property_id']) ? intval($_POST['property_id']) : 0;
    $requested_status = isset($_POST['requested_status']) ? sanitize_text_field($_POST['requested_status']) : '';
    $agent_id = isset($_POST['agent_id']) ? intval($_POST['agent_id']) : 0;
    
    if (!$property_id || !$requested_status || !$agent_id) {
        wp_send_json_error('missing_parameters', 400);
    }
    
    // التحقق من الصلاحيات (المالك فقط)
    if (!current_user_can('manage_options') && !function_exists('ms_user_has_role')) {
        wp_send_json_error('unauthorized', 403);
    }
    
    if (!current_user_can('manage_options') && !ms_user_has_role($user->ID, 'owner')) {
        wp_send_json_error('unauthorized', 403);
    }
    
    // التحقق من أن المالك هو صاحب العقار
    $property = get_post($property_id);
    if ($property && $property->post_author != $user->ID && !current_user_can('manage_options')) {
        wp_send_json_error('not_property_owner', 403);
    }
    
    if (!function_exists('ms_create_status_change_request')) {
        wp_send_json_error('function_not_found', 500);
    }
    
    $result = ms_create_status_change_request($property_id, $requested_status, $user->ID, $agent_id);
    
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result['message'], 400);
    }
});

/**
 * موافقة الوسيط على طلب تغيير الحالة
 */
add_action('wp_ajax_ms_approve_status_change_request', function() {
    check_ajax_referer('ms_facility_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 403);
    }
    
    $user = wp_get_current_user();
    $request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
    $comment = isset($_POST['comment']) ? sanitize_textarea_field($_POST['comment']) : '';
    
    if (!$request_id) {
        wp_send_json_error('missing_parameters', 400);
    }
    
    // التحقق من الصلاحيات (الوسيط فقط)
    if (!current_user_can('manage_options') && !function_exists('ms_user_has_role')) {
        wp_send_json_error('unauthorized', 403);
    }
    
    if (!current_user_can('manage_options') && !ms_user_has_role($user->ID, 'agent')) {
        wp_send_json_error('unauthorized', 403);
    }
    
    if (!function_exists('ms_approve_status_change_request')) {
        wp_send_json_error('function_not_found', 500);
    }
    
    $result = ms_approve_status_change_request($request_id, $user->ID, $comment);
    
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result['message'], 400);
    }
});

/**
 * رفض الوسيط لطلب تغيير الحالة
 */
add_action('wp_ajax_ms_reject_status_change_request', function() {
    check_ajax_referer('ms_facility_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 403);
    }
    
    $user = wp_get_current_user();
    $request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
    $reason = isset($_POST['reason']) ? sanitize_textarea_field($_POST['reason']) : '';
    
    if (!$request_id) {
        wp_send_json_error('missing_parameters', 400);
    }
    
    // التحقق من الصلاحيات (الوسيط فقط)
    if (!current_user_can('manage_options') && !function_exists('ms_user_has_role')) {
        wp_send_json_error('unauthorized', 403);
    }
    
    if (!current_user_can('manage_options') && !ms_user_has_role($user->ID, 'agent')) {
        wp_send_json_error('unauthorized', 403);
    }
    
    if (!function_exists('ms_reject_status_change_request')) {
        wp_send_json_error('function_not_found', 500);
    }
    
    $result = ms_reject_status_change_request($request_id, $user->ID, $reason);
    
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result['message'], 400);
    }
});

/**
 * جلب طلبات تغيير الحالة للوسيط
 */
add_action('wp_ajax_ms_get_agent_status_change_requests', function() {
    check_ajax_referer('ms_facility_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 403);
    }
    
    $user = wp_get_current_user();
    
    // التحقق من الصلاحيات (الوسيط فقط)
    if (!current_user_can('manage_options') && !function_exists('ms_user_has_role')) {
        wp_send_json_error('unauthorized', 403);
    }
    
    if (!current_user_can('manage_options') && !ms_user_has_role($user->ID, 'agent')) {
        wp_send_json_error('unauthorized', 403);
    }
    
    if (!function_exists('ms_get_agent_status_change_requests')) {
        wp_send_json_error('function_not_found', 500);
    }
    
    $requests = ms_get_agent_status_change_requests($user->ID);
    
    // إضافة معلومات العقار لكل طلب
    foreach ($requests as &$request) {
        $property = get_post($request->property_id);
        if ($property) {
            $request->property_title = $property->post_title;
            $request->property_link = get_permalink($property->ID);
        }
        
        // معلومات المالك
        $requester = get_userdata($request->requester_id);
        if ($requester) {
            $request->requester_name = $requester->display_name;
            $request->requester_email = $requester->user_email;
        }
    }
    
    wp_send_json_success(array('requests' => $requests));
});
