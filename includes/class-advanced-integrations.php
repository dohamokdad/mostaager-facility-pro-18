<?php
/**
 * Advanced External Integrations - Mostaager Facility PRO Add-On
 */

if (!defined('ABSPATH')) {
    exit;
}

class MS_Advanced_Integrations {
    
    private $api_configs;
    
    public function __construct() {
        // Load API configurations
        $this->api_configs = get_option('ms_api_configs', array());
        
        // Add AJAX handlers
        add_action('wp_ajax_ms_test_integration', array($this, 'test_integration'));
        add_action('wp_ajax_ms_save_integration_config', array($this, 'save_integration_config'));
        add_action('wp_ajax_ms_sync_with_crm', array($this, 'sync_with_crm'));
        add_action('wp_ajax_ms_sync_with_accounting', array($this, 'sync_with_accounting'));
    }
    
    /**
     * Test integration
     */
    public function test_integration() {
        check_ajax_referer('ms_test_integration', 'nonce');
        
        $integration_type = sanitize_text_field($_POST['integration_type']);
        $config = json_decode(stripslashes($_POST['config']), true);
        
        $result = $this->test_connection($integration_type, $config);
        
        wp_send_json_success($result);
    }
    
    /**
     * Test connection
     */
    private function test_connection($integration_type, $config) {
        switch ($integration_type) {
            case 'salesforce':
                return $this->test_salesforce_connection($config);
            case 'hubspot':
                return $this->test_hubspot_connection($config);
            case 'zoho_crm':
                return $this->test_zoho_crm_connection($config);
            case 'quickbooks':
                return $this->test_quickbooks_connection($config);
            case 'xero':
                return $this->test_xero_connection($config);
            case 'stripe':
                return $this->test_stripe_connection($config);
            case 'paypal':
                return $this->test_paypal_connection($config);
            case 'telr':
                return $this->test_telr_connection($config);
            default:
                return array('success' => false, 'error' => 'نوع التكامل غير معروف');
        }
    }
    
    /**
     * Test Salesforce connection
     */
    private function test_salesforce_connection($config) {
        // Placeholder for Salesforce API test
        return array('success' => false, 'error' => 'تكامل Salesforce يتطلب إعداد API');
    }
    
