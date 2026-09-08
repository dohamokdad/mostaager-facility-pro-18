<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function () {
    $args = array(
        'labels' => array(
            'name' => __('Buildings', 'mostaager'),
            'singular_name' => __('Building', 'mostaager'),
        ),
        'public' => false,
        'show_ui' => false,
        'has_archive' => false,
        'supports' => array('title', 'editor', 'custom-fields'),
        'show_in_rest' => true,
        'rewrite' => array('slug' => 'ms-building'),
    );
    register_post_type('ms_building', $args);

    $args = array(
        'labels' => array(
            'name' => __('Expenses', 'mostaager'),
            'singular_name' => __('Expense', 'mostaager'),
        ),
        'public' => false,
        'show_ui' => false,
        'has_archive' => false,
        'supports' => array('title', 'editor', 'custom-fields'),
        'show_in_rest' => true,
        'rewrite' => array('slug' => 'ms-expense'),
    );
    register_post_type('ms_expense', $args);

    $args = array(
        'labels' => array(
            'name' => __('Invoices', 'mostaager'),
            'singular_name' => __('Invoice', 'mostaager'),
        ),
        'public' => false,
        'show_ui' => false,
        'has_archive' => false,
        'supports' => array('title', 'editor', 'custom-fields'),
        'show_in_rest' => true,
        'rewrite' => array('slug' => 'ms-invoice'),
    );
    register_post_type('ms_invoice', $args);

    $args = array(
        'labels' => array(
            'name' => __('Discussions', 'mostaager'),
            'singular_name' => __('Discussion', 'mostaager'),
        ),
        'public' => false,
        'show_ui' => false,
        'has_archive' => false,
        'supports' => array('title', 'editor', 'comments', 'custom-fields'),
        'show_in_rest' => true,
        'rewrite' => array('slug' => 'ms-discussion'),
    );
    register_post_type('ms_discussion', $args);

    $args = array(
        'labels' => array(
            'name' => __('Transfers', 'mostaager'),
            'singular_name' => __('Transfer', 'mostaager'),
        ),
        'public' => false,
        'show_ui' => false,
        'has_archive' => false,
        'supports' => array('title', 'editor', 'custom-fields'),
        'show_in_rest' => true,
        'rewrite' => array('slug' => 'ms-transfer'),
    );
    register_post_type('ms_transfer', $args);

    if (!post_type_exists('building')) {
        register_post_type('building', [
            'labels' => [
                'name' => 'المباني',
                'singular_name' => 'مبنى',
                'add_new' => 'إضافة مبنى',
                'add_new_item' => 'إضافة مبنى جديد',
                'edit_item' => 'تعديل المبنى',
                'view_item' => 'عرض المبنى',
                'search_items' => 'بحث في المباني',
                'not_found' => 'لا توجد مباني',
            ],
            'public' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
            'rewrite' => ['slug' => 'buildings'],
            'menu_icon' => 'dashicons-building',
            'menu_position' => 25,
        ]);
    }

    if (!post_type_exists('invoices')) {
        register_post_type('invoices', [
            'labels' => [
                'name' => 'الفواتير',
                'singular_name' => 'فاتورة',
                'add_new' => 'إضافة فاتورة',
                'add_new_item' => 'إضافة فاتورة جديدة',
                'edit_item' => 'تعديل الفاتورة',
                'view_item' => 'عرض الفاتورة',
                'search_items' => 'بحث في الفواتير',
                'not_found' => 'لا توجد فواتير',
            ],
            'public' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'supports' => ['title', 'custom-fields'],
            'rewrite' => ['slug' => 'invoices'],
            'menu_icon' => 'dashicons-media-spreadsheet',
            'menu_position' => 27,
        ]);
    }

    if (!post_type_exists('discussions')) {
        register_post_type('discussions', [
            'labels' => [
                'name' => 'المناقشات',
                'singular_name' => 'مناقشة',
                'add_new' => 'إضافة مناقشة',
                'add_new_item' => 'إضافة مناقشة جديدة',
                'edit_item' => 'تعديل المناقشة',
                'view_item' => 'عرض المناقشة',
                'search_items' => 'بحث في المناقشات',
                'not_found' => 'لا توجد مناقشات',
            ],
            'public' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'supports' => ['title', 'editor', 'author', 'comments', 'custom-fields'],
            'rewrite' => ['slug' => 'discussions'],
            'menu_icon' => 'dashicons-groups',
            'menu_position' => 28,
        ]);
    }

    if (!post_type_exists('transfers')) {
        register_post_type('transfers', [
            'labels' => [
                'name' => 'طلبات التحويل',
                'singular_name' => 'طلب تحويل',
                'add_new' => 'إضافة طلب',
                'add_new_item' => 'إضافة طلب تحويل جديد',
                'edit_item' => 'تعديل طلب التحويل',
                'view_item' => 'عرض طلب التحويل',
                'search_items' => 'بحث في طلبات التحويل',
                'not_found' => 'لا توجد طلبات تحويل',
            ],
            'public' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'supports' => ['title', 'editor', 'custom-fields'],
            'rewrite' => ['slug' => 'transfers'],
            'menu_icon' => 'dashicons-money',
            'menu_position' => 29,
        ]);
    }

    // Invoice Category Taxonomy for categorizing invoices
    if (!taxonomy_exists('invoice_category')) {
        register_taxonomy('invoice_category', ['ms_invoice', 'houzez_invoice'], [
            'labels' => [
                'name' => 'تصنيفات الفواتير',
                'singular_name' => 'تصنيف الفاتورة',
                'search_items' => 'بحث في التصنيفات',
                'all_items' => 'جميع التصنيفات',
                'parent_item' => 'التصنيف الأب',
                'parent_item_colon' => 'التصنيف الأب:',
                'edit_item' => 'تعديل التصنيف',
                'update_item' => 'تحديث التصنيف',
                'add_new_item' => 'إضافة تصنيف جديد',
                'new_item_name' => 'اسم التصنيف الجديد',
                'menu_name' => 'تصنيفات الفواتير',
            ],
            'hierarchical' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'invoice-category'],
        ]);

        // Create default invoice categories
        $default_categories = [
            'property-rent' => 'إيجار العقار',
            'property-monthly' => 'إيجار شهري',
            'property-sale' => 'بيع وشراء',
            'building-maintenance' => 'صيانة المبنى',
            'building-facilities' => 'مرافق المبنى',
            'building-utilities' => 'مرافق (كهرباء/مياه)',
            'agent-fees' => 'رسوم الوسيط',
        ];

        foreach ($default_categories as $slug => $name) {
            if (!term_exists($slug, 'invoice_category')) {
                wp_insert_term($name, 'invoice_category', ['slug' => $slug]);
            }
        }
    }

    // تسجيل نوع محتوى العقود
    if (!post_type_exists('ms_contract')) {
        $args = array(
            'labels' => array(
                'name' => __('Contracts', 'mostaager'),
                'singular_name' => __('Contract', 'mostaager'),
            ),
            'public' => false,
            'show_ui' => true,
            'has_archive' => false,
            'supports' => array('title', 'editor', 'custom-fields'),
            'show_in_rest' => true,
            'rewrite' => array('slug' => 'ms-contract'),
        );
        register_post_type('ms_contract', $args);
    }
});

