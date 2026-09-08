<?php
if (!defined('ABSPATH')) {
    exit;
}

function ms_inline_user_is_owner($user_id = 0) {
    $user = get_userdata(absint($user_id ?: get_current_user_id()));
    if (!$user) return false;
    $roles = array_map('sanitize_key', (array) $user->roles);
    return (bool) array_intersect(array('owner','property_owner','houzez_owner','houzez-owner','real_estate_owner'), $roles);
}

function ms_inline_user_is_agent($user_id = 0) {
    $user = get_userdata(absint($user_id ?: get_current_user_id()));
    if (!$user) return false;
    $roles = array_map('sanitize_key', (array) $user->roles);
    return (bool) array_intersect(array('agent','houzez_agent','houzez-agent'), $roles);
}

/**
 * Inline property creation for owner/agent dashboards.
 * It intentionally uses the Houzez property CPT and common Houzez meta keys,
 * while keeping the workflow inside the Mostaager dashboard tab.
 */
function ms_inline_property_can_access($user_id, $post_id = 0)
{
    if (current_user_can('manage_options')) {
        return true;
    }

    $can_agent = ms_inline_user_is_agent($user_id);
    $can_owner = ms_inline_user_is_owner($user_id);
    if (!$can_agent && !$can_owner) {
        return false;
    }

    if (!$post_id) {
        return true;
    }

    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'property') {
        return false;
    }

    if ((int) $post->post_author === (int) $user_id) {
        return true;
    }

    $keys = array('owner_id', 'property_owner', 'fave_property_owner', 'ms_property_owner_id', 'agent_id', 'fave_agents');
    foreach ($keys as $key) {
        $value = get_post_meta($post_id, $key, true);
        if (is_array($value) && in_array((string) $user_id, array_map('strval', $value), true)) {
            return true;
        }
        if ((string) $value === (string) $user_id || strpos((string) $value, '"' . $user_id . '"') !== false) {
            return true;
        }
    }

    return false;
}

function ms_inline_property_building_options($user_id)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ms_buildings';
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    if (!$exists) {
        return array();
    }

    if (current_user_can('manage_options')) {
        return $wpdb->get_results("SELECT id, title FROM {$table} ORDER BY title ASC");
    }

    // An agent may list a property in any available building. Ownership of
    // the listing is still enforced by ms_inline_property_can_access() and the
    // save endpoint; the building selector must not be restricted to manager
    // or owner relationships.
    if (ms_inline_user_is_agent($user_id)) {
        return $wpdb->get_results("SELECT id, title FROM {$table} ORDER BY title ASC");
    }

    // Owners can add a property to any building where they own a unit. They
    // may also have an existing Houzez property linked through building_id.
    $unit_table = $wpdb->prefix . 'ms_units';
    $unit_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $unit_table));
    $owner_property_building_ids = array();
    $owner_property_ids = get_posts(array(
        'post_type' => 'property',
        'post_status' => array('publish', 'pending', 'draft', 'private', 'future'),
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => array(
            'relation' => 'OR',
            array('key' => 'owner_id', 'value' => $user_id, 'compare' => '='),
            array('key' => 'property_owner', 'value' => $user_id, 'compare' => '='),
            array('key' => 'ms_property_owner_id', 'value' => $user_id, 'compare' => '='),
            array('key' => 'building_id', 'compare' => 'EXISTS'),
        ),
    ));
    foreach ((array) $owner_property_ids as $property_id) {
        $property_owner = ms_extract_first_meta_id(get_post_meta($property_id, 'owner_id', true));
        $property_owner = $property_owner ?: ms_extract_first_meta_id(get_post_meta($property_id, 'property_owner', true));
        $property_owner = $property_owner ?: ms_extract_first_meta_id(get_post_meta($property_id, 'ms_property_owner_id', true));
        if ($property_owner === (int) $user_id) {
            $building_id = absint(get_post_meta($property_id, 'building_id', true));
            if ($building_id) {
                $owner_property_building_ids[] = $building_id;
            }
        }
    }

    $where = array('manager_id = %d');
    $params = array($user_id);
    if ($unit_exists) {
        $where[] = "id IN (SELECT building_id FROM {$unit_table} WHERE owner_id = %d)";
        $params[] = $user_id;
    }
    $owner_property_building_ids = array_values(array_unique(array_filter(array_map('absint', $owner_property_building_ids))));
    if ($owner_property_building_ids) {
        $where[] = 'id IN (' . implode(',', $owner_property_building_ids) . ')';
    }

    $sql = "SELECT id, title FROM {$table} WHERE (" . implode(' OR ', $where) . ') ORDER BY title ASC';
    return $wpdb->get_results($wpdb->prepare($sql, $params));
}

