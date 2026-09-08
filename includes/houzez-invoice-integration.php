<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Houzez Invoice Integration
 * Integrates Mostaager invoice system with Houzez invoice post type
 */

// Sync ms_invoices table with houzez_invoice post type
add_action('save_post', 'ms_houzez_sync_invoice_to_post', 20, 2);

function ms_houzez_sync_invoice_to_post($post_id, $post) {
    if ($post->post_type !== 'houzez_invoice') {
        return;
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'ms_invoices';
    
    $user_id = intval(get_post_meta($post_id, 'fave_invoice_user', true));
    $amount = floatval(get_post_meta($post_id, 'fave_invoice_price', true));
    $status = get_post_meta($post_id, 'fave_invoice_status', true) ?: 'pending';
    $due_date = get_post_meta($post_id, 'fave_invoice_due_date', true);
    $invoice_category = get_post_meta($post_id, 'ms_invoice_category', true) ?: 'property-rent';
    $payer_type = get_post_meta($post_id, 'ms_payer_type', true) ?: 'tenant';
    
    // For rent invoices, calculate due_date as start_date + 1 month
    if (in_array($invoice_category, ['property-rent', 'property-monthly'])) {
        // Get tenant's lease start date
        $lease_start_date = null;
        if ($user_id) {
            $tenant_unit_table = $wpdb->prefix . 'ms_unit_tenants';
            $lease = $wpdb->get_row($wpdb->prepare(
                "SELECT start_date FROM {$tenant_unit_table} WHERE tenant_id = %d AND (end_date IS NULL OR end_date = '' OR end_date >= CURDATE()) ORDER BY start_date DESC LIMIT 1",
                $user_id
            ));
            if ($lease && $lease->start_date) {
                $lease_start_date = $lease->start_date;
            }
        }
        
        // Calculate due_date as start_date + 1 month
        if ($lease_start_date) {
            $due_date = date('Y-m-d', strtotime($lease_start_date . ' +1 month'));
        } else {
            // Fallback: use current date + 1 month if no lease found
            $due_date = date('Y-m-d', strtotime('+1 month'));
        }
        
        // Update the meta field to reflect the calculated due_date
        update_post_meta($post_id, 'fave_invoice_due_date', $due_date);
    }
    
    // Check if invoice exists in ms_invoices
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE wp_post_id = %d",
        $post_id
    ));
    
    if ($existing) {
        // Update existing
        $wpdb->update($table, [
            'user_id' => $user_id,
            'amount' => $amount,
            'status' => $status,
            'due_date' => $due_date,
            'invoice_category' => $invoice_category,
            'payer_type' => $payer_type,
            'updated_at' => current_time('mysql'),
        ], ['id' => $existing->id]);
    } else {
        // Create new
        $wpdb->insert($table, [
            'user_id' => $user_id,
            'amount' => $amount,
            'status' => $status,
            'due_date' => $due_date,
            'invoice_category' => $invoice_category,
            'payer_type' => $payer_type,
            'wp_post_id' => $post_id,
            'created_at' => $post->post_date,
        ]);
    }
}

// Add invoice category field to Houzez invoice metabox
add_action('add_meta_boxes', 'ms_houzez_add_invoice_category_field');

function ms_houzez_add_invoice_category_field() {
    add_meta_box(
        'ms_houzez_invoice_category',
        '📋 تصنيف الفاتورة (Mostaager)',
        'ms_houzez_render_invoice_category_field',
        'houzez_invoice',
        'side',
        'default'
    );
}

