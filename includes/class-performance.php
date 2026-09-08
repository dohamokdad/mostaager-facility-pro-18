<?php
/**
 * Performance Optimizer - Mostaager Facility PRO Add-On
 */

if (!defined('ABSPATH')) {
    exit;
}

class MS_Performance_Optimizer {

    public function __construct() {
        // Optimize database queries
        add_action('pre_get_posts', array($this, 'optimize_property_queries'));
        
        // Add performance monitoring (delayed to avoid activation issues)
        add_action('admin_init', array($this, 'delayed_performance_monitoring'));
        
        // Cleanup routine
        add_action('ms_cleanup_cron', array($this, 'cleanup_old_data'));
        
        // Schedule cleanup
        if (!wp_next_scheduled('ms_cleanup_cron')) {
            wp_schedule_event(time(), 'daily', 'ms_cleanup_cron');
        }
    }
    
    /**
     * Optimize property queries
     */
    public function optimize_property_queries($query) {
        if (!is_admin() && $query->get('post_type') === 'property') {
            // Enable lazy loading for better performance
            $query->set('lazy_load_term_meta', true);
            $query->set('lazy_load_meta', true);
        }
    }
    
    /**
     * Add performance monitoring
     */
    public function add_performance_monitoring() {
        // This function is deprecated - monitoring is now handled directly in constructor
    }
    
    /**
     * Delayed performance monitoring
     */
    public function delayed_performance_monitoring() {
        if (current_user_can('manage_options')) {
            add_action('admin_notices', array($this, 'show_performance_alerts'));
        }
    }
    
    /**
     * Show performance alerts
     */
    public function show_performance_alerts() {
        // Don't show alerts during plugin activation
        if (defined('WP_INSTALLING') && WP_INSTALLING) {
            return;
        }
        
        // Only show in admin
        if (!is_admin()) {
            return;
        }
        
        // Only show for administrators
        if (!current_user_can('manage_options')) {
            return;
        }
        
        try {
            $performance_data = $this->get_performance_data();
            
            if ($performance_data['slow_queries'] > 5) {
                echo '<div class="notice notice-warning">';
                echo '<p>⚠️ <strong>تنبيه الأداء:</strong> تم رصد استعلامات بطيئة في إضافة Mostaager. يُنصح بتحسين قاعدة البيانات.</p>';
                echo '</div>';
            }
            
            if ($performance_data['cache_hit_rate'] !== null && $performance_data['cache_hit_rate'] < 70) {
                echo '<div class="notice notice-info">';
                echo '<p>ℹ️ <strong>تحسين الأداء:</strong> معدل ضربات الذاكرة المؤقتة منخفض. يُنصح بتفعيل التخزين المؤقت.</p>';
                echo '</div>';
            }
        } catch (Exception $e) {
            // Silently fail to avoid breaking the admin
        }
    }
    
    /**
     * Get performance data
     */
    public function get_performance_data() {
        return array(
            'slow_queries' => $this->count_slow_queries(),
            'cache_hit_rate' => $this->calculate_cache_hit_rate(),
            'memory_usage' => $this->get_memory_usage(),
            'page_load_time' => $this->get_page_load_time()
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
     * Calculate cache hit rate
     */
    private function calculate_cache_hit_rate() {
        // Hit-rate counters are unavailable without an owning cache backend.
        return null;
    }
    
    /**
     * Get memory usage
     */
    private function get_memory_usage() {
        $memory_limit = ini_get('memory_limit');
        $memory_used = memory_get_usage(true);
        
        return array(
            'used' => $this->format_bytes($memory_used),
            'limit' => $memory_limit,
            'percentage' => round(($memory_used / $this->return_bytes($memory_limit)) * 100, 1)
        );
    }
    
    /**
     * Get page load time
     */
    private function get_page_load_time() {
        if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
            return round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 3);
        }
        return 0;
    }
    
    /**
     * Format bytes
     */
    private function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    /**
     * Return bytes from string
     */
    private function return_bytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        
        // Convert to float first to avoid non-numeric value warning
        $val = floatval($val);
        
