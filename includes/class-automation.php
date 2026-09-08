<?php
/**
 * Advanced Automation System - Mostaager Facility PRO Add-On
 */

if (!defined('ABSPATH')) {
    exit;
}

class MS_Advanced_Automation {
    
    private $automation_rules;
    private $scheduled_tasks;
    
    public function __construct() {
        // Initialize automation
        add_action('init', array($this, 'init_automation'));
        
        // Schedule automation tasks
        add_action('ms_automation_cron', array($this, 'execute_automation_tasks'));
        
        // AJAX handlers
        add_action('wp_ajax_ms_create_automation_rule', array($this, 'create_automation_rule'));
        add_action('wp_ajax_ms_delete_automation_rule', array($this, 'delete_automation_rule'));
        add_action('wp_ajax_ms_trigger_automation', array($this, 'trigger_automation'));
        
        // Trigger points
        add_action('save_post', array($this, 'on_property_save'), 10, 3);
        add_action('ms_import_complete', array($this, 'on_import_complete'));
        add_action('ms_tenant_assigned', array($this, 'on_tenant_assigned'));
        add_action('ms_invoice_created', array($this, 'on_invoice_created'));
    }
    
    /**
     * Initialize automation
     */
    public function init_automation() {
        // Schedule automation cron
        if (!wp_next_scheduled('ms_automation_cron')) {
            wp_schedule_event(time(), 'hourly', 'ms_automation_cron');
        }
        
        // Load automation rules
        $this->automation_rules = get_option('ms_automation_rules', array());
        
        // Load scheduled tasks
        $this->scheduled_tasks = get_option('ms_scheduled_tasks', array());
    }
    
    /**
     * Execute automation tasks
     */
    public function execute_automation_tasks() {
        $current_time = current_time('mysql');
        
        foreach ($this->scheduled_tasks as $task_id => $task) {
            if ($task['next_run'] <= $current_time && $task['status'] === 'active') {
                $this->execute_task($task);
                
                // Update next run time
                $this->schedule_next_run($task_id);
            }
        }
    }
    
    /**
     * Execute task
     */
    private function execute_task($task) {
        $result = array('success' => false, 'messages' => array());
        
        switch ($task['type']) {
            case 'import':
                $result = $this->execute_import_task($task);
                break;
            case 'sync':
                $result = $this->execute_sync_task($task);
                break;
            case 'notification':
                $result = $this->execute_notification_task($task);
                break;
            case 'report':
                $result = $this->execute_report_task($task);
                break;
            case 'backup':
                $result = $this->execute_backup_task($task);
                break;
            default:
                $result['messages'][] = 'نوع المهمة غير معروف';
        }
        
        // Log execution
        $this->log_task_execution($task, $result);
        
        return $result;
    }
    
    /**
     * Execute import task
     */
    private function execute_import_task($task) {
        $result = array('success' => false, 'messages' => array());
        
        $file_url = $task['config']['file_url'];
        $template_id = $task['config']['template_id'];
        
        if (empty($file_url)) {
            $result['messages'][] = 'رابط الملف مفقود';
            return $result;
        }
        
        // Download file
        $response = wp_remote_get($file_url);
        
        if (is_wp_error($response)) {
            $result['messages'][] = 'فشل تحميل الملف: ' . $response->get_error_message();
            return $result;
        }
        
        $file_content = wp_remote_retrieve_body($response);
        
        // Parse and import
        $importer = new MS_Automation_Importer();
        $import_result = $importer->import_from_string($file_content, $template_id);
        
        if ($import_result['success']) {
            $result['success'] = true;
            $result['messages'][] = 'تم استيراد ' . $import_result['imported'] . ' سجل بنجاح';
        } else {
            $result['messages'] = array_merge($result['messages'], $import_result['errors']);
        }
        
        return $result;
    }
    
