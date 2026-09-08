<?php
/**
 * Admin Dashboard - Mostaager Facility PRO Add-On
 */

if (!defined('ABSPATH')) {
    exit;
}

class MS_Addon_Dashboard {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        add_menu_page(
            'إدارة الاستيراد',
            'إدارة الاستيراد',
            'manage_options',
            'ms-import-dashboard',
            array($this, 'render_dashboard'),
            'dashicons-download',
            30
        );
        
        add_submenu_page(
            'ms-import-dashboard',
            'لوحة التحكم',
            'لوحة التحكم',
            'manage_options',
            'ms-import-dashboard',
            array($this, 'render_dashboard')
        );
        
        add_submenu_page(
            'ms-import-dashboard',
            'الاستيراد الجديد',
            'استيراد جديد',
            'manage_options',
            'ms-import-new',
            array($this, 'render_import_wizard')
        );
        
        add_submenu_page(
            'ms-import-dashboard',
            'القوالب',
            'القوالب',
            'manage_options',
            'ms-import-templates',
            array($this, 'render_templates')
        );
        
        add_submenu_page(
            'ms-import-dashboard',
            'السجلات',
            'السجلات',
            'manage_options',
            'ms-import-logs',
            array($this, 'render_logs')
        );
        
        add_submenu_page(
            'ms-import-dashboard',
            'الإعدادات',
            'الإعدادات',
            'manage_options',
            'ms-import-settings',
            array($this, 'render_settings')
        );
        
        add_submenu_page(
            'ms-import-dashboard',
            'الأتمتة',
            'الأتمتة',
            'manage_options',
            'ms-import-automation',
            array($this, 'render_automation')
        );
        
