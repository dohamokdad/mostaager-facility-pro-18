<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('ms_get_dashboard_template_path')) {
    function ms_get_dashboard_template_path($relative_path)
    {
        $base_path = defined('MS_PLUGIN_PATH') ? MS_PLUGIN_PATH : rtrim(plugin_dir_path(dirname(__FILE__, 2)), '/\\') . '/';
        $relative_path = ltrim(str_replace('\\', '/', $relative_path), '/');
        return $base_path . $relative_path;
    }
}

if (!function_exists('ms_load_dashboard_sidebar')) {
    function ms_load_dashboard_sidebar($menu_items = array())
    {
        $sidebar_path = ms_get_dashboard_template_path('templates/partials/sidebar.php');

        if (file_exists($sidebar_path)) {
            // expose the passed menu items to the template as a compatibility variable
            $ms_dashboard_menu_items = $menu_items;
            include $sidebar_path;
            return;
        }

        echo '<aside class="ms-sidebar">';
        echo '<div class="ms-sidebar-title">القائمة</div>';
        echo '<ul class="ms-sidebar-menu">';

        foreach ((array) $menu_items as $item) {
            $href = isset($item['href']) ? esc_url($item['href']) : '#';
            $label = isset($item['label']) ? esc_html($item['label']) : '';
            echo '<li><a href="' . $href . '">' . $label . '</a></li>';
        }

        echo '</ul>';
        echo '</aside>';
    }
}

// Load core components
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'core/install.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'core/install.php';
}

// Load helper DB layer if present
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'core/database.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'core/database.php';
}

// Run database migrations for security deposit system
add_action('admin_init', function() {
    // Skip during installation
    if (defined('WP_INSTALLING') && WP_INSTALLING) {
        return;
    }

    if (function_exists('ms_migrate_add_frozen_balance_column')) {
        ms_migrate_add_frozen_balance_column();
    }
    if (function_exists('ms_migrate_create_security_deposits_table')) {
        ms_migrate_create_security_deposits_table();
    }
    // Create facility tables if they don't exist
    if (function_exists('ms_migrate_create_facility_tables')) {
        ms_migrate_create_facility_tables();
    }
});

// Load Mostaager DB wrapper and block registration
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/class-mostager-db.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/class-mostager-db.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/blocks.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/blocks.php';
}

// Load Unified Settings
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/class-unified-settings.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/class-unified-settings.php';
}

// Ensure DB versioned upgrades for existing installs
if (!defined('MOSTAGER_DB_VERSION')) {
    define('MOSTAGER_DB_VERSION', '2.4');
}

// Defer DB version check to admin_init to avoid unexpected output
add_action('admin_init', function() {
    // Skip during installation
    if (defined('WP_INSTALLING') && WP_INSTALLING) {
        return;
    }

    $installed_db_version = get_option('mostager_db_version', '');
    if ($installed_db_version !== MOSTAGER_DB_VERSION) {
        // Attempt to create/update required tables
        if (function_exists('ms_create_tables')) {
            ms_create_tables();
        }
        // Also run compatibility installer if available
        if (function_exists('mostager_plugin_install_compat')) {
            mostager_plugin_install_compat();
        }
        update_option('mostager_db_version', MOSTAGER_DB_VERSION);
    }
});

// Load includes and integration files
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/functions.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/functions.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/realtime/class-firebase-fcm.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/realtime/class-firebase-fcm.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/notification-preferences.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/notification-preferences.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/integrations/class-houzez-mostaager-adapter.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/integrations/class-houzez-mostaager-adapter.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/analytics.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/analytics.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/dashboard-action-center.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/dashboard-action-center.php';
}

// Migration runner (handles legacy -> ms_ migrations)
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/class-migration-runner.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/class-migration-runner.php';
}

// WooCommerce invoice bridge
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/class-wc-invoice-bridge.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/class-wc-invoice-bridge.php';
}

