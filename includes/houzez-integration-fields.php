<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Houzez Integration Fields
 * Adds Mostaager unit fields to Houzez property posts.
 *
 * The property-to-building selector is handled in includes/building-manager-metabox.php
 * to avoid duplicating the same UI in the Houzez property edit screen.
 */

// Add unit fields to property posts
add_action('add_meta_boxes', 'ms_houzez_add_unit_fields');

function ms_houzez_add_unit_fields() {
    add_meta_box(
        'ms_houzez_unit_fields',
        '🏠 بيانات الوحدة (Mostaager)',
        'ms_houzez_render_unit_fields',
        'property',
        'side',
        'default'
    );
}

function ms_houzez_render_unit_fields($post) {
    $unit_number = get_post_meta($post->ID, 'ms_unit_number', true);
    $floor = get_post_meta($post->ID, 'ms_floor', true);
    $unit_type = get_post_meta($post->ID, 'ms_unit_type', true);
    $unit_status = get_post_meta($post->ID, 'ms_unit_status', true);
    wp_nonce_field('ms_houzez_unit_fields', 'ms_houzez_unit_fields_nonce');
    ?>
    <p>
        <label for="ms_unit_number">رقم الوحدة:</label>
        <input type="text" id="ms_unit_number" name="ms_unit_number" value="<?php echo esc_attr($unit_number); ?>" style="width:100%;">
    </p>
    <p>
        <label for="ms_floor">الطابق:</label>
        <input type="text" id="ms_floor" name="ms_floor" value="<?php echo esc_attr($floor); ?>" style="width:100%;">
    </p>
    <p>
        <label for="ms_unit_type">نوع الوحدة:</label>
        <select id="ms_unit_type" name="ms_unit_type" style="width:100%;">
            <option value="">-- اختر --</option>
            <option value="apartment" <?php selected($unit_type, 'apartment'); ?>>شقة</option>
            <option value="shop" <?php selected($unit_type, 'shop'); ?>>محل</option>
            <option value="office" <?php selected($unit_type, 'office'); ?>>مكتب</option>
            <option value="villa" <?php selected($unit_type, 'villa'); ?>>فيلا</option>
        </select>
    </p>
    <p>
        <label for="ms_unit_status">حالة الوحدة:</label>
        <select id="ms_unit_status" name="ms_unit_status" style="width:100%;">
            <option value="">-- اختر --</option>
            <option value="available" <?php selected($unit_status, 'available'); ?>>متاحة</option>
            <option value="rented" <?php selected($unit_status, 'rented'); ?>>مؤجرة</option>
            <option value="sold" <?php selected($unit_status, 'sold'); ?>>مباعة</option>
        </select>
    </p>
    <?php
}

add_action('save_post', 'ms_houzez_save_unit_fields', 10, 2);

function ms_houzez_save_unit_fields($post_id, $post) {
    if (!isset($_POST['ms_houzez_unit_fields_nonce']) || !wp_verify_nonce($_POST['ms_houzez_unit_fields_nonce'], 'ms_houzez_unit_fields')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if ($post->post_type !== 'property') {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $unit_number = isset($_POST['ms_unit_number']) ? sanitize_text_field($_POST['ms_unit_number']) : '';
    $floor = isset($_POST['ms_floor']) ? sanitize_text_field($_POST['ms_floor']) : '';
    $unit_type = isset($_POST['ms_unit_type']) ? sanitize_text_field($_POST['ms_unit_type']) : '';
    $unit_status = isset($_POST['ms_unit_status']) ? sanitize_text_field($_POST['ms_unit_status']) : '';

    update_post_meta($post_id, 'ms_unit_number', $unit_number);
    update_post_meta($post_id, 'ms_floor', $floor);
    update_post_meta($post_id, 'ms_unit_type', $unit_type);
    update_post_meta($post_id, 'ms_unit_status', $unit_status);
}

// Helper function to get building ID from property post
function ms_houzez_get_property_building_id($property_id) {
    return intval(get_post_meta($property_id, 'ms_building_id', true)) ?: intval(get_post_meta($property_id, '_ms_building_id', true)) ?: intval(get_post_meta($property_id, 'building_id', true));
}

// Helper function to get unit data from property post
function ms_houzez_get_property_unit_data($property_id) {
    return array(
        'unit_number' => get_post_meta($property_id, 'ms_unit_number', true),
        'floor' => get_post_meta($property_id, 'ms_floor', true),
        'unit_type' => get_post_meta($property_id, 'ms_unit_type', true),
        'unit_status' => get_post_meta($property_id, 'ms_unit_status', true),
    );
}

// Sync property data with ms_units table
add_action('save_post', 'ms_houzez_sync_property_to_unit', 20, 2);

function ms_houzez_sync_property_to_unit($post_id, $post) {
    if ($post->post_type !== 'property') {
        return;
    }

    $building_id = ms_houzez_get_property_building_id($post_id);
    if (!$building_id) {
        return;
    }

    global $wpdb;
    $units_table = $wpdb->prefix . 'ms_units';

    // Check if unit exists
    $existing_unit = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$units_table} WHERE wp_post_id = %d",
        $post_id
    ));

    $unit_data = ms_houzez_get_property_unit_data($post_id);

    if ($existing_unit) {
        $wpdb->update($units_table, array(
            'building_id' => $building_id,
            'unit_number' => $unit_data['unit_number'],
            'floor' => $unit_data['floor'],
            'unit_type' => $unit_data['unit_type'],
            'status' => $unit_data['unit_status'],
        ), array('id' => $existing_unit->id));
    } else {
        $wpdb->insert($units_table, array(
            'building_id' => $building_id,
            'wp_post_id' => $post_id,
            'unit_number' => $unit_data['unit_number'],
            'floor' => $unit_data['floor'],
            'unit_type' => $unit_data['unit_type'],
            'status' => $unit_data['unit_status'],
        ));
    }
}
