<?php
/**
 * Building Manager Metabox
 *
 * يضيف حقل "مدير المبنى" على post_type=building في WordPress Admin.
 * عند الحفظ يتم تحديث manager_id في جدول ms_buildings تلقائياً.
 *
 * @package Mostaager Facility PRO
 */

if (!defined('ABSPATH')) {
    exit;
}

// ─────────────────────────────────────────────────────────────
// 1. تسجيل الـ Metabox على post_type=building
// ─────────────────────────────────────────────────────────────
add_action('add_meta_boxes', 'ms_register_building_manager_metabox');

function ms_register_building_manager_metabox()
{
    add_meta_box(
        'ms_building_manager_box',
        '🏢 مدير المبنى',
        'ms_render_building_manager_metabox',
        'building',
        'side',
        'high'
    );
}

// ─────────────────────────────────────────────────────────────
// 2. عرض الـ Metabox
// ─────────────────────────────────────────────────────────────
function ms_render_building_manager_metabox($post)
{
    global $wpdb;

    $current_manager_id = intval(get_post_meta($post->ID, 'ms_building_manager_id', true));

    if (!$current_manager_id) {
        $building_id_meta = intval(get_post_meta($post->ID, 'building_id', true));
        if ($building_id_meta) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT manager_id FROM {$wpdb->prefix}ms_buildings WHERE id = %d LIMIT 1",
                $building_id_meta
            ));
            if ($row && $row->manager_id) {
                $current_manager_id = intval($row->manager_id);
            }
        }
        if (!$current_manager_id) {
            $row2 = $wpdb->get_row($wpdb->prepare(
                "SELECT manager_id FROM {$wpdb->prefix}ms_buildings WHERE wp_post_id = %d LIMIT 1",
                $post->ID
            ));
            if ($row2 && $row2->manager_id) {
                $current_manager_id = intval($row2->manager_id);
            }
        }
    }

    $managers = get_users(array(
        'role__in' => array('building_manager'),
        'orderby'  => 'display_name',
        'order'    => 'ASC',
        'number'   => -1,
        'fields'   => array('ID', 'display_name', 'user_login'),
    ));

    wp_nonce_field('ms_building_manager_nonce', 'ms_building_manager_nonce');
    ?>
    <style>
        #ms_building_manager_box select { width:100%; padding:6px 8px; border:1px solid #ccd0d4; border-radius:4px; font-size:13px; background:#fff; margin-bottom:6px; }
        #ms_building_manager_box .ms-current { padding:6px 10px; background:#f0f9eb; border-left:3px solid #46b450; border-radius:3px; font-size:12px; line-height:1.6; }
        #ms_building_manager_box .ms-warn { color:#d63638; font-size:12px; line-height:1.6; }
    </style>

    <p style="font-size:12px;color:#666;margin:0 0 6px;">اختر مدير المبنى من قائمة مديري المباني:</p>

    <?php if (empty($managers)) : ?>
        <p class="ms-warn">
            لا يوجد مستخدمون بدور <strong>Building Manager</strong>.<br>
            <a href="<?php echo esc_url(admin_url('user-new.php')); ?>">أضف مستخدماً الآن</a>
            وعيّن له دور <em>مدير مبنى</em>.
        </p>
    <?php else : ?>
        <select name="ms_building_manager_id" id="ms_building_manager_id">
            <option value="0">-- بدون مدير --</option>
            <?php foreach ($managers as $manager) : ?>
                <option value="<?php echo intval($manager->ID); ?>"
                    <?php selected($current_manager_id, intval($manager->ID)); ?>>
                    <?php echo esc_html($manager->display_name . ' (@' . $manager->user_login . ')'); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <?php if ($current_manager_id) :
            $mgr = get_userdata($current_manager_id); ?>
            <?php if ($mgr) : ?>
                <div class="ms-current">
                    ✅ <strong><?php echo esc_html($mgr->display_name); ?></strong><br>
                    <small>@<?php echo esc_html($mgr->user_login); ?> &mdash; <?php echo esc_html($mgr->user_email); ?></small>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
    <?php
}

// ─────────────────────────────────────────────────────────────
// 3. حفظ الاختيار
// ─────────────────────────────────────────────────────────────
add_action('save_post_building', 'ms_save_building_manager', 20, 2);

