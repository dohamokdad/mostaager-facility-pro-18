<?php
if (!defined('ABSPATH')) exit;

/**
 * Agent: update property_status taxonomy and upload contracts.
 *
 * Note: This file is intended to be included from core/ajax.php
 * to keep core/ajax.php manageable.
 */

add_action('wp_ajax_ms_agent_update_property_status', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 401);
    }

    $user = wp_get_current_user();
    $can_agent = function_exists('ms_user_can_view_dashboard') && ms_user_can_view_dashboard($user->ID, 'agent');
    $can_owner = function_exists('ms_user_can_view_dashboard') && ms_user_can_view_dashboard($user->ID, 'owner');
    $is_owner_actor = function_exists('ms_user_has_role') && ms_user_has_role($user->ID, 'owner') && !$can_agent;
    if (!$can_agent && !$can_owner) {
        wp_send_json_error('forbidden', 403);
    }

    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'mostaager-ajax-nonce')) {
        wp_send_json_error('security_failed', 403);
    }

    $prop_id = isset($_POST['prop_id']) ? absint($_POST['prop_id']) : 0;
    $term_slug = isset($_POST['term_slug']) ? sanitize_title($_POST['term_slug']) : '';
    if (empty($term_slug) && isset($_POST['status'])) {
        $term_slug = sanitize_title($_POST['status']);
    }

    if (!$prop_id || empty($term_slug)) {
        wp_send_json_error('invalid_data', 400);
    }
    if ($is_owner_actor && !in_array($term_slug, ['for-rent', 'for-sale'], true)) {
        wp_send_json_error(['code' => 'owner_status_restricted', 'message' => 'يسمح للمالك بحالتي للإيجار أو للبيع فقط.'], 422);
    }

    // Ownership check (best-effort, align with ms_delete_agent_property).
    $listings = function_exists('ms_get_properties_by_agent') ? ms_get_properties_by_agent($user->ID) : [];
    if (empty($listings) && function_exists('ms_get_properties_by_owner')) {
        $listings = ms_get_properties_by_owner($user->ID);
    }

    $listing_ids = [];
    if (!empty($listings)) {
        foreach ((array)$listings as $listing) {
            $lid = 0;
            if (is_object($listing)) {
                $lid = intval($listing->ID ?? $listing->id ?? 0);
                if (!$lid && isset($listing->unit_id)) $lid = intval($listing->unit_id);
                if (!$lid && isset($listing->post_id)) $lid = intval($listing->post_id);
                if (!$lid && isset($listing->post) && is_object($listing->post)) $lid = intval($listing->post->ID);
            } elseif (is_array($listing)) {
                $lid = intval($listing['ID'] ?? $listing['id'] ?? 0);
                if (!$lid && isset($listing['unit_id'])) $lid = intval($listing['unit_id']);
                if (!$lid && isset($listing['post_id'])) $lid = intval($listing['post_id']);
            } elseif (is_numeric($listing)) {
                $lid = intval($listing);
            }
            if ($lid) $listing_ids[] = $lid;
        }
    }

    if (empty($listing_ids)) {
        // fallback to meta-based mapping
        $author_listings = get_posts([
            'post_type' => 'property',
            'post_status' => ['publish','pending','draft','expired','houzez_sold','disapproved','on_hold','private','future'],
            'posts_per_page' => -1,
            'author' => $user->ID,
            'fields' => 'ids',
        ]);
        $listing_ids = array_map('absint', (array)$author_listings);

        $agent_meta_listings = get_posts([
            'post_type' => 'property',
            'post_status' => ['publish','pending','draft','expired','houzez_sold','disapproved','on_hold','private','future'],
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'fave_agents',
                    'value' => '"'.$user->ID.'"',
                    'compare' => 'LIKE',
                ],
                [
                    'key' => 'fave_property_agency',
                    'value' => '"'.$user->ID.'"',
                    'compare' => 'LIKE',
                ],
            ],
            'fields' => 'ids',
        ]);

        $listing_ids = array_merge($listing_ids, array_map('absint', (array)$agent_meta_listings));
        
        // Also check owner meta keys
        $owner_meta_keys = array('owner_id', 'property_owner', 'fave_property_owner', 'ms_property_owner_id');
        foreach ($owner_meta_keys as $key) {
            $owner_listings = get_posts([
                'post_type' => 'property',
                'post_status' => ['publish','pending','draft','expired','houzez_sold','disapproved','on_hold','private','future'],
                'posts_per_page' => -1,
                'meta_query' => [
                    'relation' => 'OR',
                    [
                        'key' => $key,
                        'value' => $user->ID,
                        'compare' => '=',
                        'type' => 'NUMERIC',
                    ],
                    [
                        'key' => $key,
                        'value' => '"'.$user->ID.'"',
                        'compare' => 'LIKE',
                    ],
                ],
                'fields' => 'ids',
            ]);
            $listing_ids = array_merge($listing_ids, array_map('absint', (array)$owner_listings));
        }
    }

    $listing_ids = array_values(array_unique(array_filter($listing_ids)));
    
    // Debug: log for troubleshooting
    error_log('Property status update check - User ID: ' . $user->ID . ', Prop ID: ' . $prop_id . ', Listing IDs: ' . implode(',', $listing_ids));
    
    if (!in_array($prop_id, $listing_ids, true)) {
        wp_send_json_error('permission_denied', 403);
    }

    // Validate term exists under property_status
    $term = get_term_by('slug', $term_slug, 'property_status');
    if (!$term || is_wp_error($term)) {
        wp_send_json_error('invalid_term', 400);
    }

    // A listing cannot be marked rented/sold without the matching party and contract.
    $status_key = sanitize_title($term_slug);
    $tenant_id  = ms_extract_first_meta_id(get_post_meta($prop_id, 'tenant_id', true));
    $buyer_id   = ms_extract_first_meta_id(get_post_meta($prop_id, 'buyer_id', true));
    $contract_type = sanitize_key((string) get_post_meta($prop_id, 'ms_property_contract_type', true));
    $is_rented = in_array($status_key, ['rented', 'leased', 'مؤجر'], true);
    $is_sold   = in_array($status_key, ['sold', 'مباع'], true);

    if ($is_rented && (!$tenant_id || $contract_type !== 'rent')) {
        wp_send_json_error(['code' => 'rent_requires_tenant_and_contract', 'message' => 'لا يمكن اعتماد حالة مؤجر قبل اختيار مستأجر ورفع عقد إيجار صالح.'], 422);
    }
    if ($is_sold && (!$buyer_id || $contract_type !== 'sale')) {
        wp_send_json_error(['code' => 'sale_requires_buyer_and_contract', 'message' => 'لا يمكن اعتماد حالة مباع قبل اختيار شاري ورفع عقد بيع صالح.'], 422);
    }

    $updated = wp_set_post_terms($prop_id, [$term_slug], 'property_status');
    if (is_wp_error($updated)) {
        wp_send_json_error('update_failed', 500);
    }

    // Notify agent (best-effort). Admin notification depends on your existing admin workflow.
    if (function_exists('ms_add_notification')) {
        $current_slug = $term_slug;
        ms_add_notification(
            intval($user->ID),
            'property_status_updated',
            sprintf('تم تحديث حالة العقار إلى: %s', esc_html($current_slug)),
            0,
            $prop_id
        );
    }

    wp_send_json_success(['updated' => true]);
});

