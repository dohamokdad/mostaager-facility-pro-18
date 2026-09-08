<?php
/**
 * Canonical Mostaager admin records.
 * Uses the normalized ms_* tables as the source of truth instead of empty legacy CPT screens.
 */
if (!defined('ABSPATH')) { exit; }

add_action('manage_building_posts_custom_column', function ($column, $post_id) {
    if ($column !== 'ms_thumbnail') { return; }
    $thumb = get_the_post_thumbnail($post_id, array(64, 48), array('style' => 'width:64px;height:48px;object-fit:cover;border-radius:6px;')); 
    echo $thumb ? $thumb : '<span style="display:inline-flex;width:64px;height:48px;align-items:center;justify-content:center;background:#f1f5f9;color:#94a3b8;border-radius:6px;font-size:11px;">لا توجد</span>';
}, 10, 2);
add_filter('manage_building_posts_columns', function ($columns) {
    $out = array();
    foreach ($columns as $key => $label) {
        if ($key === 'cb') { $out[$key] = $label; $out['ms_thumbnail'] = 'الصورة'; continue; }
        $out[$key] = $label;
    }
    if (!isset($out['ms_thumbnail'])) { $out['ms_thumbnail'] = 'الصورة'; }
    return $out;
});

add_action('admin_menu', function () {
    if (!current_user_can('manage_options')) { return; }
    // تم حذف صفحة سجلات Mostaager بالكامل حسب طلب المستخدم
    add_submenu_page('options-general.php', 'تقرير المحفظة', 'تقرير المحفظة', 'manage_options', 'mostaager-wallet-report', 'ms_admin_wallet_report_page');
    remove_menu_page('mostaager-wallet-report');
    remove_menu_page('mostaager-scenario-runner');
    remove_menu_page('ms-import-dashboard');
    remove_menu_page('ms-unified-settings');
});

add_action('admin_init', function () {
    if (!current_user_can('manage_options')) { return; }
    $post_type = isset($_GET['post_type']) ? sanitize_key($_GET['post_type']) : '';
    $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
    if ($post_type === 'houzez_owner') {
        wp_safe_redirect(admin_url('users.php?role=owner'));
        exit;
    }
    $removed_pages = array('ms-import-dashboard','ms-import-new','ms-import-templates','ms-import-logs','ms-import-settings','ms-import-automation','ms-import-monitoring','mostaager-scenario-runner','ms-canonical-records');
    if (in_array($page, $removed_pages, true)) {
        wp_safe_redirect(admin_url('admin.php?page=options-general'));
        exit;
    }
});

// Remove the obsolete import button from the unified settings view without removing settings data.
add_filter('ms_unified_settings_show_import', '__return_false');

// Add an explicit owner marker to the standard WordPress users table.
add_filter('manage_users_columns', function ($columns) { $columns['ms_owner_status'] = 'صفة المالك'; return $columns; });
add_filter('manage_users_custom_column', function ($output, $column, $user_id) {
    if ($column !== 'ms_owner_status') { return $output; }
    $user = get_userdata($user_id);
    if ($user && in_array('owner', (array)$user->roles, true)) { return '<strong style="color:#0f766e">مالك</strong>'; }
    return '—';
}, 10, 3);
add_action('restrict_manage_users', function () {
    if (current_user_can('manage_options')) { echo '<a class="button" style="margin-right:8px" href="'.esc_url(admin_url('users.php?role=owner')).'">عرض المالكين فقط</a>'; }
});

// Admin-only role filter remains on the standard users screen.
add_filter('user_row_actions', function ($actions, $user) {
    if (in_array('owner', (array)$user->roles, true)) { $actions['ms_owner'] = '<span style="color:#0f766e">مالك</span>'; }
    return $actions;
}, 10, 2);