// Reports engine
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/class-reports-engine.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/class-reports-engine.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez/class-houzez-map-status.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez/class-houzez-map-status.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez/class-houzez-ratings.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez/class-houzez-ratings.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez/class-houzez-search-filter.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez/class-houzez-search-filter.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez/class-houzez-notifications.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez/class-houzez-notifications.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez/class-property-sync.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez/class-property-sync.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez/class-lead-converter.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez/class-lead-converter.php';
}
if (class_exists('MFP_Houzez_Map_Status')) {
    if (!defined('WP_INSTALLING') || !WP_INSTALLING) {
        MFP_Houzez_Map_Status::init();
    }
}
if (class_exists('MFP_Houzez_Ratings')) {
    if (!defined('WP_INSTALLING') || !WP_INSTALLING) {
        MFP_Houzez_Ratings::init();
    }
}
if (class_exists('MFP_Houzez_Search_Filter')) {
    if (!defined('WP_INSTALLING') || !WP_INSTALLING) {
        MFP_Houzez_Search_Filter::init();
    }
}
if (class_exists('MFP_Houzez_Notifications')) {
    if (!defined('WP_INSTALLING') || !WP_INSTALLING) {
        MFP_Houzez_Notifications::init();
    }
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/pro-platform.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/pro-platform.php';
}
// Load advanced modules (Phase 5, 6, 7)
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/smart-automation.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/smart-automation.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/advanced-financial.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/advanced-financial.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/agent-properties-ui.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/agent-properties-ui.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/agent-dashboard-tabs.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/agent-dashboard-tabs.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/tenant-rent-reminders.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/tenant-rent-reminders.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/tenant-dashboard-enhancements.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/tenant-dashboard-enhancements.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/user-experience.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/user-experience.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/API/RestApi.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/API/RestApi.php';
    add_action('rest_api_init', array('MostaagerFacilitiesPro\API\RestApi', 'register_routes'));
}

if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/API/RestApiV2.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/API/RestApiV2.php';
    if (!defined('WP_INSTALLING') || !WP_INSTALLING) {
        MostaagerFacilitiesPro\API\RestApiV2::init();
    }
}

// Load utility bills REST API
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/class-utility-bills-api.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/class-utility-bills-api.php';
}

// Load facilities API (including derived status endpoint)
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/class-facilities-api.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/class-facilities-api.php';
}
// Load roles registration (if present)

if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/roles.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/roles.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/shortcodes.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/shortcodes.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/api.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/api.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/telr-integration.php')) {
    add_action('plugins_loaded', function() {
        require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/telr-integration.php';
    }, 20);
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/agent-subscription.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/agent-subscription.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/rent-invoices.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/rent-invoices.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/actions.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/actions.php';
}

// load ajax handlers
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'core/ajax.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'core/ajax.php';
}

// register plugin post types
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'core/post-types.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'core/post-types.php';
}

// admin pages for ms_* tables
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'core/admin-wallet-report.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'core/admin-wallet-report.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'core/admin.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'core/admin.php';
}

// Building manager metabox (links building_manager user to a building post)
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/building-manager-metabox.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/building-manager-metabox.php';
}

// Houzez integration fields (building/unit fields on property posts)
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez-integration-fields.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez-integration-fields.php';
}

// WordPress notifications integration
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/wordpress-notifications-integration.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/wordpress-notifications-integration.php';
}

// Houzez roles integration
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez-roles-integration.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez-roles-integration.php';
}

// Houzez maintenance custom post type
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez-maintenance-cpt.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez-maintenance-cpt.php';
}

// WordPress comments integration for discussions
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/wordpress-comments-integration.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/wordpress-comments-integration.php';
}

// Houzez wallet integration
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez-wallet-integration.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez-wallet-integration.php';
}

// Houzez invoice integration
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez-invoice-integration.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez-invoice-integration.php';
}

// Houzez reports integration
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez-reports-integration.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez-reports-integration.php';
}

// houzez UI integration
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez-ui-integration.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez-ui-integration.php';
}

// houzez REST API integration
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez-rest-api-integration.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez-rest-api-integration.php';
}

// houzez user dashboard integration
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez-user-dashboard-integration.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/houzez-user-dashboard-integration.php';
}

// Profile update handler
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/ms-profile-handler.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/ms-profile-handler.php';
}
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'includes/unified-profile.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/unified-profile.php';
}

// Load plugin admin settings UI
if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'admin/settings-page.php')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'admin/settings-page.php';
}

// Load dashboards - defer loading to avoid conflicts
add_action('plugins_loaded', function() {
    // Skip during installation
    if (defined('WP_INSTALLING') && WP_INSTALLING) {
        return;
    }

    $dash_path = MOSTAAGER_ENTERPRISE_PATH . 'app/Dashboards/';
    if (file_exists($dash_path . 'building-dashboard.php')) {
        require_once $dash_path . 'building-dashboard.php';
    }
    if (file_exists($dash_path . 'owner-dashboard.php')) {
        require_once $dash_path . 'owner-dashboard.php';
    }
    if (file_exists($dash_path . 'agent-dashboard.php')) {
        require_once $dash_path . 'agent-dashboard.php';
    }
    if (file_exists($dash_path . 'rent-dashboard.php')) {
        require_once $dash_path . 'rent-dashboard.php';
    }
    $inline_property_path = MOSTAAGER_ENTERPRISE_PATH . 'includes/inline-property-form.php';
    if (file_exists($inline_property_path)) {
        require_once $inline_property_path;
    }
}, 20);

