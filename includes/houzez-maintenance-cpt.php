<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Houzez Maintenance Custom Post Type
 * Adds maintenance request system as a custom post type for Houzez integration
 */

// Register maintenance request custom post type
add_action('init', 'ms_houzez_register_maintenance_cpt');

function ms_houzez_register_maintenance_cpt() {
    if (post_type_exists('houzez_maintenance')) {
        return;
    }
    
    register_post_type('houzez_maintenance', [
        'labels' => [
            'name' => 'طلبات الصيانة',
            'singular_name' => 'طلب صيانة',
            'menu_name' => 'الصيانة',
            'add_new' => 'إضافة طلب جديد',
            'add_new_item' => 'إضافة طلب صيانة جديد',
            'edit_item' => 'تعديل طلب الصيانة',
            'new_item' => 'طلب صيانة جديد',
            'view_item' => 'عرض طلب الصيانة',
            'search_items' => 'بحث في طلبات الصيانة',
            'not_found' => 'لم يتم العثور على طلبات صيانة',
            'not_found_in_trash' => 'لم يتم العثور على طلبات صيانة في سلة المحذوفات',
        ],
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_position' => 30,
        'menu_icon' => 'dashicons-hammer',
        'capability_type' => 'post',
        'supports' => ['title', 'editor', 'author', 'custom-fields'],
        'has_archive' => false,
    ]);
}

// Register maintenance categories taxonomy
add_action('init', 'ms_houzez_register_maintenance_taxonomy');

function ms_houzez_register_maintenance_taxonomy() {
    if (taxonomy_exists('maintenance_category')) {
        return;
    }
    
    register_taxonomy('maintenance_category', 'houzez_maintenance', [
        'labels' => [
            'name' => 'تصنيفات الصيانة',
            'singular_name' => 'تصنيف الصيانة',
            'search_items' => 'بحث في التصنيفات',
            'all_items' => 'جميع التصنيفات',
            'parent_item' => 'التصنيف الأب',
            'parent_item_colon' => 'التصنيف الأب:',
            'edit_item' => 'تعديل التصنيف',
            'update_item' => 'تحديث التصنيف',
            'add_new_item' => 'إضافة تصنيف جديد',
            'new_item_name' => 'اسم التصنيف الجديد',
            'menu_name' => 'تصنيفات الصيانة',
        ],
        'hierarchical' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_rest' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => ['slug' => 'maintenance-category'],
    ]);
    
    // Create default maintenance categories
    $default_categories = [
        'electrical' => 'كهرباء',
        'plumbing' => 'سباكة',
        'hvac' => 'تكييف',
        'security' => 'أمان',
        'cleaning' => 'نظافة',
        'carpentry' => 'نجارة',
        'painting' => 'دهان',
        'other' => 'أخرى',
    ];
    
    foreach ($default_categories as $slug => $name) {
        if (!term_exists($slug, 'maintenance_category')) {
            wp_insert_term($name, 'maintenance_category', ['slug' => $slug]);
        }
    }
}

// Add custom fields for maintenance requests
add_action('add_meta_boxes', 'ms_houzez_add_maintenance_fields');

function ms_houzez_add_maintenance_fields() {
    add_meta_box(
        'ms_houzez_maintenance_details',
        '🔧 تفاصيل طلب الصيانة',
        'ms_houzez_render_maintenance_fields',
        'houzez_maintenance',
        'normal',
        'high'
    );
}