        switch ($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        
        return $val;
    }
    
    /**
     * Cleanup old data
     */
    public function cleanup_old_data() {
        global $wpdb;
        
        // Clean old import logs
        $retention_days = get_option('ms_log_retention_days', 30);
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}ms_import_logs 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $retention_days
        ));
        
        // Optimize tables
        $tables = array(
            $wpdb->prefix . 'ms_import_logs',
            $wpdb->prefix . 'ms_buildings',
            $wpdb->prefix . 'ms_unit_tenants',
            $wpdb->prefix . 'ms_invoices'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("OPTIMIZE TABLE $table");
        }
        
        // Record cleanup
        update_option('ms_last_cleanup', current_time('mysql'));
    }
    
    /**
     * Bulk import optimization
     */
    public function optimize_bulk_import($data) {
        $chunk_size = 100; // Process 100 records at a time
        $chunks = array_chunk($data, $chunk_size);
        
        $results = array(
            'total' => count($data),
            'processed' => 0,
            'failed' => 0,
            'errors' => array()
        );
        
        foreach ($chunks as $chunk) {
            foreach ($chunk as $record) {
                $result = $this->process_single_record($record);
                
                if ($result['success']) {
                    $results['processed']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = $result['error'];
                }
            }
            
            // Clear cache between chunks
            $this->clear_cache();
            
            // Prevent memory exhaustion
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }
        
        return $results;
    }
    
    /**
     * Process single record
     */
    private function process_single_record($record) {
        // Implementation would depend on specific record type
        // This is a placeholder for the actual implementation
        return array('success' => true);
    }
    
    /**
     * Clear cache
     */
    public function clear_cache() {
        // Cache invalidation is handled by the owning feature to avoid flushing
        // unrelated WordPress, WooCommerce, or Houzez cache entries.
        delete_transient('ms_analytics_data_cache');
        delete_transient('ms_chart_data_cache');
    }
    
    /**
     * Get performance report
     */
    public function get_performance_report() {
        return array(
            'general' => $this->get_performance_data(),
            'database' => $this->get_database_stats(),
            'cache' => $this->get_cache_stats(),
            'recommendations' => $this->get_performance_recommendations()
        );
    }
    
    /**
     * Get database stats
     */
    private function get_database_stats() {
        global $wpdb;
        
        $tables = array(
            'properties' => $wpdb->posts,
            'import_logs' => $wpdb->prefix . 'ms_import_logs',
            'buildings' => $wpdb->prefix . 'ms_buildings',
            'tenants' => $wpdb->prefix . 'ms_unit_tenants'
        );
        
        $stats = array();
        
        foreach ($tables as $name => $table) {
            $result = $wpdb->get_row("SHOW TABLE STATUS LIKE '$table'");
            if ($result) {
                $stats[$name] = array(
                    'rows' => $result->Rows,
                    'size' => $this->format_bytes($result->Data_length + $result->Index_length),
                    'engine' => $result->Engine
                );
            }
        }
        
        return $stats;
    }
    
    /**
     * Get cache stats
     */
    private function get_cache_stats() {
        return array(
            'persistent_object_cache' => wp_using_ext_object_cache(),
            'hit_rate' => null
        );
    }
    
    /**
     * Get performance recommendations
     */
    private function get_performance_recommendations() {
        $recommendations = array();
        
        $performance_data = $this->get_performance_data();
        
        if ($performance_data['memory_usage']['percentage'] > 80) {
            $recommendations[] = array(
                'priority' => 'high',
                'message' => 'زيادة حد الذاكرة لمنهج الذاكرة',
                'action' => 'increase_memory_limit'
            );
        }
        
        if ($performance_data['slow_queries'] > 5) {
            $recommendations[] = array(
                'priority' => 'medium',
                'message' => 'تحسين استعلامات قاعدة البيانات',
                'action' => 'optimize_database'
            );
        }
        
        return $recommendations;
    }
}

// Initialize performance optimizer - skip during installation
if (!defined('WP_INSTALLING') || !WP_INSTALLING) {
    new MS_Performance_Optimizer();
}
