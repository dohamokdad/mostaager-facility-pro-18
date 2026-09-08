<?php
if (!defined('ABSPATH')) {
    exit;
}

// Profile update AJAX handler
add_action('wp_ajax_ms_update_profile', 'ms_handle_profile_update');
add_action('wp_ajax_nopriv_ms_update_profile', 'ms_handle_profile_update');

function ms_handle_profile_update() {
    if (!isset($_POST['ms_profile_nonce']) || !wp_verify_nonce($_POST['ms_profile_nonce'], 'ms_update_profile')) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
    }

    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'User not logged in'));
    }

    $user_id = get_current_user_id();

    // Update user data
    $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
    $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';
    $display_name = isset($_POST['display_name']) ? sanitize_text_field($_POST['display_name']) : '';
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
    $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $address = isset($_POST['address']) ? sanitize_textarea_field(wp_unslash($_POST['address'])) : '';
    $meta_fields = array(
        'job_title' => 'fave_author_title', 'license' => 'fave_author_license', 'whatsapp' => 'fave_author_whatsapp',
        'tax_number' => 'tax_number', 'languages' => 'fave_author_language', 'company' => 'fave_author_company',
        'service_areas' => 'fave_author_service_area', 'specialties' => 'fave_author_specialties'
    );

    // Check if email is valid and not already taken by another user
    if ($email && !is_email($email)) {
        wp_send_json_error(array('message' => 'Invalid email address'));
    }

    if ($email && email_exists($email) && email_exists($email) !== $user_id) {
        wp_send_json_error(array('message' => 'Email already in use'));
    }

    $user_data = array(
        'ID' => $user_id,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'display_name' => $display_name,
        'description' => $description,
    );

    if ($email) {
        $user_data['user_email'] = $email;
    }

    $user_id = wp_update_user($user_data);

    if (is_wp_error($user_id)) {
        wp_send_json_error(array('message' => $user_id->get_error_message()));
    }

    // Update user meta
    update_user_meta($user_id, 'billing_phone', $phone);
    update_user_meta($user_id, 'billing_address_1', $address);
    foreach ($meta_fields as $request_key => $meta_key) {
        if (isset($_POST[$request_key])) {
            update_user_meta($user_id, $meta_key, sanitize_textarea_field(wp_unslash($_POST[$request_key])));
        }
    }

    // Handle avatar upload. The input is associated with the profile form via the HTML form attribute.
    if (isset($_FILES['avatar']) && !empty($_FILES['avatar']['name'])) {
        $file = $_FILES['avatar'];
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            wp_send_json_error(array('message' => 'تعذر رفع صورة الملف الشخصي.'), 400);
        }
        $allowed_mimes = array('jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp');
        $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], $allowed_mimes);
        if (empty($check['type']) || empty($check['ext'])) {
            wp_send_json_error(array('message' => 'نوع الصورة غير مدعوم. استخدم JPG أو PNG أو GIF أو WebP.'), 415);
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $upload = wp_handle_upload($file, array('test_form' => false, 'mimes' => $allowed_mimes));
        if (!empty($upload['error']) || empty($upload['file']) || empty($upload['url'])) {
            wp_send_json_error(array('message' => $upload['error'] ?? 'تعذر حفظ صورة الملف الشخصي.'), 500);
        }
        $attachment_id = wp_insert_attachment(array(
            'post_mime_type' => $upload['type'],
            'post_title' => sanitize_text_field(pathinfo($file['name'], PATHINFO_FILENAME)),
            'post_status' => 'inherit',
            'post_author' => $user_id,
        ), $upload['file']);
        if (!is_wp_error($attachment_id) && $attachment_id) {
            wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $upload['file']));
            update_user_meta($user_id, 'ms_custom_avatar_id', $attachment_id);
        }
        update_user_meta($user_id, 'ms_custom_avatar', esc_url_raw($upload['url']));
    }

    wp_send_json_success(array('message' => 'تم تحديث الملف الشخصي والصورة بنجاح.', 'avatar_url' => esc_url_raw((string) get_user_meta($user_id, 'ms_custom_avatar', true))));
}