function ms_houzez_render_maintenance_fields($post) {
    global $wpdb;
    wp_nonce_field('ms_houzez_maintenance_fields', 'ms_houzez_maintenance_fields_nonce');
    
    $building_id = get_post_meta($post->ID, 'ms_building_id', true);
    $unit_id = get_post_meta($post->ID, 'ms_unit_id', true);
    $facility_id = get_post_meta($post->ID, 'ms_facility_id', true);
    $priority = get_post_meta($post->ID, 'ms_priority', true);
    $status = get_post_meta($post->ID, 'ms_status', true);
    $cost = get_post_meta($post->ID, 'ms_cost', true);
    $assigned_to = get_post_meta($post->ID, 'ms_assigned_to', true);
    $completed_date = get_post_meta($post->ID, 'ms_completed_date', true);
    
    $building_rows = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}ms_buildings ORDER BY title ASC");
    $building_ids = array();
    foreach ((array) $building_rows as $building_row) {
        $building_ids[] = intval($building_row->id);
    }
    ?>
    <table class="form-table">
        <tr>
            <th><label for="ms_building_id">المبنى:</label></th>
            <td>
                <select id="ms_building_id" name="ms_building_id" style="width:100%;">
                    <option value="0">-- اختر مبنى --</option>
                    <?php foreach ((array) $building_rows as $building_row) : ?>
                        <?php $row_id = intval($building_row->id); ?>
                        <option value="<?php echo esc_attr($row_id); ?>" <?php selected(intval($building_id), $row_id); ?>><?php echo esc_html($building_row->title ?: 'مبنى #' . $row_id); ?></option>
                    <?php endforeach; ?>
                    <?php if (!empty($building_id) && !in_array(intval($building_id), $building_ids, true)) : ?>
                        <option value="<?php echo esc_attr(intval($building_id)); ?>" selected><?php echo esc_html('مبنى #' . intval($building_id)); ?></option>
                    <?php endif; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="ms_unit_id">معرف الوحدة:</label></th>
            <td>
                <input type="number" id="ms_unit_id" name="ms_unit_id" value="<?php echo esc_attr($unit_id); ?>" style="width:100%;">
            </td>
        </tr>
        <tr>
            <th><label for="ms_facility_id">معرف المرفق:</label></th>
            <td>
                <input type="number" id="ms_facility_id" name="ms_facility_id" value="<?php echo esc_attr($facility_id); ?>" style="width:100%;">
            </td>
        </tr>
        <tr>
            <th><label for="ms_priority">الأولوية:</label></th>
            <td>
                <select id="ms_priority" name="ms_priority" style="width:100%;">
                    <option value="">-- اختر --</option>
                    <option value="low" <?php selected($priority, 'low'); ?>>منخفضة</option>
                    <option value="medium" <?php selected($priority, 'medium'); ?>>متوسطة</option>
                    <option value="high" <?php selected($priority, 'high'); ?>>عالية</option>
                    <option value="urgent" <?php selected($priority, 'urgent'); ?>>عاجلة</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="ms_status">الحالة:</label></th>
            <td>
                <select id="ms_status" name="ms_status" style="width:100%;">
                    <option value="">-- اختر --</option>
                    <option value="pending" <?php selected($status, 'pending'); ?>>معلقة</option>
                    <option value="in_progress" <?php selected($status, 'in_progress'); ?>>قيد التنفيذ</option>
                    <option value="completed" <?php selected($status, 'completed'); ?>>مكتملة</option>
                    <option value="cancelled" <?php selected($status, 'cancelled'); ?>>ملغاة</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="ms_cost">التكلفة (ج.م):</label></th>
            <td>
                <input type="number" id="ms_cost" name="ms_cost" value="<?php echo esc_attr($cost); ?>" step="0.01" style="width:100%;">
            </td>
        </tr>
        <tr>
            <th><label for="ms_assigned_to">مسند إلى (معرف المستخدم):</label></th>
            <td>
                <input type="number" id="ms_assigned_to" name="ms_assigned_to" value="<?php echo esc_attr($assigned_to); ?>" style="width:100%;">
            </td>
        </tr>
        <tr>
            <th><label for="ms_completed_date">تاريخ الإنجاز:</label></th>
            <td>
                <input type="date" id="ms_completed_date" name="ms_completed_date" value="<?php echo esc_attr($completed_date); ?>" style="width:100%;">
            </td>
        </tr>
    </table>
    <?php
}

add_action('save_post', 'ms_houzez_save_maintenance_fields', 10, 2);

