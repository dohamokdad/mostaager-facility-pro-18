<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Houzez REST API Integration
 * Merges Mostaager REST API endpoints with Houzez API
 */

// Register unified REST API routes
add_action('rest_api_init', 'ms_houzez_register_unified_routes');

function ms_houzez_register_unified_routes() {
    // Invoices
    register_rest_route('mostager/v1', '/invoices', [
        'methods' => 'GET',
        'callback' => 'ms_houzez_get_invoices',
        'permission_callback' => 'ms_houzez_api_permission',
    ]);
    
    register_rest_route('mostager/v1', '/invoices/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'ms_houzez_get_invoice',
        'permission_callback' => 'ms_houzez_api_permission',
    ]);
    
    register_rest_route('mostager/v1', '/invoices', [
        'methods' => 'POST',
        'callback' => 'ms_houzez_rest_create_invoice',
        'permission_callback' => 'ms_houzez_api_permission',
    ]);
    
    // Buildings
    register_rest_route('mostager/v1', '/buildings', [
        'methods' => 'GET',
        'callback' => 'ms_houzez_get_buildings',
        'permission_callback' => 'ms_houzez_api_permission',
    ]);
    
    register_rest_route('mostager/v1', '/buildings/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'ms_houzez_get_building',
        'permission_callback' => 'ms_houzez_api_permission',
    ]);
    
    // Units
    register_rest_route('mostager/v1', '/units', [
        'methods' => 'GET',
        'callback' => 'ms_houzez_get_units',
        'permission_callback' => 'ms_houzez_api_permission',
    ]);
    
    register_rest_route('mostager/v1', '/units/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'ms_houzez_get_unit',
        'permission_callback' => 'ms_houzez_api_permission',
    ]);
    
    // Maintenance
    register_rest_route('mostager/v1', '/maintenance', [
        'methods' => 'GET',
        'callback' => 'ms_houzez_get_maintenance_requests',
        'permission_callback' => 'ms_houzez_api_permission',
    ]);
    
    register_rest_route('mostager/v1', '/maintenance', [
        'methods' => 'POST',
        'callback' => 'ms_houzez_create_maintenance_request',
        'permission_callback' => 'ms_houzez_api_permission',
    ]);
    
    // Wallet
    register_rest_route('mostager/v1', '/wallet/(?P<user_id>\d+)', [
        'methods' => 'GET',
        'callback' => 'ms_houzez_get_wallet',
        'permission_callback' => 'ms_houzez_api_permission',
    ]);
    
    register_rest_route('mostager/v1', '/wallet/(?P<user_id>\d+)/topup', [
        'methods' => 'POST',
        'callback' => 'ms_houzez_rest_topup_wallet',
        'permission_callback' => 'ms_houzez_api_permission',
    ]);
    
    // Notifications
    register_rest_route('mostager/v1', '/notifications', [
        'methods' => 'GET',
        'callback' => 'ms_houzez_get_notifications',
        'permission_callback' => 'ms_houzez_api_permission',
    ]);
    
    register_rest_route('mostager/v1', '/notifications/(?P<id>\d+)/read', [
        'methods' => 'POST',
        'callback' => 'ms_houzez_rest_mark_notification_read',
        'permission_callback' => 'ms_houzez_api_permission',
    ]);
    
    // Discussions
    register_rest_route('mostager/v1', '/discussions', [
        'methods' => 'GET',
        'callback' => 'ms_houzez_get_discussions',
        'permission_callback' => 'ms_houzez_api_permission',
    ]);
    
    register_rest_route('mostager/v1', '/discussions/(?P<id>\d+)/comments', [
        'methods' => 'POST',
        'callback' => 'ms_houzez_add_discussion_comment',
        'permission_callback' => 'ms_houzez_api_permission',
    ]);

    // API documentation endpoint
    register_rest_route('mostager/v1', '/docs', [
        'methods' => 'GET',
        'callback' => 'ms_houzez_api_docs',
        'permission_callback' => '__return_true',
    ]);
}

// Permission callback
function ms_houzez_api_permission() {
    return current_user_can('read');
}

