<?php
/**
 * Mostaager ↔ Houzez Dashboard Bridge (Phase 1)
 *
 * يضع لوحات مستأجر داخل إطار لوحة Houzez 4.x نفسه:
 *  - قالب صفحة من البلجن يستخدم هيدر/قائمة/شريط Houzez (get_header('dashboard') ...).
 *  - houzez_is_dashboard_filter  → Houzez يحمّل CSS/JS لوحته على صفحاتنا.
 *  - get_template_part_template-parts/dashboard/dashboard-menu (hook من ووردبريس نفسه)
 *    → قسم «إدارة العقار» داخل قائمة Houzez الجانبية، بدون تعديل أي ملف في الثيم.
 *
 * كل نقاط الربط تم التحقق منها في Houzez 4.3.5 + Houzez Theme Functionality 4.3.5.
 * إذا لم يكن Houzez هو الثيم النشط يبقى الجسر خاملاً وتعمل اللوحات كما كانت.
 *
 * تعطيل كامل: add_filter('ms_houzez_bridge_enabled', '__return_false');
 */

if (!defined('ABSPATH')) {
    exit;
}

final class MS_Houzez_Dashboard_Bridge {

    const TEMPLATE        = 'mostaager-houzez-dashboard.php';
    const OPT_PAGES       = 'ms_hz_dashboard_pages';
    const OPT_MIGRATED    = 'ms_hz_bridge_migrated';
    const META_PREV_TPL   = '_ms_prev_page_template';
    const MIGRATION_VER   = '1';

    /** shortcode => role key */
    const SHORTCODES = array(
        'rent_dashboard_v4'    => 'tenant',
        'owner_dashboard_v4'   => 'owner',
        'manager_dashboard_v4' => 'building_manager',
        'agent_dashboard_v4'   => 'agent',
    );

    public static function init() {
        add_filter('theme_page_templates', array(__CLASS__, 'register_template'), 10, 1);
        add_filter('template_include', array(__CLASS__, 'load_template'), 99);
        add_filter('houzez_is_dashboard_filter', array(__CLASS__, 'mark_as_houzez_dashboard'));
        add_action('get_template_part_template-parts/dashboard/dashboard-menu', array(__CLASS__, 'render_menu_section'), 10, 0);
        add_action('template_redirect', array(__CLASS__, 'redirect_tenant_from_houzez_home'));
        add_filter('body_class', array(__CLASS__, 'body_class'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue'), 1000);
        add_action('admin_init', array(__CLASS__, 'maybe_migrate_pages'));
        add_action('admin_notices', array(__CLASS__, 'admin_notice'));
        add_action('admin_post_ms_hz_bridge_revert', array(__CLASS__, 'handle_revert'));
        add_action('save_post_page', array(__CLASS__, 'flush_page_map'));
        // صفحات مستأجر داخل الإطار تحتاج طبقة الهوية دائماً
        add_filter('ms_load_brand_layer', function ($load) {
            return $load || MS_Houzez_Dashboard_Bridge::in_shell();
        });
    }

    /* ------------------------------------------------------------------ *
     * Guards
     * ------------------------------------------------------------------ */

    public static function houzez_active() {
        $active = get_template() === 'houzez' || function_exists('houzez_is_dashboard');
        return (bool) apply_filters('ms_houzez_bridge_enabled', $active);
    }

    /** هل الصفحة الحالية لوحة مستأجر داخل إطار Houzez؟ */
    public static function in_shell() {
        return self::houzez_active() && is_page() && get_page_template_slug(get_queried_object_id()) === self::TEMPLATE;
    }

    /* ------------------------------------------------------------------ *
     * Page template
     * ------------------------------------------------------------------ */

    public static function register_template($templates) {
        if (self::houzez_active()) {
            $templates[self::TEMPLATE] = 'Mostaager: لوحة داخل Houzez';
        }
        return $templates;
    }

    public static function load_template($template) {
        if (self::in_shell()) {
            $file = MOSTAAGER_ENTERPRISE_PATH . 'templates/houzez-dashboard-shell.php';
            if (file_exists($file)) {
                return $file;
            }
        }
        return $template;
    }

    public static function mark_as_houzez_dashboard($files) {
        $files   = (array) $files;
        $files[] = self::TEMPLATE;
        return $files;
    }

    /* ------------------------------------------------------------------ *
     * Page map: which WP page hosts which Mostaager dashboard
     * ------------------------------------------------------------------ */

    public static function page_map() {
        $map = get_option(self::OPT_PAGES);
        if (is_array($map)) {
            return $map;
        }
        global $wpdb;
        $map = array();
        foreach (self::SHORTCODES as $shortcode => $role) {
            $id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE %s
                 ORDER BY ID ASC LIMIT 1",
                '%' . $wpdb->esc_like('[' . $shortcode) . '%'
            ));
            if ($id) {
                $map[$role] = $id;
            }
        }
        update_option(self::OPT_PAGES, $map, false);
        return $map;
    }