/** Return people already related to units in the selected buildings. */
function ms_inline_property_people_options($building_ids)
{
    global $wpdb;
    $building_ids = array_values(array_unique(array_filter(array_map('absint', (array) $building_ids))));
    if (!$building_ids) return array();
    $placeholders = implode(',', array_fill(0, count($building_ids), '%d'));
    $unit_table = $wpdb->prefix . 'ms_units';
    $tenant_table = $wpdb->prefix . 'ms_unit_tenants';
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT building_id, owner_id AS user_id, 'owner' AS person_type FROM {$unit_table} WHERE building_id IN ({$placeholders}) AND owner_id > 0
         UNION ALL
         SELECT building_id, tenant_id AS user_id, 'tenant' AS person_type FROM {$unit_table} WHERE building_id IN ({$placeholders}) AND tenant_id > 0
         UNION ALL
         SELECT building_id, tenant_id AS user_id, 'tenant' AS person_type FROM {$tenant_table} WHERE building_id IN ({$placeholders}) AND status = 'active'",
        array_merge($building_ids, $building_ids, $building_ids)
    ));
    $items = array();
    foreach ((array) $rows as $row) {
        $uid = absint($row->user_id ?? 0);
        $bid = absint($row->building_id ?? 0);
        $user = $uid ? get_userdata($uid) : null;
        if (!$user || !$bid) continue;
        $key = $bid . ':' . $uid . ':' . sanitize_key($row->person_type ?? 'person');
        $items[$key] = array('building_id' => $bid, 'user_id' => $uid, 'person_type' => sanitize_key($row->person_type ?? 'person'), 'label' => $user->display_name ?: $user->user_login);
    }
    return array_values($items);
}