function ms_houzez_render_invoice_category_field($post) {
    $invoice_category = get_post_meta($post->ID, 'ms_invoice_category', true);
    $payer_type = get_post_meta($post->ID, 'ms_payer_type', true);
    wp_nonce_field('ms_houzez_invoice_category', 'ms_houzez_invoice_category_nonce');
    ?>
    <p>
        <label for="ms_invoice_category">تصنيف الفاتورة:</label>
        <select id="ms_invoice_category" name="ms_invoice_category" style="width:100%;">
            <option value="">-- اختر --</option>
            <optgroup label="فواتير العقار">
                <option value="property-rent" <?php selected($invoice_category, 'property-rent'); ?>>إيجار</option>
                <option value="property-monthly" <?php selected($invoice_category, 'property-monthly'); ?>>شهري</option>
                <option value="property-sale" <?php selected($invoice_category, 'property-sale'); ?>>بيع</option>
            </optgroup>
            <optgroup label="فواتير البناء والصيانة">
                <option value="building-maintenance" <?php selected($invoice_category, 'building-maintenance'); ?>>صيانة</option>
                <option value="building-facilities" <?php selected($invoice_category, 'building-facilities'); ?>>مرافق</option>
                <option value="building-utilities" <?php selected($invoice_category, 'building-utilities'); ?>>خدمات تشغيلية</option>
            </optgroup>
            <optgroup label="فواتير أخرى">
                <option value="agent-fees" <?php selected($invoice_category, 'agent-fees'); ?>>رسوم وسيط</option>
            </optgroup>
        </select>
    </p>
    <p>
        <label for="ms_payer_type">نوع الدافع:</label>
        <select id="ms_payer_type" name="ms_payer_type" style="width:100%;">
            <option value="">-- اختر --</option>
            <option value="tenant" <?php selected($payer_type, 'tenant'); ?>>مستأجر</option>
            <option value="owner" <?php selected($payer_type, 'owner'); ?>>مالك</option>
            <option value="agent" <?php selected($payer_type, 'agent'); ?>>وسيط</option>
            <option value="owner_or_tenant" <?php selected($payer_type, 'owner_or_tenant'); ?>>مالك/مستأجر</option>
        </select>
    </p>
    <?php
}

add_action('save_post', 'ms_houzez_save_invoice_category_field', 10, 2);

function ms_houzez_save_invoice_category_field($post_id, $post) {
    if (!isset($_POST['ms_houzez_invoice_category_nonce']) || !wp_verify_nonce($_POST['ms_houzez_invoice_category_nonce'], 'ms_houzez_invoice_category')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if ($post->post_type !== 'houzez_invoice') {
        return;
    }
    
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    $invoice_category = isset($_POST['ms_invoice_category']) ? sanitize_text_field($_POST['ms_invoice_category']) : '';
    $payer_type = isset($_POST['ms_payer_type']) ? sanitize_text_field($_POST['ms_payer_type']) : '';
    
    update_post_meta($post_id, 'ms_invoice_category', $invoice_category);
    update_post_meta($post_id, 'ms_payer_type', $payer_type);
}

// Override ms_get_user_invoices to include Houzez invoices
if (!function_exists('ms_get_user_invoices')) {
    function ms_get_user_invoices($user_id, $limit = 100) {
        global $wpdb;
        
        // Get from ms_invoices table
        $table = $wpdb->prefix . 'ms_invoices';
        $invoices = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
            $user_id,
            $limit
        ));
        
        // Also get from houzez_invoice posts
        $houzez_invoices = get_posts([
            'post_type' => 'houzez_invoice',
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => 'fave_invoice_user',
                    'value' => $user_id,
                    'compare' => '=',
                ],
            ],
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
        
        // Merge results
        foreach ($houzez_invoices as $post) {
            $invoice = (object) [
                'id' => $post->ID,
                'wp_post_id' => $post->ID,
                'user_id' => $user_id,
                'amount' => floatval(get_post_meta($post->ID, 'fave_invoice_price', true)),
                'status' => get_post_meta($post->ID, 'fave_invoice_status', true) ?: 'pending',
                'due_date' => get_post_meta($post->ID, 'fave_invoice_due_date', true),
                'invoice_category' => get_post_meta($post->ID, 'ms_invoice_category', true) ?: 'property-rent',
                'payer_type' => get_post_meta($post->ID, 'ms_payer_type', true) ?: 'tenant',
                'description' => $post->post_title,
                'created_at' => $post->post_date,
            ];
            
            // Check if already in list
            $exists = false;
            foreach ($invoices as $inv) {
                if (isset($inv->wp_post_id) && $inv->wp_post_id == $post->ID) {
                    $exists = true;
                    break;
                }
            }
            
            if (!$exists) {
                $invoices[] = $invoice;
            }
        }
        
        // Sort by created_at
        usort($invoices, function($a, $b) {
            return strtotime($b->created_at) - strtotime($a->created_at);
        });
        
        return array_slice($invoices, 0, $limit);
    }
}