    public static function flush_page_map() {
        delete_option(self::OPT_PAGES);
    }

    public static function page_url($role, $tab = '') {
        $map = self::page_map();
        if (empty($map[$role])) {
            return '';
        }
        $url = get_permalink($map[$role]);
        return $url ? $url . ($tab ? '#' . $tab : '') : '';
    }

    /* ------------------------------------------------------------------ *
     * Menu sections per role (reuse Houzez for listings/profile/messages)
     * ------------------------------------------------------------------ */

    public static function sections() {
        $sections = array(
            'tenant' => array(
                'title' => 'سكني',
                'items' => array(
                    'overview'    => 'نظرة عامة',
                    'invoices'    => 'الإيجار والفواتير',
                    'maintenance' => 'الصيانة',
                    'wallet'      => 'المحفظة',
                    'deposit'     => 'التأمين',
                    'documents'   => 'المستندات',
                    'meters'      => 'العدادات',
                    'discussions' => 'نقاشات المبنى',
                ),
            ),
            'owner' => array(
                'title' => 'إدارة أملاكي',
                'items' => array(
                    'overview'    => 'ملخص الأملاك',
                    'properties'  => 'الوحدات والمستأجرون',
                    'invoices'    => 'التحصيل والفواتير',
                    'maintenance' => 'الصيانة',
                    'wallet'      => 'المحفظة',
                    'deposits'    => 'تأمينات الأمانة',
                    'discussions' => 'النقاشات',
                ),
            ),
            'building_manager' => array(
                'title' => 'إدارة المبنى',
                'items' => array(
                    'overview'    => 'لوحة المبنى',
                    'buildings'   => 'المباني',
                    'units'       => 'الوحدات',
                    'invoices'    => 'الفواتير والتحصيل',
                    'maintenance' => 'الصيانة',
                    'wallet'      => 'محفظة المبنى',
                    'discussions' => 'النقاشات',
                ),
            ),
            'agent' => array(
                'title' => 'أعمال الوساطة',
                'items' => array(
                    'overview'      => 'ملخص الأعمال',
                    'subscriptions' => 'الاشتراكات',
                    'maintenance'   => 'الصيانة',
                    'invoices'      => 'الفواتير',
                    'discussions'   => 'النقاشات',
                    'analytics'     => 'الإحصائيات',
                ),
            ),
        );
        return apply_filters('ms_houzez_bridge_sections', $sections);
    }

    public static function roles_for_user($user_id) {
        $roles = array();
        if (user_can($user_id, 'manage_options')) {
            return array_values(self::SHORTCODES);
        }
        if (!function_exists('ms_user_has_role')) {
            return $roles;
        }
        foreach (array('tenant', 'owner', 'building_manager', 'agent') as $role) {
            if (ms_user_has_role($user_id, $role)) {
                $roles[] = $role;
            }
        }
        return $roles;
    }

