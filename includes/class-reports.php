<?php
/**
 * Advanced Reports System - Mostaager Facility PRO Add-On
 * Automatic reporting, scheduling, and advanced analytics
 */

if (!defined('ABSPATH')) {
    exit;
}

class MS_Advanced_Reports {
    
    private $report_types;
    private $scheduled_reports;
    
    public function __construct() {
        add_action('wp_ajax_ms_generate_report', array($this, 'generate_report'));
        add_action('wp_ajax_ms_export_report', array($this, 'export_report'));
        add_action('wp_ajax_ms_schedule_report', array($this, 'schedule_report'));
        add_action('wp_ajax_ms_get_scheduled_reports', array($this, 'get_scheduled_reports'));
        add_action('wp_ajax_ms_delete_scheduled_report', array($this, 'delete_scheduled_report'));
        
        // Initialize report types
        $this->init_report_types();
        
        // Initialize scheduled reports
        $this->init_scheduled_reports();
        
        // Setup cron for scheduled reports
        add_action('ms_send_scheduled_reports', array($this, 'process_scheduled_reports'));
        
        if (!wp_next_scheduled('ms_send_scheduled_reports')) {
            wp_schedule_event(time(), 'hourly', 'ms_send_scheduled_reports');
        }
    }
    
    /**
     * Initialize report types
     */
    private function init_report_types() {
        $this->report_types = array(
            'import_summary' => array(
                'name' => 'ملخص الاستيراد',
                'description' => 'ملخص شامل لعمليات الاستيراد',
                'icon' => '📊',
                'fields' => array('date', 'imported_count', 'failed_count', 'building_id', 'user_id')
            ),
            'maintenance_report' => array(
                'name' => 'تقرير الصيانة',
                'description' => 'تقرير شامل عن طلبات الصيانة',
                'icon' => '🛠️',
                'fields' => array('request_id', 'title', 'status', 'cost', 'created_at', 'completed_at')
            ),
            'financial_report' => array(
                'name' => 'التقرير المالي',
                'description' => 'تقرير مالي شامل للفواتير والمدفوعات',
                'icon' => '💰',
                'fields' => array('invoice_id', 'amount', 'status', 'payment_date', 'building_id')
            ),
            'building_performance' => array(
                'name' => 'أداء المباني',
                'description' => 'تحليل أداء المباني المختلفة',
                'icon' => '🏢',
                'fields' => array('building_id', 'name', 'total_units', 'occupied_units', 'maintenance_count', 'revenue')
            ),
            'user_activity' => array(
                'name' => 'نشاط المستخدمين',
                'description' => 'تقرير نشاط المستخدمين والناطورين',
                'icon' => '👥',
                'fields' => array('user_id', 'name', 'role', 'login_count', 'actions_count', 'last_active')
            ),
            'invoice_summary' => array(
                'name' => 'ملخص الفواتير',
                'description' => 'ملخص شامل للفواتير وحالاتها',
                'icon' => '🧾',
                'fields' => array('invoice_id', 'amount', 'status', 'due_date', 'payment_date')
            ),
            'occupancy_report' => array(
                'name' => 'تقرير الإشغال',
                'description' => 'تقرير نسب الإشغال في المباني',
                'icon' => '🏠',
                'fields' => array('building_id', 'total_units', 'occupied_units', 'vacancy_rate', 'average_rent')
            ),
            'custom_report' => array(
                'name' => 'تقرير مخصص',
                'description' => 'إنشاء تقرير مخصص حسب الحاجة',
                'icon' => '⚙️',
                'fields' => array('custom')
            )
        );
    }
    
    /**
     * Initialize scheduled reports
     */
    private function init_scheduled_reports() {
        $this->scheduled_reports = get_option('ms_scheduled_reports', array());
    }
    
    /**
     * Add reports menu
     */
    public function add_reports_menu() {
        add_submenu_page(
            'options-general.php',
            'التقارير المتقدمة',
            'التقارير',
            'manage_options',
            'ms-import-reports',
            array($this, 'render_reports_page')
        );
    }
    
