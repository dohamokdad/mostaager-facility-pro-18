<?php
/**
 * Advanced Analytics - Mostaager Facility PRO Add-On
 * Data visualization, predictive analytics, and business intelligence
 */

if (!defined('ABSPATH')) {
    exit;
}

class MS_Advanced_Analytics {
    
    private $analytics_data;
    private $charts_data;
    private $cache_expiry = 1800; // 30 minutes cache
    
    public function __construct() {
        add_action('wp_ajax_ms_get_analytics_data', array($this, 'get_analytics_data'));
        add_action('wp_ajax_ms_get_chart_data', array($this, 'get_chart_data'));
        add_action('wp_ajax_ms_get_predictions', array($this, 'get_predictions'));
        add_action('wp_ajax_ms_export_analytics', array($this, 'export_analytics'));
        add_action('wp_ajax_ms_clear_analytics_cache', array($this, 'clear_analytics_cache_ajax'));
        
        // Invalidate cache when underlying data changes
        add_action('ms_invoice_created', array($this, 'invalidate_analytics_cache'));
        add_action('ms_maintenance_updated', array($this, 'invalidate_analytics_cache'));
        add_action('ms_tenant_assigned', array($this, 'invalidate_analytics_cache'));
        
        // Lazy init: analytics_data loaded on demand, not on every page load
    }
    
    /**
     * Initialize analytics data
     */
    private function init_analytics_data() {
        // Return cached data if available
        $cached = get_transient('ms_analytics_data_cache');
        if ($cached !== false) {
            $this->analytics_data = $cached;
            return;
        }
        
        // Build analytics data fresh from DB
        $data = array(
            'key_metrics' => $this->get_key_metrics(),
            'trends'      => $this->get_trends(),
            'comparisons' => $this->get_comparisons(),
            'performance' => $this->get_performance_data()
        );
        
        set_transient('ms_analytics_data_cache', $data, $this->cache_expiry);
        $this->analytics_data = $data;
    }
    
    /**
     * Invalidate analytics cache (called when data changes)
     */
    public function invalidate_analytics_cache() {
        delete_transient('ms_analytics_data_cache');
        delete_transient('ms_chart_data_cache');
    }
    
