<?php
/**
 * Mostaager Facility PRO Add-On - WP All Import Integration
 * This file is loaded by the main plugin and should not be treated as a separate plugin
 */

// Include rapid-addon framework
if (file_exists(plugin_dir_path(__FILE__) . 'rapid-addon.php')) {
    require_once plugin_dir_path(__FILE__) . 'rapid-addon.php';
}

// Include admin dashboard
if (file_exists(plugin_dir_path(__FILE__) . 'admin/dashboard.php')) {
    require_once plugin_dir_path(__FILE__) . 'admin/dashboard.php';
}

// Include additional features
if (file_exists(plugin_dir_path(__FILE__) . 'includes/class-validator.php')) {
    require_once plugin_dir_path(__FILE__) . 'includes/class-validator.php';
}

if (file_exists(plugin_dir_path(__FILE__) . 'includes/class-templates.php')) {
    require_once plugin_dir_path(__FILE__) . 'includes/class-templates.php';
}

// Include Houzez integration
if (file_exists(plugin_dir_path(__FILE__) . 'includes/class-houzez-integration.php')) {
    require_once plugin_dir_path(__FILE__) . 'includes/class-houzez-integration.php';
}

// Include reports system
if (file_exists(plugin_dir_path(__FILE__) . 'includes/class-reports.php')) {
    require_once plugin_dir_path(__FILE__) . 'includes/class-reports.php';
}

// Include custom fields manager
if (file_exists(plugin_dir_path(__FILE__) . 'includes/class-custom-fields.php')) {
    require_once plugin_dir_path(__FILE__) . 'includes/class-custom-fields.php';
}

// Include performance optimizer
if (file_exists(plugin_dir_path(__FILE__) . 'includes/class-performance.php')) {
    require_once plugin_dir_path(__FILE__) . 'includes/class-performance.php';
}

// Include additional integrations
if (file_exists(plugin_dir_path(__FILE__) . 'includes/class-integrations.php')) {
    require_once plugin_dir_path(__FILE__) . 'includes/class-integrations.php';
}

// Include advanced automation
if (file_exists(plugin_dir_path(__FILE__) . 'includes/class-automation.php')) {
    require_once plugin_dir_path(__FILE__) . 'includes/class-automation.php';
}

// Include automation importer
if (file_exists(plugin_dir_path(__FILE__) . 'includes/class-automation-importer.php')) {
    require_once plugin_dir_path(__FILE__) . 'includes/class-automation-importer.php';
}

// Include data sync
if (file_exists(plugin_dir_path(__FILE__) . 'includes/class-data-sync.php')) {
    require_once plugin_dir_path(__FILE__) . 'includes/class-data-sync.php';
}

// Include notifications
if (file_exists(plugin_dir_path(__FILE__) . 'includes/class-notifications.php')) {
    require_once plugin_dir_path(__FILE__) . 'includes/class-notifications.php';
}

// Include backup system
if (file_exists(plugin_dir_path(__FILE__) . 'includes/class-backup.php')) {
    require_once plugin_dir_path(__FILE__) . 'includes/class-backup.php';
}

// Include advanced integrations
if (file_exists(plugin_dir_path(__FILE__) . 'includes/class-advanced-integrations.php')) {
    require_once plugin_dir_path(__FILE__) . 'includes/class-advanced-integrations.php';
}

// Include monitoring and analytics
if (file_exists(plugin_dir_path(__FILE__) . 'includes/class-monitoring.php')) {
    require_once plugin_dir_path(__FILE__) . 'includes/class-monitoring.php';
}

$mostaager_addon = new RapidAddon( 'Mostaager Facility PRO Add-On', 'mostaager_facility_addon' );

$mostaager_addon->disable_default_images();

// Import images for properties
$mostaager_addon->import_images( 'mostaager_addon_property_images', 'Property Images' );

function mostaager_addon_property_images( $post_id, $attachment_id, $image_filepath, $import_options ) {
    add_post_meta( $post_id, 'fave_property_images', $attachment_id ) || update_post_meta( $post_id, 'fave_property_images', $attachment_id );
}

// Import floor plan images
$mostaager_addon->import_images( 'mostaager_addon_floorplan_images', 'Floor Plan Images' );