add_action('wp_ajax_ms_agent_upload_property_contract', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error('not_logged_in', 401);
    }

    $user = wp_get_current_user();
    $can_agent = function_exists('ms_user_can_view_dashboard') && ms_user_can_view_dashboard($user->ID, 'agent');
    $can_owner = function_exists('ms_user_can_view_dashboard') && ms_user_can_view_dashboard($user->ID, 'owner');
    $is_owner_actor = function_exists('ms_user_has_role') && ms_user_has_role($user->ID, 'owner') && !$can_agent;
    if (!$can_agent && !$can_owner) {
        wp_send_json_error('forbidden', 403);
    }

    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'mostaager-ajax-nonce')) {
        wp_send_json_error('security_failed', 403);
    }

    $prop_id = isset($_POST['prop_id']) ? absint($_POST['prop_id']) : 0;
    $contract_type = isset($_POST['contract_type']) ? sanitize_text_field($_POST['contract_type']) : '';

    if ($is_owner_actor) {
        wp_send_json_error(['code' => 'owner_contract_forbidden', 'message' => 'لا يحق للمالك رفع عقد إيجار أو بيع من هذه اللوحة.'], 403);
    }

    if (!$prop_id || !in_array($contract_type, ['rent','sale'], true)) {
        wp_send_json_error('invalid_data', 400);
    }

    if (empty($_FILES) || empty($_FILES['contract_file']) || empty($_FILES['contract_file']['name'])) {
        wp_send_json_error('file_missing', 400);
    }

    // Ownership check (best-effort, align with ms_delete_agent_property)
    $listings = function_exists('ms_get_properties_by_agent') ? ms_get_properties_by_agent($user->ID) : [];
    if (empty($listings) && function_exists('ms_get_properties_by_owner')) {
        $listings = ms_get_properties_by_owner($user->ID);
    }

    $listing_ids = [];
    if (!empty($listings)) {
        foreach ((array)$listings as $listing) {
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
        $author_listings = get_posts([
            'post_type' => 'property',
            'post_status' => ['publish','pending','draft','expired','houzez_sold','disapproved','on_hold','private','future'],
            'posts_per_page' => -1,
            'author' => $user->ID,
            'fields' => 'ids',
        ]);
        $listing_ids = array_map('absint', (array)$author_listings);

        $agent_meta_listings = get_posts([
            'post_type' => 'property',
            'post_status' => ['publish','pending','draft','expired','houzez_sold','disapproved','on_hold','private','future'],
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'fave_agents',
                    'value' => '"'.$user->ID.'"',
                    'compare' => 'LIKE',
                ],
                [
                    'key' => 'fave_property_agency',
                    'value' => '"'.$user->ID.'"',
                    'compare' => 'LIKE',
                ],
            ],
            'fields' => 'ids',
        ]);

        $listing_ids = array_merge($listing_ids, array_map('absint', (array)$agent_meta_listings));
    }

    $listing_ids = array_values(array_unique(array_filter($listing_ids)));
    if (!in_array($prop_id, $listing_ids, true)) {
        wp_send_json_error('permission_denied', 403);
    }

    $file = $_FILES['contract_file'];

    // Basic validation
    $allowed_ext = ['pdf','jpg','jpeg','png','doc','docx'];
    $filename = isset($file['name']) ? (string)$file['name'] : '';
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext, true)) {
        wp_send_json_error('invalid_file_type', 400);
    }

    if (!empty($file['size']) && intval($file['size']) > 10 * 1024 * 1024) {
        wp_send_json_error('file_too_large', 400);
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $overrides = [
        'test_form' => false,
        'mimes' => [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
    ];

    $movefile = wp_handle_upload($file, $overrides);
    if (isset($movefile['error'])) {
        wp_send_json_error('upload_failed', 500);
    }

    $url = isset($movefile['url']) ? esc_url_raw($movefile['url']) : '';
    if (empty($url)) {
        wp_send_json_error('upload_url_missing', 500);
    }

    update_post_meta($prop_id, 'ms_property_contract_original_url', $url);
    update_post_meta($prop_id, 'ms_property_contract_signature_status', 'awaiting_signatures');
    update_post_meta($prop_id, 'ms_property_contract_uploaded_by', $user->ID);
    update_post_meta($prop_id, 'ms_property_contract_uploaded_at', current_time('mysql'));

    require_once ABSPATH . 'wp-admin/includes/media.php';

    $filetype = wp_check_filetype($movefile['file'], null);
    $attachment = [
        'post_mime_type' => $filetype['type'] ?? 'application/octet-stream',
        'post_title' => 'Contract for property #' . $prop_id . ' (' . $contract_type . ')',
        'post_content' => 'Contract uploaded on ' . current_time('mysql'),
        'post_status' => 'inherit',
    ];

    $attach_id = wp_insert_attachment($attachment, $movefile['file'], $prop_id);
    if (is_wp_error($attach_id) || empty($attach_id)) {
        // still store url as best-effort
        update_post_meta($prop_id, 'ms_property_contract_url', $url);
        update_post_meta($prop_id, 'ms_property_contract_type', $contract_type);
    } else {
        update_post_meta($prop_id, 'ms_property_contract_url', $url);
        update_post_meta($prop_id, 'ms_property_contract_type', $contract_type);
        update_post_meta($prop_id, 'ms_property_contract_id', $attach_id);
        $attach_data = wp_generate_attachment_metadata($attach_id, $movefile['file']);
        if (!is_wp_error($attach_data) && $attach_data) {
            wp_update_attachment_metadata($attach_id, $attach_data);
        }
    }

    if (function_exists('ms_add_notification')) {
        ms_add_notification(
            intval($user->ID),
            'property_contract_uploaded',
            'تم رفع عقد العقار بنجاح.',
            0,
            $prop_id
        );
    }

    wp_send_json_success([
        'url' => $url,
        'attachment_id' => $attach_id,
        'contract_type' => $contract_type,
        'signature_status' => 'awaiting_signatures',
        'message' => 'تم رفع العقد بنجاح، وهو الآن بانتظار توقيع الطرفين.'
    ]);
});