function ms_save_building_manager($post_id, $post)
{
    if (
        !isset($_POST['ms_building_manager_nonce']) ||
        !wp_verify_nonce(sanitize_text_field($_POST['ms_building_manager_nonce']), 'ms_building_manager_nonce')
    ) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $manager_id = isset($_POST['ms_building_manager_id']) ? intval($_POST['ms_building_manager_id']) : 0;
    if ($manager_id === 0) {
        delete_post_meta($post_id, 'ms_building_manager_id');
    } else {
        update_post_meta($post_id, 'ms_building_manager_id', $manager_id);
    }
    ms_sync_building_manager_to_db($post_id, $manager_id);
}

// ─────────────────────────────────────────────────────────────
// 4. مزامنة manager_id مع ms_buildings
// ─────────────────────────────────────────────────────────────
function ms_sync_building_manager_to_db($wp_post_id, $manager_id)
{
    global $wpdb;
    $wp_post_id = absint($wp_post_id);
    $manager_id = absint($manager_id);
    if (!$wp_post_id) return false;

    $tbl = $wpdb->prefix . 'ms_buildings';

    $building_row = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$tbl} WHERE wp_post_id = %d LIMIT 1",
        $wp_post_id
    ));

    if (!$building_row) {
        $meta_bid = intval(get_post_meta($wp_post_id, 'building_id', true));
        if ($meta_bid) {
            $building_row = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$tbl} WHERE id = %d LIMIT 1",
                $meta_bid
            ));
        }
    }

    if ($building_row) {
        return $wpdb->update(
            $tbl,
            array('manager_id' => $manager_id),
            array('id' => intval($building_row->id)),
            array('%d'),
            array('%d')
        ) !== false;
    }

    if ($manager_id <= 0) {
        return false;
    }

    $title = get_the_title($wp_post_id);
    if (empty($title)) {
        $post = get_post($wp_post_id);
        $title = $post ? $post->post_title : 'Building #' . $wp_post_id;
    }

    $inserted = $wpdb->insert(
        $tbl,
        array(
            'title' => $title,
            'manager_id' => $manager_id,
            'wp_post_id' => $wp_post_id,
        ),
        array('%s', '%d', '%d')
    );

    return $inserted !== false;
}

// ─────────────────────────────────────────────────────────────
// 5. Form Handler: تعيين مدير من صفحة Mostaager > Buildings
// ─────────────────────────────────────────────────────────────
add_action('admin_post_ms_assign_building_manager', 'ms_handle_assign_building_manager');

function ms_handle_assign_building_manager()
{
    if (!current_user_can('manage_options')) {
        wp_die('Forbidden', '', array('response' => 403));
    }
    if (
        !isset($_POST['_wpnonce']) ||
        !wp_verify_nonce(sanitize_text_field($_POST['_wpnonce']), 'ms_assign_building_manager')
    ) {
        wp_die('Invalid nonce', '', array('response' => 400));
    }

    global $wpdb;

    $ms_building_id = isset($_POST['ms_building_id']) ? intval($_POST['ms_building_id']) : 0;
    $manager_id     = isset($_POST['manager_id'])     ? intval($_POST['manager_id'])     : 0;

    if (!$ms_building_id) {
        wp_die('Invalid building id', '', array('response' => 400));
    }

    $tbl = $wpdb->prefix . 'ms_buildings';

    $updated = $wpdb->update(
        $tbl,
        array('manager_id' => $manager_id),
        array('id' => $ms_building_id),
        array('%d'),
        array('%d')
    );

    $wp_post_id = intval($wpdb->get_var($wpdb->prepare(
        "SELECT wp_post_id FROM {$tbl} WHERE id = %d LIMIT 1",
        $ms_building_id
    )));
    if ($wp_post_id) {
        update_post_meta($wp_post_id, 'ms_building_manager_id', $manager_id);
    }

    $msg = ($updated !== false) ? 'manager_assigned' : 'manager_error';
    $referer = wp_get_referer() ?: admin_url('admin.php?page=mostaager-buildings');
    wp_safe_redirect(add_query_arg('ms_building_message', $msg, $referer));
    exit;
}

// ─────────────────────────────────────────────────────────────
// 6. دوال مساعدة عامة
// ─────────────────────────────────────────────────────────────