// Create Houzez invoice from ms_invoices data
function ms_houzez_create_invoice($data) {
    $post_data = [
        'post_title' => $data['description'] ?? 'فاتورة جديدة',
        'post_content' => $data['description'] ?? '',
        'post_status' => 'publish',
        'post_type' => 'houzez_invoice',
        'post_author' => $data['created_by'] ?? get_current_user_id(),
    ];
    
    $post_id = wp_insert_post($post_data);
    
    if (is_wp_error($post_id)) {
        return false;
    }
    
    // Add Houzez invoice meta fields
    update_post_meta($post_id, 'fave_invoice_user', $data['user_id'] ?? 0);
    update_post_meta($post_id, 'fave_invoice_price', $data['amount'] ?? 0);
    update_post_meta($post_id, 'fave_invoice_status', $data['status'] ?? 'pending');
    update_post_meta($post_id, 'fave_invoice_due_date', $data['due_date'] ?? '');
    
    // Add Mostaager specific fields
    update_post_meta($post_id, 'ms_invoice_category', $data['invoice_category'] ?? 'property-rent');
    update_post_meta($post_id, 'ms_payer_type', $data['payer_type'] ?? 'tenant');
    
    // Add invoice category term
    if (!empty($data['invoice_category'])) {
        wp_set_object_terms($post_id, $data['invoice_category'], 'invoice_category');
    }
    
    return $post_id;
}

// Sync old ms_invoices to houzez_invoice posts
add_action('init', 'ms_sync_old_invoices');

function ms_sync_old_invoices() {
    if (get_option('ms_invoices_synced')) {
        return;
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'ms_invoices';
    
    $invoices = $wpdb->get_results(
        "SELECT * FROM {$table} WHERE wp_post_id = 0 OR wp_post_id IS NULL LIMIT 100"
    );
    
    foreach ($invoices as $invoice) {
        $post_id = ms_houzez_create_invoice([
            'user_id' => $invoice->user_id,
            'amount' => $invoice->amount,
            'status' => $invoice->status,
            'due_date' => $invoice->due_date,
            'invoice_category' => $invoice->invoice_category ?? 'property-rent',
            'payer_type' => $invoice->payer_type ?? 'tenant',
            'description' => $invoice->description ?? 'فاتورة',
            'created_by' => $invoice->created_by ?? 1,
        ]);
        
        if ($post_id) {
            $wpdb->update($table, ['wp_post_id' => $post_id], ['id' => $invoice->id]);
        }
    }
    
    // Mark as synced if no more invoices to sync
    $remaining = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$table} WHERE wp_post_id = 0 OR wp_post_id IS NULL"
    );
    
    if ($remaining == 0) {
        update_option('ms_invoices_synced', true);
    }
}

// Filter invoices by category in admin
add_filter('pre_get_posts', 'ms_filter_invoices_by_category');

function ms_filter_invoices_by_category($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return $query;
    }
    
    if ($query->get('post_type') !== 'houzez_invoice') {
        return $query;
    }
    
    if (isset($_GET['invoice_category']) && !empty($_GET['invoice_category'])) {
        $tax_query = [
            [
                'taxonomy' => 'invoice_category',
                'field' => 'slug',
                'terms' => sanitize($_GET['invoice_category']),
            ],
        ];
        
        $query->set('tax_query', $tax_query);
    }
    
    return $query;
}

// Add invoice category filter to admin list
add_action('restrict_manage_posts', 'ms_add_invoice_category_filter');

function ms_add_invoice_category_filter($post_type) {
    if ($post_type !== 'houzez_invoice') {
        return;
    }
    
    $categories = get_terms(['taxonomy' => 'invoice_category', 'hide_empty' => false]);
    
    if (empty($categories)) {
        return;
    }
    
    $selected = isset($_GET['invoice_category']) ? sanitize($_GET['invoice_category']) : '';
    
    echo '<select name="invoice_category">';
    echo '<option value="">جميع التصنيفات</option>';
    
    foreach ($categories as $category) {
        echo '<option value="' . esc_attr($category->slug) . '"' . selected($selected, $category->slug, false) . '>' . esc_html($category->name) . '</option>';
    }
    
    echo '</select>';
}