    /**
     * Render reports page
     */
    public function render_reports_page() {
        $report_types = $this->report_types;
        $scheduled_reports = $this->scheduled_reports;
        
        include dirname(__FILE__) . '/../admin/views/reports.php';
    }
    
    /**
     * Get available report types
     */
    public function get_report_types() {
        return $this->report_types;
    }
    
    /**
     * Generate report
     */
    public function generate_report() {
        check_ajax_referer('ms_generate_report', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'ليس لديك الصلاحية'), 403);
        }
        
        $report_type = sanitize_text_field($_POST['report_type']);
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        $filters = isset($_POST['filters']) ? json_decode(stripslashes($_POST['filters']), true) : array();
        
        $result = $this->create_report($report_type, $start_date, $end_date, $filters);
        
        wp_send_json_success($result);
    }
    
    /**
     * Create report
     */
    public function create_report($report_type, $start_date, $end_date, $filters = array()) {
        if (!isset($this->report_types[$report_type])) {
            return array('success' => false, 'error' => 'نوع التقرير غير صالح');
        }
        
        $data = $this->get_report_data($report_type, $start_date, $end_date, $filters);
        
        return array(
            'success' => true,
            'report_type' => $report_type,
            'data' => $data,
            'generated_at' => current_time('mysql'),
            'start_date' => $start_date,
            'end_date' => $end_date
        );
    }
    
    /**
     * Get report data
     */
    private function get_report_data($report_type, $start_date, $end_date, $filters) {
        switch ($report_type) {
            case 'maintenance_report':
                return $this->get_maintenance_report_data($start_date, $end_date, $filters);
            case 'financial_report':
                return $this->get_financial_report_data($start_date, $end_date, $filters);
            case 'building_performance':
                return $this->get_building_performance_data($filters);
            case 'user_activity':
                return $this->get_user_activity_data($start_date, $end_date, $filters);
            case 'invoice_summary':
                return $this->get_invoice_summary_data($start_date, $end_date, $filters);
            case 'occupancy_report':
                return $this->get_occupancy_report_data($filters);
            default:
                return array();
        }
    }
    
    /**
     * Get maintenance report data
     */
    private function get_maintenance_report_data($start_date, $end_date, $filters) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ms_maintenance_requests';
        
        $where = "1=1";
        $params = array();
        
        if ($start_date) {
            $where .= " AND created_at >= %s";
            $params[] = $start_date;
        }
        
        if ($end_date) {
            $where .= " AND created_at <= %s";
            $params[] = $end_date;
        }
        
        if (isset($filters['building_id']) && $filters['building_id']) {
            $where .= " AND building_id = %d";
            $params[] = intval($filters['building_id']);
        }
        
        if (isset($filters['status']) && $filters['status']) {
            $where .= " AND status = %s";
            $params[] = sanitize_text_field($filters['status']);
        }
        
        $limit = isset($filters['limit']) ? intval($filters['limit']) : 5000;
        $limit = max(1, min($limit, 10000)); // Between 1 and 10000
        
        $query = "SELECT * FROM $table_name WHERE $where ORDER BY created_at DESC LIMIT %d";
        $params[] = $limit;
        
        $query = $wpdb->prepare($query, $params);
        
        $results = $wpdb->get_results($query);
        
        // Calculate statistics
        $stats = array(
            'total' => count($results),
            'completed' => 0,
            'pending' => 0,
            'in_progress' => 0,
            'total_cost' => 0
        );
        
        foreach ($results as $row) {
            if ($row->status === 'completed') {
                $stats['completed']++;
                $stats['total_cost'] += floatval($row->cost);
            } elseif ($row->status === 'pending') {
                $stats['pending']++;
            } elseif ($row->status === 'in_progress') {
                $stats['in_progress']++;
            }
        }
        
        return array(
            'requests' => $results,
            'statistics' => $stats
        );
    }
    
    /**
     * Get financial report data
     */
    private function get_financial_report_data($start_date, $end_date, $filters) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ms_invoices';
        
        $where = "1=1";
        $params = array();
        
        if ($start_date) {
            $where .= " AND created_at >= %s";
            $params[] = $start_date;
        }
        
        if ($end_date) {
            $where .= " AND created_at <= %s";
            $params[] = $end_date;
        }
        
        if (isset($filters['building_id']) && $filters['building_id']) {
            $where .= " AND building_id = %d";
            $params[] = intval($filters['building_id']);
        }
        
        if (isset($filters['status']) && $filters['status']) {
            $where .= " AND status = %s";
            $params[] = sanitize_text_field($filters['status']);
        }
        
        $limit = isset($filters['limit']) ? intval($filters['limit']) : 5000;
        $limit = max(1, min($limit, 10000)); // Between 1 and 10000
        
        $query = "SELECT * FROM $table_name WHERE $where ORDER BY created_at DESC LIMIT %d";
        $params[] = $limit;
        
        $query = $wpdb->prepare($query, $params);
        
        $results = $wpdb->get_results($query);
        
        // Calculate statistics
        $stats = array(
            'total' => count($results),
            'paid' => 0,
            'pending' => 0,
            'overdue' => 0,
            'total_amount' => 0,
            'paid_amount' => 0
        );
        
        foreach ($results as $row) {
            $stats['total_amount'] += floatval($row->amount);
            
            if ($row->status === 'paid') {
                $stats['paid']++;
                $stats['paid_amount'] += floatval($row->amount);
            } elseif ($row->status === 'pending') {
                $stats['pending']++;
            } elseif ($row->status === 'overdue') {
                $stats['overdue']++;
            }
        }
        
        return array(
            'invoices' => $results,
            'statistics' => $stats
        );
    }
    
    /**
     * Get building performance data
     */
    private function get_building_performance_data($filters) {
        $buildings = function_exists('ms_get_all_buildings') ? ms_get_all_buildings() : array();
        
        $performance_data = array();
        
        foreach ($buildings as $building) {
            $building_id = $building->id ?? $building->ID ?? 0;
            
            // Get maintenance count
            $maintenance_count = $this->get_building_maintenance_count($building_id);
            
            // Get revenue
            $revenue = $this->get_building_revenue($building_id);
            
            // Get occupancy
            $total_units = $this->get_building_total_units($building_id);
            $occupied_units = $this->get_building_occupied_units($building_id);
            
            $performance_data[] = array(
                'building_id' => $building_id,
                'name' => $building->post_title ?? $building->name ?? 'غير معروف',
                'total_units' => $total_units,
                'occupied_units' => $occupied_units,
                'occupancy_rate' => $total_units > 0 ? round(($occupied_units / $total_units) * 100, 2) : 0,
                'maintenance_count' => $maintenance_count,
                'revenue' => $revenue
            );
        }
        
        return $performance_data;
    }
    
    /**
     * Get user activity data
     */
    private function get_user_activity_data($start_date, $end_date, $filters) {
        $users = get_users(array('role__in' => array('building_manager', 'administrator')));
        
        $activity_data = array();
        
        foreach ($users as $user) {
            $login_count = $this->get_user_login_count($user->ID, $start_date, $end_date);
            $actions_count = $this->get_user_actions_count($user->ID, $start_date, $end_date);
            $last_active = get_user_meta($user->ID, 'last_activity', true);
            
            $activity_data[] = array(
                'user_id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'role' => implode(', ', $user->roles),
                'login_count' => $login_count,
                'actions_count' => $actions_count,
                'last_active' => $last_active
            );
        }
        
        return $activity_data;
    }
    
    /**
     * Get invoice summary data
     */
    private function get_invoice_summary_data($start_date, $end_date, $filters) {
        return $this->get_financial_report_data($start_date, $end_date, $filters);
    }
    
    /**
     * Get occupancy report data
     */
    private function get_occupancy_report_data($filters) {
        $buildings = function_exists('ms_get_all_buildings') ? ms_get_all_buildings() : array();
        
        $occupancy_data = array();
        
        foreach ($buildings as $building) {
            $building_id = $building->id ?? $building->ID ?? 0;
            
            $total_units = $this->get_building_total_units($building_id);
            $occupied_units = $this->get_building_occupied_units($building_id);
            $vacancy_rate = $total_units > 0 ? round((($total_units - $occupied_units) / $total_units) * 100, 2) : 0;
            $average_rent = $this->get_building_average_rent($building_id);
            
            $occupancy_data[] = array(
                'building_id' => $building_id,
                'name' => $building->post_title ?? $building->name ?? 'غير معروف',
                'total_units' => $total_units,
                'occupied_units' => $occupied_units,
                'vacancy_rate' => $vacancy_rate,
                'average_rent' => $average_rent
            );
        }
        
        return $occupancy_data;
    }
    
    /**
     * Helper functions
     */
    private function get_building_maintenance_count($building_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ms_maintenance_requests';
        return $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE building_id = %d", $building_id));
    }
    
    private function get_building_revenue($building_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ms_invoices';
        $revenue = $wpdb->get_var($wpdb->prepare("SELECT SUM(amount) FROM $table_name WHERE building_id = %d AND status = 'paid'", $building_id));
        return $revenue ? floatval($revenue) : 0;
    }
    
    private function get_building_total_units($building_id) {
        // This would be implemented based on your property management system
        return 10; // Placeholder
    }
    
    private function get_building_occupied_units($building_id) {
        // This would be implemented based on your property management system
        return 7; // Placeholder
    }
    
    private function get_building_average_rent($building_id) {
        // This would be implemented based on your property management system
        return 5000; // Placeholder
    }
    
    private function get_user_login_count($user_id, $start_date, $end_date) {
        // This would be implemented with proper user activity tracking
        return 5; // Placeholder
    }
    
    private function get_user_actions_count($user_id, $start_date, $end_date) {
        // This would be implemented with proper user activity tracking
        return 15; // Placeholder
    }
    
    /**
     * Export report
     */
    public function export_report() {
        check_ajax_referer('ms_export_report', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'ليس لديك الصلاحية'), 403);
        }
        
        $report_type = sanitize_text_field($_POST['report_type']);
        $format = sanitize_text_field($_POST['format']);
        $data = json_decode(stripslashes($_POST['data']), true);
        
        $result = $this->export_data($data, $format, $report_type);
        
        wp_send_json_success($result);
    }
    
    /**
     * Export data
     */
    private function export_data($data, $format, $report_type) {
        switch ($format) {
            case 'csv':
                return $this->export_to_csv($data, $report_type);
            case 'excel':
                return $this->export_to_excel($data, $report_type);
            case 'pdf':
                return $this->export_to_pdf($data, $report_type);
            default:
                return array('success' => false, 'error' => 'صيغة التصدير غير مدعومة');
        }
    }
    
    /**
     * Export to CSV
     */
    private function export_to_csv($data, $report_type) {
        $filename = $report_type . '_report_' . date('Y-m-d') . '.csv';
        $filepath = wp_upload_dir()['path'] . '/' . $filename;
        
        $file = fopen($filepath, 'w');
        
        if (isset($data['requests'])) {
            fputcsv($file, array_keys((array)$data['requests'][0]));
            foreach ($data['requests'] as $row) {
                fputcsv($file, (array)$row);
            }
        } elseif (isset($data['invoices'])) {
            fputcsv($file, array_keys((array)$data['invoices'][0]));
            foreach ($data['invoices'] as $row) {
                fputcsv($file, (array)$row);
            }
        } else {
            fputcsv($file, array_keys($data[0]));
            foreach ($data as $row) {
                fputcsv($file, $row);
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
     * Export to Excel
     */
    private function export_to_excel($data, $report_type) {
        // This would use a library like PhpSpreadsheet
        return array('success' => false, 'error' => 'تصدير Excel يتطلب مكتبة PhpSpreadsheet');
    }
    
    /**
     * Export to PDF
     */
    private function export_to_pdf($data, $report_type) {
        // This would use a library like TCPDF or DomPDF
        return array('success' => false, 'error' => 'تصدير PDF يتطلب مكتبة TCPDF');
    }
    
    /**
     * Schedule report
     */
    public function schedule_report() {
        check_ajax_referer('ms_schedule_report', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'ليس لديك الصلاحية'), 403);
        }
        
        $report_type = sanitize_text_field($_POST['report_type']);
        $schedule = sanitize_text_field($_POST['schedule']); // daily, weekly, monthly
        $recipients = json_decode(stripslashes($_POST['recipients']), true);
        $filters = isset($_POST['filters']) ? json_decode(stripslashes($_POST['filters']), true) : array();
        
        $result = $this->add_scheduled_report($report_type, $schedule, $recipients, $filters);
        
        wp_send_json_success($result);
    }
    
    /**
     * Add scheduled report
     */
    private function add_scheduled_report($report_type, $schedule, $recipients, $filters) {
        $scheduled_report = array(
            'id' => uniqid(),
            'report_type' => $report_type,
            'schedule' => $schedule,
            'recipients' => $recipients,
            'filters' => $filters,
            'created_at' => current_time('mysql'),
            'next_run' => $this->calculate_next_run($schedule)
        );
        
        $this->scheduled_reports[] = $scheduled_report;
        update_option('ms_scheduled_reports', $this->scheduled_reports);
        
        return array('success' => true, 'scheduled_report' => $scheduled_report);
    }
    
    /**
     * Calculate next run time
     */
    private function calculate_next_run($schedule) {
        $now = current_time('timestamp');
        
        switch ($schedule) {
            case 'daily':
                return date('Y-m-d H:i:s', strtotime('+1 day', $now));
            case 'weekly':
                return date('Y-m-d H:i:s', strtotime('+1 week', $now));
            case 'monthly':
                return date('Y-m-d H:i:s', strtotime('+1 month', $now));
            default:
                return date('Y-m-d H:i:s', strtotime('+1 day', $now));
        }
    }
    
    /**
     * Get scheduled reports
     */
    public function get_scheduled_reports() {
        check_ajax_referer('ms_get_scheduled_reports', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'ليس لديك الصلاحية'), 403);
        }
        
        wp_send_json_success($this->scheduled_reports);
    }
    
    /**
     * Delete scheduled report
     */
    public function delete_scheduled_report() {
        check_ajax_referer('ms_delete_scheduled_report', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'ليس لديك الصلاحية'), 403);
        }
        
        $report_id = sanitize_text_field($_POST['report_id']);
        
        foreach ($this->scheduled_reports as $key => $report) {
            if ($report['id'] === $report_id) {
                unset($this->scheduled_reports[$key]);
                break;
            }
        }
        
        update_option('ms_scheduled_reports', $this->scheduled_reports);
        
        wp_send_json_success(array('success' => true));
    }
    
    /**
     * Process scheduled reports
     */
    public function process_scheduled_reports() {
        $now = current_time('mysql');
        
        foreach ($this->scheduled_reports as $key => $report) {
            if ($report['next_run'] <= $now) {
                $this->generate_and_send_scheduled_report($report);
                
                // Update next run time
                $this->scheduled_reports[$key]['next_run'] = $this->calculate_next_run($report['schedule']);
                update_option('ms_scheduled_reports', $this->scheduled_reports);
            }
        }
    }
    
    /**
     * Generate and send scheduled report
     */
    private function generate_and_send_scheduled_report($report) {
        $end_date = current_time('mysql');
        $start_date = $this->calculate_start_date($report['schedule']);
        
        $report_data = $this->create_report($report['report_type'], $start_date, $end_date, $report['filters']);
        
        if ($report_data['success']) {
            $message = $this->format_report_message($report_data);
            
            if (class_exists('MS_Multi_Channel_Notifications')) {
                $notifications = new MS_Multi_Channel_Notifications();
                
                foreach ($report['recipients'] as $recipient) {
                    $notifications->send_notification($recipient, $message, array('email'), $report['report_type']);
                }
            }
        }
    }
    
    /**
     * Calculate start date based on schedule
     */
    private function calculate_start_date($schedule) {
        $now = current_time('timestamp');
        
        switch ($schedule) {
            case 'daily':
                return date('Y-m-d H:i:s', strtotime('-1 day', $now));
            case 'weekly':
                return date('Y-m-d H:i:s', strtotime('-1 week', $now));
            case 'monthly':
                return date('Y-m-d H:i:s', strtotime('-1 month', $now));
            default:
                return date('Y-m-d H:i:s', strtotime('-1 day', $now));
        }
    }
    
    /**
     * Format report message
     */
    private function format_report_message($report_data) {
        $message = '<h2>تقرير ' . $report_data['report_type'] . '</h2>';
        $message .= '<p>من: ' . $report_data['start_date'] . '</p>';
        $message .= '<p>إلى: ' . $report_data['end_date'] . '</p>';
        $message .= '<p>تم التوليد: ' . $report_data['generated_at'] . '</p>';
        
        if (isset($report_data['data']['statistics'])) {
            $message .= '<h3>الإحصائيات</h3>';
            $message .= '<ul>';
            foreach ($report_data['data']['statistics'] as $key => $value) {
                $message .= '<li>' . $key . ': ' . $value . '</li>';
            }
            $message .= '</ul>';
        }
        
        return $message;
    }

    /**
     * Generate report data
     */
    public function generate_report_data($report_type, $date_from, $date_to) {
        switch ($report_type) {
            case 'import_summary':
                return $this->generate_import_summary_report($date_from, $date_to);
            case 'property_analysis':
                return $this->generate_property_analysis_report($date_from, $date_to);
            case 'tenant_report':
                return $this->generate_tenant_report($date_from, $date_to);
            case 'financial_report':
                return $this->generate_financial_report($date_from, $date_to);
            case 'performance_report':
                return $this->generate_performance_report($date_from, $date_to);
            default:
                return array('success' => false, 'error' => 'نوع التقرير غير معروف');
        }
    }
    
    /**
     * Generate import summary report
     */
    private function generate_import_summary_report($date_from, $date_to) {
        global $wpdb;
        
        $where = '';
        $params = array();
        
        if ($date_from && $date_to) {
            $where = "WHERE created_at BETWEEN %s AND %s";
            $params = array($date_from, $date_to);
        }
        
        $imports = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ms_import_logs $where ORDER BY created_at DESC",
            $params
        ));
        
        $total_imports = count($imports);
        $successful_imports = 0;
        $failed_imports = 0;
        $total_records = 0;
        $total_errors = 0;
        
        foreach ($imports as $import) {
            if ($import->status === 'success') {
                $successful_imports++;
            } else {
                $failed_imports++;
            }
            
            $total_records += $import->records_processed;
            $total_errors += $import->errors_count;
        }
        
        return array(
            'success' => true,
            'report_type' => 'import_summary',
            'period' => array(
                'from' => $date_from,
                'to' => $date_to
            ),
            'summary' => array(
                'total_imports' => $total_imports,
                'successful_imports' => $successful_imports,
                'failed_imports' => $failed_imports,
                'success_rate' => $total_imports > 0 ? round(($successful_imports / $total_imports) * 100, 1) : 0,
                'total_records' => $total_records,
                'total_errors' => $total_errors,
                'avg_duration' => $total_imports > 0 ? round(array_sum(array_column($imports, 'duration')) / $total_imports, 2) : 0
            ),
            'charts' => array(
                'imports_over_time' => $this->get_imports_over_time_chart($imports),
                'success_rate_trend' => $this->get_success_rate_trend($imports)
            )
        );
    }
    
    /**
     * Generate property analysis report
     */
    private function generate_property_analysis_report($date_from, $date_to) {
        global $wpdb;
        
        $properties = $this->get_properties_in_period($date_from, $date_to);
        
        $property_types = array();
        $unit_statuses = array();
        $building_distribution = array();
        
        foreach ($properties as $property) {
            $unit_type = get_post_meta($property->ID, 'ms_unit_type', true);
            $unit_status = get_post_meta($property->ID, 'ms_unit_status', true);
            $building_id = get_post_meta($property->ID, 'ms_building_id', true);
            
            if ($unit_type) {
                $property_types[$unit_type] = isset($property_types[$unit_type]) ? $property_types[$unit_type] + 1 : 1;
            }
            
            if ($unit_status) {
                $unit_statuses[$unit_status] = isset($unit_statuses[$unit_status]) ? $unit_statuses[$unit_status] + 1 : 1;
            }
            
            if ($building_id) {
                $building_distribution[$building_id] = isset($building_distribution[$building_id]) ? $building_distribution[$building_id] + 1 : 1;
            }
        }
        
        return array(
            'success' => true,
            'report_type' => 'property_analysis',
            'period' => array(
                'from' => $date_from,
                'to' => $date_to
            ),
            'summary' => array(
                'total_properties' => count($properties),
                'property_types' => $property_types,
                'unit_statuses' => $unit_statuses,
                'building_distribution' => $building_distribution
            ),
            'charts' => array(
                'property_types_pie' => $this->get_property_types_pie_chart($property_types),
                'unit_status_bar' => $this->get_unit_status_bar_chart($unit_statuses)
            )
        );
    }
    
    /**
     * Generate tenant report
     */
    private function generate_tenant_report($date_from, $date_to) {
        global $wpdb;
        
        $tenants = $wpdb->get_results($wpdb->prepare(
            "SELECT t.*, u.display_name, u.user_email 
            FROM {$wpdb->prefix}ms_unit_tenants t
            LEFT JOIN {$wpdb->users} u ON t.tenant_id = u.ID
            WHERE t.created_at BETWEEN %s AND %s
            ORDER BY t.created_at DESC",
            $date_from,
            $date_to
        ));
        
        $active_tenants = 0;
        $new_tenants = 0;
        $total_revenue = 0;
        
        foreach ($tenants as $tenant) {
            if ($tenant->status === 'active') {
                $active_tenants++;
            }
            
            // Check if tenant is new (created in period)
            if (strtotime($tenant->created_at) >= strtotime($date_from)) {
                $new_tenants++;
            }
        }
        
        return array(
            'success' => true,
            'report_type' => 'tenant_report',
            'period' => array(
                'from' => $date_from,
                'to' => $date_to
            ),
            'summary' => array(
                'total_tenants' => count($tenants),
                'active_tenants' => $active_tenants,
                'new_tenants' => $new_tenants,
                'tenant_retention_rate' => $this->calculate_retention_rate($tenants)
            ),
            'details' => $tenants
        );
    }
    
    /**
     * Generate financial report
     */
    private function generate_financial_report($date_from, $date_to) {
        global $wpdb;
        
        $invoices = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ms_invoices 
            WHERE created_at BETWEEN %s AND %s
            ORDER BY created_at DESC",
            $date_from,
            $date_to
        ));
        
        $total_amount = 0;
        $paid_amount = 0;
        $pending_amount = 0;
        $overdue_amount = 0;
        
        foreach ($invoices as $invoice) {
            $total_amount += $invoice->amount;
            
            switch ($invoice->status) {
                case 'paid':
                    $paid_amount += $invoice->amount;
                    break;
                case 'pending':
                    $pending_amount += $invoice->amount;
                    break;
                case 'overdue':
                    $overdue_amount += $invoice->amount;
                    break;
            }
        }
        
        return array(
            'success' => true,
            'report_type' => 'financial_report',
            'period' => array(
                'from' => $date_from,
                'to' => $date_to
            ),
            'summary' => array(
                'total_invoices' => count($invoices),
                'total_amount' => $total_amount,
                'paid_amount' => $paid_amount,
                'pending_amount' => $pending_amount,
                'overdue_amount' => $overdue_amount,
                'collection_rate' => $total_amount > 0 ? round(($paid_amount / $total_amount) * 100, 1) : 0
            ),
            'charts' => array(
                'revenue_trend' => $this->get_revenue_trend_chart($invoices),
                'invoice_status_pie' => $this->get_invoice_status_pie_chart($invoices)
            )
        );
    }
    
    /**
     * Generate performance report
     */
    private function generate_performance_report($date_from, $date_to) {
        global $wpdb;
        
        $imports = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ms_import_logs 
            WHERE created_at BETWEEN %s AND %s
            ORDER BY created_at DESC",
            $date_from,
            $date_to
        ));
        
        $durations = array();
        $record_counts = array();
        
        foreach ($imports as $import) {
            $durations[] = $import->duration;
            $record_counts[] = $import->records_processed;
        }
        
        return array(
            'success' => true,
            'report_type' => 'performance_report',
            'period' => array(
                'from' => $date_from,
                'to' => $date_to
            ),
            'summary' => array(
                'total_imports' => count($imports),
                'avg_duration' => !empty($durations) ? round(array_sum($durations) / count($durations), 2) : 0,
                'avg_records_per_import' => !empty($record_counts) ? round(array_sum($record_counts) / count($record_counts), 2) : 0,
                'max_duration' => !empty($durations) ? max($durations) : 0,
                'min_duration' => !empty($durations) ? min($durations) : 0
            ),
            'charts' => array(
                'duration_distribution' => $this->get_duration_distribution_chart($durations),
                'records_per_import' => $this->get_records_per_import_chart($record_counts)
            )
        );
    }
    
    /**
     * Get properties in period
     */
    private function get_properties_in_period($date_from, $date_to) {
        $args = array(
            'post_type' => 'property',
            'post_status' => 'publish',
            'date_query' => array(
                array(
                    'after' => $date_from,
                    'before' => $date_to,
                    'inclusive' => true
                )
            ),
            'posts_per_page' => -1
        );
        
        return get_posts($args);
    }
    
    /**
     * Calculate retention rate
     */
    private function calculate_retention_rate($tenants) {
        if (empty($tenants)) {
            return 0;
        }
        
        $total = count($tenants);
        $active = 0;
        
        foreach ($tenants as $tenant) {
            if ($tenant->status === 'active') {
                $active++;
            }
        }
        
        return $total > 0 ? round(($active / $total) * 100, 1) : 0;
    }
    
    /**
     * Chart generation methods (placeholders)
     */
    private function get_imports_over_time_chart($imports) {
        // Would generate chart data for imports over time
        return array('type' => 'line', 'data' => array());
    }
    
    private function get_success_rate_trend($imports) {
        // Would generate chart data for success rate trend
        return array('type' => 'line', 'data' => array());
    }
    
    private function get_property_types_pie_chart($property_types) {
        return array('type' => 'pie', 'data' => $property_types);
    }
    
    private function get_unit_status_bar_chart($unit_statuses) {
        return array('type' => 'bar', 'data' => $unit_statuses);
    }
    
    private function get_revenue_trend_chart($invoices) {
        return array('type' => 'line', 'data' => array());
    }
    
    private function get_invoice_status_pie_chart($invoices) {
        return array('type' => 'pie', 'data' => array());
    }
    
    private function get_duration_distribution_chart($durations) {
        return array('type' => 'bar', 'data' => array());
    }
    
    private function get_records_per_import_chart($record_counts) {
        return array('type' => 'bar', 'data' => array());
    }
}