/**
 * Houzez submit-form bridge for Mostaager operational fields.
 * Uses the Houzez property post as the source of truth; Mostaager stores only
 * relationship metadata required for building/contract validation.
 */
add_action('save_post_property', function ($post_id, $post, $update) {
    static $ms_status_guard = false;
    if ($ms_status_guard) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id) || (defined('DOING_CRON') && DOING_CRON)) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $building_id = isset($_POST['building-id']) ? absint($_POST['building-id']) : absint(get_post_meta($post_id, 'building_id', true));
    $actor_id = get_current_user_id();
    $is_agent_actor = function_exists('ms_user_has_role') && ms_user_has_role($actor_id, 'agent');
    $is_owner_actor = function_exists('ms_user_has_role') && ms_user_has_role($actor_id, 'owner') && !$is_agent_actor;
    if ($is_owner_actor) {
        $submitted = '';
        foreach (array('listing_status', 'status', 'property_status', 'fave_property_status') as $status_key) {
            if (isset($_POST[$status_key])) { $submitted = sanitize_title(wp_unslash($_POST[$status_key])); break; }
        }
        if ($submitted && !in_array($submitted, array('for-rent', 'for-sale'), true)) {
            update_post_meta($post_id, 'ms_status_validation_notice', 'owner_status_restricted');
            $ms_status_guard = true;
            wp_update_post(array('ID' => $post_id, 'post_status' => 'draft'));
            $ms_status_guard = false;
            return;
        }
    }
    if ($is_agent_actor && function_exists('msfp_agent_can_manage_building') && (!$building_id || !msfp_agent_can_manage_building($actor_id, $building_id))) {
        update_post_meta($post_id, 'ms_building_validation_notice', 'agent_building_not_assigned');
        if (get_post_status($post_id) !== 'draft') {
            $ms_status_guard = true;
            wp_update_post(array('ID' => $post_id, 'post_status' => 'draft'));
            $ms_status_guard = false;
        }
        return;
    }
    if ($is_agent_actor && function_exists('ms_agent_has_active_subscription') && !ms_agent_has_active_subscription($actor_id)) {
        update_post_meta($post_id, 'ms_subscription_validation_notice', 'agent_subscription_required');
        if (get_post_status($post_id) !== 'draft') {
            $ms_status_guard = true;
            wp_update_post(array('ID' => $post_id, 'post_status' => 'draft'));
            $ms_status_guard = false;
        }
        return;
    }
    if ($building_id) {
        update_post_meta($post_id, 'building_id', $building_id);
        update_post_meta($post_id, 'fave_building-id', $building_id);
    }

    foreach (array(
        'ms_property_owner_id' => 'owner_id',
        'ms_property_tenant_id' => 'tenant_id',
        'ms_property_buyer_id' => 'buyer_id',
    ) as $request_key => $meta_key) {
        if (isset($_POST[$request_key])) {
            $value = absint($_POST[$request_key]);
            if ($value) update_post_meta($post_id, $meta_key, $value);
            else delete_post_meta($post_id, $meta_key);
        }
    }

    // Houzez may save status before this callback. Block invalid rented/sold states.
    $status_terms = wp_get_post_terms($post_id, 'property_status', array('fields' => 'slugs'));
    $status_key = !is_wp_error($status_terms) && !empty($status_terms) ? sanitize_title($status_terms[0]) : '';
    $tenant_id = ms_extract_first_meta_id(get_post_meta($post_id, 'tenant_id', true));
    $buyer_id = ms_extract_first_meta_id(get_post_meta($post_id, 'buyer_id', true));
    $contract_type = sanitize_key((string) get_post_meta($post_id, 'ms_property_contract_type', true));
    $invalid_rent = in_array($status_key, array('rented','rent','for-rent','leased'), true) && (!$tenant_id || $contract_type !== 'rent');
    $invalid_sale = in_array($status_key, array('sold','sale','for-sale'), true) && (!$buyer_id || $contract_type !== 'sale');
    if ($invalid_rent || $invalid_sale) {
        $fallback = $invalid_sale ? 'for-sale' : 'for-rent';
        if (term_exists($fallback, 'property_status')) {
            wp_set_post_terms($post_id, array($fallback), 'property_status', false);
        }
        update_post_meta($post_id, 'ms_status_validation_notice', $invalid_sale ? 'sale_requires_buyer_and_contract' : 'rent_requires_tenant_and_contract');
    }

    if ($is_agent_actor && $building_id && function_exists('msfp_get_agent_building_ids')) {
        global $wpdb;
        $unit_table = $wpdb->prefix . 'ms_units';
        $owner_id = absint(get_post_meta($post_id, 'owner_id', true));
        $tenant_id = absint(get_post_meta($post_id, 'tenant_id', true));
        $buyer_id = absint(get_post_meta($post_id, 'buyer_id', true));
        $existing_unit = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$unit_table} WHERE property_id = %d LIMIT 1", $post_id));
        $unit_data = array('building_id' => $building_id, 'owner_id' => $owner_id, 'tenant_id' => $tenant_id, 'buyer_id' => $buyer_id, 'agent_id' => $actor_id, 'property_id' => $post_id, 'updated_at' => current_time('mysql'));
        if ($existing_unit) {
            $wpdb->update($unit_table, $unit_data, array('id' => absint($existing_unit->id)), array('%d','%d','%d','%d','%d','%d','%s'), array('%d'));
            update_post_meta($post_id, 'ms_unit_id', absint($existing_unit->id));
        } else {
            $unit_data['created_at'] = current_time('mysql');
            $wpdb->insert($unit_table, $unit_data, array('%d','%d','%d','%d','%d','%d','%s','%s'));
            if ($wpdb->insert_id) update_post_meta($post_id, 'ms_unit_id', absint($wpdb->insert_id));
        }
    }
}, 20, 3);