function ms_houzez_can_access_building($building_id) {
    $building_id = absint($building_id);
    if (!$building_id) {
        return false;
    }

    $user_id = get_current_user_id();
    return current_user_can('manage_options')
        || (function_exists('ms_current_user_manages_building')
            && ms_current_user_manages_building($user_id, $building_id));
}

function ms_houzez_rest_create_invoice($request) {
    if (!current_user_can('manage_options') && (!function_exists('ms_user_has_role') || !ms_user_has_role(get_current_user_id(), 'building_manager'))) {
        return new WP_Error('forbidden', 'غير مصرح', array('status' => 403));
    }

    $data = $request->get_json_params();
    $data = is_array($data) ? $data : $request->get_params();
    $data['user_id'] = absint($data['user_id'] ?? 0);
    $data['amount'] = floatval($data['amount'] ?? 0);
    if (!$data['user_id'] || $data['amount'] <= 0) {
        return new WP_Error('invalid_invoice', 'بيانات الفاتورة غير صالحة', array('status' => 400));
    }

    $invoice_id = function_exists('ms_houzez_create_invoice') ? ms_houzez_create_invoice($data) : 0;
    return $invoice_id
        ? rest_ensure_response(array('success' => true, 'invoice_id' => absint($invoice_id)))
        : new WP_Error('invoice_create_failed', 'تعذر إنشاء الفاتورة', array('status' => 500));
}

function ms_houzez_rest_topup_wallet($request) {
    $user_id = get_current_user_id();
    $target_user_id = absint($request->get_param('user_id'));
    if (!$target_user_id || (!current_user_can('manage_options') && $target_user_id !== $user_id)) {
        return new WP_Error('forbidden', 'غير مصرح', array('status' => 403));
    }

    $params = $request->get_json_params();
    $amount = floatval($params['amount'] ?? $request->get_param('amount'));
    if ($amount <= 0 || !function_exists('ms_create_woo_order_for_wallet_recharge')) {
        return new WP_Error('invalid_topup', 'مبلغ التعبئة غير صالح أو بوابة الدفع غير متاحة', array('status' => 400));
    }

    $payment_url = ms_create_woo_order_for_wallet_recharge($target_user_id, $amount);
    return $payment_url
        ? rest_ensure_response(array('success' => true, 'payment_url' => $payment_url))
        : new WP_Error('topup_failed', 'تعذر إنشاء طلب التعبئة', array('status' => 500));
}

function ms_houzez_rest_mark_notification_read($request) {
    $notification_id = absint($request->get_param('id'));
    if (!$notification_id || !function_exists('ms_mark_notification_read')) {
        return new WP_Error('invalid_notification', 'الإشعار غير صالح', array('status' => 400));
    }

    $updated = ms_mark_notification_read($notification_id, get_current_user_id());
    return $updated
        ? rest_ensure_response(array('success' => true, 'notification_id' => $notification_id, 'read' => true))
        : new WP_Error('notification_update_failed', 'تعذر تحديث الإشعار', array('status' => 404));
}