        add_submenu_page(
            'ms-import-dashboard',
            'المراقبة',
            'المراقبة',
            'manage_options',
            'ms-import-monitoring',
            array($this, 'render_monitoring')
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'ms-import') !== false) {
            wp_enqueue_style('ms-addon-admin', plugin_dir_url(__FILE__) . '../assets/css/admin.css', array(), '1.1.0');
            wp_enqueue_script('ms-addon-admin', plugin_dir_url(__FILE__) . '../assets/js/admin.js', array('jquery'), '1.1.0', true);
            
            // Pass data to JavaScript
            wp_localize_script('ms-addon-admin', 'msAddonData', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ms_addon_nonce'),
                'adminUrl' => admin_url('admin.php?page=ms-import-new'),
                'strings' => array(
                    'importSuccess' => 'تم الاستيراد بنجاح',
                    'importError' => 'حدث خطأ أثناء الاستيراد',
                    'confirmDelete' => 'هل أنت متأكد من الحذف؟',
                    'selectSource' => 'الرجاء اختيار مصدر البيانات',
                    'mapRequiredFields' => 'الرجاء ربط جميع الحقول المطلوبة'
                )
            ));
        }
    }
    
    /**
     * Render main dashboard
     */
    public function render_dashboard() {
        $stats = $this->get_dashboard_stats();
        $recent_imports = $this->get_recent_imports();
        
        include dirname(__FILE__) . '/views/dashboard.php';
    }
    
    /**
     * Render import wizard
     */
    public function render_import_wizard() {
        $templates = $this->get_available_templates();
        
        include dirname(__FILE__) . '/views/import-wizard.php';
    }
    
    /**
     * Render templates page
     */
    public function render_templates() {
        $templates = $this->get_all_templates();
        
        include dirname(__FILE__) . '/views/templates.php';
    }
    
    /**
     * Render logs page
     */
    public function render_logs() {
        $logs = $this->get_import_logs();
        
        include dirname(__FILE__) . '/views/logs.php';
    }
    
    /**
     * Render settings page
     */
    public function render_settings() {
        $settings = $this->get_settings();
        
        include dirname(__FILE__) . '/views/settings.php';
    }
    
    /**
     * Render automation page
     */
    public function render_automation() {
        $scheduled_tasks = get_option('ms_scheduled_tasks', array());
        $automation_rules = get_option('ms_automation_rules', array());
        $automation_logs = get_option('ms_automation_logs', array());
        
        // Calculate statistics
        $successful_runs = 0;
        $total_duration = 0;
        
        foreach ($automation_logs as $log) {
            if ($log['success']) {
                $successful_runs++;
            }
            // Assuming duration might be in the log data
            if (isset($log['duration'])) {
                $total_duration += $log['duration'];
            }
        }
        
        $avg_duration = count($automation_logs) > 0 ? round($total_duration / count($automation_logs), 2) : 0;
        
        include dirname(__FILE__) . '/views/automation.php';
    }
    
    /**
     * Render monitoring page
     */
    public function render_monitoring() {
        $metrics = get_option('ms_metrics', array());
        $alerts = get_option('ms_alerts', array());
        $hourly_reports = get_option('ms_hourly_reports', array());
        
        include dirname(__FILE__) . '/views/monitoring.php';
    }
    
    /**
     * Get dashboard statistics
     */
    private function get_dashboard_stats() {
        global $wpdb;
        
        $stats = array(
            'properties' => 0,
            'buildings' => 0,
            'tenants' => 0,
            'imports' => 0,
            'success_rate' => 0
        );
        
        // Count properties
        $stats['properties'] = wp_count_posts('property')->publish;
        
        // Count buildings
        $stats['buildings'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ms_buildings");
        
        // Count tenants
        $stats['tenants'] = $wpdb->get_var("SELECT COUNT(DISTINCT tenant_id) FROM {$wpdb->prefix}ms_unit_tenants WHERE status = 'active'");
        
        // Count imports
        $stats['imports'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ms_import_logs");
        
        // Calculate success rate
        $success_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ms_import_logs WHERE status = 'success'");
        $stats['success_rate'] = $stats['imports'] > 0 ? round(($success_count / $stats['imports']) * 100, 1) : 0;
        
        return $stats;
    }
    
    /**
     * Get recent imports
     */
    private function get_recent_imports($limit = 5) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ms_import_logs 
            ORDER BY created_at DESC 
            LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Get available templates
     */
    private function get_available_templates() {
        return array(
            'buildings' => array(
                'id' => 'buildings',
                'name' => 'قالب المباني',
                'icon' => '🏗️',
                'description' => 'قالب جاهز لاستيراد بيانات المباني',
                'fields' => array('building_title', 'manager_id', 'supervisor_name', 'supervisor_phone')
            ),
            'properties' => array(
                'id' => 'properties',
                'name' => 'قالب العقارات',
                'icon' => '🏠',
                'description' => 'قالب جاهز لاستيراد بيانات العقارات',
                'fields' => array('unit_number', 'floor', 'unit_type', 'unit_status', 'building_title')
            ),
            'tenants' => array(
                'id' => 'tenants',
                'name' => 'قالب المستأجرين',
                'icon' => '👥',
                'description' => 'قالب جاهز لاستيراد بيانات المستأجرين',
                'fields' => array('tenant_name', 'tenant_email', 'tenant_phone', 'national_id')
            ),
            'full' => array(
                'id' => 'full',
                'name' => 'قالب شامل',
                'icon' => '📋',
                'description' => 'قالب شامل لاستيراد جميع البيانات',
                'fields' => array('building_title', 'unit_number', 'unit_type', 'tenant_name', 'monthly_rent')
            )
        );
    }
    
    /**
     * Get all custom templates
     */
    private function get_all_templates() {
        return get_option('ms_custom_templates', array());
    }
    
    /**
     * Get import logs
     */
    private function get_import_logs($limit = 20) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ms_import_logs 
            ORDER BY created_at DESC 
            LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Get settings
     */
    private function get_settings() {
        return get_option('ms_import_settings', array(
            'auto_validation' => true,
            'create_users' => true,
            'send_notifications' => true,
            'default_status' => 'available',
            'logging_enabled' => true
        ));
    }
}

// Initialize dashboard
new MS_Addon_Dashboard();