    /**
     * يُطبع داخل .dashboard-sidebar مباشرة قبل قائمة Houzez الأصلية.
     */
    public static function render_menu_section() {
        if (!self::houzez_active() || !is_user_logged_in()) {
            return;
        }
        $user_id  = get_current_user_id();
        $roles    = self::roles_for_user($user_id);
        $sections = self::sections();
        $is_admin = user_can($user_id, 'manage_options');
        $current  = self::in_shell() ? (int) get_queried_object_id() : 0;
        $map      = self::page_map();

        if (empty($roles)) {
            return;
        }

        if (!function_exists('ms_dashboard_icon')) {
            require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/ms-icons.php';
        }

        echo '<div class="sidebar-nav ms-hz-nav" data-ms-hz-nav>';

        // الأدمن: رابط واحد لكل لوحة بدل عرض كل التبويبات
        if ($is_admin) {
            $labels = array('tenant' => 'لوحة المستأجر', 'owner' => 'لوحة المالك', 'building_manager' => 'لوحة مدير المبنى', 'agent' => 'لوحة الوسيط');
            echo '<div class="nav-box"><h5>' . esc_html__('إدارة العقار', 'mostaager') . '</h5><ul>';
            foreach ($labels as $role => $label) {
                if (empty($map[$role])) continue;
                $active = $current === (int) $map[$role] ? ' active' : '';
                printf(
                    '<li><a href="%s" class="%s"><i class="houzez-icon ms-hz-icon">%s</i><span>%s</span></a></li>',
                    esc_url(get_permalink($map[$role])),
                    esc_attr(trim($active)),
                    ms_dashboard_icon(array('tenant' => 'home', 'owner' => 'key', 'building_manager' => 'building', 'agent' => 'users')[$role]), // phpcs:ignore
                    esc_html($label)
                );
            }
            echo '</ul></div></div>';
            return;
        }

        foreach ($roles as $role) {
            if (empty($sections[$role]) || empty($map[$role])) {
                continue;
            }
            $page_id  = (int) $map[$role];
            $page_url = get_permalink($page_id);
            $on_page  = $current === $page_id;

            echo '<div class="nav-box"><h5>' . esc_html($sections[$role]['title']) . '</h5><ul>';
            foreach ($sections[$role]['items'] as $tab => $label) {
                // على نفس الصفحة: رابط hash فقط ليتبدّل القسم بدون إعادة تحميل
                $href = $on_page ? '#' . $tab : $page_url . '#' . $tab;
                printf(
                    '<li><a href="%s" data-ms-tab="%s" data-ms-page="%d" class="%s"><i class="houzez-icon ms-hz-icon">%s</i><span>%s</span></a></li>',
                    esc_url($href),
                    esc_attr($tab),
                    $page_id,
                    ($on_page && $tab === 'overview') ? 'active' : '',
                    ms_dashboard_icon($tab), // phpcs:ignore — static SVG
                    esc_html($label)
                );
            }
            echo '</ul></div>';
        }
        echo '</div>';
    }

    /* ------------------------------------------------------------------ *
     * Single entry point: tenants landing on Houzez home dashboard
     * go straight to their Mostaager page (Houzez shows buyers almost nothing there).
     * ------------------------------------------------------------------ */

    public static function redirect_tenant_from_houzez_home() {
        if (!self::houzez_active() || !is_user_logged_in() || !is_page_template('template/user_dashboard.php')) {
            return;
        }
        if (!apply_filters('ms_houzez_redirect_tenant_home', true)) {
            return;
        }
        $user_id = get_current_user_id();
        if (user_can($user_id, 'manage_options') || !function_exists('ms_user_has_role') || !ms_user_has_role($user_id, 'tenant')) {
            return;
        }
        // مستأجر يملك أيضاً دوراً آخر (مالك/وسيط/مدير) يبقى في لوحة Houzez
        foreach (array('owner', 'building_manager', 'agent') as $other) {
            if (ms_user_has_role($user_id, $other)) {
                return;
            }
        }
        $url = self::page_url('tenant');
        if ($url) {
            wp_safe_redirect($url);
            exit;
        }
    }

    /* ------------------------------------------------------------------ *
     * Assets & branding
     * ------------------------------------------------------------------ */

    public static function body_class($classes) {
        if (self::houzez_active() && function_exists('houzez_is_dashboard') && houzez_is_dashboard()
            && apply_filters('ms_houzez_brand_dashboard', true)) {
            $classes[] = 'ms-hz-brand';
        }
        if (self::in_shell()) {
            $classes[] = 'ms-hz-shell';
        }
        return $classes;
    }