// Get invoices (merged from ms_invoices and houzez_invoice)
function ms_houzez_get_invoices($request) {
    $user_id = get_current_user_id();
    $building_id = $request->get_param('building_id');
    $category = $request->get_param('category');
    
    // Get from ms_invoices table
    global $wpdb;
    $table = $wpdb->prefix . 'ms_invoices';
    
    $where = ['user_id = %d'];
    $params = [$user_id];
    
    if ($building_id) {
        $where[] = 'building_id = %d';
        $params[] = $building_id;
    }
    
    if ($category) {
        $where[] = 'invoice_category = %s';
        $params[] = $category;
    }
    
    $invoices = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC LIMIT 100",
            ...$params
        )
    );
    
    // Also get from houzez_invoice posts
    $args = [
        'post_type' => 'houzez_invoice',
        'posts_per_page' => 100,
        'post_status' => 'publish',
        'meta_query' => [
            ['key' => 'fave_invoice_user', 'value' => $user_id, 'compare' => '='],
        ],
        'orderby' => 'date',
        'order' => 'DESC',
    ];
    
    if ($building_id) {
        $args['meta_query'][] = ['key' => 'ms_building_id', 'value' => $building_id, 'compare' => '='];
    }
    
    if ($category) {
        $args['tax_query'] = [
            [
                'taxonomy' => 'invoice_category',
                'field' => 'slug',
                'terms' => $category,
            ],
        ];
    }
    
    $houzez_invoices = get_posts($args);
    
    // Merge results
    $merged = [];
    
    foreach ($invoices as $invoice) {
        $merged[] = [
            'id' => $invoice->id,
            'source' => 'mostaager',
            'user_id' => $invoice->user_id,
            'amount' => floatval($invoice->amount),
            'status' => $invoice->status,
            'category' => $invoice->invoice_category,
            'due_date' => $invoice->due_date,
            'created_at' => $invoice->created_at,
        ];
    }
    
    foreach ($houzez_invoices as $post) {
        $merged[] = [
            'id' => $post->ID,
            'source' => 'houzez',
            'user_id' => get_post_meta($post->ID, 'fave_invoice_user', true),
            'amount' => floatval(get_post_meta($post->ID, 'fave_invoice_price', true)),
            'status' => get_post_meta($post->ID, 'fave_invoice_status', true),
            'category' => get_post_meta($post->ID, 'ms_invoice_category', true),
            'due_date' => get_post_meta($post->ID, 'fave_invoice_due_date', true),
            'created_at' => $post->post_date,
        ];
    }
    
    // Sort by created_at
    usort($merged, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    return rest_ensure_response($merged);
}

// Get buildings
function ms_houzez_get_buildings($request) {
    if (!current_user_can('manage_options') && (!function_exists('ms_user_has_role') || !ms_user_has_role(get_current_user_id(), 'building_manager'))) {
        return new WP_Error('forbidden', 'غير مصرح', array('status' => 403));
    }

    $user_id = get_current_user_id();
    
    // Get from ms_buildings table
    global $wpdb;
    $table = $wpdb->prefix . 'ms_buildings';
    
    $buildings = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE manager_id = %d ORDER BY title ASC",
            $user_id
        )
    );
    
    $result = [];
    
    foreach ($buildings as $building) {
        $result[] = [
            'id' => $building->id,
            'source' => 'mostaager',
            'title' => $building->title,
            'address' => $building->address,
            'manager_id' => $building->manager_id,
            'created_at' => $building->created_at,
        ];
    }
    
    // Also get from houzez_building_manager posts
    $houzez_buildings = get_posts([
        'post_type' => 'houzez_building_manager',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'author' => $user_id,
    ]);
    
    foreach ($houzez_buildings as $post) {
        $result[] = [
            'id' => $post->ID,
            'source' => 'houzez',
            'title' => $post->post_title,
            'address' => get_post_meta($post->ID, 'fave_property_address', true),
            'manager_id' => $post->post_author,
            'created_at' => $post->post_date,
        ];
    }
    
    return rest_ensure_response($result);
}

// Get units
function ms_houzez_get_units($request) {
    $building_id = $request->get_param('building_id') ?: $request->get_param('id');

    if (!ms_houzez_can_access_building($building_id)) {
        return new WP_Error('forbidden_building', 'غير مصرح بهذا المبنى', array('status' => 403));
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'ms_units';
    
    $where = ['1=1'];
    $params = [];
    
    if ($building_id) {
        $where[] = 'building_id = %d';
        $params[] = $building_id;
    }
    
    $units = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . " ORDER BY unit_number ASC",
            ...$params
        )
    );
    
    $result = [];
    
    foreach ($units as $unit) {
        $result[] = [
            'id' => $unit->id,
            'source' => 'mostaager',
            'building_id' => $unit->building_id,
            'unit_number' => $unit->unit_number,
            'floor' => $unit->floor,
            'unit_type' => $unit->unit_type,
            'status' => $unit->status,
        ];
    }
    
    // Also get from property posts with ms_unit_number meta
    $args = [
        'post_type' => 'property',
        'posts_per_page' => -1,
        'post_status' => 'publish',
    ];
    
    if ($building_id) {
        $args['meta_query'] = [
            ['key' => 'ms_building_id', 'value' => $building_id, 'compare' => '='],
        ];
    }
    
    $properties = get_posts($args);
    
    foreach ($properties as $post) {
        $result[] = [
            'id' => $post->ID,
            'source' => 'houzez',
            'building_id' => get_post_meta($post->ID, 'ms_building_id', true),
            'unit_number' => get_post_meta($post->ID, 'ms_unit_number', true),
            'floor' => get_post_meta($post->ID, 'ms_floor', true),
            'unit_type' => get_post_meta($post->ID, 'ms_unit_type', true),
            'status' => get_post_meta($post->ID, 'ms_unit_status', true),
        ];
    }
    
    return rest_ensure_response($result);
}