// Enqueue styles & scripts
add_action('wp_enqueue_scripts', function () {
    $css_path = MOSTAAGER_ENTERPRISE_PATH . 'assets/css/dashboard.css';
    $js_path = MOSTAAGER_ENTERPRISE_PATH . 'assets/js/dashboard.js';
    $inline_property_css = MOSTAAGER_ENTERPRISE_PATH . 'assets/css/inline-property.css';
    $inline_property_js = MOSTAAGER_ENTERPRISE_PATH . 'assets/js/inline-property.js';
    $unified_profile_css = MOSTAAGER_ENTERPRISE_PATH . 'assets/css/unified-profile.css';
    $unified_profile_js = MOSTAAGER_ENTERPRISE_PATH . 'assets/js/unified-profile.js';
    $ms_page_content = is_singular() ? (string) get_post_field('post_content', get_queried_object_id()) : '';
    $ms_has_dashboard_ui = false;
    foreach (array('owner_dashboard_v4', 'rent_dashboard_v4', 'agent_dashboard_v4', 'manager_dashboard_v4', 'ms_dashboard') as $ms_shortcode) {
        if (strpos($ms_page_content, '[' . $ms_shortcode) !== false) {
            $ms_has_dashboard_ui = true;
            break;
        }
    }
    $ms_has_inline_property_ui = $ms_has_dashboard_ui || strpos($ms_page_content, '[ms_inline_add_property') !== false;
    $ms_has_profile_ui = $ms_has_dashboard_ui || strpos($ms_page_content, '[ms_unified_profile') !== false;

    if ($ms_has_profile_ui && file_exists($unified_profile_css)) {
        wp_enqueue_style('ms-unified-profile', MS_PLUGIN_URL . 'assets/css/unified-profile.css', array('mostaager-design-system'), filemtime($unified_profile_css));
    }
    if ($ms_has_inline_property_ui && file_exists($inline_property_css)) {
        wp_enqueue_style('ms-inline-property', MS_PLUGIN_URL . 'assets/css/inline-property.css', array('mostaager-design-system'), filemtime($inline_property_css));
    }

    wp_enqueue_style(
        'ms-dashboard',
        MS_PLUGIN_URL . 'assets/css/dashboard.css',
        [],
        file_exists($css_path) ? filemtime($css_path) : '1.0'
    );

    // Design system CSS overrides (load after dashboard to override legacy rules)
    $design_css_path = MOSTAAGER_ENTERPRISE_PATH . 'assets/css/design-system.css';
    wp_enqueue_style(
        'mostaager-design-system',
        MS_PLUGIN_URL . 'assets/css/design-system.css',
        array('ms-dashboard'),
        file_exists($design_css_path) ? filemtime($design_css_path) : '1.0'
    );

    wp_enqueue_script(
        'mostaager-dashboard-js',
        MOSTAAGER_ENTERPRISE_URL . 'assets/js/dashboard.js',
        [],
        file_exists($js_path) ? filemtime($js_path) : '1.0',
        true
    );
    // dashboard-tabs should load after main dashboard script (lightweight helpers)
    $tabs_js = MOSTAAGER_ENTERPRISE_PATH . 'assets/js/dashboard-tabs.js';
    if (file_exists($tabs_js)) {
        wp_enqueue_script(
            'mostaager-dashboard-tabs-js',
            MOSTAAGER_ENTERPRISE_URL . 'assets/js/dashboard-tabs.js',
            array('mostaager-dashboard-js'),
            filemtime($tabs_js),
            true
        );
    }
    wp_localize_script('mostaager-dashboard-js', 'MostaagerAjax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mostaager-ajax-nonce')
    ));
    $agent_properties_css = MOSTAAGER_ENTERPRISE_PATH . 'assets/css/agent-properties-ui.css';
    $agent_properties_js = MOSTAAGER_ENTERPRISE_PATH . 'assets/js/agent-properties-ui.js';
    $agent_tabs_css = MOSTAAGER_ENTERPRISE_PATH . 'assets/css/agent-dashboard-tabs.css';
    $agent_tabs_js = MOSTAAGER_ENTERPRISE_PATH . 'assets/js/agent-dashboard-tabs.js';
    $tenant_tabs_css = MOSTAAGER_ENTERPRISE_PATH . 'assets/css/tenant-dashboard-enhancements.css';
    $tenant_tabs_js = MOSTAAGER_ENTERPRISE_PATH . 'assets/js/tenant-dashboard-enhancements.js';
    $building_dashboard_css = MOSTAAGER_ENTERPRISE_PATH . 'assets/css/building-dashboard-enhancements.css';
    $building_dashboard_js = MOSTAAGER_ENTERPRISE_PATH . 'assets/js/building-dashboard-enhancements.js';
    $rent_dashboard_css = MOSTAAGER_ENTERPRISE_PATH . 'assets/css/rent-dashboard-enhancements.css';
    $rent_dashboard_js = MOSTAAGER_ENTERPRISE_PATH . 'assets/js/rent-dashboard-enhancements.js';
    $owner_agent_dashboard_css = MOSTAAGER_ENTERPRISE_PATH . 'assets/css/owner-agent-dashboard-enhancements.css';
    $owner_agent_dashboard_js = MOSTAAGER_ENTERPRISE_PATH . 'assets/js/owner-agent-dashboard-enhancements.js';
    $ms_current_user_id = get_current_user_id();
    $ms_is_owner = $ms_current_user_id && function_exists('ms_user_has_role') && ms_user_has_role($ms_current_user_id, 'owner');
    $ms_is_agent = $ms_current_user_id && function_exists('ms_user_has_role') && ms_user_has_role($ms_current_user_id, 'agent');
    $ms_is_tenant = $ms_current_user_id && function_exists('ms_user_has_role') && ms_user_has_role($ms_current_user_id, 'tenant');
    $ms_is_building_manager = $ms_current_user_id && function_exists('ms_user_has_role') && ms_user_has_role($ms_current_user_id, 'building_manager');

    // Dashboard-specific assets must not be loaded globally. The agent lazy loader
    // targets generic ids such as #maintenance and #invoices and otherwise replaces
    // owner/tenant panels with the agent error message.
    if ($ms_is_agent && file_exists($agent_properties_css)) {
        wp_enqueue_style('mostaager-agent-properties-ui', MS_PLUGIN_URL . 'assets/css/agent-properties-ui.css', array('mostaager-design-system'), filemtime($agent_properties_css));
    }
    if ($ms_is_agent && file_exists($agent_properties_js)) {
        wp_enqueue_script('mostaager-agent-properties-ui', MS_PLUGIN_URL . 'assets/js/agent-properties-ui.js', array('mostaager-dashboard-js'), filemtime($agent_properties_js), true);
    }
    if ($ms_is_agent && file_exists($agent_tabs_css)) {
        wp_enqueue_style('mostaager-agent-dashboard-tabs', MS_PLUGIN_URL . 'assets/css/agent-dashboard-tabs.css', array('mostaager-design-system'), filemtime($agent_tabs_css));
    }
    if ($ms_is_agent && file_exists($agent_tabs_js)) {
        wp_enqueue_script('mostaager-agent-dashboard-tabs', MS_PLUGIN_URL . 'assets/js/agent-dashboard-tabs.js', array('mostaager-dashboard-js'), filemtime($agent_tabs_js), true);
    }
    if ($ms_is_tenant && file_exists($tenant_tabs_css)) {
        wp_enqueue_style('mostaager-tenant-dashboard-enhancements', MS_PLUGIN_URL . 'assets/css/tenant-dashboard-enhancements.css', array('mostaager-design-system'), filemtime($tenant_tabs_css));
    }
    if ($ms_is_tenant && file_exists($tenant_tabs_js)) {
        wp_enqueue_script('mostaager-tenant-dashboard-enhancements', MS_PLUGIN_URL . 'assets/js/tenant-dashboard-enhancements.js', array('mostaager-dashboard-js'), filemtime($tenant_tabs_js), true);
    }
    if ($ms_is_building_manager && file_exists($building_dashboard_css)) {
        wp_enqueue_style('mostaager-building-dashboard-enhancements', MS_PLUGIN_URL . 'assets/css/building-dashboard-enhancements.css', array('mostaager-design-system'), filemtime($building_dashboard_css));
    }
    if ($ms_is_building_manager && file_exists($building_dashboard_js)) {
        wp_enqueue_script('mostaager-building-dashboard-enhancements', MS_PLUGIN_URL . 'assets/js/building-dashboard-enhancements.js', array('mostaager-dashboard-js'), filemtime($building_dashboard_js), true);
    }
    if ($ms_is_tenant && file_exists($rent_dashboard_css)) {
        wp_enqueue_style('mostaager-rent-dashboard-enhancements', MS_PLUGIN_URL . 'assets/css/rent-dashboard-enhancements.css', array('mostaager-design-system'), filemtime($rent_dashboard_css));
    }
    if ($ms_is_tenant && file_exists($rent_dashboard_js)) {
        wp_enqueue_script('mostaager-rent-dashboard-enhancements', MS_PLUGIN_URL . 'assets/js/rent-dashboard-enhancements.js', array('mostaager-dashboard-js'), filemtime($rent_dashboard_js), true);
    }
    if (($ms_is_owner || $ms_is_agent) && file_exists($owner_agent_dashboard_css)) {
        wp_enqueue_style('mostaager-owner-agent-dashboard-enhancements', MS_PLUGIN_URL . 'assets/css/owner-agent-dashboard-enhancements.css', array('mostaager-design-system'), filemtime($owner_agent_dashboard_css));
    }
    if (($ms_is_owner || $ms_is_agent) && file_exists($owner_agent_dashboard_js)) {
        wp_enqueue_script('mostaager-owner-agent-dashboard-enhancements', MS_PLUGIN_URL . 'assets/js/owner-agent-dashboard-enhancements.js', array('mostaager-dashboard-js'), filemtime($owner_agent_dashboard_js), true);
    }
    if ($ms_has_inline_property_ui && file_exists($inline_property_js)) {
        wp_enqueue_script('ms-inline-property', MS_PLUGIN_URL . 'assets/js/inline-property.js', array('mostaager-dashboard-js'), filemtime($inline_property_js), true);
        wp_localize_script('ms-inline-property', 'MostaagerInlineProperty', array(
            'storage_prefix' => 'ms_inline_property_'
        ));
    }
    if ($ms_has_profile_ui && file_exists($unified_profile_js)) {
        wp_enqueue_script('ms-unified-profile', MS_PLUGIN_URL . 'assets/js/unified-profile.js', array(), filemtime($unified_profile_js), true);
    }
    $analytics_js = MOSTAAGER_ENTERPRISE_PATH . 'assets/js/analytics.js';
    if (is_user_logged_in() && file_exists($analytics_js)) {
        wp_enqueue_script('mostaager-analytics-js', MS_PLUGIN_URL . 'assets/js/analytics.js', array('jquery', 'mostaager-dashboard-js'), filemtime($analytics_js), true);
        wp_localize_script('mostaager-analytics-js', 'MostaagerAnalytics', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mostaager-ajax-nonce'),
        ));
    }

    // Front-end dashboard notifications: lightweight polling keeps the feature
    // compatible with ordinary WordPress hosting without requiring WebSockets.
    $notifications_css = MOSTAAGER_ENTERPRISE_PATH . 'assets/css/notifications.css';
    $notifications_js = MOSTAAGER_ENTERPRISE_PATH . 'assets/js/notifications.js';
    if (is_user_logged_in() && file_exists($notifications_css)) {
        wp_enqueue_style(
            'mostaager-notifications-css',
            MS_PLUGIN_URL . 'assets/css/notifications.css',
            array('mostaager-design-system'),
            filemtime($notifications_css)
        );
    }
    if (is_user_logged_in() && file_exists($notifications_js)) {
        wp_enqueue_script(
            'mostaager-notifications-js',
            MS_PLUGIN_URL . 'assets/js/notifications.js',
            array('jquery', 'mostaager-dashboard-js'),
            filemtime($notifications_js),
            true
        );
        wp_localize_script('mostaager-notifications-js', 'MostaagerNotifications', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mostaager-ajax-nonce'),
            'poll_interval' => 20000,
            'toast_duration' => 6500,
            'i18n' => array(
                'new_notification' => 'إشعار جديد',
                'maintenance_status_changed' => 'تحديث حالة الصيانة',
            ),
        ));

        $firebase_js = MOSTAAGER_ENTERPRISE_PATH . 'assets/js/firebase-push.js';
        $firebase_config = defined('MS_FIREBASE_WEB_CONFIG') ? MS_FIREBASE_WEB_CONFIG : array();
        if (is_string($firebase_config)) $firebase_config = json_decode($firebase_config, true);
        $firebase_vapid = defined('MS_FIREBASE_WEB_VAPID_KEY') ? MS_FIREBASE_WEB_VAPID_KEY : '';
        if (file_exists($firebase_js) && is_array($firebase_config) && !empty($firebase_config['projectId']) && $firebase_vapid) {
            wp_enqueue_script('ms-firebase-app', 'https://www.gstatic.com/firebasejs/10.13.2/firebase-app-compat.js', array(), '10.13.2', true);
            wp_enqueue_script('ms-firebase-messaging', 'https://www.gstatic.com/firebasejs/10.13.2/firebase-messaging-compat.js', array('ms-firebase-app'), '10.13.2', true);
            wp_enqueue_script('ms-firebase-push', MS_PLUGIN_URL . 'assets/js/firebase-push.js', array('ms-firebase-messaging', 'mostaager-notifications-js'), filemtime($firebase_js), true);
            wp_localize_script('ms-firebase-push', 'MostaagerFirebase', array(
                'enabled' => true,
                'config' => $firebase_config,
                'vapid_key' => sanitize_text_field($firebase_vapid),
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mostaager-ajax-nonce'),
            ));
        }
    }
});

