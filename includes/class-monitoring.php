<?php
/**
 * Monitoring and Analytics System - Mostaager Facility PRO Add-On
 */

if (!defined('ABSPATH')) {
    exit;
}

class MS_Monitoring_Analytics {
    
    private $metrics;
    private $alerts;
    
    public function __construct() {
        // Initialize monitoring
        add_action('init', array($this, 'init_monitoring'));
        
        // Schedule monitoring tasks
        add_action('ms_monitoring_cron', array($this, 'execute_monitoring'));
        
        // AJAX handlers
        add_action('wp_ajax_ms_get_analytics', array($this, 'get_analytics'));
        add_action('wp_ajax_ms_get_real_time_stats', array($this, 'get_real_time_stats'));
        add_action('wp_ajax_ms_set_alert', array($this, 'set_alert'));
        
        // Track events
        add_action('ms_import_complete', array($this, 'track_import_event'));
        add_action('ms_tenant_assigned', array($this, 'track_tenant_event'));
        add_action('ms_invoice_created', array($this, 'track_invoice_event'));
    }
    
    /**
     * Initialize monitoring
     */
    public function init_monitoring() {
        // Schedule monitoring cron
        if (!wp_next_scheduled('ms_monitoring_cron')) {
            wp_schedule_event(time(), 'hourly', 'ms_monitoring_cron');
        }
        
        // Load metrics
        $this->metrics = get_option('ms_metrics', array());
        
        // Load alerts
        $this->alerts = get_option('ms_alerts', array());
    }
    
    /**
     * Execute monitoring
     */
    public function execute_monitoring() {
        // Collect metrics
        $this->collect_metrics();
        
        // Check alerts
        $this->check_alerts();
        
        // Generate reports
        $this->generate_hourly_report();
    }
    
    /**
     * Collect metrics
     */
    private function collect_metrics() {
        $current_time = current_time('mysql');
        
        // System metrics
        $system_metrics = array(
            'timestamp' => $current_time,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            'server_load' => $this->get_server_load()
        );
        
        // Database metrics
        $db_metrics = $this->get_database_metrics();
        
        // Application metrics
        $app_metrics = $this->get_application_metrics();
        
        // Store metrics
        $this->metrics['system'][] = $system_metrics;
        $this->metrics['database'][] = $db_metrics;
        $this->metrics['application'][] = $app_metrics;
        
        // Keep only last 24 hours of metrics
        $this->cleanup_old_metrics();
        
        update_option('ms_metrics', $this->metrics);
    }
    
    /**
     * Get server load
     */
    private function get_server_load() {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return array(
                '1min' => $load[0],
                '5min' => $load[1],
                '15min' => $load[2]
            );
        }
        