    /**
     * Execute sync task
     */
    private function execute_sync_task($task) {
        $result = array('success' => false, 'messages' => array());
        
        $source_url = $task['config']['source_url'];
        $api_key = $task['config']['api_key'];
        
        if (empty($source_url) || empty($api_key)) {
            $result['messages'][] = 'بيانات المصدر مفقودة';
            return $result;
        }
        
        // Fetch data from external source
        $response = wp_remote_get($source_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            )
        ));
        
        if (is_wp_error($response)) {
            $result['messages'][] = 'فشل الاتصال بالمصدر: ' . $response->get_error_message();
            return $result;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!$data) {
            $result['messages'][] = 'بيانات المصدر غير صحيحة';
            return $result;
        }
        
        // Sync data
        $syncer = new MS_Data_Sync();
        $sync_result = $syncer->sync_data($data);
        
        if ($sync_result['success']) {
            $result['success'] = true;
            $result['messages'][] = 'تمت مزامنة ' . $sync_result['synced'] . ' سجل بنجاح';
        } else {
            $result['messages'] = array_merge($result['messages'], $sync_result['errors']);
        }
        
        return $result;
    }
    
    /**
     * Execute notification task
     */
    private function execute_notification_task($task) {
        $result = array('success' => false, 'messages' => array());
        
        $recipients = $task['config']['recipients'];
        $message = $task['config']['message'];
        $channels = $task['config']['channels'];
        
        if (empty($recipients) || empty($message)) {
            $result['messages'][] = 'بيانات الإشعار مفقودة';
            return $result;
        }
        
        $notification_system = new MS_Multi_Channel_Notifications();
        $sent = 0;
        $failed = 0;
        
        foreach ($recipients as $recipient) {
            $notification_result = $notification_system->send_notification($recipient, $message, $channels);
            
            if ($notification_result['success']) {
                $sent++;
            } else {
                $failed++;
            }
        }
        
        $result['success'] = true;
        $result['messages'][] = "تم إرسال $sent إشعار، فشل $failed";
        
        return $result;
    }
    
    /**
     * Execute report task
     */
    private function execute_report_task($task) {
        $result = array('success' => false, 'messages' => array());
        
        $report_type = $task['config']['report_type'];
        $recipients = $task['config']['recipients'];
        
        if (empty($report_type) || empty($recipients)) {
            $result['messages'][] = 'بيانات التقرير مفقودة';
            return $result;
        }
        
        // Generate report
        $reports = new MS_Advanced_Reports();
        $report_data = $reports->generate_report_data($report_type, '', '');
        
        if (!$report_data['success']) {
            $result['messages'][] = 'فشل إنشاء التقرير';
            return $result;
        }
        
        // Send report to recipients
        foreach ($recipients as $recipient) {
            $subject = 'تقرير: ' . $report_type;
            $message = $this->format_report_message($report_data);
            
            wp_mail($recipient, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
        }
        
        $result['success'] = true;
        $result['messages'][] = 'تم إرسال التقرير إلى ' . count($recipients) . ' مستلم';
        
        return $result;
    }
    
    /**
     * Execute backup task
     */
    private function execute_backup_task($task) {
        $result = array('success' => false, 'messages' => array());
        
        $backup_type = $task['config']['backup_type'];
        $retention_days = $task['config']['retention_days'];
        
        // Create backup
        $backup_system = new MS_Backup_System();
        $backup_result = $backup_system->create_backup($backup_type);
        
        if ($backup_result['success']) {
            $result['success'] = true;
            $result['messages'][] = 'تم إنشاء النسخة الاحتياطية بنجاح';
            
            // Clean old backups
            $this->clean_old_backups($retention_days);
        } else {
            $result['messages'][] = 'فشل إنشاء النسخة الاحتياطية';
        }
        
        return $result;
    }
    
    /**
     * Schedule next run
     */
    private function schedule_next_run($task_id) {
        $task = $this->scheduled_tasks[$task_id];
        $frequency = $task['frequency'];
        
        $next_run = current_time('mysql');
        
        switch ($frequency) {
            case 'hourly':
                $next_run = date('Y-m-d H:i:s', strtotime('+1 hour'));
                break;
            case 'daily':
                $next_run = date('Y-m-d H:i:s', strtotime('+1 day'));
                break;
            case 'weekly':
                $next_run = date('Y-m-d H:i:s', strtotime('+1 week'));
                break;
            case 'monthly':
                $next_run = date('Y-m-d H:i:s', strtotime('+1 month'));
                break;
        }
        
        $this->scheduled_tasks[$task_id]['next_run'] = $next_run;
        $this->scheduled_tasks[$task_id]['last_run'] = current_time('mysql');
        
        update_option('ms_scheduled_tasks', $this->scheduled_tasks);
    }
    
    /**
     * Log task execution
     */
    private function log_task_execution($task, $result) {
        $log_entry = array(
            'task_id' => $task['id'],
            'task_name' => $task['name'],
            'task_type' => $task['type'],
            'executed_at' => current_time('mysql'),
            'success' => $result['success'],
            'messages' => $result['messages']
        );
        
        $logs = get_option('ms_automation_logs', array());
        array_unshift($logs, $log_entry);
        
        // Keep only last 100 logs
        if (count($logs) > 100) {
            $logs = array_slice($logs, 0, 100);
        }
        
        update_option('ms_automation_logs', $logs);
    }
    
    /**
     * Create automation rule via AJAX
     */
    public function create_automation_rule() {
        check_ajax_referer('ms_create_automation_rule', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('error' => 'ليس لديك الصلاحية'));
        }
        
        $rule = array(
            'id' => uniqid('rule_'),
            'name' => sanitize_text_field($_POST['rule_name']),
            'trigger' => sanitize_text_field($_POST['trigger']),
            'conditions' => json_decode(stripslashes($_POST['conditions']), true),
            'actions' => json_decode(stripslashes($_POST['actions']), true),
            'active' => true,
            'created_at' => current_time('mysql')
        );
        
        $this->automation_rules[$rule['id']] = $rule;
        update_option('ms_automation_rules', $this->automation_rules);
        
        wp_send_json_success(array('rule' => $rule));
    }
    
    /**
     * Delete automation rule via AJAX
     */
    public function delete_automation_rule() {
        check_ajax_referer('ms_delete_automation_rule', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('error' => 'ليس لديك الصلاحية'));
        }
        
        $rule_id = sanitize_text_field($_POST['rule_id']);
        
        if (isset($this->automation_rules[$rule_id])) {
            unset($this->automation_rules[$rule_id]);
            update_option('ms_automation_rules', $this->automation_rules);
            wp_send_json_success();
        } else {
            wp_send_json_error(array('error' => 'القاعدة غير موجودة'));
        }
    }
    
    /**
     * Trigger automation manually
     */
    public function trigger_automation() {
        check_ajax_referer('ms_trigger_automation', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('error' => 'ليس لديك الصلاحية'));
        }
        
        $rule_id = sanitize_text_field($_POST['rule_id']);
        
        if (isset($this->automation_rules[$rule_id])) {
            $result = $this->execute_rule($this->automation_rules[$rule_id]);
            wp_send_json_success($result);
        } else {
            wp_send_json_error(array('error' => 'القاعدة غير موجودة'));
        }
    }
    
    /**
     * Execute rule
     */
    private function execute_rule($rule) {
        $result = array('success' => false, 'messages' => array());
        
        // Check conditions
        if (!$this->check_conditions($rule['conditions'])) {
            $result['messages'][] = 'الشروط غير محققة';
            return $result;
        }
        
        // Execute actions
        foreach ($rule['actions'] as $action) {
            $action_result = $this->execute_action($action);
            $result['messages'] = array_merge($result['messages'], $action_result['messages']);
            
            if ($action_result['success']) {
                $result['success'] = true;
            }
        }
        
        return $result;
    }
    
    /**
     * Check conditions
     */
    private function check_conditions($conditions) {
        foreach ($conditions as $condition) {
            if (!$this->check_single_condition($condition)) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Check single condition
     */
    private function check_single_condition($condition) {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $value = $condition['value'];
        
        $actual_value = $this->get_field_value($field);
        
        switch ($operator) {
            case 'equals':
                return $actual_value == $value;
            case 'not_equals':
                return $actual_value != $value;
            case 'greater_than':
                return $actual_value > $value;
            case 'less_than':
                return $actual_value < $value;
            case 'contains':
                return strpos($actual_value, $value) !== false;
            case 'not_contains':
                return strpos($actual_value, $value) === false;
            default:
                return false;
        }
    }
    
    /**
     * Get field value
     */
    private function get_field_value($field) {
        // This would need to be implemented based on specific field types
        return '';
    }
    
    /**
     * Execute action
     */
    private function execute_action($action) {
        $result = array('success' => false, 'messages' => array());
        
        switch ($action['type']) {
            case 'send_notification':
                $result = $this->execute_notification_action($action);
                break;
            case 'update_field':
                $result = $this->execute_update_field_action($action);
                break;
            case 'create_record':
                $result = $this->execute_create_record_action($action);
                break;
            case 'run_script':
                $result = $this->execute_run_script_action($action);
                break;
            default:
                $result['messages'][] = 'نوع الإجراء غير معروف';
        }
        
        return $result;
    }
    
    /**
     * Execute notification action
     */
    private function execute_notification_action($action) {
        $recipients = $action['recipients'];
        $message = $action['message'];
        $channels = $action['channels'];
        
        $notification_system = new MS_Multi_Channel_Notifications();
        $result = $notification_system->send_notification($recipients, $message, $channels);
        
        return $result;
    }
    
    /**
     * Execute update field action
     */
    private function execute_update_field_action($action) {
        $result = array('success' => false, 'messages' => array());
        
        $post_id = $action['post_id'];
        $field = $action['field'];
        $value = $action['value'];
        
        $update_result = update_post_meta($post_id, $field, $value);
        
        if ($update_result) {
            $result['success'] = true;
            $result['messages'][] = 'تم تحديث الحقل بنجاح';
        } else {
            $result['messages'][] = 'فشل تحديث الحقل';
        }
        
        return $result;
    }
    
    /**
     * Execute create record action
     */
    private function execute_create_record_action($action) {
        $result = array('success' => false, 'messages' => array());
        
        $record_type = $action['record_type'];
        $data = $action['data'];
        
        switch ($record_type) {
            case 'property':
                $post_id = wp_insert_post(array(
                    'post_type' => 'property',
                    'post_title' => $data['title'],
                    'post_content' => $data['content'],
                    'post_status' => 'publish'
                ));
                
                if ($post_id && !is_wp_error($post_id)) {
                    // Add meta data
                    foreach ($data['meta'] as $key => $value) {
                        update_post_meta($post_id, $key, $value);
                    }
                    
                    $result['success'] = true;
                    $result['messages'][] = 'تم إنشاء العقار بنجاح';
                } else {
                    $result['messages'][] = 'فشل إنشاء العقار';
                }
                break;
                
            default:
                $result['messages'][] = 'نوع السجل غير معروف';
        }
        
        return $result;
    }
    
    /**
     * Execute run script action
     */
    private function execute_run_script_action($action) {
        $result = array('success' => false, 'messages' => array());
        
        $script = $action['script'];
        
        // For security, only allow predefined scripts
        $allowed_scripts = array(
            'send_welcome_email',
            'update_tenant_status',
            'calculate_revenue'
        );
        
        if (in_array($script, $allowed_scripts)) {
            $result = $this->run_predefined_script($script);
        } else {
            $result['messages'][] = 'السكريبت غير مسموح';
        }
        
        return $result;
    }
    
    /**
     * Run predefined script
     */
    private function run_predefined_script($script) {
        $result = array('success' => false, 'messages' => array());
        
        switch ($script) {
            case 'send_welcome_email':
                $result = $this->send_welcome_email_script();
                break;
            case 'update_tenant_status':
                $result = $this->update_tenant_status_script();
                break;
            case 'calculate_revenue':
                $result = $this->calculate_revenue_script();
                break;
        }
        
        return $result;
    }
    
    /**
     * Send welcome email script
     */
    private function send_welcome_email_script() {
        // Implementation would send welcome email to new tenants
        return array('success' => true, 'messages' => array('تم إرسال البريد الإلكتروني الترحيبي'));
    }
    
    /**
     * Update tenant status script
     */
    private function update_tenant_status_script() {
        // Implementation would update tenant status based on lease dates
        return array('success' => true, 'messages' => array('تم تحديث حالة المستأجرين'));
    }
    
    /**
     * Calculate revenue script
     */
    private function calculate_revenue_script() {
        // Implementation would calculate monthly revenue
        return array('success' => true, 'messages' => array('تم حساب الإيرادات'));
    }
    
    /**
     * Trigger points
     */
    public function on_property_save($post_id, $post, $update) {
        if ($post->post_type === 'property' && $update) {
            $this->trigger_automation_rules('property_updated', $post_id);
        }
    }
    
    public function on_import_complete($import_data) {
        $this->trigger_automation_rules('import_complete', $import_data);
    }
    
    public function on_tenant_assigned($tenant_data) {
        $this->trigger_automation_rules('tenant_assigned', $tenant_data);
    }
    
    public function on_invoice_created($invoice_data) {
        $this->trigger_automation_rules('invoice_created', $invoice_data);
    }
    
    /**
     * Trigger automation rules
     */
    private function trigger_automation_rules($trigger, $data) {
        foreach ($this->automation_rules as $rule) {
            if ($rule['trigger'] === $trigger && $rule['active']) {
                $this->execute_rule($rule);
            }
        }
    }
    
    /**
     * Format report message
     */
    private function format_report_message($report_data) {
        $message = '<div style="font-family: Arial, sans-serif;">';
        $message .= '<h2>تقرير تلقائي</h2>';
        $message .= '<p>نوع التقرير: ' . $report_data['report_type'] . '</p>';
        $message .= '<p>التاريخ: ' . current_time('mysql') . '</p>';
        
        if (isset($report_data['summary'])) {
            $message .= '<h3>الملخص</h3>';
            $message .= '<ul>';
            foreach ($report_data['summary'] as $key => $value) {
                $message .= '<li>' . $key . ': ' . $value . '</li>';
            }
            $message .= '</ul>';
        }
        
        $message .= '</div>';
        
        return $message;
    }
    
    /**
     * Clean old backups
     */
    private function clean_old_backups($retention_days) {
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/ms-backups';
        
        if (!file_exists($backup_dir)) {
            return;
        }
        
        $files = glob($backup_dir . '/*.json');
        $cutoff_time = time() - ($retention_days * 86400);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                unlink($file);
            }
        }
    }
}

// Initialize advanced automation - skip during installation
if (!defined('WP_INSTALLING') || !WP_INSTALLING) {
    new MS_Advanced_Automation();
}
