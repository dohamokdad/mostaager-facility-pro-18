<?php
/**
 * Backup System - Mostaager Facility PRO Add-On
 */

if (!defined('ABSPATH')) {
    exit;
}

class MS_Backup_System {
    
    public function __construct() {
        add_action('wp_ajax_ms_create_backup', array($this, 'create_backup_ajax'));
        add_action('wp_ajax_ms_restore_backup', array($this, 'restore_backup_ajax'));
        add_action('wp_ajax_ms_list_backups', array($this, 'list_backups'));
    }
    
    /**
     * Create backup via AJAX
     */
    public function create_backup_ajax() {
        check_ajax_referer('ms_create_backup', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('error' => 'ليس لديك الصلاحية'));
        }
        
        $backup_type = sanitize_text_field($_POST['backup_type']);
        
        $result = $this->create_backup($backup_type);
        
        wp_send_json_success($result);
    }
    
    /**
     * Create backup
     */
    public function create_backup($backup_type = 'full') {
        $backup_data = $this->collect_backup_data($backup_type);
        $backup_file = $this->save_backup_file($backup_data, $backup_type);
        
        return array(
            'success' => true,
            'file' => $backup_file,
            'size' => filesize($backup_file),
            'type' => $backup_type
        );
    }
    
    /**
     * Collect backup data
     */
    private function collect_backup_data($backup_type) {
        $data = array(
            'metadata' => array(
                'backup_date' => current_time('mysql'),
                'version' => '1.3.0',
                'wordpress_version' => get_bloginfo('version'),
                'php_version' => phpversion(),
                'backup_type' => $backup_type
            )
        );
        
        switch ($backup_type) {
            case 'full':
                $data['properties'] = $this->backup_properties();
                $data['buildings'] = $this->backup_buildings();
                $data['tenants'] = $this->backup_tenants();
                $data['invoices'] = $this->backup_invoices();
                $data['settings'] = $this->backup_settings();
                break;
                
            case 'properties':
                $data['properties'] = $this->backup_properties();
                break;
                
            case 'tenants':
                $data['tenants'] = $this->backup_tenants();
                break;
                
            case 'settings':
                $data['settings'] = $this->backup_settings();
                break;
        }
        
        return $data;
    }
    
    /**
     * Backup properties
     */
    private function backup_properties() {
        $properties = get_posts(array(
            'post_type' => 'property',
            'posts_per_page' => -1,
            'post_status' => 'any'
        ));
        
        $backup = array();
        
        foreach ($properties as $property) {
            $backup[] = array(
                'id' => $property->ID,
                'title' => $property->post_title,
                'content' => $property->post_content,
                'status' => $property->post_status,
                'meta' => get_post_meta($property->ID)
            );
        }
        
        return $backup;
    }
    
    /**
     * Backup buildings
     */
    private function backup_buildings() {
        global $wpdb;
        
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ms_buildings", ARRAY_A);
    }
    
    /**
     * Backup tenants
     */
    private function backup_tenants() {
        global $wpdb;
        
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ms_unit_tenants", ARRAY_A);
    }
    
    /**
     * Backup invoices
     */
    private function backup_invoices() {
        global $wpdb;
        
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ms_invoices", ARRAY_A);
    }
    
    /**
     * Backup settings
     */
    private function backup_settings() {
        return array(
            'ms_import_settings' => get_option('ms_import_settings'),
            'ms_custom_fields_config' => get_option('ms_custom_fields_config'),
            'ms_custom_templates' => get_option('ms_custom_templates'),
            'ms_automation_rules' => get_option('ms_automation_rules'),
            'ms_scheduled_tasks' => get_option('ms_scheduled_tasks')
        );
    }
    
    /**
     * Save backup file
     */
    private function save_backup_file($backup_data, $backup_type) {
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/ms-backups';
        
        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }
        
        $filename = 'ms-backup-' . $backup_type . '-' . date('Y-m-d-H-i-s') . '.json';
        $filepath = $backup_dir . '/' . $filename;
        
        file_put_contents($filepath, json_encode($backup_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        return $filepath;
    }
    
    /**
     * Restore backup via AJAX
     */
    public function restore_backup_ajax() {
        check_ajax_referer('ms_restore_backup', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('error' => 'ليس لديك الصلاحية'));
        }
        
        $backup_file = sanitize_text_field($_POST['backup_file']);
        
        $result = $this->restore_backup($backup_file);
        
        wp_send_json_success($result);
    }
    
    /**
     * Restore backup
     */
    public function restore_backup($backup_file) {
        if (!file_exists($backup_file)) {
            return array('success' => false, 'error' => 'ملف النسخة الاحتياطية غير موجود');
        }
        
        $backup_data = json_decode(file_get_contents($backup_file), true);
        
        if (!$backup_data) {
            return array('success' => false, 'error' => 'ملف النسخة الاحتياطية تالف');
        }
        
        $results = $this->restore_from_backup($backup_data);
        
        return array(
            'success' => true,
            'results' => $results
        );
    }
    
    /**
     * Restore from backup
     */
    private function restore_from_backup($backup_data) {
        $results = array();
        
        // Restore settings
        if (isset($backup_data['settings'])) {
            foreach ($backup_data['settings'] as $key => $value) {
                update_option($key, $value);
            }
            $results['settings'] = 'success';
        }
        
        // Restore buildings
        if (isset($backup_data['buildings'])) {
            $results['buildings'] = $this->restore_buildings($backup_data['buildings']);
        }
        
        // Restore tenants
        if (isset($backup_data['tenants'])) {
            $results['tenants'] = $this->restore_tenants($backup_data['tenants']);
        }
        
        // Restore invoices
        if (isset($backup_data['invoices'])) {
            $results['invoices'] = $this->restore_invoices($backup_data['invoices']);
        }
        
        return $results;
    }
    
    /**
     * Restore buildings
     */
    private function restore_buildings($buildings) {
        global $wpdb;
        
        foreach ($buildings as $building) {
            $wpdb->replace($wpdb->prefix . 'ms_buildings', $building);
        }
        
        return 'success';
    }
    
    /**
     * Restore tenants
     */
    private function restore_tenants($tenants) {
        global $wpdb;
        
        foreach ($tenants as $tenant) {
            $wpdb->replace($wpdb->prefix . 'ms_unit_tenants', $tenant);
        }
        
        return 'success';
    }
    
    /**
     * Restore invoices
     */
    private function restore_invoices($invoices) {
        global $wpdb;
        
        foreach ($invoices as $invoice) {
            $wpdb->replace($wpdb->prefix . 'ms_invoices', $invoice);
        }
        
        return 'success';
    }
    
    /**
     * List backups
     */
    public function list_backups() {
        check_ajax_referer('ms_list_backups', 'nonce');
        
        $upload_dir = wp_upload_dir();
        $backup_dir = $backup_dir['basedir'] . '/ms-backups';
        
        if (!file_exists($backup_dir)) {
            wp_send_json_success(array('backups' => array()));
        }
        
        $files = glob($backup_dir . '/*.json');
        $backups = array();
        
        foreach ($files as $file) {
            $backups[] = array(
                'filename' => basename($file),
                'size' => filesize($file),
                'modified' => filemtime($file),
                'url' => $backup_dir['baseurl'] . '/ms-backups/' . basename($file)
            );
        }
        
        // Sort by modified date (newest first)
        usort($backups, function($a, $b) {
            return $b['modified'] - $a['modified'];
        });
        
        wp_send_json_success(array('backups' => $backups));
    }
}