if (!function_exists('ms_get_building_manager')) {
    /**
     * جلب WP_User لمدير مبنى محدد.
     * @param int $ms_building_id رقم المبنى في ms_buildings
     * @return WP_User|null
     */
    function ms_get_building_manager($ms_building_id)
    {
        global $wpdb;
        $ms_building_id = absint($ms_building_id);
        if (!$ms_building_id) return null;

        $manager_id = intval($wpdb->get_var($wpdb->prepare(
            "SELECT manager_id FROM {$wpdb->prefix}ms_buildings WHERE id = %d LIMIT 1",
            $ms_building_id
        )));

        return $manager_id ? get_userdata($manager_id) : null;
    }
}

// ─────────────────────────────────────────────────────────────
// 7. Metabox لربط العقار (post_type=property) بمبنى من Mostaager
// ─────────────────────────────────────────────────────────────
add_action('add_meta_boxes', 'ms_register_property_building_metabox');

function ms_register_property_building_metabox()
{
    add_meta_box(
        'ms_property_building_box',
        '🏢 ربط العقار بمبنى (Mostaager)',
        'ms_render_property_building_metabox',
        'property',
        'side',
        'high'
    );
}

function ms_render_property_building_metabox($post)
{
    global $wpdb;

    $current = intval(get_post_meta($post->ID, '_ms_building_id', true)) ?: intval(get_post_meta($post->ID, 'ms_building_id', true)) ?: intval(get_post_meta($post->ID, 'building_id', true));

    $tbl = $wpdb->prefix . 'ms_buildings';
    $rows = $wpdb->get_results("SELECT id, title, wp_post_id FROM {$tbl} ORDER BY title ASC");

    wp_nonce_field('ms_property_building_nonce', 'ms_property_building_nonce');
    ?>
    <style>#ms_property_building_box select{width:100%;padding:6px 8px;border:1px solid #ccd0d4;border-radius:4px;font-size:13px;background:#fff}</style>
    <p style="font-size:12px;color:#666;margin:0 0 6px;">اختر المبنى الذي ينتمي إليه هذا العقار (يعرض أسماء الأبنية وليس رقم المعرف):</p>
    <select name="ms_property_building_id" id="ms_property_building_id">
        <option value="0">-- غير مرتبط بمبنى --</option>
        <?php if (!empty($rows)) : foreach ($rows as $r) :
            $label = trim($r->title) ?: ('Building #' . intval($r->id));
            if (!empty($r->wp_post_id)) {
                $wp_title = get_the_title(intval($r->wp_post_id));
                if ($wp_title) $label = $wp_title . ' (' . $label . ')';
            }
        ?>
            <option value="<?php echo intval($r->id); ?>" <?php selected($current, intval($r->id)); ?>><?php echo esc_html($label); ?></option>
        <?php endforeach; endif; ?>
    </select>
    <p style="font-size:11px;color:#777;margin-top:6px;">عند الحفظ سيُخزن معرف المبنى في المفاتيح <code>_ms_building_id</code>, <code>ms_building_id</code>, و <code>building_id</code> لزيادة التوافق.</p>
    <?php
}

add_action('save_post_property', 'ms_save_property_building', 20, 2);

function ms_save_property_building($post_id, $post)
{
    if (
        !isset($_POST['ms_property_building_nonce']) ||
        !wp_verify_nonce(sanitize_text_field($_POST['ms_property_building_nonce']), 'ms_property_building_nonce')
    ) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $ms_bid = isset($_POST['ms_property_building_id']) ? intval($_POST['ms_property_building_id']) : 0;

    if ($ms_bid <= 0) {
        delete_post_meta($post_id, '_ms_building_id');
        delete_post_meta($post_id, 'ms_building_id');
        delete_post_meta($post_id, 'building_id');
    } else {
        update_post_meta($post_id, '_ms_building_id', $ms_bid);
        update_post_meta($post_id, 'ms_building_id', $ms_bid);
        update_post_meta($post_id, 'building_id', $ms_bid);
    }
}


if (!function_exists('ms_get_buildings_for_manager')) {
    /**
     * جلب كل المباني المرتبطة بمدير معين.
     * @param int $manager_user_id  WP user ID للمدير
     * @return array
     */
    function ms_get_buildings_for_manager($manager_user_id)
    {
        global $wpdb;
        $manager_user_id = absint($manager_user_id);
        if (!$manager_user_id) return array();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ms_buildings WHERE manager_id = %d ORDER BY title ASC",
            $manager_user_id
        ));
    }
}