function mostaager_addon_floorplan_images( $post_id, $attachment_id, $image_filepath, $import_options ) {
    static $last_post_id, $fp_count;

    $fp_count = ($post_id === $last_post_id) ? $fp_count + 1 : 0;
    $fp_meta = get_post_meta( $post_id, 'floor_plans', true );
    $image = wp_get_attachment_image_src( $attachment_id, 'full' );
    if ( !is_array( $image ) ) return;
    $fp_meta[$fp_count]['fave_plan_image'] = $image[0];
    update_post_meta( $post_id, 'floor_plans', $fp_meta );
    $last_post_id = $post_id;
}

// Import property attachments
$mostaager_addon->import_files( 'mostaager_addon_property_attachments', 'Property Attachments' );

function mostaager_addon_property_attachments( $post_id, $attachment_id, $image_filepath, $import_options ) {
    add_post_meta( $post_id, 'fave_attachments', $attachment_id ) || update_post_meta( $post_id, 'fave_attachments', $attachment_id );
}

// ============== MOSTAGER BUILDING FIELDS ==============

$mostaager_addon->add_field( 'ms_building_title', 'Building Title', 'text', null, 'Building name for Mostaager system' );
$mostaager_addon->add_field( 'ms_building_manager_id', 'Building Manager ID', 'text', null, 'User ID of the building manager' );
$mostaager_addon->add_field( 'ms_building_supervisor_name', 'Supervisor Name', 'text', null, 'Name of the building supervisor' );
$mostaager_addon->add_field( 'ms_building_supervisor_phone', 'Supervisor Phone', 'text', null, 'Phone number of the building supervisor' );

// ============== MOSTAGER UNIT FIELDS ==============

$mostaager_addon->add_field( 'ms_unit_number', 'Unit Number', 'text', null, 'Unit number in the building' );
$mostaager_addon->add_field( 'ms_floor', 'Floor Number', 'text', null, 'Floor number (e.g., 1, 2, ground)' );
$mostaager_addon->add_field( 'ms_unit_type', 'Unit Type', 'radio', array(
    'apartment' => 'شقة (Apartment)',
    'shop' => 'محل (Shop)',
    'office' => 'مكتب (Office)',
    'villa' => 'فيلا (Villa)',
    'studio' => 'ستوديو (Studio)'
));
$mostaager_addon->add_field( 'ms_unit_status', 'Unit Status', 'radio', array(
    'available' => 'متاحة (Available)',
    'rented' => 'مؤجرة (Rented)',
    'sold' => 'مباعة (Sold)',
    'maintenance' => 'تحت الصيانة (Maintenance)'
));

// ============== HOUZEZ PROPERTY FIELDS ==============

$mostaager_addon->add_field( 'fave_currency', 'Select Currency', 'text', null, 'example: EGP, USD' );
$mostaager_addon->add_field( 'fave_property_price', 'Sale or Rent Price', 'text', null, 'Only digits, example: 557000' );
$mostaager_addon->add_field( 'fave_property_sec_price', 'Second Price', 'text', null, 'Optional price for rental or square feet' );
$mostaager_addon->add_field( 'fave_property_price_prefix', 'Before Price label', 'text', null, 'Example: Start From' );
$mostaager_addon->add_field( 'fave_property_price_postfix', 'After Price label', 'text', null, 'Example: Per Month' );
$mostaager_addon->add_field( 'fave_property_size', 'Area Size', 'text', null, 'Only digits, example: 250' );
$mostaager_addon->add_field( 'fave_property_size_prefix', 'Area Size Postfix', 'text', null, 'Example: sq m, m²' );
$mostaager_addon->add_field( 'fave_property_land', 'Land Area', 'text', null, 'Only digits, example: 500' );
$mostaager_addon->add_field( 'fave_property_land_postfix', 'Land Area Postfix', 'text', null, 'Example: sq m, m²' );
$mostaager_addon->add_field( 'fave_property_bedrooms', 'Bedrooms', 'text', null, 'Example: 3' );
$mostaager_addon->add_field( 'fave_property_bathrooms', 'Bathrooms', 'text', null, 'Example: 2' );
$mostaager_addon->add_field( 'fave_property_rooms', 'Rooms', 'text', null, 'Example: 4' );
$mostaager_addon->add_field( 'fave_property_garage', 'Garages', 'text', null, 'Example: 1' );
$mostaager_addon->add_field( 'fave_private_note', 'Private Note', 'textarea', null, 'Internal notes' );
$mostaager_addon->add_field( 'fave_property_garage_size', 'Garage Size', 'text', null, 'Example: 50 sq m' );
$mostaager_addon->add_field( 'fave_virtual_tour', '360° Virtual Tour', 'textarea', null, 'Embed code or iframe' );
$mostaager_addon->add_field( 'fave_video_url', 'Video URL', 'text', null, 'YouTube, Vimeo, etc.' );
$mostaager_addon->add_field( 'fave_video_image', 'Video Image', 'image', null, 'Placeholder image for video' );