// Mirror normalized records into the admin-facing CPT screens. The ms_* tables
// remain the source of truth for dashboards and APIs.
add_action('admin_init', function () {
    if (!current_user_can('manage_options')) {
        return;
    }

    global $wpdb;

    $buildings = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}ms_buildings ORDER BY id ASC");
    foreach ((array) $buildings as $building) {
        $existing = get_posts(array(
            'post_type' => 'building',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_key' => '_ms_source_building_id',
            'meta_value' => absint($building->id),
        ));
        $post_id = !empty($existing) ? absint($existing[0]) : 0;
        $post_data = array('post_title' => sanitize_text_field($building->title), 'post_status' => 'publish', 'post_type' => 'building');
        if ($post_id) {
            $post_data['ID'] = $post_id;
            wp_update_post($post_data);
        } else {
            $post_id = wp_insert_post($post_data, true);
            if (!is_wp_error($post_id)) {
                update_post_meta($post_id, '_ms_source_building_id', absint($building->id));
            }
        }
    }

    $invoices = $wpdb->get_results("SELECT id, description, amount, status, due_date FROM {$wpdb->prefix}ms_invoices ORDER BY id ASC LIMIT 500");
    foreach ((array) $invoices as $invoice) {
        $existing = get_posts(array(
            'post_type' => 'invoices',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_key' => '_ms_source_invoice_id',
            'meta_value' => absint($invoice->id),
        ));
        $post_data = array(
            'post_title' => sanitize_text_field($invoice->description ?: 'فاتورة #' . absint($invoice->id)),
            'post_content' => 'المبلغ: ' . number_format_i18n((float) $invoice->amount, 2) . ' ج.م\nالحالة: ' . sanitize_text_field($invoice->status) . '\nالاستحقاق: ' . sanitize_text_field($invoice->due_date),
            'post_status' => 'publish',
            'post_type' => 'invoices',
        );
        if (!empty($existing)) {
            $post_data['ID'] = absint($existing[0]);
            wp_update_post($post_data);
        } else {
            $post_id = wp_insert_post($post_data, true);
            if (!is_wp_error($post_id)) {
                update_post_meta($post_id, '_ms_source_invoice_id', absint($invoice->id));
            }
        }
    }

    $discussions = get_posts(array('post_type' => 'ms_discussion', 'post_status' => 'any', 'posts_per_page' => 500));
    foreach ((array) $discussions as $discussion) {
        $existing = get_posts(array(
            'post_type' => 'discussions',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_key' => '_ms_source_discussion_id',
            'meta_value' => absint($discussion->ID),
        ));
        $post_data = array(
            'post_title' => $discussion->post_title,
            'post_content' => $discussion->post_content,
            'post_status' => $discussion->post_status,
            'post_type' => 'discussions',
            'post_author' => absint($discussion->post_author),
        );
        if (!empty($existing)) {
            $post_data['ID'] = absint($existing[0]);
            wp_update_post($post_data);
        } else {
            $post_id = wp_insert_post($post_data, true);
            if (!is_wp_error($post_id)) {
                update_post_meta($post_id, '_ms_source_discussion_id', absint($discussion->ID));
                $building_id = get_post_meta($discussion->ID, 'ms_building_id', true);
                if ($building_id) {
                    update_post_meta($post_id, 'ms_building_id', absint($building_id));
                }
            }
        }
    }

    $transfer_requests = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ms_transfer_requests ORDER BY id ASC LIMIT 500");
    foreach ((array) $transfer_requests as $request) {
        $existing = get_posts(array(
            'post_type' => 'transfers',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_key' => '_ms_source_transfer_request_id',
            'meta_value' => absint($request->id),
        ));
        $requester = get_userdata(absint($request->requested_by));
        $post_data = array(
            'post_title' => sprintf('طلب تحويل صيانة #%d - %s', absint($request->id), $requester ? $requester->display_name : 'رئيس اتحاد الملاك'),
            'post_content' => (string) ($request->notes ?? ''),
            'post_status' => 'publish',
            'post_type' => 'transfers',
            'post_author' => absint($request->requested_by),
        );
        if (!empty($existing)) {
            $post_data['ID'] = absint($existing[0]);
            wp_update_post($post_data);
            $post_id = absint($existing[0]);
        } else {
            $post_id = wp_insert_post($post_data, true);
            if (is_wp_error($post_id)) {
                continue;
            }
            update_post_meta($post_id, '_ms_source_transfer_request_id', absint($request->id));
        }
        update_post_meta($post_id, 'building_id', absint($request->building_id));
        update_post_meta($post_id, 'maintenance_id', absint($request->work_order_id));
        update_post_meta($post_id, 'amount', (float) $request->amount);
        update_post_meta($post_id, 'status', sanitize_key($request->status));
        update_post_meta($post_id, 'requested_by', absint($request->requested_by));
        update_post_meta($post_id, 'owner_id', absint($request->owner_id ?? 0));
        update_post_meta($post_id, 'bank_name', sanitize_text_field($request->bank_name ?? ''));
        update_post_meta($post_id, 'bank_account_name', sanitize_text_field($request->bank_account_name ?? ''));
        update_post_meta($post_id, 'bank_account_number', sanitize_text_field($request->bank_account_number ?? ''));
        update_post_meta($post_id, 'bank_iban', sanitize_text_field($request->bank_iban ?? ''));
    }
});

// ============================================================
// Cron Job للتحقق من انتهاء اشتراكات الوسطاء
// ============================================================

/**
 * جدولة Cron Job للتحقق اليومي من الاشتراكات
 */
add_action('init', function () {
    if (!wp_next_scheduled('ms_check_agent_subscriptions_daily')) {
        wp_schedule_event(time(), 'daily', 'ms_check_agent_subscriptions_daily');
    }
});

/**
 * معالج Cron Job للتحقق من انتهاء الاشتراكات
 */
add_action('ms_check_agent_subscriptions_daily', 'ms_process_agent_subscription_expiry');

function ms_process_agent_subscription_expiry() {
    $agents = get_users(array('role__in' => array('agent', 'houzez_agent')));
    
    foreach ($agents as $agent) {
        $subscription = function_exists('ms_get_agent_subscription_status') 
            ? ms_get_agent_subscription_status($agent->ID) 
            : array('status' => 'active');
        
        if ($subscription['status'] !== 'active') {
            if (function_exists('ms_convert_agent_properties_to_draft')) {
                ms_convert_agent_properties_to_draft($agent->ID);
            }
        }
    }
}