// Get maintenance requests
function ms_houzez_get_maintenance_requests($request) {
    $building_id = $request->get_param('building_id');
    $status = $request->get_param('status');

    if (!$building_id || !ms_houzez_can_access_building($building_id)) {
        return new WP_Error('forbidden_building', 'يجب تحديد مبنى مصرح به', array('status' => 403));
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'ms_maintenance_requests';
    
    $where = ['1=1'];
    $params = [];
    
    if ($building_id) {
        $where[] = 'building_id = %d';
        $params[] = $building_id;
    }
    
    if ($status) {
        $where[] = 'status = %s';
        $params[] = $status;
    }
    
    $requests = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC LIMIT 100",
            ...$params
        )
    );
    
    $result = [];
    
    foreach ($requests as $req) {
        $result[] = [
            'id' => $req->id,
            'source' => 'mostaager',
            'building_id' => $req->building_id,
            'unit_id' => $req->unit_id,
            'title' => $req->title,
            'description' => $req->description,
            'priority' => $req->priority,
            'status' => $req->status,
            'cost' => floatval($req->cost),
            'created_at' => $req->created_at,
        ];
    }
    
    // Also get from houzez_maintenance posts
    $args = [
        'post_type' => 'houzez_maintenance',
        'posts_per_page' => 100,
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC',
    ];
    
    if ($building_id) {
        $args['meta_query'] = [
            ['key' => 'ms_building_id', 'value' => $building_id, 'compare' => '='],
        ];
    }
    
    if ($status) {
        $args['meta_query'][] = ['key' => 'ms_status', 'value' => $status, 'compare' => '='];
    }
    
    $houzez_maintenance = get_posts($args);
    
    foreach ($houzez_maintenance as $post) {
        $result[] = [
            'id' => $post->ID,
            'source' => 'houzez',
            'building_id' => get_post_meta($post->ID, 'ms_building_id', true),
            'unit_id' => get_post_meta($post->ID, 'ms_unit_id', true),
            'title' => $post->post_title,
            'description' => $post->post_content,
            'priority' => get_post_meta($post->ID, 'ms_priority', true),
            'status' => get_post_meta($post->ID, 'ms_status', true),
            'cost' => floatval(get_post_meta($post->ID, 'ms_cost', true)),
            'created_at' => $post->post_date,
        ];
    }
    
    return rest_ensure_response($result);
}

// Get wallet balance
function ms_houzez_get_wallet($request) {
    $user_id = intval($request['user_id']);

    if (!current_user_can('manage_options') && $user_id !== get_current_user_id()) {
        return new WP_Error('forbidden', 'غير مصرح', array('status' => 403));
    }
    
    $balance = function_exists('ms_get_wallet_balance') ? ms_get_wallet_balance($user_id) : 0;
    $currency = get_user_meta($user_id, MS_WALLET_CURRENCY_META, true) ?: 'EGP';
    
    $transactions = function_exists('ms_get_wallet_transactions') ? ms_get_wallet_transactions($user_id, 20) : [];
    
    return rest_ensure_response([
        'user_id' => $user_id,
        'balance' => floatval($balance),
        'currency' => $currency,
        'transactions' => $transactions,
    ]);
}

// Get notifications
function ms_houzez_get_notifications($request) {
    $user_id = get_current_user_id();
    
    $notifications = function_exists('ms_get_user_notifications') ? ms_get_user_notifications($user_id, 50) : [];
    $unread_count = function_exists('ms_get_unread_notification_count') ? ms_get_unread_notification_count($user_id) : 0;
    
    return rest_ensure_response([
        'notifications' => $notifications,
        'unread_count' => intval($unread_count),
    ]);
}

