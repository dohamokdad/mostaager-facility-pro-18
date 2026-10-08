<?php
/**
 * Mostaager ↔ Houzez Roles (Phase 2)
 *
 * أدوار Houzez الحقيقية (مؤكدة من Houzez 4.3.5):
 *   houzez_owner · houzez_agent · houzez_agency · houzez_manager · houzez_buyer · houzez_seller
 * الدور houzez_building_manager الذي كان يستخدمه البلجن غير موجود في Houzez إطلاقاً.
 *
 * المزامنة عبر hooks ووردبريس الأساسية (تعمل دائماً) بدل hooks مفترضة:
 *   user_register · set_user_role · add_user_role · remove_user_role · profile_update
 * بالإضافة إلى houzez_after_register (موجود فعلاً في membership-functions.php).
 *
 * المستأجر يُمنح houzez_buyer لأن houzez_check_role() تعرض للمشتري قائمة مختصرة
 * (المفضلة/الرسائل/الملف الشخصي) بدون قوائم العقارات و CRM — وهذا المطلوب.
 */

if (!defined('ABSPATH')) {
    exit;
}

/** دور مستأجر → دور Houzez المقابل */
function ms_houzez_map_role($ms_role) {
    $map = array(
        'owner'            => 'houzez_owner',
        'agent'            => 'houzez_agent',
        'building_manager' => 'houzez_manager',
        'tenant'           => 'houzez_buyer',
    );
    return apply_filters('ms_houzez_role_map', $map[$ms_role] ?? $ms_role, $ms_role);
}

/** دور Houzez → دور مستأجر المقابل */
function ms_houzez_reverse_map_role($houzez_role) {
    $map = array(
        'houzez_owner'   => 'owner',
        'houzez_agent'   => 'agent',
        'houzez_agency'  => 'agent',
        'houzez_manager' => 'building_manager',
        // houzez_buyer لا يعني مستأجراً: المشتري في Houzez مجرد زائر مسجّل.
        // الاتجاه أحادي — المستأجر يحصل على houzez_buyer، وليس العكس.
    );
    return apply_filters('ms_houzez_reverse_role_map', $map[$houzez_role] ?? '', $houzez_role);
}

function ms_facility_roles() {
    return array('owner', 'tenant', 'agent', 'building_manager');
}

/** هل للمستخدم هذا الدور (بدور مستأجر أو بمقابله في Houzez)؟ */
function ms_user_has_houzez_role($user_id, $role) {
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }
    $roles = (array) $user->roles;

    if (in_array($role, $roles, true)) {
        return true;
    }
    foreach ($roles as $r) {
        if (ms_houzez_reverse_map_role($r) === $role) {
            return true;
        }
    }
    return false;
}

if (!function_exists('ms_user_has_role')) {
    function ms_user_has_role($user_id, $role) {
        return ms_user_has_houzez_role($user_id, $role);
    }
}

/* ====================================================================== *
 * المزامنة في الاتجاهين
 * ====================================================================== */

/**
 * يضيف الأدوار المقابلة الناقصة (لا يحذف أي دور).
 * @return string[] الأدوار المضافة
 */
function ms_sync_user_roles($user_id) {
    $user = get_userdata($user_id);
    if (!$user || !apply_filters('ms_houzez_sync_roles_enabled', true, $user_id)) {
        return array();
    }

    static $running = array();
    if (isset($running[$user_id])) {
        return array();
    }
    $running[$user_id] = true;

    $roles = (array) $user->roles;
    $added = array();

    // Houzez → مستأجر
    foreach ($roles as $role) {
        $ms_role = ms_houzez_reverse_map_role($role);
        if ($ms_role && !in_array($ms_role, $roles, true) && get_role($ms_role)) {
            $user->add_role($ms_role);
            $added[] = $ms_role;
            $roles[] = $ms_role;
        }
    }

    // مستأجر → Houzez
    foreach (ms_facility_roles() as $ms_role) {
        if (!in_array($ms_role, $roles, true)) {
            continue;
        }
        $houzez_role = ms_houzez_map_role($ms_role);
        if ($houzez_role && $houzez_role !== $ms_role && !in_array($houzez_role, $roles, true) && get_role($houzez_role)) {
            $user->add_role($houzez_role);
            $added[] = $houzez_role;
            $roles[] = $houzez_role;
        }
    }

    if ($added) {
        do_action('ms_user_roles_synced', $user_id, $added);
    }

    unset($running[$user_id]);
    return $added;
}

