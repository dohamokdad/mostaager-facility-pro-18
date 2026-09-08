<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Houzez Roles Integration
 * Integrates Mostaager roles with Houzez user roles
 */

// Map Mostaager roles to Houzez roles
function ms_houzez_map_role($ms_role) {
    $role_map = [
        'agent' => 'houzez_agent',
        'owner' => 'houzez_owner',
        'building_manager' => 'houzez_building_manager',
    ];
    
    return $role_map[$ms_role] ?? $ms_role;
}

// Check if user has Houzez role equivalent
function ms_user_has_houzez_role($user_id, $role) {
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }
    
    $houzez_role = ms_houzez_map_role($role);
    
    // Check both original role and Houzez equivalent
    if (in_array($role, $user->roles, true)) {
        return true;
    }
    
    if (in_array($houzez_role, $user->roles, true)) {
        return true;
    }
    
    return false;
}

// Override ms_user_has_role to check Houzez roles
if (!function_exists('ms_user_has_role')) {
    function ms_user_has_role($user_id, $role) {
        return ms_user_has_houzez_role($user_id, $role);
    }
}

// Sync Houzez agent to Mostaager agent
add_action('houzez_agent_created', 'ms_sync_houzez_agent', 10, 1);

function ms_sync_houzez_agent($agent_id) {
    $user = get_userdata($agent_id);
    if (!$user) {
        return;
    }
    
    // Add Mostaager agent role if not present
    if (!in_array('agent', $user->roles, true)) {
        $user->add_role('agent');
    }
}

// Sync Houzez owner to Mostaager owner
add_action('houzez_owner_created', 'ms_sync_houzez_owner', 10, 1);

function ms_sync_houzez_owner($owner_id) {
    $user = get_userdata($owner_id);
    if (!$user) {
        return;
    }
    
    // Add Mostaager owner role if not present
    if (!in_array('owner', $user->roles, true)) {
        $user->add_role('owner');
    }
}

// Sync Houzez building manager to Mostaager building manager
add_action('houzez_building_manager_created', 'ms_sync_houzez_building_manager', 10, 1);

function ms_sync_houzez_building_manager($manager_id) {
    $user = get_userdata($manager_id);
    if (!$user) {
        return;
    }
    
    // Add Mostaager building_manager role if not present
    if (!in_array('building_manager', $user->roles, true)) {
        $user->add_role('building_manager');
    }
}

// Get agent properties using Houzez fields
function ms_houzez_get_agent_properties($agent_id) {
    $args = [
        'post_type' => 'property',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'meta_query' => [
            'relation' => 'OR',
            [
                'key' => 'fave_agents',
                'value' => '"' . $agent_id . '"',
                'compare' => 'LIKE',
            ],
            [
                'key' => 'fave_property_agent',
                'value' => $agent_id,
                'compare' => '=',
            ],
        ],
    ];
    
    return get_posts($args);
}

// Get owner properties using Houzez fields
function ms_houzez_get_owner_properties($owner_id) {
    $args = [
        'post_type' => 'property',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'meta_query' => [
            'relation' => 'OR',
            [
                'key' => 'owner_id',
                'value' => $owner_id,
                'compare' => '=',
            ],
            [
                'key' => 'property_owner',
                'value' => $owner_id,
                'compare' => '=',
            ],
            [
                'key' => 'fave_property_owner',
                'value' => $owner_id,
                'compare' => '=',
            ],
        ],
    ];
    
    return get_posts($args);
}

// Get building manager buildings using Houzez post type
function ms_houzez_get_manager_buildings($manager_id) {
    $args = [
        'post_type' => 'houzez_building_manager',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'author' => $manager_id,
    ];
    
    return get_posts($args);
}

// Update agent dashboard to use Houzez properties
add_filter('ms_agent_dashboard_properties', 'ms_houzez_filter_agent_properties', 10, 2);

function ms_houzez_filter_agent_properties($properties, $agent_id) {
    // If properties empty, try to get from Houzez
    if (empty($properties)) {
        $properties = ms_houzez_get_agent_properties($agent_id);
    }
    
    return $properties;
}

// Update owner dashboard to use Houzez properties
add_filter('ms_owner_dashboard_properties', 'ms_houzez_filter_owner_properties', 10, 2);

function ms_houzez_filter_owner_properties($properties, $owner_id) {
    // If properties empty, try to get from Houzez
    if (empty($properties)) {
        $properties = ms_houzez_get_owner_properties($owner_id);
    }
    
    return $properties;
}

// Update building manager dashboard to use Houzez buildings
add_filter('ms_building_manager_buildings', 'ms_houzez_filter_manager_buildings', 10, 2);

function ms_houzez_filter_manager_buildings($buildings, $manager_id) {
    // If buildings empty, try to get from Houzez
    if (empty($buildings)) {
        $houzez_buildings = ms_houzez_get_manager_buildings($manager_id);
        
        // Convert to building objects
        $buildings = [];
        foreach ($houzez_buildings as $post) {
            $buildings[] = (object) [
                'id' => $post->ID,
                'title' => $post->post_title,
                'manager_id' => $manager_id,
            ];
        }
    }
    
    return $buildings;
}