function ms_houzez_save_maintenance_fields($post_id, $post) {
    if (!isset($_POST['ms_houzez_maintenance_fields_nonce']) || !wp_verify_nonce($_POST['ms_houzez_maintenance_fields_nonce'], 'ms_houzez_maintenance_fields')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if ($post->post_type !== 'houzez_maintenance') {
        return;
    }
    
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    $fields = [
        'ms_building_id' => 'intval',
        'ms_unit_id' => 'intval',
        'ms_facility_id' => 'intval',
        'ms_priority' => 'sanitize_text_field',
        'ms_status' => 'sanitize_text_field',
        'ms_cost' => 'floatval',
        'ms_assigned_to' => 'intval',
        'ms_completed_date' => 'sanitize_text_field',
    ];
    
    foreach ($fields as $field => $sanitizer) {
        $value = isset($_POST[$field]) ? call_user_func($sanitizer, $_POST[$field]) : '';
        update_post_meta($post_id, $field, $value);
    }
}

// Sync Houzez maintenance post with ms_maintenance_requests table
add_action('save_post', 'ms_houzez_sync_maintenance_to_db', 20, 2);

function ms_houzez_sync_maintenance_to_db($post_id, $post) {
    if ($post->post_type !== 'houzez_maintenance') {
        return;
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'ms_maintenance_requests';
    
    $building_id = intval(get_post_meta($post_id, 'ms_building_id', true));
    $unit_id = intval(get_post_meta($post_id, 'ms_unit_id', true));
    $facility_id = intval(get_post_meta($post_id, 'ms_facility_id', true));
    $priority = get_post_meta($post_id, 'ms_priority', true);
    $status = get_post_meta($post_id, 'ms_status', true);
    $cost = floatval(get_post_meta($post_id, 'ms_cost', true));
    
    // Check if maintenance request exists
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE wp_post_id = %d",
        $post_id
    ));
    
    if ($existing) {
        // Update existing
        $wpdb->update($table, [
            'building_id' => $building_id,
            'unit_id' => $unit_id,
            'facility_id' => $facility_id,
            'priority' => $priority ?: 'medium',
            'status' => $status ?: 'pending',
            'cost' => $cost,
        ], ['id' => $existing->id]);
    } else {
        // Create new
        $wpdb->insert($table, [
            'building_id' => $building_id,
            'unit_id' => $unit_id,
            'facility_id' => $facility_id,
            'title' => $post->post_title,
            'description' => $post->post_content,
            'priority' => $priority ?: 'medium',
            'status' => $status ?: 'pending',
            'cost' => $cost,
            'wp_post_id' => $post_id,
            'created_by' => $post->post_author,
            'created_at' => $post->post_date,
        ]);
    }
}

// Helper function to create maintenance request from Houzez post
function ms_houzez_create_maintenance_request($data) {
    $post_data = [
        'post_title' => $data['title'] ?? 'طلب صيانة جديد',
        'post_content' => $data['description'] ?? '',
        'post_status' => 'publish',
        'post_type' => 'houzez_maintenance',
        'post_author' => $data['created_by'] ?? get_current_user_id(),
    ];
    
    $post_id = wp_insert_post($post_data);
    
    if (is_wp_error($post_id)) {
        return false;
    }
    
    // Add custom fields
    update_post_meta($post_id, 'ms_building_id', $data['building_id'] ?? 0);
    update_post_meta($post_id, 'ms_unit_id', $data['unit_id'] ?? 0);
    update_post_meta($post_id, 'ms_facility_id', $data['facility_id'] ?? 0);
    update_post_meta($post_id, 'ms_priority', $data['priority'] ?? 'medium');
    update_post_meta($post_id, 'ms_status', $data['status'] ?? 'pending');
    update_post_meta($post_id, 'ms_cost', $data['cost'] ?? 0);
    
    // Add category if provided
    if (!empty($data['category'])) {
        wp_set_object_terms($post_id, $data['category'], 'maintenance_category');
    }
    
    return $post_id;
}
