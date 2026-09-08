<?php
/**
 * Migration Script - Mostaager Facility PRO Enterprise
 * This script helps migrate data from the old system to the new unified structure
 */

if (!defined('ABSPATH')) {
    die('Direct access not allowed');
}

global $wpdb;

$ms_prefix = $wpdb->prefix . 'ms_';

// Check if this is a migration request
if (isset($_POST['ms_migration_action']) && check_admin_referer('ms_migration_nonce', 'ms_migration_nonce')) {
    $action = sanitize_text_field($_POST['ms_migration_action']);
    
    switch ($action) {
        case 'backup':
            $result = ms_create_migration_backup();
            break;
        case 'migrate':
            $result = ms_perform_migration();
            break;
        case 'verify':
            $result = ms_verify_migration();
            break;
        default:
            $result = array('success' => false, 'message' => 'Invalid action');
    }
    
    echo json_encode($result);
    exit;
}

/**
 * Create migration backup
 */
function ms_create_migration_backup() {
    global $wpdb;
    
    $backup_data = array(
        'timestamp' => current_time('mysql'),
        'version' => MOSTAAGER_ENTERPRISE_VERSION,
        'tables' => array()
    );
    
    // Backup mostaager tables
    $tables = array('buildings', 'units', 'unit_tenants', 'invoices', 'maintenance_requests');
    
    foreach ($tables as $table) {
        $table_name = $ms_prefix . $table;
        $backup_data['tables'][$table] = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
    }
    
    // Backup property meta
    $backup_data['property_meta'] = array();
    $properties = get_posts(array('post_type' => 'property', 'posts_per_page' => -1));
    
    foreach ($properties as $property) {
        $backup_data['property_meta'][$property->ID] = get_post_meta($property->ID);
    }
    
    // Save backup
    $upload_dir = wp_upload_dir();
    $backup_dir = $upload_dir['basedir'] . '/ms-migration-backups';
    
    if (!file_exists($backup_dir)) {
        wp_mkdir_p($backup_dir);
    }
    
    $filename = 'ms-migration-backup-' . date('Y-m-d-H-i-s') . '.json';
    $filepath = $backup_dir . '/' . $filename;
    
    file_put_contents($filepath, json_encode($backup_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    return array(
        'success' => true,
        'message' => 'Backup created successfully',
        'file' => $filename
    );
}

/**
 * Perform migration
 */
function ms_perform_migration() {
    global $wpdb;
    
    $results = array(
        'success' => true,
        'migrated' => array(),
        'errors' => array()
    );
    
    // Check if ms_units table exists
    $units_table = $ms_prefix . 'units';
    $units_exists = $wpdb->get_var("SHOW TABLES LIKE '$units_table'");
    
    if ($units_exists) {
        // Migrate units to properties
        $units = $wpdb->get_results("SELECT * FROM $units_table", ARRAY_A);
        
        foreach ($units as $unit) {
            $result = ms_migrate_unit_to_property($unit);
            
            if ($result['success']) {
                $results['migrated']['units'][] = $result;
            } else {
                $results['errors'][] = $result['error'];
            }
        }
    }
    
    // Migrate tenant links
    $tenant_links_table = $ms_prefix . 'unit_tenants';
    $tenant_links_exists = $wpdb->get_var("SHOW TABLES LIKE '$tenant_links_table'");
    
    if ($tenant_links_exists) {
        $tenant_links = $wpdb->get_results("SELECT * FROM $tenant_links_table", ARRAY_A);
        
        foreach ($tenant_links as $link) {
            $result = ms_migrate_tenant_link($link);
            
            if ($result['success']) {
                $results['migrated']['tenant_links'][] = $result;
            } else {
                $results['errors'][] = $result['error'];
            }
        }
    }
    
    return $results;
}

/**
 * Migrate unit to property
 */
function ms_migrate_unit_to_property($unit) {
    global $wpdb;
    
    // Find if property already exists for this unit
    $existing_property = $wpdb->get_row($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} 
        WHERE meta_key = 'ms_unit_external_id' 
        AND meta_value = %d",
        $unit['id']
    ));
    
    if ($existing_property) {
        return array('success' => true, 'message' => 'Property already exists', 'property_id' => $existing_property->post_id);
    }
    
    // Create new property
    $post_id = wp_insert_post(array(
        'post_type' => 'property',
        'post_title' => $unit['title'] ?? 'Unit ' . $unit['unit_number'],
        'post_content' => $unit['description'] ?? '',
        'post_status' => 'publish'
    ));
    
    if (is_wp_error($post_id)) {
        return array('success' => false, 'error' => 'Failed to create property');
    }
    
    // Add unit data as meta
    update_post_meta($post_id, 'ms_unit_external_id', $unit['id']);
    update_post_meta($post_id, 'ms_unit_number', $unit['unit_number']);
    update_post_meta($post_id, 'ms_floor', $unit['floor']);
    update_post_meta($post_id, 'ms_unit_type', $unit['type']);
    update_post_meta($post_id, 'ms_unit_status', $unit['status']);
    update_post_meta($post_id, 'ms_unit_size', $unit['size']);
    update_post_meta($post_id, 'ms_unit_price', $unit['price']);
    update_post_meta($post_id, 'ms_unit_rent', $unit['rent']);
    update_post_meta($post_id, 'ms_building_id', $unit['building_id']);
    
    return array('success' => true, 'message' => 'Unit migrated successfully', 'property_id' => $post_id);
}

/**
 * Migrate tenant link
 */
function ms_migrate_tenant_link($link) {
    global $wpdb;
    
    // Find property by unit external ID
    $property_id = $wpdb->get_var($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} 
        WHERE meta_key = 'ms_unit_external_id' 
        AND meta_value = %d",
        $link['unit_id']
    ));
    
    if (!$property_id) {
        return array('success' => false, 'error' => 'Property not found for unit');
    }
    
    // Update tenant link to use post_id
    $wpdb->update(
        $wpdb->prefix . 'ms_unit_tenants',
        array('unit_id' => $property_id),
        array('id' => $link['id'])
    );
    
    // Add tenant data to property
    update_post_meta($property_id, 'ms_current_tenant_id', $link['tenant_id']);
    update_post_meta($property_id, 'ms_current_tenant_name', $link['tenant_name']);
    update_post_meta($property_id, 'ms_current_tenant_email', $link['tenant_email']);
    update_post_meta($property_id, 'ms_current_tenant_phone', $link['tenant_phone']);
    
    return array('success' => true, 'message' => 'Tenant link migrated successfully');
}

/**
 * Verify migration
 */
function ms_verify_migration() {
    global $wpdb;
    
    $results = array(
        'success' => true,
        'checks' => array()
    );
    
    // Check if properties have unit data
    $properties_with_unit_data = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->postmeta} 
        WHERE meta_key = 'ms_unit_number'"
    );
    
    $results['checks']['properties_with_unit_data'] = $properties_with_unit_data;
    
    // Check if tenant links are updated
    $updated_links = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ms_unit_tenants 
        WHERE unit_id REGEXP '^[0-9]+$' AND unit_id > 10000"
    );
    
    $results['checks']['updated_tenant_links'] = $updated_links;
    
    // Check for orphaned records
    $orphaned_links = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ms_unit_tenants t
        LEFT JOIN {$wpdb->posts} p ON t.unit_id = p.ID
        WHERE p.ID IS NULL"
    );
    
    $results['checks']['orphaned_links'] = $orphaned_links;
    
    return $results;
}