// Energy efficiency fields
$mostaager_addon->add_field( 'fave_energy_class', 'Energy Class', 'text', null, 'Example: A+, A, B' );
$mostaager_addon->add_field( 'fave_energy_global_index', 'Global Energy Performance Index', 'text', null, 'Example: 92.42 kWh/m²a' );
$mostaager_addon->add_field( 'fave_renewable_energy_global_index', 'Renewable Energy Index', 'text', null, 'Example: 00.00 kWh/m²a' );
$mostaager_addon->add_field( 'fave_energy_performance', 'Energy Performance', 'text', null, '' );
$mostaager_addon->add_field( 'fave_epc_current_rating', 'EPC Current Rating', 'text', null, '' );
$mostaager_addon->add_field( 'fave_epc_potential_rating', 'EPC Potential Rating', 'text', null, '' );

// Agent and agency
$mostaager_addon->add_field( 'fave_agents', 'Agent', 'text', null, 'Agent ID or name' );
$mostaager_addon->add_field( 'fave_property_agency', 'Agency', 'text', null, 'Agency ID or name' );

// Map settings
$mostaager_addon->add_field( 'fave_property_map', 'Show Map', 'radio', array(
    '1' => 'Yes',
    '0' => 'No'
));

// Location settings
$mostaager_addon->add_field(
    'location_settings',
    'Property Map Location',
    'radio',
    array(
        'search_by_address' => array(
            'Search by Address',
            $mostaager_addon->add_options(
                $mostaager_addon->add_field(
                    'fave_property_map_address',
                    'Property Address',
                    'text'
                ),
                'Google Geocode API Settings',
                array(
                    $mostaager_addon->add_field(
                        'address_geocode',
                        'Request Method',
                        'radio',
                        array(
                            'address_no_key' => array(
                                'No API Key',
                                'Limited requests'
                            ),
                            'address_google_developers' => array(
                                'Google Developers API Key',
                                $mostaager_addon->add_field(
                                    'address_google_developers_api_key',
                                    'API Key',
                                    'text'
                                ),
                                'Up to 2500 requests per day'
                            )
                        )
                    )
                )
            )
        ),
        'search_by_coordinates' => array(
            'Search by Coordinates',
            $mostaager_addon->add_field(
                'property_latitude',
                'Latitude',
                'text',
                null,
                'Example: 30.0444'
            ),
            $mostaager_addon->add_field(
                'property_longitude',
                'Longitude',
                'text',
                null,
                'Example: 31.2357'
            )
        )
    )
);

// Property type and status
$mostaager_addon->add_field( 'fave_property_type', 'Property Type', 'text', null, 'e.g., apartment, villa' );
$mostaager_addon->add_field( 'fave_property_status', 'Property Status', 'radio', array(
    'for_sale' => 'للبيع',
    'for_rent' => 'للإيجار'
));

// ============== TENANT AND OWNER FIELDS ==============

$mostaager_addon->add_field( 'ms_tenant_name', 'Tenant Name', 'text', null, 'Full name of the tenant' );
$mostaager_addon->add_field( 'ms_tenant_phone', 'Tenant Phone', 'text', null, 'Contact phone number' );
$mostaager_addon->add_field( 'ms_tenant_email', 'Tenant Email', 'text', null, 'Email address' );
$mostaager_addon->add_field( 'ms_tenant_national_id', 'Tenant National ID', 'text', null, 'National ID number' );
$mostaager_addon->add_field( 'ms_owner_name', 'Owner Name', 'text', null, 'Full name of the owner' );
$mostaager_addon->add_field( 'ms_owner_phone', 'Owner Phone', 'text', null, 'Contact phone number' );
$mostaager_addon->add_field( 'ms_owner_email', 'Owner Email', 'text', null, 'Email address' );
$mostaager_addon->add_field( 'ms_agent_name', 'Agent Name', 'text', null, 'Full name of the agent' );
$mostaager_addon->add_field( 'ms_agent_phone', 'Agent Phone', 'text', null, 'Contact phone number' );

