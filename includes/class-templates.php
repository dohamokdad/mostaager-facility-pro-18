<?php
/**
 * Templates Manager - Mostaager Facility PRO Add-On
 */

if (!defined('ABSPATH')) {
    exit;
}

class MS_Templates_Manager {
    
    /**
     * Get all templates
     */
    public function get_all_templates() {
        $default_templates = $this->get_default_templates();
        $custom_templates = get_option('ms_custom_templates', array());
        
        return array_merge($default_templates, $custom_templates);
    }
    
    /**
     * Get default templates
     */
    private function get_default_templates() {
        return array(
            'buildings' => array(
                'id' => 'buildings',
                'name' => 'قالب المباني',
                'icon' => '🏗️',
                'description' => 'قالب جاهز لاستيراد بيانات المباني',
                'fields' => array(
                    'ms_building_title' => 'اسم المبنى',
                    'ms_building_manager_id' => 'معرف المدير',
                    'ms_building_supervisor_name' => 'اسم المشرف',
                    'ms_building_supervisor_phone' => 'هاتف المشرف'
                ),
                'is_default' => true
            ),
            'properties' => array(
                'id' => 'properties',
                'name' => 'قالب العقارات',
                'icon' => '🏠',
                'description' => 'قالب جاهز لاستيراد بيانات العقارات',
                'fields' => array(
                    'ms_building_title' => 'اسم المبنى',
                    'ms_unit_number' => 'رقم الوحدة',
                    'ms_floor' => 'الطابق',
                    'ms_unit_type' => 'نوع الوحدة',
                    'ms_unit_status' => 'حالة الوحدة',
                    'fave_property_price' => 'السعر',
                    'fave_property_size' => 'المساحة'
                ),
                'is_default' => true
            ),
            'tenants' => array(
                'id' => 'tenants',
                'name' => 'قالب المستأجرين',
                'icon' => '👥',
                'description' => 'قالب جاهز لاستيراد بيانات المستأجرين',
                'fields' => array(
                    'ms_tenant_name' => 'اسم المستأجر',
                    'ms_tenant_email' => 'بريد المستأجر',
                    'ms_tenant_phone' => 'هاتف المستأجر',
                    'ms_tenant_national_id' => 'الرقم القومي'
                ),
                'is_default' => true
            ),
            'full' => array(
                'id' => 'full',
                'name' => 'قالب شامل',
                'icon' => '📋',
                'description' => 'قالب شامل لاستيراد جميع البيانات',
                'fields' => array(
                    'ms_building_title' => 'اسم المبنى',
                    'ms_unit_number' => 'رقم الوحدة',
                    'ms_floor' => 'الطابق',
                    'ms_unit_type' => 'نوع الوحدة',
                    'ms_unit_status' => 'حالة الوحدة',
                    'ms_tenant_name' => 'اسم المستأجر',
                    'ms_tenant_email' => 'بريد المستأجر',
                    'ms_tenant_phone' => 'هاتف المستأجر',
                    'ms_monthly_rent' => 'الإيجار الشهري',
                    'ms_lease_start_date' => 'تاريخ بداية العقد',
                    'ms_lease_end_date' => 'تاريخ نهاية العقد'
                ),
                'is_default' => true
            )
        );
    }
    
    /**
     * Get template by ID
     */
    public function get_template($template_id) {
        $templates = $this->get_all_templates();
        return isset($templates[$template_id]) ? $templates[$template_id] : null;
    }
    
    /**
     * Save custom template
     */
    public function save_template($template_data) {
        $custom_templates = get_option('ms_custom_templates', array());
        
        $template_id = sanitize_title($template_data['name']);
        $template_data['id'] = $template_id;
        $template_data['is_default'] = false;
        $template_data['created_at'] = current_time('mysql');
        
        $custom_templates[$template_id] = $template_data;
        
        update_option('ms_custom_templates', $custom_templates);
        
        return array('success' => true, 'template_id' => $template_id);
    }
    
    /**
     * Delete custom template
     */
    public function delete_template($template_id) {
        $custom_templates = get_option('ms_custom_templates', array());
        
        if (isset($custom_templates[$template_id])) {
            unset($custom_templates[$template_id]);
            update_option('ms_custom_templates', $custom_templates);
            return array('success' => true);
        }
        
        return array('success' => false, 'error' => 'القالب غير موجود');
    }
    
    /**
     * Apply template to import data
     */
    public function apply_template($template_id, $import_data) {
        $template = $this->get_template($template_id);
        
        if (!$template) {
            return array('success' => false, 'error' => 'القالب غير موجود');
        }
        
        $mapped_data = array();
        
        foreach ($template['fields'] as $dest_field => $field_label) {
            // Try to find matching field in import data
            foreach ($import_data as $source_field => $value) {
                if ($this->fields_match($source_field, $dest_field)) {
                    $mapped_data[$dest_field] = $value;
                    break;
                }
            }
        }
        
        return array(
            'success' => true,
            'mapped_data' => $mapped_data,
            'template' => $template
        );
    }
    
    /**
     * Check if fields match
     */
    private function fields_match($source, $dest) {
        $source_lower = strtolower($source);
        $dest_lower = strtolower($dest);
        
        // Direct match
        if ($source_lower === $dest_lower) {
            return true;
        }
        
        // Partial match
        if (strpos($source_lower, $dest_lower) !== false || 
            strpos($dest_lower, $source_lower) !== false) {
            return true;
        }
        
        // Similar sounding
        $similar = array(
            'building' => 'masonry',
            'apartment' => 'flat',
            'rent' => 'lease'
        );
        
        foreach ($similar as $key => $value) {
            if (strpos($source_lower, $key) !== false && strpos($dest_lower, $value) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get template statistics
     */
    public function get_template_stats($template_id) {
        $template = $this->get_template($template_id);
        
        if (!$template) {
            return array('success' => false, 'error' => 'القالب غير موجود');
        }
        
        global $wpdb;
        
        $stats = array(
            'total_fields' => count($template['fields']),
            'uses_count' => 0,
            'last_used' => null
        );
        
        // Get usage count from logs
        $usage_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ms_import_logs WHERE template_id = %s",
            $template_id
        ));
        
        if ($usage_count) {
            $stats['uses_count'] = intval($usage_count);
            
            // Get last used date
            $last_used = $wpdb->get_var($wpdb->prepare(
                "SELECT created_at FROM {$wpdb->prefix}ms_import_logs WHERE template_id = %s ORDER BY created_at DESC LIMIT 1",
                $template_id
            ));
            
            if ($last_used) {
                $stats['last_used'] = $last_used;
            }
        }
        
        return array('success' => true, 'stats' => $stats);
    }
}