add_action('user_register', 'ms_sync_user_roles', 20, 1);
add_action('houzez_after_register', 'ms_sync_user_roles', 20, 1);
add_action('set_user_role', function ($user_id) { ms_sync_user_roles($user_id); }, 20, 1);
add_action('add_user_role', function ($user_id) { ms_sync_user_roles($user_id); }, 20, 1);
add_action('profile_update', 'ms_sync_user_roles', 20, 1);

/** إزالة دور تُزيل مقابله — حتى لا يبقى وصول بعد سحب الصلاحية */
add_action('remove_user_role', function ($user_id, $role) {
    $user = get_userdata($user_id);
    if (!$user || !apply_filters('ms_houzez_sync_roles_enabled', true, $user_id)) {
        return;
    }
    $counterparts = array();
    $reverse = ms_houzez_reverse_map_role($role);
    if ($reverse) {
        $counterparts[] = $reverse;
    }
    if (in_array($role, ms_facility_roles(), true)) {
        $counterparts[] = ms_houzez_map_role($role);
    }
    $current = (array) $user->roles;
    foreach (array_filter($counterparts) as $counterpart) {
        if ($counterpart === $role || !in_array($counterpart, $current, true)) {
            continue;
        }
        // الوكيل والوكالة يشيران لنفس دور مستأجر: لا تُزل إن بقي الآخر
        if ($counterpart === 'agent' && (in_array('houzez_agent', $current, true) || in_array('houzez_agency', $current, true))) {
            continue;
        }
        $user->remove_role($counterpart);
    }
}, 20, 2);

/* ====================================================================== *
 * مزامنة لمرة واحدة للمستخدمين الحاليين
 * ====================================================================== */

add_action('admin_init', function () {
    if (!current_user_can('manage_options') || get_option('ms_roles_synced_v2') === '1') {
        return;
    }
    if (!apply_filters('ms_houzez_sync_existing_users', true)) {
        update_option('ms_roles_synced_v2', '1', false);
        return;
    }

    $roles = array_merge(ms_facility_roles(), array('houzez_owner', 'houzez_agent', 'houzez_agency', 'houzez_manager', 'houzez_buyer'));
    $roles = array_values(array_filter($roles, 'get_role'));
    $changed = 0;

    if ($roles) {
        foreach (get_users(array('role__in' => $roles, 'fields' => 'ID', 'number' => 2000)) as $uid) {
            if (ms_sync_user_roles($uid)) {
                $changed++;
            }
        }
    }

    update_option('ms_roles_synced_v2', '1', false);
    if ($changed) {
        set_transient('ms_roles_sync_notice', $changed, DAY_IN_SECONDS);
    }
});

add_action('admin_notices', function () {
    $changed = get_transient('ms_roles_sync_notice');
    if ($changed === false || !current_user_can('manage_options')) {
        return;
    }
    delete_transient('ms_roles_sync_notice');
    printf(
        '<div class="notice notice-info is-dismissible"><p><strong>مستأجر:</strong> تمت مزامنة أدوار %d مستخدم مع أدوار Houzez (مالك ← houzez_owner، وسيط ← houzez_agent، مدير مبنى ← houzez_manager، مستأجر ← houzez_buyer).</p></div>',
        (int) $changed
    );
});

/* ====================================================================== *
 * مساعدات العقارات (حقول Houzez الحقيقية)
 * ====================================================================== */

/** عقارات الوسيط (كاتب المنشور في نموذج Houzez للواجهة) */
function ms_houzez_get_agent_properties($agent_id) {
    $agent_id = absint($agent_id);
    if (!$agent_id) {
        return array();
    }
    return get_posts(array(
        'post_type'      => 'property',
        'posts_per_page' => -1,
        'post_status'    => array('publish', 'pending', 'draft'),
        'author'         => $agent_id,
        'fields'         => 'ids',
    ));
}

/** عقارات المالك */
function ms_houzez_get_owner_properties($owner_id) {
    $owner_id = absint($owner_id);
    if (!$owner_id) {
        return array();
    }
    return get_posts(array(
        'post_type'      => 'property',
        'posts_per_page' => -1,
        'post_status'    => array('publish', 'pending', 'draft'),
        'fields'         => 'ids',
        'meta_query'     => array(
            'relation' => 'OR',
            array('key' => 'owner_id', 'value' => $owner_id),
            array('key' => 'property_owner', 'value' => $owner_id),
            array('key' => 'fave_property_owner', 'value' => $owner_id),
        ),
    ));
}