// ============== LEASE INFORMATION ==============

$mostaager_addon->add_field( 'ms_lease_start_date', 'Lease Start Date', 'text', null, 'YYYY-MM-DD format' );
$mostaager_addon->add_field( 'ms_lease_end_date', 'Lease End Date', 'text', null, 'YYYY-MM-DD format' );
$mostaager_addon->add_field( 'ms_monthly_rent', 'Monthly Rent', 'text', null, 'Monthly rent amount' );
$mostaager_addon->add_field( 'ms_payment_method', 'Payment Method', 'radio', array(
    'cash' => 'Cash',
    'bank_transfer' => 'Bank Transfer',
    'check' => 'Check',
    'online' => 'Online Payment'
));

// ============== IMPORT FUNCTION ==============

$mostaager_addon->set_import_function( 'mostaager_facility_import_function' );

function mostaager_facility_import_function( $post_id, $import_options ) {
    global $wpdb;
    
    // Check if ms_units table exists for backward compatibility
    $units_table = $wpdb->prefix . 'ms_units';
    $units_table_exists = ( $wpdb->get_var( "SHOW TABLES LIKE '{$units_table}'" ) === $units_table );
    
    // Save Houzez fields
    $houzez_fields = array(
        'fave_currency', 'fave_property_price', 'fave_property_sec_price',
        'fave_property_price_prefix', 'fave_property_price_postfix',
        'fave_property_size', 'fave_property_size_prefix',
        'fave_property_land', 'fave_property_land_postfix',
        'fave_property_bedrooms', 'fave_property_bathrooms', 'fave_property_rooms',
        'fave_property_garage', 'fave_private_note', 'fave_property_garage_size',
        'fave_virtual_tour', 'fave_video_url', 'fave_video_image',
        'fave_energy_class', 'fave_energy_global_index', 'fave_renewable_energy_global_index',
        'fave_energy_performance', 'fave_epc_current_rating', 'fave_epc_potential_rating',
        'fave_agents', 'fave_property_agency', 'fave_property_map',
        'fave_property_map_address', 'property_latitude', 'property_longitude',
        'fave_property_type', 'fave_property_status'
    );
    
    foreach ( $houzez_fields as $field ) {
        if ( isset( $import_options[$field] ) && !empty( $import_options[$field] ) ) {
            update_post_meta( $post_id, $field, $import_options[$field] );
        }
    }
    
    // Save Mostaager unit fields directly to property post
    $ms_unit_fields = array(
        'ms_unit_number', 'ms_floor', 'ms_unit_type', 'ms_unit_status'
    );
    
    foreach ( $ms_unit_fields as $field ) {
        if ( isset( $import_options[$field] ) && !empty( $import_options[$field] ) ) {
            update_post_meta( $post_id, $field, $import_options[$field] );
        }
    }
    
    // Handle building creation/update
    if ( !empty( $import_options['ms_building_title'] ) ) {
        $building_title = sanitize_text_field( $import_options['ms_building_title'] );
        $manager_id = !empty( $import_options['ms_building_manager_id'] ) ? intval( $import_options['ms_building_manager_id'] ) : 0;
        $supervisor_name = !empty( $import_options['ms_building_supervisor_name'] ) ? sanitize_text_field( $import_options['ms_building_supervisor_name'] ) : '';
        $supervisor_phone = !empty( $import_options['ms_building_supervisor_phone'] ) ? sanitize_text_field( $import_options['ms_building_supervisor_phone'] ) : '';
        
        // Check if building exists
        $buildings_table = $wpdb->prefix . 'ms_buildings';
        $existing_building = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$buildings_table} WHERE title = %s LIMIT 1",
            $building_title
        ) );
        
        if ( $existing_building ) {
            $building_id = $existing_building->id;
            update_post_meta( $post_id, 'ms_building_id', $building_id );
        } else {
            // Create new building
            $wpdb->insert(
                $buildings_table,
                array(
                    'title' => $building_title,
                    'manager_id' => $manager_id,
                    'wp_post_id' => $post_id,
                    'created_at' => current_time( 'mysql' )
                ),
                array( '%s', '%d', '%d', '%s' )
            );
            
            $building_id = $wpdb->insert_id;
            update_post_meta( $post_id, 'ms_building_id', $building_id );
            
            // Save supervisor info
            if ( !empty( $supervisor_name ) ) {
                update_post_meta( $post_id, 'ms_sync_supervisor_name', $supervisor_name );
            }
            if ( !empty( $supervisor_phone ) ) {
                update_post_meta( $post_id, 'ms_sync_supervisor_phone', $supervisor_phone );
            }
        }
    }
    
    // Handle existing ms_units table data migration (for backward compatibility)
    if ( $units_table_exists ) {
        // Check if there's an existing unit for this property
        $existing_unit = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$units_table} WHERE wp_post_id = %d LIMIT 1",
            $post_id
        ) );
        
        if ( $existing_unit ) {
            // Migrate data from ms_units to post meta if not already done
            if ( !get_post_meta( $post_id, 'ms_unit_number', true ) && !empty( $existing_unit->unit_number ) ) {
                update_post_meta( $post_id, 'ms_unit_number', $existing_unit->unit_number );
            }
            if ( !get_post_meta( $post_id, 'ms_floor', true ) && !empty( $existing_unit->floor ) ) {
                update_post_meta( $post_id, 'ms_floor', $existing_unit->floor );
            }
            if ( !get_post_meta( $post_id, 'ms_unit_type', true ) && !empty( $existing_unit->unit_type ) ) {
                update_post_meta( $post_id, 'ms_unit_type', $existing_unit->unit_type );
            }
            if ( !get_post_meta( $post_id, 'ms_unit_status', true ) && !empty( $existing_unit->status ) ) {
                update_post_meta( $post_id, 'ms_unit_status', $existing_unit->status );
            }
            
            // Update existing unit with new data if provided
            if ( !empty( $import_options['ms_unit_number'] ) ) {
                $wpdb->update(
                    $units_table,
                    array( 'unit_number' => $import_options['ms_unit_number'] ),
                    array( 'id' => $existing_unit->id ),
                    array( '%s' ),
                    array( '%d' )
                );
            }
        }
    }
    
    // No separate units table - property IS the unit
    // Link tenant directly to property post
    if ( !empty( $import_options['ms_tenant_name'] ) ) {
        $tenant_name = sanitize_text_field( $import_options['ms_tenant_name'] );
        $tenant_phone = !empty( $import_options['ms_tenant_phone'] ) ? sanitize_text_field( $import_options['ms_tenant_phone'] ) : '';
        $tenant_email = !empty( $import_options['ms_tenant_email'] ) ? sanitize_email( $import_options['ms_tenant_email'] ) : '';
        $tenant_national_id = !empty( $import_options['ms_tenant_national_id'] ) ? sanitize_text_field( $import_options['ms_tenant_national_id'] ) : '';
        
        // Create or find user
        $user = get_user_by( 'email', $tenant_email );
        if ( !$user && !empty( $tenant_email ) ) {
            $username = sanitize_user( $tenant_name );
            $user_id = wp_create_user( $username, wp_generate_password(), $tenant_email );
            $user = get_user_by( 'id', $user_id );
            
            // Update user meta
            update_user_meta( $user_id, 'first_name', $tenant_name );
            update_user_meta( $user_id, 'billing_phone', $tenant_phone );
            update_user_meta( $user_id, 'national_id', $tenant_national_id );
            
            // Assign tenant role
            $user->set_role( 'subscriber' );
        }
        
        if ( $user ) {
            $building_id = get_post_meta( $post_id, 'ms_building_id', true );
            
            // Link tenant directly to property (property IS the unit)
            $lease_start = !empty( $import_options['ms_lease_start_date'] ) ? sanitize_text_field( $import_options['ms_lease_start_date'] ) : current_time( 'Y-m-d' );
            $lease_end = !empty( $import_options['ms_lease_end_date'] ) ? sanitize_text_field( $import_options['ms_lease_end_date'] ) : '';
            
            $unit_tenants_table = $wpdb->prefix . 'ms_unit_tenants';
            
            // Check if tenant already linked to this property or existing unit
            $existing_tenant = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$unit_tenants_table} WHERE unit_id = %d AND tenant_id = %d AND (end_date IS NULL OR end_date >= CURDATE()) LIMIT 1",
                $post_id,
                $user->ID
            ) );
            
            // Also check if tenant is linked via old ms_units table
            if ( !$existing_tenant && $units_table_exists ) {
                $old_unit_id = $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM {$units_table} WHERE wp_post_id = %d LIMIT 1",
                    $post_id
                ) );
                
                if ( $old_unit_id ) {
                    $existing_tenant = $wpdb->get_row( $wpdb->prepare(
                        "SELECT * FROM {$unit_tenants_table} WHERE unit_id = %d AND tenant_id = %d AND (end_date IS NULL OR end_date >= CURDATE()) LIMIT 1",
                        $old_unit_id,
                        $user->ID
                    ) );
                    
                    // If found via old unit_id, update it to use post_id
                    if ( $existing_tenant ) {
                        $wpdb->update(
                            $unit_tenants_table,
                            array( 'unit_id' => $post_id ),
                            array( 'id' => $existing_tenant->id ),
                            array( '%d' ),
                            array( '%d' )
                        );
                    }
                }
            }
            
            if ( $existing_tenant ) {
                // Update existing record
                $wpdb->update(
                    $unit_tenants_table,
                    array(
                        'start_date' => $lease_start,
                        'end_date' => $lease_end,
                        'building_id' => $building_id ? $building_id : 0,
                        'status' => 'active'
                    ),
                    array( 'id' => $existing_tenant->id ),
                    array( '%s', '%s', '%d', '%s' ),
                    array( '%d' )
                );
            } else {
                // Insert new record - use post_id as unit_id
                $wpdb->insert(
                    $unit_tenants_table,
                    array(
                        'unit_id' => $post_id, // Property post ID acts as unit ID
                        'tenant_id' => $user->ID,
                        'building_id' => $building_id ? $building_id : 0,
                        'start_date' => $lease_start,
                        'end_date' => $lease_end,
                        'status' => 'active',
                        'created_at' => current_time( 'mysql' )
                    ),
                    array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
                );
            }
            
            // Save tenant info to property meta for easy access
            update_post_meta( $post_id, 'ms_current_tenant_id', $user->ID );
            update_post_meta( $post_id, 'ms_current_tenant_name', $tenant_name );
            update_post_meta( $post_id, 'ms_current_tenant_phone', $tenant_phone );
            update_post_meta( $post_id, 'ms_current_tenant_email', $tenant_email );
        }
    }
    
    // Save owner information to property meta
    if ( !empty( $import_options['ms_owner_name'] ) ) {
        update_post_meta( $post_id, 'ms_owner_name', sanitize_text_field( $import_options['ms_owner_name'] ) );
        if ( !empty( $import_options['ms_owner_phone'] ) ) {
            update_post_meta( $post_id, 'ms_owner_phone', sanitize_text_field( $import_options['ms_owner_phone'] ) );
        }
        if ( !empty( $import_options['ms_owner_email'] ) ) {
            update_post_meta( $post_id, 'ms_owner_email', sanitize_email( $import_options['ms_owner_email'] ) );
        }
    }
    
    // Save agent information to property meta
    if ( !empty( $import_options['ms_agent_name'] ) ) {
        update_post_meta( $post_id, 'ms_agent_name', sanitize_text_field( $import_options['ms_agent_name'] ) );
        if ( !empty( $import_options['ms_agent_phone'] ) ) {
            update_post_meta( $post_id, 'ms_agent_phone', sanitize_text_field( $import_options['ms_agent_phone'] ) );
        }
    }
    
    // Save lease information to property meta
    if ( !empty( $import_options['ms_lease_start_date'] ) ) {
        update_post_meta( $post_id, 'ms_lease_start_date', sanitize_text_field( $import_options['ms_lease_start_date'] ) );
    }
    if ( !empty( $import_options['ms_lease_end_date'] ) ) {
        update_post_meta( $post_id, 'ms_lease_end_date', sanitize_text_field( $import_options['ms_lease_end_date'] ) );
    }
    if ( !empty( $import_options['ms_monthly_rent'] ) ) {
        update_post_meta( $post_id, 'ms_monthly_rent', sanitize_text_field( $import_options['ms_monthly_rent'] ) );
    }
    if ( !empty( $import_options['ms_payment_method'] ) ) {
        update_post_meta( $post_id, 'ms_payment_method', sanitize_text_field( $import_options['ms_payment_method'] ) );
    }
}

// Run the addon
$mostaager_addon->run();

// Load text domain
function ms_addon_load_textdomain() {
    load_plugin_textdomain('ms-addon', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('init', 'ms_addon_load_textdomain');