add_action('wp_footer', function () {
    if (!is_user_logged_in() || (!is_page_template('template/user_dashboard_submit.php') && !is_page(16053))) return;
    global $wpdb;
    $user_id = get_current_user_id();
    $buildings = function_exists('msfp_get_agent_buildings') ? msfp_get_agent_buildings($user_id) : array();
    $allowed_ids = array_values(array_filter(array_map(function ($building) { return absint($building->id ?? 0); }, (array) $buildings)));
    $units = array();
    $unit_table = $wpdb->prefix . 'ms_units';
    if (!empty($allowed_ids) && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($unit_table)))) {
        $placeholders = implode(',', array_fill(0, count($allowed_ids), '%d'));
        $units = $wpdb->get_results($wpdb->prepare("SELECT building_id,owner_id,tenant_id,buyer_id FROM {$unit_table} WHERE building_id IN ({$placeholders}) AND (owner_id>0 OR tenant_id>0 OR buyer_id>0) ORDER BY building_id,id ASC", $allowed_ids));
    }
    $people = array();
    foreach ((array) $units as $unit) {
        foreach (array('owner_id' => 'owner', 'tenant_id' => 'tenant', 'buyer_id' => 'buyer') as $key => $type) {
            $uid = absint($unit->$key ?? 0);
            if (!$uid) continue;
            $u = get_userdata($uid);
            if (!$u) continue;
            $people[] = array('building_id' => absint($unit->building_id), 'user_id' => $uid, 'type' => $type, 'label' => $u->display_name ?: $u->user_login);
        }
    }
    echo '<script id="ms-houzez-agent-fields">window.MostaagerHouzezFields=' . wp_json_encode(array('buildings' => $buildings, 'people' => $people), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';</script>';
    echo '<script>(function(){function ready(){var f=document.querySelector("#submit_property_form");var d=window.MostaagerHouzezFields;if(!f||!d||f.dataset.msFieldsReady)return;f.dataset.msFieldsReady="1";var b=f.querySelector("[name=\"building-id\"]");if(b){var s=document.createElement("select");s.name="building-id";s.className=b.className||"form-control";s.required=true;s.innerHTML="<option value=\"\">اختر المبنى</option>"+d.buildings.map(function(x){return "<option value=\""+x.id+"\">"+x.title+"</option>"}).join("");b.replaceWith(s);b=s;}var box=document.createElement("div");box.className="ms-houzez-relations";box.innerHTML="<label>المالك *</label><select name=\"ms_property_owner_id\" required><option value=\"\">اختر المالك</option></select><label>المستأجر</label><select name=\"ms_property_tenant_id\"><option value=\"\">اختر المستأجر</option></select><label>الشاري</label><select name=\"ms_property_buyer_id\"><option value=\"\">اختر الشاري</option></select>";(b.closest(".form-group,.form-field,li")||b.parentElement).after(box);var sels=box.querySelectorAll("select");function fill(){var id=String(b.value||"");["owner","tenant"].forEach(function(t,i){var sel=sels[i];sel.innerHTML="<option value=\"\">"+(t==="owner"?"اختر المالك":"اختر المستأجر")+"</option>";d.people.filter(function(p){return String(p.building_id)===id&&p.type===t}).forEach(function(p){sel.insertAdjacentHTML("beforeend","<option value=\""+p.user_id+"\">"+p.label+"</option>")});});var buyer=sels[2];buyer.innerHTML="<option value=\"\">اختر الشاري</option>";d.people.filter(function(p){return String(p.building_id)===id&&p.type===\'buyer\'}).forEach(function(p){buyer.insertAdjacentHTML("beforeend","<option value=\""+p.user_id+"\">"+p.label+"</option>")});}b.addEventListener("change",fill);fill();}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",ready);else ready();})();</script>';
}, 30);