// Load notification assets
add_action('admin_enqueue_scripts', function() {
    $notifications_css = MOSTAAGER_ENTERPRISE_PATH . 'assets/css/notifications.css';
    if (file_exists($notifications_css)) {
        wp_enqueue_style(
            'mostaager-notifications-css',
            MS_PLUGIN_URL . 'assets/css/notifications.css',
            array(),
            file_exists($notifications_css) ? filemtime($notifications_css) : '1.0'
        );
    }
    
    $notifications_js = MOSTAAGER_ENTERPRISE_PATH . 'assets/js/notifications.js';
    if (file_exists($notifications_js)) {
        wp_enqueue_script(
            'mostaager-notifications-js',
            MS_PLUGIN_URL . 'assets/js/notifications.js',
            array('jquery'),
            file_exists($notifications_js) ? filemtime($notifications_js) : '1.0',
            true
        );
    }
});

add_action('template_redirect', function () {
    $path = wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (rtrim((string) $path, '/') !== '/firebase-messaging-sw.js') return;
    $config = defined('MS_FIREBASE_WEB_CONFIG') ? MS_FIREBASE_WEB_CONFIG : array();
    if (is_string($config)) $config = json_decode($config, true);
    if (!is_array($config) || empty($config['projectId'])) {
        status_header(404);
        exit;
    }
    nocache_headers();
    header('Content-Type: application/javascript; charset=utf-8');
    echo "importScripts('https://www.gstatic.com/firebasejs/10.13.2/firebase-app-compat.js');\n";
    echo "importScripts('https://www.gstatic.com/firebasejs/10.13.2/firebase-messaging-compat.js');\n";
    echo 'firebase.initializeApp(' . wp_json_encode($config) . ");\n";
    echo "const messaging = firebase.messaging();\n";
    echo "messaging.onBackgroundMessage(function(payload) { const n = payload.notification || {}; self.registration.showNotification(n.title || 'إشعار جديد', { body: n.body || '', icon: n.icon || '/favicon.ico', data: { link: (payload.data || {}).link || '/' } }); });\n";
    echo "self.addEventListener('notificationclick', function(event) { event.notification.close(); const link = event.notification.data && event.notification.data.link ? event.notification.data.link : '/'; event.waitUntil(clients.openWindow(link)); });\n";
    exit;
});

if (defined('WP_CLI') && WP_CLI) {

    require_once MOSTAAGER_ENTERPRISE_PATH . 'core/cli.php';
}

// Add monthly cron schedule
add_filter('cron_schedules', function ($schedules) {
    if (!isset($schedules['monthly'])) {
        $schedules['monthly'] = array(
            'interval' => 30 * DAY_IN_SECONDS,
            'display' => __('Once Monthly', 'mostaager-facility-pro')
        );
    }
    return $schedules;
});

// Schedule recurring maintenance cron
add_action('init', function () {
    // Skip during installation
    if (defined('WP_INSTALLING') && WP_INSTALLING) {
        return;
    }

    if (!wp_next_scheduled('ms_recurring_maintenance_cron')) {
        wp_schedule_event(time(), 'monthly', 'ms_recurring_maintenance_cron');
    }
});

add_action('ms_recurring_maintenance_cron', 'ms_process_recurring_maintenance');

