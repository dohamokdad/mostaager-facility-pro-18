<?php
/*
Plugin Name: Mostaager Facility PRO
Plugin URI:  https://ejar-egy.com
Description: نظام إدارة المرافق المتكامل لقالب Houzez — يتيح للناطور إنشاء طلبات صيانة وتوزيع تكاليفها على الشقق وتحصيل المبالغ عبر فواتير إلكترونية وإدارة محفظة المبنى. يدعم الدفع عبر WooCommerce مع بوابة Telr. يتضمن نظام استيراد متقدم، تقارير تحليلية، أتمتة ذكية، ومراقبة في الوقت الفعلي.
Version:     18.0.0
Author:      Doha Mokdad
Text Domain: mostaager-facility
*/

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MOSTAAGER_ENTERPRISE_PATH', plugin_dir_path(__FILE__));
define('MOSTAAGER_ENTERPRISE_URL', plugin_dir_url(__FILE__));
define('MOSTAAGER_ENTERPRISE_VERSION', '18.0.0');

if (!defined('MS_PLUGIN_PATH')) {
    define('MS_PLUGIN_PATH', MOSTAAGER_ENTERPRISE_PATH);
}
if (!defined('MS_PLUGIN_URL')) {
    define('MS_PLUGIN_URL', MOSTAAGER_ENTERPRISE_URL);
}
if (!defined('MOSTAGER_PLUGIN_DIR')) {
    define('MOSTAGER_PLUGIN_DIR', MOSTAAGER_ENTERPRISE_PATH);
}
if (!defined('MOSTAGER_PLUGIN_URL')) {
    define('MOSTAGER_PLUGIN_URL', MOSTAAGER_ENTERPRISE_URL);
}

// Backward-compatible path constant
if (!defined('MOSTAAGER_FACILITY_PRO_PATH')) {
    define('MOSTAAGER_FACILITY_PRO_PATH', MOSTAAGER_ENTERPRISE_PATH);
}

// Disable WP_DEBUG_DISPLAY on production for security
if (!defined('WP_DEBUG_DISPLAY')) {
    define('WP_DEBUG_DISPLAY', false);
}

// Disable vendor autoload completely - TCPDF causes too many issues
// The PDF functionality will be handled without autoload

// Load everything in plugins_loaded to avoid unexpected output during activation
add_action('plugins_loaded', function() {
    // Don't load during activation to prevent output
    if (defined('WP_INSTALLING') && WP_INSTALLING) {
        return;
    }

    // Load core bootstrap first
    if (file_exists(MOSTAAGER_ENTERPRISE_PATH . 'core/bootstrap.php')) {
        require_once MOSTAAGER_ENTERPRISE_PATH . 'core/bootstrap.php';
    }

    // Load enhancement classes (with file existence checks)
    $enhancement_classes = array(
        'includes/class-dashboard-charts.php',
        'includes/class-invoice-pdf.php',
        'includes/class-maintenance-api.php',
        'includes/class-whatsapp-integration.php',
        'includes/telr-integration.php',
        'mostaager-facility-pro-add-on.php',
        'admin/dashboard.php',
        'includes/class-validator.php',
        'includes/class-templates.php',
        'includes/class-houzez-integration.php',
        // 'includes/class-houzez-building-integration.php', // Temporarily disabled due to compatibility issues
        'includes/class-reports.php',
        'includes/class-custom-fields.php',
        'includes/class-performance.php',
        'includes/class-integrations.php',
        'includes/class-automation.php',
        'includes/class-automation-importer.php',
        'includes/class-data-sync.php',
        'includes/class-notifications.php',
        'includes/class-backup.php',
        'includes/class-advanced-integrations.php',
        'includes/class-monitoring.php',
        'includes/class-analytics.php',
        'includes/class-user-management.php',
                'includes/class-advanced-settings.php'

    );

    foreach ($enhancement_classes as $class_file) {
        $file_path = MOSTAAGER_ENTERPRISE_PATH . $class_file;
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
}, 10);

register_activation_hook(__FILE__, 'ms_run_installer');
register_deactivation_hook(__FILE__, 'ms_cleanup_on_deactivate');

function ms_run_installer()
{
    if (!function_exists('ms_create_tables')) {
        return;
    }

    ms_create_tables();
    ms_create_notifications_table();
    ms_create_user_activities_table();

    if (function_exists('ms_register_facility_roles')) {
        ms_register_facility_roles();
    }

    if (function_exists('ms_create_default_facility_types')) {
        ms_create_default_facility_types();
    }

    if (function_exists('ms_initialize_facility_database')) {
        ms_initialize_facility_database();
    }

    flush_rewrite_rules();
}

function ms_create_notifications_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'ms_notifications';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED DEFAULT 0,
        recipient varchar(255) DEFAULT '',
        message text,
        channels text,
        type varchar(100) NOT NULL DEFAULT 'custom',
        status varchar(20) NOT NULL DEFAULT 'sent',
        results longtext,
        building_id bigint(20) UNSIGNED DEFAULT 0,
        related_id bigint(20) UNSIGNED DEFAULT 0,
        is_read tinyint(1) NOT NULL DEFAULT 0,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_read_created (user_id, is_read, created_at),
        KEY recipient_status (recipient, status),
        KEY type_created (type, created_at)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function ms_create_user_activities_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'ms_user_activities';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        action varchar(100) NOT NULL,
        details text,
        ip_address varchar(45),
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY action (action),
        KEY created_at (created_at)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function ms_cleanup_on_deactivate()
{
    // Clean up any transient data
    delete_transient('ms_facility_data_cache');
    delete_transient('ms_maintenance_cache');
    delete_transient('ms_notification_cache');

    flush_rewrite_rules();
}
