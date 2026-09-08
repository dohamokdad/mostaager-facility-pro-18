<?php
/**
 * Data Sync - Mostaager Facility PRO Add-On
 */

if (!defined('ABSPATH')) {
    exit;
}

class MS_Data_Sync {
    
    public function __construct() {
        add_action('wp_ajax_ms_sync_data', array($this, 'sync_data'));
        add_action('wp_ajax_ms_check_sync_status', array($this, 'check_sync_status'));
    }
    
    /**
     * Sync data
     */
    public function sync_data() {
        check_ajax_referer('ms_sync_data', 'nonce');
        
        $source_url = sanitize_url($_POST['source_url']);
        $api_key = sanitize_text_field($_POST['api_key']);
        $sync_type = sanitize_text_field($_POST['sync_type']);
        
        $result = $this->perform_sync($source_url, $api_key, $sync_type);
        
        wp_send_json_success($result);
    }
    
    /**
     * Perform sync
     */
    private function perform_sync($source_url, $api_key, $sync_type) {
        $result = array('success' => false, 'synced' => 0, 'errors' => array());
        
        // Fetch data from external source
        $response = wp_remote_get($source_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            $result['errors'][] = 'فشل الاتصال بالمصدر: ' . $response->get_error_message();
            return $result;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!$data) {
            $result['errors'][] = 'بيانات المصدر غير صحيحة';
            return $result;
        }
        
        // Sync based on type
        switch ($sync_type) {
            case 'properties':
                $sync_result = $this->sync_properties($data);
                break;
            case 'tenants':
                $sync_result = $this->sync_tenants($data);
                break;
            case 'full':
                $sync_result = $this->sync_full($data);
                break;
            default:
                $result['errors'][] = 'نوع المزامنة غير معروف';
                return $result;
        }
        
        return $sync_result;
    }
    
    /**
     * Sync properties
     */
    private function sync_properties($data) {
        $result = array('success' => false, 'synced' => 0, 'errors' => array());
        
        if (!isset($data['properties'])) {
            $result['errors'][] = 'بيانات العقارات مفقودة';
            return $result;
        }
        
        foreach ($data['properties'] as $property_data) {
            $sync_result = $this->sync_single_property($property_data);
            
            if ($sync_result['success']) {
                $result['synced']++;
            } else {
                $result['errors'] = array_merge($result['errors'], $sync_result['errors']);
            }
        }
        
        $result['success'] = $result['synced'] > 0;
        
        return $result;
    }
    
    /**
     * Sync single property
     */
    private function sync_single_property($property_data) {
        $result = array('success' => false, 'errors' => array());
        
        // Check if property exists
        $existing = $this->find_existing_property($property_data['external_id']);
        
        if ($existing) {
            // Update existing property
            $result = $this->update_property($existing->ID, $property_data);
        } else {
            // Create new property
            $result = $this->create_property($property_data);
        }
        
        return $result;
    }
    
    /**
     * Find existing property
     */
    private function find_existing_property($external_id) {
        $args = array(
            'post_type' => 'property',
            'meta_query' => array(
                array(
                    'key' => 'ms_external_id',
                    'value' => $external_id
                )
            ),
            'posts_per_page' => 1
        );
        
        $posts = get_posts($args);
        
        return !empty($posts) ? $posts[0] : null;
    }
    
    /**
     * Update property
     */
    private function update_property($post_id, $property_data) {
        $result = array('success' => false, 'errors' => array());
        
        $update_result = wp_update_post(array(
            'ID' => $post_id,
            'post_title' => $property_data['title'],
            'post_content' => $property_data['description']
        ));
        
        if ($update_result && !is_wp_error($update_result)) {
            // Update meta data
            foreach ($property_data['meta'] as $key => $value) {
                update_post_meta($post_id, $key, $value);
            }
            
            $result['success'] = true;
        } else {
            $result['errors'][] = 'فشل تحديث العقار';
        }
        
        return $result;
    }
    
    /**
     * Create property
     */
    private function create_property($property_data) {
        $result = array('success' => false, 'errors' => array());
        
        $post_id = wp_insert_post(array(
            'post_type' => 'property',
            'post_title' => $property_data['title'],
            'post_content' => $property_data['description'],
            'post_status' => 'publish'
        ));
        
        if ($post_id && !is_wp_error($post_id)) {
            // Add meta data
            update_post_meta($post_id, 'ms_external_id', $property_data['external_id']);
            
            foreach ($property_data['meta'] as $key => $value) {
                update_post_meta($post_id, $key, $value);
            }
            
            $result['success'] = true;
        } else {
            $result['errors'][] = 'فشل إنشاء العقار';
        }
        
        return $result;
    }
    
    /**
     * Sync tenants
     */
    private function sync_tenants($data) {
        $result = array('success' => false, 'synced' => 0, 'errors' => array());
        
        if (!isset($data['tenants'])) {
            $result['errors'][] = 'بيانات المستأجرين مفقودة';
            return $result;
        }
        
        foreach ($data['tenants'] as $tenant_data) {
            $sync_result = $this->sync_single_tenant($tenant_data);
            
            if ($sync_result['success']) {
                $result['synced']++;
            } else {
                $result['errors'] = array_merge($result['errors'], $sync_result['errors']);
            }
        }
        
        $result['success'] = $result['synced'] > 0;
        
        return $result;
    }
    
    /**
     * Sync single tenant
     */
    private function sync_single_tenant($tenant_data) {
        $result = array('success' => false, 'errors' => array());
        
        // Check if user exists
        $user = get_user_by('email', $tenant_data['email']);
        
        if ($user) {
            // Update existing user
            $update_result = wp_update_user(array(
                'ID' => $user->ID,
                'display_name' => $tenant_data['name']
            ));
            
            if (is_wp_error($update_result)) {
                $result['errors'][] = 'فشل تحديث المستخدم';
            } else {
                $result['success'] = true;
            }
        } else {
            // Create new user
            $user_id = wp_create_user($tenant_data['email'], wp_generate_password(), $tenant_data['email']);
            
            if (is_wp_error($user_id)) {
                $result['errors'][] = 'فشل إنشاء المستخدم';
            } else {
                wp_update_user(array(
                    'ID' => $user_id,
                    'display_name' => $tenant_data['name']
                ));
                
                $result['success'] = true;
            }
        }
        
        return $result;
    }
    
    /**
     * Sync full data
     */
    private function sync_full($data) {
        $result = array('success' => false, 'synced' => 0, 'errors' => array());
        
        // Sync properties
        if (isset($data['properties'])) {
            $properties_result = $this->sync_properties($data);
            $result['synced'] += $properties_result['synced'];
            $result['errors'] = array_merge($result['errors'], $properties_result['errors']);
        }
        
        // Sync tenants
        if (isset($data['tenants'])) {
            $tenants_result = $this->sync_tenants($data);
            $result['synced'] += $tenants_result['synced'];
            $result['errors'] = array_merge($result['errors'], $tenants_result['errors']);
        }
        
        $result['success'] = $result['synced'] > 0;
        
        return $result;
    }
    
    /**
     * Check sync status
     */
    public function check_sync_status() {
        check_ajax_referer('ms_check_sync_status', 'nonce');
        
        $last_sync = get_option('ms_last_sync', array());
        
        wp_send_json_success(array(
            'last_sync' => $last_sync,
            'sync_count' => $last_sync['synced'] ?? 0,
            'sync_errors' => $last_sync['errors'] ?? array()
        ));
    }
}