    /**
     * Clear analytics cache via AJAX (admin only)
     */
    public function clear_analytics_cache_ajax() {
        check_ajax_referer('ms_clear_analytics_cache', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'غير مصرح'));
        }
        $this->invalidate_analytics_cache();
        wp_send_json_success(array('message' => 'تم مسح ذاكرة التخزين المؤقت بنجاح'));
    }
    
    /**
     * Get key metrics
     */
    private function get_key_metrics() {
        return array(
            'total_revenue' => $this->get_total_revenue(),
            'total_maintenance' => $this->get_total_maintenance(),
            'occupancy_rate' => $this->get_average_occupancy_rate(),
            'user_activity' => $this->get_user_activity_score(),
            'invoice_collection_rate' => $this->get_invoice_collection_rate(),
            'building_efficiency' => $this->get_building_efficiency_score()
        );
    }
    
    /**
     * Get trends
     */
    private function get_trends() {
        return array(
            'revenue_trend' => $this->get_revenue_trend(),
            'maintenance_trend' => $this->get_maintenance_trend(),
            'occupancy_trend' => $this->get_occupancy_trend(),
            'user_activity_trend' => $this->get_user_activity_trend()
        );
    }
    
    /**
     * Get comparisons
     */
    private function get_comparisons() {
        return array(
            'building_comparison' => $this->get_building_comparison(),
            'period_comparison' => $this->get_period_comparison(),
            'category_comparison' => $this->get_category_comparison()
        );
    }
    
    /**
     * Get performance data
     */
    private function get_performance_data() {
        return array(
            'building_performance' => $this->get_building_performance(),
            'user_performance' => $this->get_user_performance(),
            'service_performance' => $this->get_service_performance()
        );
    }
    
    /**
     * Get analytics data via AJAX
     */
    public function get_analytics_data() {
        check_ajax_referer('ms_get_analytics_data', 'nonce');
        
        $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : 'month';
        $filters = isset($_POST['filters']) ? json_decode(stripslashes($_POST['filters']), true) : array();
        
        // Lazy init: load analytics data only when needed
        if (empty($this->analytics_data)) {
            $this->init_analytics_data();
        }
        
        $data = $this->get_filtered_analytics($period, $filters);
        
        wp_send_json_success($data);
    }
    
    /**
     * Get filtered analytics
     */
    private function get_filtered_analytics($period, $filters) {
        $analytics = $this->analytics_data;
        
        // Apply period filter
        $analytics['period'] = $period;
        $analytics['filtered_data'] = $this->apply_period_filter($period);
        
        // Apply custom filters
        if (!empty($filters)) {
            $analytics = $this->apply_custom_filters($analytics, $filters);
        }
        
        return $analytics;
    }
    
    /**
     * Apply period filter
     */
    private function apply_period_filter($period) {
        $now = current_time('timestamp');
        
        switch ($period) {
            case 'week':
                $start_date = date('Y-m-d', strtotime('-1 week', $now));
                break;
            case 'month':
                $start_date = date('Y-m-d', strtotime('-1 month', $now));
                break;
            case 'quarter':
                $start_date = date('Y-m-d', strtotime('-3 months', $now));
                break;
            case 'year':
                $start_date = date('Y-m-d', strtotime('-1 year', $now));
                break;
            default:
                $start_date = date('Y-m-d', strtotime('-1 month', $now));
        }
        
        return array(
            'start_date' => $start_date,
            'end_date' => date('Y-m-d', $now),
            'data' => $this->get_data_for_period($start_date, date('Y-m-d', $now))
        );
    }
    
    /**
     * Get data for period
     */
    private function get_data_for_period($start_date, $end_date) {
        return array(
            'revenue' => $this->get_revenue_for_period($start_date, $end_date),
            'maintenance' => $this->get_maintenance_for_period($start_date, $end_date),
            'invoices' => $this->get_invoices_for_period($start_date, $end_date),
            'user_actions' => $this->get_user_actions_for_period($start_date, $end_date)
        );
    }
    
    /**
     * Get chart data
     */
    public function get_chart_data() {
        check_ajax_referer('ms_get_chart_data', 'nonce');
        
        $chart_type = sanitize_text_field($_POST['chart_type']);
        $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : 'month';
        $filters = isset($_POST['filters']) ? json_decode(stripslashes($_POST['filters']), true) : array();
        
        $data = $this->get_chart_data_by_type($chart_type, $period, $filters);
        
        wp_send_json_success($data);
    }
    
    /**
     * Get chart data by type
     */
    private function get_chart_data_by_type($chart_type, $period, $filters) {
        switch ($chart_type) {
            case 'revenue_chart':
                return $this->get_revenue_chart_data($period, $filters);
            case 'maintenance_chart':
                return $this->get_maintenance_chart_data($period, $filters);
            case 'occupancy_chart':
                return $this->get_occupancy_chart_data($period, $filters);
            case 'user_activity_chart':
                return $this->get_user_activity_chart_data($period, $filters);
            case 'comparison_chart':
                return $this->get_comparison_chart_data($period, $filters);
            case 'trend_chart':
                return $this->get_trend_chart_data($period, $filters);
            default:
                return array('error' => 'نوع الرسم البياني غير مدعوم');
        }
    }
    
    /**
     * Get revenue chart data
     */
    private function get_revenue_chart_data($period, $filters) {
        $period_data = $this->apply_period_filter($period);
        
        $labels = $this->generate_time_labels($period);
        $data = $this->get_revenue_by_time_period($period_data['start_date'], $period_data['end_date']);
        
        return array(
            'type' => 'line',
            'data' => array(
                'labels' => $labels,
                'datasets' => array(
                    array(
                        'label' => 'الإيرادات',
                        'data' => $data,
                        'borderColor' => '#3b82f6',
                        'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                        'fill' => true
                    )
                )
            ),
            'options' => array(
                'responsive' => true,
                'plugins' => array(
                    'legend' => array('display' => true)
                ),
                'scales' => array(
                    'y' => array('beginAtZero' => true)
                )
            )
        );
    }
    
    /**
     * Get maintenance chart data
     */
    private function get_maintenance_chart_data($period, $filters) {
        $period_data = $this->apply_period_filter($period);
        
        $labels = $this->generate_time_labels($period);
        $data = $this->get_maintenance_by_time_period($period_data['start_date'], $period_data['end_date']);
        
        return array(
            'type' => 'bar',
            'data' => array(
                'labels' => $labels,
                'datasets' => array(
                    array(
                        'label' => 'طلبات الصيانة',
                        'data' => $data,
                        'backgroundColor' => '#10b981'
                    )
                )
            ),
            'options' => array(
                'responsive' => true,
                'plugins' => array(
                    'legend' => array('display' => true)
                ),
                'scales' => array(
                    'y' => array('beginAtZero' => true)
                )
            )
        );
    }
    
    /**
     * Get occupancy chart data
     */
    private function get_occupancy_chart_data($period, $filters) {
        $buildings = function_exists('ms_get_all_buildings') ? ms_get_all_buildings() : array();
        
        $labels = array();
        $data = array();
        
        foreach ($buildings as $building) {
            $building_id = $building->id ?? $building->ID ?? 0;
            $labels[] = $building->post_title ?? $building->name ?? 'غير معروف';
            $data[] = $this->get_building_occupancy_rate($building_id);
        }
        
        return array(
            'type' => 'doughnut',
            'data' => array(
                'labels' => $labels,
                'datasets' => array(
                    array(
                        'data' => $data,
                        'backgroundColor' => array(
                            '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'
                        )
                    )
                )
            ),
            'options' => array(
                'responsive' => true,
                'plugins' => array(
                    'legend' => array('display' => true)
                )
            )
        );
    }
    
    /**
     * Get user activity chart data
     */
    private function get_user_activity_chart_data($period, $filters) {
        $period_data = $this->apply_period_filter($period);
        
        $labels = $this->generate_time_labels($period);
        $data = $this->get_user_activity_by_time_period($period_data['start_date'], $period_data['end_date']);
        
        return array(
            'type' => 'line',
            'data' => array(
                'labels' => $labels,
                'datasets' => array(
                    array(
                        'label' => 'نشاط المستخدمين',
                        'data' => $data,
                        'borderColor' => '#8b5cf6',
                        'backgroundColor' => 'rgba(139, 92, 246, 0.1)',
                        'fill' => true
                    )
                )
            ),
            'options' => array(
                'responsive' => true,
                'plugins' => array(
                    'legend' => array('display' => true)
                ),
                'scales' => array(
                    'y' => array('beginAtZero' => true)
                )
            )
        );
    }
    
    /**
     * Get comparison chart data
     */
    private function get_comparison_chart_data($period, $filters) {
        $buildings = function_exists('ms_get_all_buildings') ? ms_get_all_buildings() : array();
        
        $labels = array();
        $revenue_data = array();
        $maintenance_data = array();
        
        foreach ($buildings as $building) {
            $building_id = $building->id ?? $building->ID ?? 0;
            $labels[] = $building->post_title ?? $building->name ?? 'غير معروف';
            $revenue_data[] = $this->get_building_revenue($building_id);
            $maintenance_data[] = $this->get_building_maintenance_count($building_id);
        }
        
        return array(
            'type' => 'bar',
            'data' => array(
                'labels' => $labels,
                'datasets' => array(
                    array(
                        'label' => 'الإيرادات',
                        'data' => $revenue_data,
                        'backgroundColor' => '#3b82f6'
                    ),
                    array(
                        'label' => 'الصيانة',
                        'data' => $maintenance_data,
                        'backgroundColor' => '#ef4444'
                    )
                )
            ),
            'options' => array(
                'responsive' => true,
                'plugins' => array(
                    'legend' => array('display' => true)
                ),
                'scales' => array(
                    'y' => array('beginAtZero' => true)
                )
            )
        );
    }
    
    /**
     * Get trend chart data
     */
    private function get_trend_chart_data($period, $filters) {
        $period_data = $this->apply_period_filter($period);
        
        $labels = $this->generate_time_labels($period);
        $revenue_trend = $this->get_revenue_by_time_period($period_data['start_date'], $period_data['end_date']);
        $maintenance_trend = $this->get_maintenance_by_time_period($period_data['start_date'], $period_data['end_date']);
        
        return array(
            'type' => 'line',
            'data' => array(
                'labels' => $labels,
                'datasets' => array(
                    array(
                        'label' => 'الإيرادات',
                        'data' => $revenue_trend,
                        'borderColor' => '#3b82f6',
                        'backgroundColor' => 'transparent'
                    ),
                    array(
                        'label' => 'الصيانة',
                        'data' => $maintenance_trend,
                        'borderColor' => '#ef4444',
                        'backgroundColor' => 'transparent'
                    )
                )
            ),
            'options' => array(
                'responsive' => true,
                'plugins' => array(
                    'legend' => array('display' => true)
                ),
                'scales' => array(
                    'y' => array('beginAtZero' => true)
                )
            )
        );
    }
    
    /**
     * Get predictions
     */
    public function get_predictions() {
        check_ajax_referer('ms_get_predictions', 'nonce');
        
        $prediction_type = sanitize_text_field($_POST['prediction_type']);
        $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : 'month';
        
        $predictions = $this->generate_predictions($prediction_type, $period);
        
        wp_send_json_success($predictions);
    }
    
    /**
     * Generate predictions
     */
    private function generate_predictions($prediction_type, $period) {
        switch ($prediction_type) {
            case 'revenue_prediction':
                return $this->predict_revenue($period);
            case 'maintenance_prediction':
                return $this->predict_maintenance($period);
            case 'occupancy_prediction':
                return $this->predict_occupancy($period);
            case 'user_activity_prediction':
                return $this->predict_user_activity($period);
            default:
                return array('error' => 'نوع التنبؤ غير مدعوم');
        }
    }
    
    /**
     * Predict revenue
     */
    private function predict_revenue($period) {
        $historical_data = $this->get_historical_revenue_data($period);
        $prediction = $this->calculate_trend_prediction($historical_data);
        
        return array(
            'type' => 'revenue',
            'period' => $period,
            'current_value' => end($historical_data),
            'predicted_value' => $prediction,
            'confidence' => $this->calculate_confidence($historical_data),
            'trend' => $this->determine_trend($historical_data)
        );
    }
    
    /**
     * Predict maintenance
     */
    private function predict_maintenance($period) {
        $historical_data = $this->get_historical_maintenance_data($period);
        $prediction = $this->calculate_trend_prediction($historical_data);
        
        return array(
            'type' => 'maintenance',
            'period' => $period,
            'current_value' => end($historical_data),
            'predicted_value' => $prediction,
            'confidence' => $this->calculate_confidence($historical_data),
            'trend' => $this->determine_trend($historical_data)
        );
    }
    
    /**
     * Predict occupancy
     */
    private function predict_occupancy($period) {
        $historical_data = $this->get_historical_occupancy_data($period);
        $prediction = $this->calculate_trend_prediction($historical_data);
        
        return array(
            'type' => 'occupancy',
            'period' => $period,
            'current_value' => end($historical_data),
            'predicted_value' => $prediction,
            'confidence' => $this->calculate_confidence($historical_data),
            'trend' => $this->determine_trend($historical_data)
        );
    }
    
    /**
     * Predict user activity
     */
    private function predict_user_activity($period) {
        $historical_data = $this->get_historical_user_activity_data($period);
        $prediction = $this->calculate_trend_prediction($historical_data);
        
        return array(
            'type' => 'user_activity',
            'period' => $period,
            'current_value' => end($historical_data),
            'predicted_value' => $prediction,
            'confidence' => $this->calculate_confidence($historical_data),
            'trend' => $this->determine_trend($historical_data)
        );
    }
    
    /**
     * Export analytics
     */
    public function export_analytics() {
        check_ajax_referer('ms_export_analytics', 'nonce');
        
        $format = sanitize_text_field($_POST['format']);
        $data = json_decode(stripslashes($_POST['data']), true);
        
        $result = $this->export_analytics_data($data, $format);
        
        wp_send_json_success($result);
    }
    
    /**
     * Export analytics data
     */
    private function export_analytics_data($data, $format) {
        switch ($format) {
            case 'csv':
                return $this->export_analytics_to_csv($data);
            case 'json':
                return $this->export_analytics_to_json($data);
            case 'pdf':
                return $this->export_analytics_to_pdf($data);
            default:
                return array('success' => false, 'error' => 'صيغة التصدير غير مدعومة');
        }
    }
    
    /**
     * Export analytics to CSV
     */
    private function export_analytics_to_csv($data) {
        $filename = 'analytics_export_' . date('Y-m-d') . '.csv';
        $filepath = wp_upload_dir()['path'] . '/' . $filename;
        
        $file = fopen($filepath, 'w');
        
        foreach ($data as $section => $section_data) {
            if (is_array($section_data)) {
                fputcsv($file, array($section));
                foreach ($section_data as $key => $value) {
                    if (is_array($value)) {
                        fputcsv($file, array($key, json_encode($value)));
                    } else {
                        fputcsv($file, array($key, $value));
                    }
                }
                fputcsv($file, array()); // Empty line separator
            }
        }
        
        fclose($file);
        
        return array(
            'success' => true,
            'download_url' => wp_upload_dir()['url'] . '/' . $filename,
            'filename' => $filename
        );
    }
    
    /**
     * Export analytics to JSON
     */
    private function export_analytics_to_json($data) {
        $filename = 'analytics_export_' . date('Y-m-d') . '.json';
        $filepath = wp_upload_dir()['path'] . '/' . $filename;
        
        file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        return array(
            'success' => true,
            'download_url' => wp_upload_dir()['url'] . '/' . $filename,
            'filename' => $filename
        );
    }
    
    /**
     * Export analytics to PDF
     */
    private function export_analytics_to_pdf($data) {
        return array('success' => false, 'error' => 'تصدير PDF يتطلب مكتبة TCPDF');
    }
    
    /**
     * Helper functions
     */
    private function generate_time_labels($period) {
        $labels = array();
        $now = current_time('timestamp');
        
        switch ($period) {
            case 'week':
                for ($i = 6; $i >= 0; $i--) {
                    $labels[] = date('D', strtotime("-$i days", $now));
                }
                break;
            case 'month':
                for ($i = 29; $i >= 0; $i--) {
                    $labels[] = date('d', strtotime("-$i days", $now));
                }
                break;
            case 'quarter':
                for ($i = 11; $i >= 0; $i--) {
                    $labels[] = date('M', strtotime("-$i weeks", $now));
                }
                break;
            case 'year':
                for ($i = 11; $i >= 0; $i--) {
                    $labels[] = date('M', strtotime("-$i months", $now));
                }
                break;
            default:
                for ($i = 29; $i >= 0; $i--) {
                    $labels[] = date('d', strtotime("-$i days", $now));
                }
        }
        
        return $labels;
    }
    
    private function calculate_trend_prediction($historical_data) {
        if (count($historical_data) < 2) {
            return end($historical_data);
        }
        
        $trend = $this->calculate_linear_regression($historical_data);
        $last_value = end($historical_data);
        
        return round($last_value + $trend, 2);
    }
    
    private function calculate_linear_regression($data) {
        $n = count($data);
        if ($n < 2) return 0;
        
        $sum_x = $n * ($n - 1) / 2;
        $sum_y = array_sum($data);
        $sum_xy = 0;
        $sum_x2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sum_xy += $i * $data[$i];
            $sum_x2 += $i * $i;
        }
        
        $slope = ($n * $sum_xy - $sum_x * $sum_y) / ($n * $sum_x2 - $sum_x * $sum_x);
        
        return $slope;
    }
    
    private function calculate_confidence($data) {
        if (count($data) < 3) return 50;
        
        $mean = array_sum($data) / count($data);
        $variance = 0;
        
        foreach ($data as $value) {
            $variance += pow($value - $mean, 2);
        }
        
        $variance /= count($data);
        $std_dev = sqrt($variance);
        
        // Higher standard deviation = lower confidence
        $confidence = max(0, min(100, 100 - ($std_dev / $mean) * 100));
        
        return round($confidence, 2);
    }
    
    private function determine_trend($data) {
        if (count($data) < 2) return 'stable';
        
        $first_half = array_slice($data, 0, count($data) / 2);
        $second_half = array_slice($data, count($data) / 2);
        
        $first_avg = array_sum($first_half) / count($first_half);
        $second_avg = array_sum($second_half) / count($second_half);
        
        if ($second_avg > $first_avg * 1.05) {
            return 'increasing';
        } elseif ($second_avg < $first_avg * 0.95) {
            return 'decreasing';
        } else {
            return 'stable';
        }
    }
    
    // Placeholder helper functions
    private function get_total_revenue() { return 150000; }
    private function get_total_maintenance() { return 250; }
    private function get_average_occupancy_rate() { return 85; }
    private function get_user_activity_score() { return 75; }
    private function get_invoice_collection_rate() { return 90; }
    private function get_building_efficiency_score() { return 80; }
    private function get_revenue_trend() { return array(12000, 15000, 13000, 16000, 14000, 17000); }
    private function get_maintenance_trend() { return array(20, 25, 22, 28, 24, 30); }
    private function get_occupancy_trend() { return array(82, 84, 83, 85, 84, 86); }
    private function get_user_activity_trend() { return array(70, 72, 75, 73, 76, 78); }
    private function get_building_comparison() { return array(); }
    private function get_period_comparison() { return array(); }
    private function get_category_comparison() { return array(); }
    private function get_building_performance() { return array(); }
    private function get_user_performance() { return array(); }
    private function get_service_performance() { return array(); }
    private function apply_custom_filters($analytics, $filters) { return $analytics; }
    private function get_revenue_for_period($start, $end) { return 45000; }
    private function get_maintenance_for_period($start, $end) { return 75; }
    private function get_invoices_for_period($start, $end) { return 120; }
    private function get_user_actions_for_period($start, $end) { return 450; }
    private function get_revenue_by_time_period($start, $end) { return array(12000, 15000, 13000, 16000, 14000, 17000); }
    private function get_maintenance_by_time_period($start, $end) { return array(20, 25, 22, 28, 24, 30); }
    private function get_user_activity_by_time_period($start, $end) { return array(70, 72, 75, 73, 76, 78); }
    private function get_building_occupancy_rate($building_id) { return 85; }
    private function get_building_revenue($building_id) { return 50000; }
    private function get_building_maintenance_count($building_id) { return 50; }
    private function get_historical_revenue_data($period) { return array(10000, 12000, 15000, 13000, 16000, 14000); }
    private function get_historical_maintenance_data($period) { return array(18, 20, 25, 22, 28, 24); }
    private function get_historical_occupancy_data($period) { return array(80, 82, 84, 83, 85, 84); }
    private function get_historical_user_activity_data($period) { return array(65, 70, 72, 75, 73, 76); }
}