    /**
     * Test HubSpot connection
     */
    private function test_hubspot_connection($config) {
        $api_key = $config['api_key'];
        
        $response = wp_remote_get('https://api.hubapi.com/crm/v3/owners/hapikey=' . $api_key);
        
        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code === 200) {
            return array('success' => true, 'message' => 'اتصال HubSpot ناجح');
        } else {
            return array('success' => false, 'error' => 'رمز API غير صحيح');
        }
    }
    
    /**
     * Test Zoho CRM connection
     */
    private function test_zoho_crm_connection($config) {
        // Placeholder for Zoho CRM API test
        return array('success' => false, 'error' => 'تكامل Zoho CRM يتطلب إعداد API');
    }
    
    /**
     * Test QuickBooks connection
     */
    private function test_quickbooks_connection($config) {
        // Placeholder for QuickBooks API test
        return array('success' => false, 'error' => 'تكامل QuickBooks يتطلب إعداد API');
    }
    
    /**
     * Test Xero connection
     */
    private function test_xero_connection($config) {
        // Placeholder for Xero API test
        return array('success' => false, 'error' => 'تكامل Xero يتطلب إعداد API');
    }
    
    /**
     * Test Stripe connection
     */
    private function test_stripe_connection($config) {
        $api_key = $config['api_key'];
        
        $response = wp_remote_get('https://api.stripe.com/v1/account', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key
            )
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code === 200) {
            return array('success' => true, 'message' => 'اتصال Stripe ناجح');
        } else {
            return array('success' => false, 'error' => 'رمز API غير صحيح');
        }
    }
    
    /**
     * Test PayPal connection
     */
    private function test_paypal_connection($config) {
        // Placeholder for PayPal API test
        return array('success' => false, 'error' => 'تكامل PayPal يتطلب إعداد API');
    }
    
    /**
     * Test Telr connection
     */
    private function test_telr_connection($config) {
        // Placeholder for Telr API test
        return array('success' => false, 'error' => 'تكامل Telr يتطلب إعداد API');
    }
    
    /**
     * Save integration config
     */
    public function save_integration_config() {
        check_ajax_referer('ms_save_integration_config', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('error' => 'ليس لديك الصلاحية'));
        }
        
        $integration_type = sanitize_text_field($_POST['integration_type']);
        $config = json_decode(stripslashes($_POST['config']), true);
        
        $this->api_configs[$integration_type] = $config;
        update_option('ms_api_configs', $this->api_configs);
        
        wp_send_json_success(array('message' => 'تم حفظ التكوين بنجاح'));
    }
    
    /**
     * Sync with CRM
     */
    public function sync_with_crm() {
        check_ajax_referer('ms_sync_with_crm', 'nonce');
        
        $crm_type = sanitize_text_field($_POST['crm_type']);
        $sync_direction = sanitize_text_field($_POST['sync_direction']); // 'to_crm' or 'from_crm'
        
        $result = $this->perform_crm_sync($crm_type, $sync_direction);
        
        wp_send_json_success($result);
    }
    
    /**
     * Perform CRM sync
     */
    private function perform_crm_sync($crm_type, $sync_direction) {
        $config = isset($this->api_configs[$crm_type]) ? $this->api_configs[$crm_type] : null;
        
        if (!$config) {
            return array('success' => false, 'error' => 'تكوين CRM غير موجود');
        }
        
        switch ($crm_type) {
            case 'salesforce':
                return $this->sync_salesforce($config, $sync_direction);
            case 'hubspot':
                return $this->sync_hubspot($config, $sync_direction);
            case 'zoho_crm':
                return $this->sync_zoho_crm($config, $sync_direction);
            default:
                return array('success' => false, 'error' => 'نوع CRM غير معروف');
        }
    }
    
    /**
     * Sync Salesforce
     */
    private function sync_salesforce($config, $sync_direction) {
        // Placeholder for Salesforce sync
        return array('success' => false, 'error' => 'تكامل Salesforce يتطلب إعداد API');
    }
    
    /**
     * Sync HubSpot
     */
    private function sync_hubspot($config, $sync_direction) {
        $result = array('success' => false, 'synced' => 0, 'errors' => array());
        
        $api_key = $config['api_key'];
        
        if ($sync_direction === 'to_crm') {
            // Sync properties and tenants to HubSpot
            $result = $this->sync_to_hubspot($api_key);
        } else {
            // Sync from HubSpot
            $result = $this->sync_from_hubspot($api_key);
        }
        
        return $result;
    }
    
    /**
     * Sync to HubSpot
     */
    private function sync_to_hubspot($api_key) {
        $result = array('success' => false, 'synced' => 0, 'errors' => array());
        
        // Get properties
        $properties = get_posts(array(
            'post_type' => 'property',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        foreach ($properties as $property) {
            $contact_data = array(
                'properties' => array(
                    array(
                        'property' => 'email',
                        'value' => get_post_meta($property->ID, 'ms_current_tenant_email', true)
                    ),
                    array(
                        'property' => 'firstname',
                        'value' => get_post_meta($property->ID, 'ms_current_tenant_name', true)
                    ),
                    array(
                        'property' => 'property_address',
                        'value' => get_post_meta($property->ID, 'fave_property_address', true)
                    ),
                    array(
                        'property' => 'property_unit',
                        'value' => get_post_meta($property->ID, 'ms_unit_number', true)
                    )
                )
            );
            
            $response = wp_remote_post('https://api.hubapi.com/crm/v3/objects/contacts?hapikey=' . $api_key, array(
                'headers' => array('Content-Type' => 'application/json'),
                'body' => json_encode($contact_data)
            ));
            
            if (!is_wp_error($response)) {
                $result['synced']++;
            } else {
                $result['errors'][] = $response->get_error_message();
            }
        }
        
        $result['success'] = $result['synced'] > 0;
        
        return $result;
    }
    
    /**
     * Sync from HubSpot
     */
    private function sync_from_hubspot($api_key) {
        // Placeholder for syncing from HubSpot
        return array('success' => false, 'error' => 'المزامنة من HubSpot قيد التطوير');
    }
    
    /**
     * Sync Zoho CRM
     */
    private function sync_zoho_crm($config, $sync_direction) {
        // Placeholder for Zoho CRM sync
        return array('success' => false, 'error' => 'تكامل Zoho CRM يتطلب إعداد API');
    }
    
    /**
     * Sync with accounting
     */
    public function sync_with_accounting() {
        check_ajax_referer('ms_sync_with_accounting', 'nonce');
        
        $accounting_type = sanitize_text_field($_POST['accounting_type']);
        $sync_direction = sanitize_text_field($_POST['sync_direction']);
        
        $result = $this->perform_accounting_sync($accounting_type, $sync_direction);
        
        wp_send_json_success($result);
    }
    
    /**
     * Perform accounting sync
     */
    private function perform_accounting_sync($accounting_type, $sync_direction) {
        $config = isset($this->api_configs[$accounting_type]) ? $this->api_configs[$accounting_type] : null;
        
        if (!$config) {
            return array('success' => false, 'error' => 'تكوين المحاسبة غير موجود');
        }
        
        switch ($accounting_type) {
            case 'quickbooks':
                return $this->sync_quickbooks($config, $sync_direction);
            case 'xero':
                return $this->sync_xero($config, $sync_direction);
            default:
                return array('success' => false, 'error' => 'نظام المحاسبة غير معروف');
        }
    }
    
    /**
     * Sync QuickBooks
     */
    private function sync_quickbooks($config, $sync_direction) {
        // Placeholder for QuickBooks sync
        return array('success' => false, 'error' => 'تكامل QuickBooks يتطلب إعداد API');
    }
    
    /**
     * Sync Xero
     */
    private function sync_xero($config, $sync_direction) {
        // Placeholder for Xero sync
        return array('success' => false, 'error' => 'تكامل Xero يتطلب إعداد API');
    }
    
    /**
     * Get available integrations
     */
    public function get_available_integrations() {
        return array(
            'crm' => array(
                'salesforce' => array(
                    'name' => 'Salesforce',
                    'description' => 'تكامل مع Salesforce CRM',
                    'icon' => '🏢',
                    'required_fields' => array('api_key', 'api_secret', 'instance_url')
                ),
                'hubspot' => array(
                    'name' => 'HubSpot',
                    'description' => 'تكامل مع HubSpot CRM',
                    'icon' => '🎯',
                    'required_fields' => array('api_key')
                ),
                'zoho_crm' => array(
                    'name' => 'Zoho CRM',
                    'description' => 'تكامل مع Zoho CRM',
                    'icon' => '📊',
                    'required_fields' => array('api_key', 'org_id')
                )
            ),
            'accounting' => array(
                'quickbooks' => array(
                    'name' => 'QuickBooks',
                    'description' => 'تكامل مع QuickBooks',
                    'icon' => '💰',
                    'required_fields' => array('api_key', 'api_secret', 'realm_id')
                ),
                'xero' => array(
                    'name' => 'Xero',
                    'description' => 'تكامل مع Xero',
                    'icon' => '📈',
                    'required_fields' => array('api_key', 'api_secret')
                )
            ),
            'payment' => array(
                'stripe' => array(
                    'name' => 'Stripe',
                    'description' => 'تكامل مع Stripe',
                    'icon' => '💳',
                    'required_fields' => array('api_key', 'secret_key')
                ),
                'paypal' => array(
                    'name' => 'PayPal',
                    'description' => 'تكامل مع PayPal',
                    'icon' => '🅿️',
                    'required_fields' => array('client_id', 'client_secret')
                ),
                'telr' => array(
                    'name' => 'Telr',
                    'description' => 'تكامل مع Telr',
                    'icon' => '🌍',
                    'required_fields' => array('api_key', 'store_id')
                )
            )
        );
    }
}

// Initialize advanced integrations - skip during installation
if (!defined('WP_INSTALLING') || !WP_INSTALLING) {
    new MS_Advanced_Integrations();
}