function ms_render_inline_property_form($args = array())
{
    if (!is_user_logged_in()) {
        return '<p class="ms-inline-property-message">يرجى تسجيل الدخول لإضافة عقار.</p>';
    }

    $user_id = get_current_user_id();
    if (!ms_inline_property_can_access($user_id)) {
        return '<p class="ms-inline-property-message">ليس لديك صلاحية إضافة عقار من هذه اللوحة.</p>';
    }

    $is_agent = ms_inline_user_is_agent($user_id);
    $is_owner = ms_inline_user_is_owner($user_id);
    if ($is_owner) { $is_agent = false; }
    if ($is_agent && function_exists('ms_agent_has_active_subscription') && !ms_agent_has_active_subscription($user_id)) {
        $subscriptions_url = esc_url(add_query_arg(array(), home_url('/agent-dashboard/#subscriptions')));
        return '<div class="ms-inline-property-message ms-subscription-required" role="alert"><strong>لا يمكنك إضافة شقة جديدة أو عقار جديد حتى تكون مشتركًا بأحد الباقات.</strong><br><a class="ms-action-button ms-action-button-primary" href="' . $subscriptions_url . '">عرض الباقات والاشتراك الآن</a></div>';
    }

    $buildings = ms_inline_property_building_options($user_id);
    $building_ids = array_map(function ($building) { return absint($building->id); }, (array) $buildings);
    $people = ms_inline_property_people_options($building_ids);
    $nonce = wp_create_nonce('ms_inline_property');
    $form_id = 'ms-inline-property-form-' . wp_rand(100, 99999);

    ob_start();
    ?>
    <form id="<?php echo esc_attr($form_id); ?>" class="ms-inline-property-form" data-user-id="<?php echo esc_attr($user_id); ?>" novalidate>
        <input type="hidden" name="action" value="ms_inline_save_property">
        <input type="hidden" name="security" value="<?php echo esc_attr($nonce); ?>">
        <input type="hidden" name="property_id" value="0">
        <script type="application/json" data-building-people><?php echo wp_json_encode($people, JSON_UNESCAPED_UNICODE); ?></script>
        <div class="ms-inline-property-progress" role="progressbar" aria-valuemin="1" aria-valuemax="3" aria-valuenow="1"><span class="is-active">1. الأساسيات</span><span>2. التفاصيل</span><span>3. المراجعة</span></div>
        <div class="ms-inline-property-step is-active" data-step="1">
            <div class="ms-inline-property-grid">
                <label><span>اسم العقار <b>*</b></span><input required name="title" type="text" maxlength="191" autocomplete="off"></label>
                <label><span>المبنى <b>*</b></span><select required name="building_id"><option value="0">اختر المبنى</option><?php foreach ($buildings as $building) : ?><option value="<?php echo absint($building->id); ?>"><?php echo esc_html($building->title); ?></option><?php endforeach; ?></select></label>
                <label><span>رقم الوحدة</span><input name="unit_number" type="text" maxlength="60"></label>
                <label><span>نوع العقار</span><input name="property_type" type="text" maxlength="80" placeholder="شقة، فيلا، مكتب"></label>
                <label><span>حالة العرض <b>*</b></span><?php if ($is_owner) : ?><select required name="listing_status"><option value="for-rent" selected>للإيجار</option><option value="for-sale">للبيع</option></select><?php else : ?><select required name="listing_status"><option value="available">متاح</option><option value="for-rent">للإيجار</option><option value="for-sale">للبيع</option><option value="rented">مؤجر</option><option value="sold">مباع</option></select><?php endif; ?></label>
                <?php if ($is_owner) : ?><input type="hidden" name="owner_id" value="<?php echo esc_attr($user_id); ?>"><label><span>المالك</span><input value="<?php echo esc_attr(wp_get_current_user()->display_name ?: wp_get_current_user()->user_login); ?>" readonly></label><label><span>الوسيط المتابع <b>*</b></span><select required name="agent_id"><option value="0">اختر الوسيط العقاري</option><?php foreach ($agents as $agent) : ?><option value="<?php echo absint($agent['user_id']); ?>"><?php echo esc_html($agent['label']); ?></option><?php endforeach; ?></select></label><?php else : ?><label><span>المالك</span><select name="owner_id" data-building-person="owner"><option value="0">اختر المالك بعد تحديد المبنى</option></select></label><label><span>المستأجر</span><select name="tenant_id" data-building-person="tenant"><option value="0">اختر المستأجر بعد تحديد المبنى</option></select></label><label><span>المشتري</span><select name="buyer_id" data-building-person="buyer"><option value="0">اختر المشتري بعد تحديد المبنى</option></select></label><?php endif; ?>
            </div>
            <label class="ms-inline-property-full"><span>وصف مختصر</span><textarea name="description" rows="5" maxlength="5000" placeholder="اكتب وصفًا واضحًا للعقار..."></textarea></label>
            <div class="ms-inline-property-actions"><button type="button" class="ms-inline-next ms-action-button ms-action-button-primary">التالي</button></div>
        </div>
        <div class="ms-inline-property-step" data-step="2">
            <div class="ms-inline-property-grid">
                <label><span>السعر</span><input name="price" type="number" min="0" step="0.01" inputmode="decimal"></label>
                <label><span>المساحة</span><input name="size" type="number" min="0" step="0.01" inputmode="decimal"></label>
                <label><span>غرف النوم</span><input name="bedrooms" type="number" min="0" step="1"></label>
                <label><span>الحمامات</span><input name="bathrooms" type="number" min="0" step="1"></label>
                <label class="ms-inline-property-full"><span>العنوان</span><input name="address" type="text" maxlength="255"></label>
            </div>
            <div class="ms-inline-property-actions"><button type="button" class="ms-inline-prev ms-action-button">السابق</button><button type="button" class="ms-inline-next ms-action-button ms-action-button-primary">التالي</button></div>
        </div>
        <div class="ms-inline-property-step" data-step="3">
            <div class="ms-inline-property-review" data-review></div>
            <p class="ms-inline-property-status" data-status aria-live="polite"></p>
            <div class="ms-inline-property-actions"><button type="button" class="ms-inline-prev ms-action-button">السابق</button><button type="button" class="ms-inline-draft ms-action-button">حفظ كمسودة</button><button type="submit" class="ms-action-button ms-action-button-primary">إرسال للمراجعة</button></div>
        </div>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('ms_inline_add_property', 'ms_render_inline_property_form');

add_action('wp_ajax_ms_inline_save_property', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'يجب تسجيل الدخول.'), 401);
    }

    $user_id = get_current_user_id();
    if (!ms_inline_property_can_access($user_id)) {
        wp_send_json_error(array('message' => 'ليس لديك صلاحية.'), 403);
    }
    if (ms_inline_user_is_agent($user_id) && function_exists('ms_agent_has_active_subscription') && !ms_agent_has_active_subscription($user_id)) {
        wp_send_json_error(array('message' => 'لا يمكنك إضافة شقة جديدة أو عقار جديد حتى تكون مشتركًا بأحد الباقات.'), 403);
    }
    check_ajax_referer('ms_inline_property', 'security');

    $property_id = isset($_POST['property_id']) ? absint($_POST['property_id']) : 0;
    if ($property_id && !ms_inline_property_can_access($user_id, $property_id)) {
        wp_send_json_error(array('message' => 'لا يمكنك تعديل هذا العقار.'), 403);
    }

    $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
    $description = isset($_POST['description']) ? wp_kses_post(wp_unslash($_POST['description'])) : '';
    $building_id = isset($_POST['building_id']) ? absint($_POST['building_id']) : 0;
    $owner_id = isset($_POST['owner_id']) ? absint($_POST['owner_id']) : 0;
    $tenant_id = isset($_POST['tenant_id']) ? absint($_POST['tenant_id']) : 0;
    $buyer_id = isset($_POST['buyer_id']) ? absint($_POST['buyer_id']) : 0;
    $listing_status = isset($_POST['listing_status']) ? sanitize_key(wp_unslash($_POST['listing_status'])) : 'available';
    if ($title === '') {
        wp_send_json_error(array('message' => 'اسم العقار مطلوب.'), 422);
    }
    if (!$building_id) {
        wp_send_json_error(array('message' => 'يجب اختيار المبنى.'), 422);
    }
    if (!$owner_id) {
        wp_send_json_error(array('message' => 'يجب اختيار مالك العقار.'), 422);
    }
    $valid_statuses = array('available', 'for-rent', 'for-sale', 'rented', 'sold');
    if (!in_array($listing_status, $valid_statuses, true)) {
        wp_send_json_error(array('message' => 'حالة العقار غير صالحة.'), 422);
    }
    global $wpdb;
    $unit_table = $wpdb->prefix . 'ms_units';
    $building_exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}ms_buildings WHERE id = %d LIMIT 1", $building_id));
    if (!$building_exists) {
        wp_send_json_error(array('message' => 'المبنى المختار غير موجود.'), 422);
    }
    if ($owner_id && !$wpdb->get_var($wpdb->prepare("SELECT id FROM {$unit_table} WHERE building_id = %d AND owner_id = %d LIMIT 1", $building_id, $owner_id))) {
        wp_send_json_error(array('message' => 'المالك يجب أن يكون مرتبطًا بالمبنى المختار.'), 422);
    }
    if ($tenant_id && !$wpdb->get_var($wpdb->prepare("SELECT id FROM {$unit_table} WHERE building_id = %d AND tenant_id = %d LIMIT 1", $building_id, $tenant_id))) {
        wp_send_json_error(array('message' => 'المستأجر يجب أن يكون مرتبطًا بالمبنى المختار.'), 422);
    }
    if ($buyer_id && !$wpdb->get_var($wpdb->prepare("SELECT id FROM {$unit_table} WHERE building_id = %d AND (owner_id = %d OR tenant_id = %d) LIMIT 1", $building_id, $buyer_id, $buyer_id))) {
        wp_send_json_error(array('message' => 'المشتري يجب أن يكون مرتبطًا بالمبنى المختار.'), 422);
    }
    if (in_array($listing_status, array('rented', 'sold'), true)) {
        $required_person = $listing_status === 'rented' ? $tenant_id : $buyer_id;
        $required_contract = $listing_status === 'rented' ? 'ms_rent_contract_url' : 'ms_sale_contract_url';
        if (!$required_person) {
            wp_send_json_error(array('message' => $listing_status === 'rented' ? 'لا يمكن جعل العقار مؤجرًا دون اختيار مستأجر.' : 'لا يمكن جعل العقار مباعًا دون اختيار مشترٍ.'), 422);
        }
        if ($property_id && !get_post_meta($property_id, $required_contract, true)) {
            wp_send_json_error(array('message' => $listing_status === 'rented' ? 'ارفع عقد الإيجار أولًا قبل اعتماد حالة مؤجر.' : 'ارفع عقد البيع أولًا قبل اعتماد حالة مباع.'), 422);
        }
    }

    $is_submit = isset($_POST['submit_mode']) && $_POST['submit_mode'] === 'submit';
    $postarr = array(
        'post_type' => 'property',
        'post_title' => $title,
        'post_content' => $description,
        'post_author' => $user_id,
        'post_status' => $is_submit && current_user_can('publish_posts') ? 'publish' : ($is_submit ? 'pending' : 'draft'),
    );
    if ($property_id) {
        $postarr['ID'] = $property_id;
    }

    $saved_id = $property_id ? wp_update_post(wp_slash($postarr), true) : wp_insert_post(wp_slash($postarr), true);
    if (is_wp_error($saved_id) || !$saved_id) {
        wp_send_json_error(array('message' => 'تعذر حفظ العقار. راجع سجل الأخطاء.'), 500);
    }
    $saved_id = absint($saved_id);

    $meta = array(
        'building_id' => $building_id,
        'ms_building_id' => $building_id,
        'owner_id' => $owner_id,
        'property_owner' => $owner_id,
        'tenant_id' => $tenant_id,
        'buyer_id' => $buyer_id,
        'ms_listing_status' => $listing_status,
        'unit_number' => isset($_POST['unit_number']) ? sanitize_text_field(wp_unslash($_POST['unit_number'])) : '',
        'property_type' => isset($_POST['property_type']) ? sanitize_text_field(wp_unslash($_POST['property_type'])) : '',
        'fave_property_price' => isset($_POST['price']) ? (float) $_POST['price'] : 0,
        'fave_property_size' => isset($_POST['size']) ? (float) $_POST['size'] : 0,
        'fave_property_bedrooms' => isset($_POST['bedrooms']) ? absint($_POST['bedrooms']) : 0,
        'fave_property_bathrooms' => isset($_POST['bathrooms']) ? absint($_POST['bathrooms']) : 0,
        'fave_property_address' => isset($_POST['address']) ? sanitize_text_field(wp_unslash($_POST['address'])) : '',
    );
    foreach ($meta as $key => $value) {
        update_post_meta($saved_id, $key, $value);
    }
    // Keep the canonical Mostaager unit synchronized with the Houzez property.
    // This is required so rent and building invoices resolve to the correct payer.
    $unit_number_value = isset($_POST['unit_number']) ? sanitize_text_field(wp_unslash($_POST['unit_number'])) : '';
    $existing_unit_id = absint(get_post_meta($saved_id, 'ms_unit_id', true));
    if (!$existing_unit_id && $building_id && $owner_id) {
        $existing_unit_id = absint($wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$unit_table} WHERE building_id = %d AND owner_id = %d AND unit_number = %s LIMIT 1",
            $building_id, $owner_id, $unit_number_value
        )));
    }
    $unit_status = in_array($listing_status, array('rented', 'sold'), true) ? $listing_status : 'available';
    $existing_tenant_id = $existing_unit_id ? absint($wpdb->get_var($wpdb->prepare("SELECT tenant_id FROM {$unit_table} WHERE id = %d LIMIT 1", $existing_unit_id))) : 0;
    $unit_data = array(
        'building_id' => $building_id,
        'owner_id' => $owner_id,
        'tenant_id' => $tenant_id ?: $existing_tenant_id,
        'agent_id' => $agent_id ?: $user_id,
        'unit_number' => $unit_number_value,
        'status' => $unit_status,
        'updated_at' => current_time('mysql'),
    );
    if ($existing_unit_id) {
        $wpdb->update($unit_table, $unit_data, array('id' => $existing_unit_id));
    } else {
        $unit_data['created_at'] = current_time('mysql');
        $wpdb->insert($unit_table, $unit_data);
        $existing_unit_id = absint($wpdb->insert_id);
    }
    if ($existing_unit_id) {
        update_post_meta($saved_id, 'ms_unit_id', $existing_unit_id);
        update_post_meta($saved_id, 'unit_id', $existing_unit_id);
    }

    // Houzez remains the primary property record; these keys are compatibility aliases.
    update_post_meta($saved_id, 'fave_agents', $user_id);
    update_post_meta($saved_id, 'ms_mostaager_property_id', $saved_id);

    if (taxonomy_exists('property_status')) {
        $status_term = get_term_by('slug', $listing_status, 'property_status');
        if ($status_term && !is_wp_error($status_term)) {
            wp_set_post_terms($saved_id, array($status_term->term_id), 'property_status', false);
        }
    }

    if (taxonomy_exists('property_type') && !empty($meta['property_type'])) {
        wp_set_object_terms($saved_id, sanitize_title($meta['property_type']), 'property_type');
    }

    wp_send_json_success(array(
        'property_id' => $saved_id,
        'status' => get_post_status($saved_id),
        'message' => $is_submit ? 'تم إرسال العقار للمراجعة بنجاح.' : 'تم حفظ المسودة تلقائيًا.',
        'permalink' => get_permalink($saved_id),
    ));
});
