<?php
/**
 * أيقونات خطية مشتركة (لوحات مستأجر + قائمة Houzez الجانبية)
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('ms_dashboard_icon')) {
    /**
     * أيقونات خطية موحّدة (stroke) بدل الـ emoji — تتبع لون النص (currentColor)
     * وتطابق نمط الأيقونات في هوية مستأجر.
     */
    function ms_dashboard_icon($key) {
        $paths = array(
            'grid'     => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/>',
            'home'     => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
            'home-add' => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M12 11v6"/><path d="M9 14h6"/>',
            'building' => '<path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4M10 10h4M10 14h4M10 18h4"/>',
            'file'     => '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5z"/><path d="M14 2v6h6"/><path d="M16 13H8M16 17H8"/>',
            'docs'     => '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5z"/><path d="M14 2v6h6"/><circle cx="12" cy="15" r="2.5"/>',
            'wrench'   => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94z"/>',
            'wallet'   => '<path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4z"/>',
            'shield'   => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/>',
            'gauge'    => '<path d="m12 14 4-4"/><path d="M3.34 19a10 10 0 1 1 17.32 0"/>',
            'chat'     => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M8 9h8M8 13h5"/>',
            'user'     => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
            'users'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
            'package'  => '<path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="m3.3 7 8.7 5 8.7-5M12 22V12"/>',
            'chart'    => '<path d="M3 3v18h18"/><path d="M18 17V9M13 17V5M8 17v-3"/>',
            'bell'     => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
            'key'      => '<circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6M15.5 7.5l3 3L22 7l-3-3"/>',
            'logout'   => '<path d="M15 21h4a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2h-4"/><path d="m8 17-5-5 5-5"/><path d="M3 12h12"/>',
            'dot'      => '<circle cx="12" cy="12" r="3"/>',
        );
        $map = array(
            'overview' => 'grid', 'add-property' => 'home-add', 'properties' => 'home', 'listings' => 'home',
            'units' => 'home', 'buildings' => 'building', 'invoices' => 'file', 'documents' => 'docs',
            'maintenance' => 'wrench', 'wallet' => 'wallet', 'deposit' => 'shield', 'deposits' => 'shield',
            'meters' => 'gauge', 'discussions' => 'chat', 'profile' => 'user', 'users' => 'users',
            'subscriptions' => 'package', 'analytics' => 'chart', 'notifications' => 'bell',
            'facilities' => 'key', 'logout' => 'logout',
        );
        $name = isset($paths[$key]) ? $key : ($map[$key] ?? 'dot');
        return '<svg class="ms-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
    }
}

if (!function_exists('ms_building_display_name')) {
    /**
     * اسم المبنى من المعرّف الداخلي (ms_buildings.id).
     * الكود القديم كان يستدعي get_post($building_id) مباشرة — أي يعرض عنوان أي منشور
     * ووردبريس يصادف أن رقمه يساوي رقم المبنى الداخلي (مثال: "Hello world!").
     */
    function ms_building_display_name($building_id) {
        static $cache = array();
        $building_id = absint($building_id);
        if (!$building_id) {
            return '';
        }
        if (isset($cache[$building_id])) {
            return $cache[$building_id];
        }
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT title, wp_post_id FROM {$wpdb->prefix}ms_buildings WHERE id = %d", $building_id));
        $name = '';
        if ($row) {
            $name = (string) $row->title;
            if ($name === '' && !empty($row->wp_post_id)) {
                $name = (string) get_the_title((int) $row->wp_post_id);
            }
        }
        return $cache[$building_id] = $name;
    }
}