    public static function enqueue() {
        if (!self::houzez_active() || !function_exists('houzez_is_dashboard') || !houzez_is_dashboard()) {
            return;
        }
        $css = MOSTAAGER_ENTERPRISE_PATH . 'assets/css/ms-houzez-shell.css';
        if (file_exists($css)) {
            wp_enqueue_style('ms-houzez-shell', MOSTAAGER_ENTERPRISE_URL . 'assets/css/ms-houzez-shell.css', array(), filemtime($css));
        }
        $js = MOSTAAGER_ENTERPRISE_PATH . 'assets/js/ms-houzez-shell.js';
        if (file_exists($js)) {
            wp_enqueue_script('ms-houzez-shell', MOSTAAGER_ENTERPRISE_URL . 'assets/js/ms-houzez-shell.js', array(), filemtime($js), true);
            // إضافة العقار تتم عبر نموذج Houzez الأصلي (حقول Houzez + منشئ الحقول + الباقات)
            $submit = function_exists('houzez_get_template_link_2') ? houzez_get_template_link_2('template/user_dashboard_submit.php') : '';
            wp_localize_script('ms-houzez-shell', 'MSHouzezShell', array(
                'submitUrl' => $submit ? esc_url_raw($submit) : '',
            ));
        }
    }

    /* ------------------------------------------------------------------ *
     * One-time migration: assign the shell template to existing dashboard pages
     * (reversible — previous template is stored per page).
     * ------------------------------------------------------------------ */

    public static function maybe_migrate_pages() {
        if (!self::houzez_active() || !current_user_can('manage_options')) {
            return;
        }
        if (get_option(self::OPT_MIGRATED) === self::MIGRATION_VER || !apply_filters('ms_houzez_bridge_auto_migrate', true)) {
            return;
        }
        self::flush_page_map();
        $done = array();
        foreach (self::page_map() as $role => $page_id) {
            $prev = get_page_template_slug($page_id);
            if ($prev === self::TEMPLATE) {
                continue;
            }
            update_post_meta($page_id, self::META_PREV_TPL, $prev ? $prev : 'default');
            update_post_meta($page_id, '_wp_page_template', self::TEMPLATE);
            $done[$role] = $page_id;
        }
        update_option(self::OPT_MIGRATED, self::MIGRATION_VER, false);
        set_transient('ms_hz_bridge_notice', $done, DAY_IN_SECONDS);
    }

    public static function admin_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $done = get_transient('ms_hz_bridge_notice');
        if ($done === false) {
            return;
        }
        $labels = array('tenant' => 'المستأجر', 'owner' => 'المالك', 'building_manager' => 'مدير المبنى', 'agent' => 'الوسيط');
        $names  = array();
        foreach ((array) $done as $role => $id) {
            $names[] = ($labels[$role] ?? $role) . ' (' . esc_html(get_the_title($id)) . ')';
        }
        $revert = wp_nonce_url(admin_url('admin-post.php?action=ms_hz_bridge_revert'), 'ms_hz_bridge_revert');
        echo '<div class="notice notice-success is-dismissible"><p><strong>مستأجر:</strong> ';
        if ($names) {
            echo 'تم نقل لوحات ' . implode('، ', $names) . ' إلى داخل لوحة Houzez. ';
        } else {
            echo 'لوحات مستأجر تعمل داخل إطار Houzez. ';
        }
        echo '<a href="' . esc_url($revert) . '">تراجع وإعادة القوالب السابقة</a></p></div>';
        delete_transient('ms_hz_bridge_notice');
    }

    public static function handle_revert() {
        if (!current_user_can('manage_options') || !check_admin_referer('ms_hz_bridge_revert')) {
            wp_die('غير مصرح');
        }
        foreach (self::page_map() as $page_id) {
            $prev = get_post_meta($page_id, self::META_PREV_TPL, true);
            if ($prev !== '') {
                update_post_meta($page_id, '_wp_page_template', $prev);
                delete_post_meta($page_id, self::META_PREV_TPL);
            }
        }
        // لا تُعِد الترحيل تلقائياً بعد التراجع
        update_option(self::OPT_MIGRATED, self::MIGRATION_VER, false);
        wp_safe_redirect(admin_url('edit.php?post_type=page'));
        exit;
    }
}

/** مساعد عام تستخدمه اللوحات لمعرفة أنها داخل إطار Houzez */
function ms_in_houzez_shell() {
    return class_exists('MS_Houzez_Dashboard_Bridge') && MS_Houzez_Dashboard_Bridge::in_shell();
}

MS_Houzez_Dashboard_Bridge::init();