        return array('1min' => 0, '5min' => 0, '15min' => 0);
    }
    
    /**
     * Get database metrics
     */
    private function get_database_metrics() {
        global $wpdb;
        
        return array(
            'query_count' => $wpdb->num_queries,
            'query_time' => $wpdb->timer_stop(),
            'slow_queries' => $this->count_slow_queries(),
            'table_sizes' => $this->get_table_sizes()
        );
    }
    
    /**
     * Count slow queries
     */
    private function count_slow_queries() {
        global $wpdb;
        
        $slow_queries = $wpdb->get_var(
            "SELECT COUNT(*) FROM information_schema.processlist 
            WHERE user = DB_USER() AND time > 1"
        );
        
        return intval($slow_queries);
    }
    
    /**
     * Get table sizes
     */
    private function get_table_sizes() {
        global $wpdb;
        
        $tables = array(
            'posts' => $wpdb->posts,
            'postmeta' => $wpdb->postmeta,
            'ms_import_logs' => $wpdb->prefix . 'ms_import_logs',
            'ms_buildings' => $wpdb->prefix . 'ms_buildings',
            'ms_unit_tenants' => $wpdb->prefix . 'ms_unit_tenants'
        );
        
        $sizes = array();
        
        foreach ($tables as $name => $table) {
            $result = $wpdb->get_row("SHOW TABLE STATUS LIKE '$table'");
            if ($result) {
                $sizes[$name] = array(
                    'rows' => $result->Rows,
                    'size' => $result->Data_length + $result->Index_length
                );
            }
        }
        
        return $sizes;
    }
    
    /**
     * Get application metrics
     */
    private function get_application_metrics() {
        return array(
            'active_users' => count_users(),
            'properties_count' => wp_count_posts('property')->publish,
            'imports_today' => $this->count_imports_today(),
            'errors_today' => $this->count_errors_today(),
            'success_rate' => $this->calculate_success_rate()
        );
    }
    
    /**
     * Count imports today
     */
    private function count_imports_today() {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ms_import_logs 
            WHERE DATE(created_at) = CURDATE()"
        ));
    }
    
    /**
     * Count errors today
     */
    private function count_errors_today() {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ms_import_logs 
            WHERE DATE(created_at) = CURDATE() AND status = 'failed'"
        ));
    }
    
    /**
     * Calculate success rate
     */
    private function calculate_success_rate() {
        global $wpdb;
        
        $total = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ms_import_logs 
            WHERE DATE(created_at) = CURDATE()"
        );
        
        $successful = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ms_import_logs 
            WHERE DATE(created_at) = CURDATE() AND status = 'success'"
        );
        
        return $total > 0 ? round(($successful / $total) * 100, 1) : 0;
    }
    
    /**
     * Cleanup old metrics
     */
    private function cleanup_old_metrics() {
        $cutoff_time = time() - (24 * 3600); // 24 hours ago
        
        foreach ($this->metrics as $type => $metrics) {
            $this->metrics[$type] = array_filter($metrics, function($metric) use ($cutoff_time) {
                return strtotime($metric['timestamp']) > $cutoff_time;
            });
        }
    }
    
    /**
     * Check alerts
     */
    private function check_alerts() {
        foreach ($this->alerts as $alert_id => $alert) {
            if (!$alert['active']) {
                continue;
            }
            
            $triggered = $this->check_alert_condition($alert);
            
            if ($triggered) {
                $this->send_alert_notification($alert);
            }
        }
    }
    
    /**
     * Check alert condition
     */
    private function check_alert_condition($alert) {
        $metric = $alert['metric'];
        $operator = $alert['operator'];
        $threshold = $alert['threshold'];
        
        $current_value = $this->get_metric_value($metric);
        
        switch ($operator) {
            case 'greater_than':
                return $current_value > $threshold;
            case 'less_than':
                return $current_value < $threshold;
            case 'equals':
                return $current_value == $threshold;
            default:
                return false;
        }
    }
    
    /**
     * Get metric value
     */
    private function get_metric_value($metric) {
        switch ($metric) {
            case 'memory_usage':
                return memory_get_usage(true);
            case 'server_load':
                $load = sys_getloadavg();
                return $load[0];
            case 'slow_queries':
                return $this->count_slow_queries();
            case 'error_rate':
                return 100 - $this->calculate_success_rate();
            case 'response_time':
                return microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
            default:
                return 0;
        }
    }
    
    /**
     * Send alert notification
     */
    private function send_alert_notification($alert) {
        $recipients = $alert['recipients'];
        $message = $this->format_alert_message($alert);
        
        $notification_system = new MS_Multi_Channel_Notifications();
        $notification_system->send_notification($recipients, $message, array('email'));
    }
    
    /**
     * Format alert message
     */
    private function format_alert_message($alert) {
        $message = '<div style="font-family: Arial, sans-serif;">';
        $message .= '<h2>⚠️ تنبيه النظام</h2>';
        $message .= '<p><strong>المؤشر:</strong> ' . $alert['metric'] . '</p>';
        $message .= '<p><strong>القيمة الحالية:</strong> ' . $this->get_metric_value($alert['metric']) . '</p>';
        $message .= '<p><strong>الحد:</strong> ' . $alert['threshold'] . '</p>';
        $message .= '<p><strong>الوقت:</strong> ' . current_time('mysql') . '</p>';
        $message .= '</div>';
        
        return $message;
    }
    
    /**
     * Generate hourly report
     */
    private function generate_hourly_report() {
        $report = array(
            'timestamp' => current_time('mysql'),
            'system' => end($this->metrics['system']),
            'database' => end($this->metrics['database']),
            'application' => end($this->metrics['application'])
        );
        
        $reports = get_option('ms_hourly_reports', array());
        array_unshift($reports, $report);
        
        // Keep only last 24 hours
        if (count($reports) > 24) {
            $reports = array_slice($reports, 0, 24);
        }
        
        update_option('ms_hourly_reports', $reports);
    }
    
    /**
     * Get analytics via AJAX
     */
    public function get_analytics() {
        check_ajax_referer('ms_get_analytics', 'nonce');
        
        $period = sanitize_text_field($_POST['period']);
        $metrics = sanitize_text_field($_POST['metrics']);
        
        $analytics = $this->generate_analytics($period, $metrics);
        
        wp_send_json_success($analytics);
    }
    
    /**
     * Generate analytics
     */
    private function generate_analytics($period, $metrics) {
        $reports = get_option('ms_hourly_reports', array());
        
        $filtered_reports = $this->filter_reports_by_period($reports, $period);
        
        $analytics = array(
            'period' => $period,
            'data' => array()
        );
        
        foreach ($filtered_reports as $report) {
            $report_data = array(
                'timestamp' => $report['timestamp']
            );
            
            foreach ($metrics as $metric) {
                $report_data[$metric] = $this->extract_metric($report, $metric);
            }
            
            $analytics['data'][] = $report_data;
        }
        
        return $analytics;
    }
    
    /**
     * Filter reports by period
     */
    private function filter_reports_by_period($reports, $period) {
        $cutoff_time = time();
        
        switch ($period) {
            case '1h':
                $cutoff_time -= 3600;
                break;
            case '24h':
                $cutoff_time -= 86400;
                break;
            case '7d':
                $cutoff_time -= 604800;
                break;
            case '30d':
                $cutoff_time -= 2592000;
                break;
        }
        
        return array_filter($reports, function($report) use ($cutoff_time) {
            return strtotime($report['timestamp']) > $cutoff_time;
        });
    }
    
    /**
     * Extract metric from report
     */
    private function extract_metric($report, $metric) {
        $metric_parts = explode('.', $metric);
        
        if (count($metric_parts) === 2) {
            $section = $metric_parts[0];
            $key = $metric_parts[1];
            
            if (isset($report[$section][$key])) {
                return $report[$section][$key];
            }
        }
        
        return 0;
    }
    
    /**
     * Get real-time stats via AJAX
     */
    public function get_real_time_stats() {
        check_ajax_referer('ms_get_real_time_stats', 'nonce');
        
        $stats = array(
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            'server_load' => $this->get_server_load(),
            'active_users' => count_users(),
            'database_queries' => $GLOBALS['wpdb']->num_queries
        );
        
        wp_send_json_success($stats);
    }
    
    /**
     * Set alert via AJAX
     */
    public function set_alert() {
        check_ajax_referer('ms_set_alert', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('error' => 'ليس لديك الصلاحية'));
        }
        
        $alert = array(
            'id' => uniqid('alert_'),
            'name' => sanitize_text_field($_POST['alert_name']),
            'metric' => sanitize_text_field($_POST['metric']),
            'operator' => sanitize_text_field($_POST['operator']),
            'threshold' => floatval($_POST['threshold']),
            'recipients' => json_decode(stripslashes($_POST['recipients']), true),
            'active' => true,
            'created_at' => current_time('mysql')
        );
        
        $this->alerts[$alert['id']] = $alert;
        update_option('ms_alerts', $this->alerts);
        
        wp_send_json_success(array('alert' => $alert));
    }
    
    /**
     * Track import event
     */
    public function track_import_event($import_data) {
        $event = array(
            'type' => 'import',
            'timestamp' => current_time('mysql'),
            'data' => $import_data
        );
        
        $this->track_event($event);
    }
    
    /**
     * Track tenant event
     */
    public function track_tenant_event($tenant_data) {
        $event = array(
            'type' => 'tenant',
            'timestamp' => current_time('mysql'),
            'data' => $tenant_data
        );
        
        $this->track_event($event);
    }
    
    /**
     * Track invoice event
     */
    public function track_invoice_event($invoice_data) {
        $event = array(
            'type' => 'invoice',
            'timestamp' => current_time('mysql'),
            'data' => $invoice_data
        );
        
        $this->track_event($event);
    }
    
    /**
     * Track event
     */
    private function track_event($event) {
        $events = get_option('ms_events', array());
        array_unshift($events, $event);
        
        // Keep only last 1000 events
        if (count($events) > 1000) {
            $events = array_slice($events, 0, 1000);
        }
        
        update_option('ms_events', $events);
    }
}

// Initialize monitoring and analytics - skip during installation
if (!defined('WP_INSTALLING') || !WP_INSTALLING) {
    new MS_Monitoring_Analytics();
}
