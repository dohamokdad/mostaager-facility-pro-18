<?php
if (!defined('ABSPATH')) exit;

/**
 * Register Mostaager custom roles and capabilities
 */
function ms_register_facility_roles()
{
    $roles = array(
        'owner' => array(
            'label' => __('مالك', 'mostaager-facility-pro'),
            'capabilities' => array(
                'read' => true,
                'edit_posts' => true,
                'upload_files' => true,
                'edit_others_posts' => true,
                'delete_posts' => true,
                'publish_posts' => true,
                'ms_view_dashboard' => true,
            ),
        ),
        'tenant' => array(
            'label' => __('مستأجر', 'mostaager-facility-pro'),
            'capabilities' => array(
                'read' => true,
                'edit_posts' => false,
                'ms_view_dashboard' => true,
            ),
        ),
        'agent' => array(
            'label' => __('وسيط', 'mostaager-facility-pro'),
            'capabilities' => array(
                'read' => true,
                'edit_posts' => false,
                'ms_view_dashboard' => true,
            ),
        ),
        'building_manager' => array(
            'label' => __('مدير مبنى', 'mostaager-facility-pro'),
            'capabilities' => array(
                'read' => true,
                'edit_posts' => false,
                'ms_view_dashboard' => true,
            ),
        ),
        'mostaager_manager' => array(
            'label' => __('Mostaager Manager', 'mostaager-facility-pro'),
            'capabilities' => array(
                'read' => true,
                'ms_view_dashboard' => true,
            ),
        ),
        'mostaager_supervisor' => array(
            'label' => __('Mostaager Supervisor', 'mostaager-facility-pro'),
            'capabilities' => array(
                'read' => true,
                'ms_view_dashboard' => true,
            ),
        ),
    );

    foreach ($roles as $role_key => $role_data) {
        $existing = get_role($role_key);
        if (!$existing) {
            add_role($role_key, $role_data['label'], $role_data['capabilities']);
        } else {
            // Ensure existing roles get the capabilities declared in $roles
            foreach ((array) $role_data['capabilities'] as $cap => $val) {
                if ($val && ! $existing->has_cap($cap)) {
                    $existing->add_cap($cap);
                }
            }
        }
    }

    $admin = get_role('administrator');
    if ($admin) {
        // Grant administrator full access to all Mostaager capabilities
        $admin->add_cap('ms_view_dashboard');
        $admin->add_cap('ms_manage_buildings');
        $admin->add_cap('ms_manage_units');
        $admin->add_cap('ms_manage_invoices');
        $admin->add_cap('ms_manage_maintenance');
        $admin->add_cap('ms_manage_transfers');
        $admin->add_cap('ms_manage_tenants');
        $admin->add_cap('ms_manage_roles');
        $admin->add_cap('ms_manage_notifications');
        $admin->add_cap('ms_edit_ms_building');
        $admin->add_cap('ms_read_ms_building');
        $admin->add_cap('ms_delete_ms_building');
    }
}
add_action('init', 'ms_register_facility_roles');

/**
 * Remove Mostaager roles and capabilities on cleanup
 */
function ms_remove_facility_roles()
{
    $remove_roles = array(
        'owner',
        'tenant',
        'agent',
        'building_manager',
        'mostaager_manager',
        'mostaager_supervisor',
    );

    foreach ($remove_roles as $role_key) {
        if (get_role($role_key)) {
            remove_role($role_key);
        }
    }
}