// Get discussions
function ms_houzez_get_discussions($request) {
    $building_id = $request->get_param('building_id');

    if (!$building_id || !ms_houzez_can_access_building($building_id)) {
        return new WP_Error('forbidden_building', 'يجب تحديد مبنى مصرح به', array('status' => 403));
    }
    
    $args = [
        'post_type' => 'ms_discussion',
        'posts_per_page' => 50,
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC',
    ];
    
    if ($building_id) {
        $args['meta_query'] = [
            ['key' => 'ms_building_id', 'value' => $building_id, 'compare' => '='],
        ];
    }
    
    $discussions = get_posts($args);
    
    $result = [];
    
    foreach ($discussions as $post) {
        $comments = get_comments([
            'post_id' => $post->ID,
            'status' => 'approve',
            'orderby' => 'comment_date',
            'order' => 'ASC',
        ]);
        
        $result[] = [
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'author' => get_the_author_meta('display_name', $post->post_author),
            'created_at' => $post->post_date,
            'comments' => array_map(function($comment) {
                return [
                    'id' => $comment->comment_ID,
                    'author' => $comment->comment_author,
                    'content' => $comment->comment_content,
                    'created_at' => $comment->comment_date,
                ];
            }, $comments),
        ];
    }
    
    return rest_ensure_response($result);
}

function ms_houzez_api_docs() {
    $docs = [
        'title' => 'Mostaager Facility Pro API Documentation',
        'version' => '1.0',
        'endpoints' => [
            [
                'path' => '/mostager/v1/invoices',
                'method' => 'GET',
                'description' => 'Get all invoices for current user',
                'parameters' => [
                    'building_id' => 'Filter by building ID (optional)',
                    'category' => 'Filter by invoice category (optional)',
                ],
            ],
            [
                'path' => '/mostager/v1/buildings',
                'method' => 'GET',
                'description' => 'Get all buildings managed by current user',
                'parameters' => [],
            ],
            [
                'path' => '/mostager/v1/units',
                'method' => 'GET',
                'description' => 'Get all units',
                'parameters' => [
                    'building_id' => 'Filter by building ID (optional)',
                ],
            ],
            [
                'path' => '/mostager/v1/maintenance',
                'method' => 'GET',
                'description' => 'Get all maintenance requests',
                'parameters' => [
                    'building_id' => 'Filter by building ID (optional)',
                    'status' => 'Filter by status (optional)',
                ],
            ],
            [
                'path' => '/mostager/v1/wallet/{user_id}',
                'method' => 'GET',
                'description' => 'Get wallet balance and transactions',
                'parameters' => [
                    'user_id' => 'User ID (required)',
                ],
            ],
                        [
                'path' => '/mostager/v1/notifications',
                'method' => 'GET',
                'description' => 'Get user notifications',
                'parameters' => [],
            ],
            [
                'path' => '/wp-json/mfp/v1/notification-preferences',
                'method' => 'GET/POST',
                'description' => 'Read or save per-user notification channel preferences',
                'parameters' => ['in_app' => '0/1', 'email' => '0/1', 'whatsapp' => '0/1', 'push' => '0/1'],
            ],
            [
                'path' => '/wp-json/mfp/v1/push-tokens',
                'method' => 'POST/DELETE',
                'description' => 'Register or remove a Firebase web/device token',
                'parameters' => ['token' => 'FCM registration token', 'platform' => 'web, android, ios'],
            ],
            [
                'path' => '/wp-json/mfp/v1/maintenance/{id}/timeline',
                'method' => 'GET',
                'description' => 'Get the status history and actors for a maintenance request',
                'parameters' => ['id' => 'Maintenance request ID'],
            ],

            [
                'path' => '/mostager/v1/discussions',
                'method' => 'GET',
                'description' => 'Get building discussions',
                'parameters' => [
                    'building_id' => 'Filter by building ID (optional)',
                ],
            ],
        ],
    ];
    
    return rest_ensure_response($docs);
}
