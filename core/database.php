<?php
if (!defined('ABSPATH')) {
    exit;
}

function ms_create_tables()
{
    if (function_exists('ms_plugin_install')) {
        ms_plugin_install();
    }
}

/** Minimal DB helper functions used by dashboards **/
function ms_get_buildings_by_manager($user_id)
{
    global $wpdb;

    $table = $wpdb->prefix . 'ms_buildings';
    if ($user_id && user_can($user_id, 'manage_options')) {
        $sql = "SELECT * FROM $table";
    } else {
        $sql = $wpdb->prepare("SELECT * FROM $table WHERE manager_id = %d", $user_id);
    }

    $rows = $wpdb->get_results($sql);
    if (!empty($rows)) {
        return $rows;
    }

    if (function_exists('ms_get_legacy_buildings_for_manager')) {
        $legacy = ms_get_legacy_buildings_for_manager($user_id);
        if (!empty($legacy)) {
            return $legacy;
        }
    }

    if (function_exists('ms_get_buildings_by_manager_cpt')) {
        return ms_get_buildings_by_manager_cpt($user_id);
    }

    return array();
}

/**
 * Resolve a Mostaager building id to a linked WordPress post id if available.
 * Returns 0 if no linked post is found.
 */
function ms_get_linked_wp_post_id_for_building($ms_building_id)
{
    global $wpdb;
    $ms_building_id = absint($ms_building_id);
    if (!$ms_building_id) {
        return 0;
    }

    $table = $wpdb->prefix . 'ms_buildings';
    $wp_post_id = $wpdb->get_var($wpdb->prepare("SELECT wp_post_id FROM {$table} WHERE id = %d LIMIT 1", $ms_building_id));
    return $wp_post_id ? intval($wp_post_id) : 0;
}

function ms_get_building_id_values_for_query($building_id)
{
    global $wpdb;
    $building_id = absint($building_id);
    if (!$building_id) {
        return array();
    }

    $table = $wpdb->prefix . 'ms_buildings';
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT id, wp_post_id FROM {$table} WHERE id = %d OR wp_post_id = %d LIMIT 1",
        $building_id,
        $building_id
    ));

    $values = array();
    if ($row) {
        if (!empty($row->id)) {
            $values[] = intval($row->id);
        }
        if (!empty($row->wp_post_id) && intval($row->wp_post_id) !== intval($row->id)) {
            $values[] = intval($row->wp_post_id);
        }
    }

    if (empty($values)) {
        $values[] = $building_id;
    }

    return array_values(array_unique($values));
}

function ms_get_tenant_by_unit($unit_id)
{
    global $wpdb;
    $unit_id = absint($unit_id);
    if (!$unit_id) {
        return null;
    }

    $table = $wpdb->prefix . 'ms_unit_tenants';
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE unit_id = %d AND (end_date IS NULL OR end_date = '' OR end_date >= CURDATE()) ORDER BY start_date DESC LIMIT 1",
        $unit_id
    ));

    if ($row) {
        return $row;
    }

    $unit_tbl = $wpdb->prefix . 'ms_units';
    $tenant_id = $wpdb->get_var($wpdb->prepare(
        "SELECT tenant_id FROM $unit_tbl WHERE id = %d LIMIT 1",
        $unit_id
    ));

    if ($tenant_id) {
        return (object) array(
            'tenant_id' => absint($tenant_id),
            'unit_id' => $unit_id,
            'source' => 'fallback_ms_units',
        );
    }

    return null;
}

function ms_get_units_by_building($building_id)
{
    $building_id = absint($building_id);
    if (!$building_id) {
        return [];
    }

    $properties = ms_get_properties_by_building($building_id);
    if (!empty($properties)) {
        return $properties;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'ms_units';
    return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE building_id = %d", $building_id));
}

function ms_get_tenant_unit($tenant_id)
{
    global $wpdb;
    $tenant_id = absint($tenant_id);
    if (!$tenant_id) {
        return null;
    }

    $table = $wpdb->prefix . 'ms_unit_tenants';
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE tenant_id = %d AND (end_date IS NULL OR end_date = '' OR end_date >= CURDATE()) ORDER BY start_date DESC LIMIT 1",
        $tenant_id
    ));
}

function ms_link_tenant_to_unit($tenant_id, $unit_id, $building_id, $start_date)
{
    global $wpdb;
    $tenant_id = absint($tenant_id);
    $unit_id = absint($unit_id);
    $building_id = absint($building_id);
    $start_date = sanitize_text_field($start_date);

    if (!$tenant_id || !$unit_id || !$building_id || empty($start_date)) {
        return false;
    }

    $table = $wpdb->prefix . 'ms_unit_tenants';
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE unit_id = %d AND (end_date IS NULL OR end_date = '' OR end_date >= CURDATE()) LIMIT 1",
        $unit_id
    ));

    if ($existing) {
        $wpdb->update($table, array('end_date' => current_time('Y-m-d'), 'status' => 'ended'), array('id' => intval($existing->id)), array('%s','%s'), array('%d'));
    }

    $inserted = $wpdb->insert($table, array(
        'unit_id' => $unit_id,
        'tenant_id' => $tenant_id,
        'building_id' => $building_id,
        'start_date' => $start_date,
        'end_date' => null,
        'status' => 'active',
        'created_at' => current_time('mysql'),
    ), array('%d','%d','%d','%s','%s','%s','%s'));

    if ($inserted === false) {
        return false;
    }

    return intval($wpdb->insert_id);
}

function ms_get_tenants_by_building($building_id)
{
    global $wpdb;
    $building_id = absint($building_id);
    if (!$building_id) {
        return [];
    }

    $table = $wpdb->prefix . 'ms_unit_tenants';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE building_id = %d AND (end_date IS NULL OR end_date = '' OR end_date >= CURDATE()) ORDER BY start_date DESC",
        $building_id
    ));
}

function ms_get_maintenance_by_property_ids(array $property_ids, $limit = 20)
{
    global $wpdb;
    $property_ids = array_filter(array_map('absint', $property_ids));
    if (empty($property_ids)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($property_ids), '%d'));
    $table = $wpdb->prefix . 'ms_maintenance_requests';
    $sql = "SELECT * FROM $table WHERE unit_id IN ({$placeholders}) ORDER BY created_at DESC LIMIT %d";
    $params = array_merge($property_ids, array(absint($limit)));

    return $wpdb->get_results(call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sql), $params)));
}

function ms_get_building_id_for_agent($agent_id)
{
    global $wpdb;
    $agent_id = absint($agent_id);
    if (!$agent_id) {
        return 0;
    }

    $unit_tbl = $wpdb->prefix . 'ms_units';
    $building_id = $wpdb->get_var($wpdb->prepare("SELECT building_id FROM $unit_tbl WHERE agent_id = %d LIMIT 1", $agent_id));
    if ($building_id) {
        return intval($building_id);
    }

    if (function_exists('ms_get_properties_by_agent')) {
        $properties = ms_get_properties_by_agent($agent_id);
        foreach ((array) $properties as $property) {
            $prop_id = isset($property->ID) ? intval($property->ID) : (isset($property->id) ? intval($property->id) : 0);
            if ($prop_id) {
                $b = absint(get_post_meta($prop_id, 'building_id', true));
                if ($b) {
                    return $b;
                }
            }
        }
    }

    return 0;
}

function ms_get_properties_by_owner($user_id, $limit = 0)
{
    global $wpdb;

    $merged = array();
    $seen_ids = array();

    // 1) Query Houzez property posts by author first
    if (post_type_exists('property')) {
        $args = array(
            'post_type' => 'property',
            'post_status' => array('publish', 'pending', 'draft', 'expired', 'houzez_sold', 'disapproved', 'on_hold', 'private', 'future'),
            'posts_per_page' => $limit > 0 ? absint($limit) : -1,
            'author' => $user_id,
            'fields' => 'all',
        );
        $author_posts = get_posts($args);

        foreach ($author_posts as $p) {
            if (!in_array($p->ID, $seen_ids, true)) {
                $seen_ids[] = $p->ID;
                // Normalize to consistent structure
                $merged[] = (object) array(
                    'id' => $p->ID,
                    'type' => 'houzez',
                    'name' => $p->post_title,
                    'status' => $p->post_status,
                    'post' => $p,
                );
            }
        }

        // 2) Search Houzez property posts by owner meta keys
        $owner_meta_keys = array('owner_id', 'property_owner', 'fave_property_owner', 'ms_property_owner_id');
        $meta_query = array('relation' => 'OR');
        foreach ($owner_meta_keys as $key) {
            $meta_query[] = array(
                'key' => $key,
                'value' => $user_id,
                'compare' => '=',
                'type' => 'NUMERIC',
            );
            // also support serialized / ACF-stored post object values
            $meta_query[] = array(
                'key' => $key,
                'value' => '"' . $user_id . '"',
                'compare' => 'LIKE',
            );
        }

        $args_meta = array(
            'post_type' => 'property',
            'post_status' => $args['post_status'],
            'posts_per_page' => $limit > 0 ? absint($limit) : -1,
            'meta_query' => $meta_query,
            'fields' => 'all',
        );
        $meta_posts = get_posts($args_meta);

        foreach ($meta_posts as $p) {
            if (!in_array($p->ID, $seen_ids, true)) {
                $seen_ids[] = $p->ID;
                // Normalize to consistent structure
                $merged[] = (object) array(
                    'id' => $p->ID,
                    'type' => 'houzez',
                    'name' => $p->post_title,
                    'status' => $p->post_status,
                    'post' => $p,
                );
            }
        }
    }

    // 3) Incorporate ms_units rows
    $table = $wpdb->prefix . 'ms_units';
    $limit_sql = $limit > 0 ? ' LIMIT ' . absint($limit) : '';
    $sql = $wpdb->prepare("SELECT * FROM $table WHERE owner_id = %d{$limit_sql}", $user_id);
    $unit_rows = $wpdb->get_results($sql);

    foreach ($unit_rows as $unit) {
        $unit_id = isset($unit->id) ? $unit->id : (isset($unit->unit_id) ? $unit->unit_id : null);
        if ($unit_id && !in_array($unit_id, $seen_ids, true)) {
            $seen_ids[] = $unit_id;
            // Normalize to consistent structure
            $merged[] = (object) array(
                'id' => $unit_id,
                'type' => 'unit',
                'name' => isset($unit->unit_name) ? $unit->unit_name : (isset($unit->name) ? $unit->name : 'Unit #' . $unit_id),
                'status' => isset($unit->status) ? $unit->status : 'active',
                'unit' => $unit,
            );
        }
    }

    return $limit > 0 ? array_slice($merged, 0, absint($limit)) : $merged;
}


function ms_get_properties_by_agent($user_id, $limit = 0)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ms_units';
    // 1) Preferred source: ms_units table
    $limit_sql = $limit > 0 ? ' LIMIT ' . absint($limit) : '';
    $sql = $wpdb->prepare("SELECT * FROM $table WHERE agent_id = %d{$limit_sql}", $user_id);
    $rows = $wpdb->get_results($sql);
    if (!empty($rows)) {
        return $rows;
    }

    if (!post_type_exists('property')) {
        return array();
    }

    // 2) Search Houzez property posts by agent meta keys
    $agent_meta_keys = array('fave_agents', 'fave_property_agent', 'fave_property_agency');
    $meta_query = array('relation' => 'OR');
    foreach ($agent_meta_keys as $key) {
        $meta_query[] = array(
            'key' => $key,
            'value' => $user_id,
            'compare' => '=',
            'type' => 'NUMERIC',
        );
        $meta_query[] = array(
            'key' => $key,
            'value' => '"' . $user_id . '"',
            'compare' => 'LIKE',
        );
    }

    $args_meta = array(
        'post_type' => 'property',
        'post_status' => array('publish', 'pending', 'draft', 'expired', 'houzez_sold', 'disapproved', 'on_hold', 'private', 'future'),
        'posts_per_page' => $limit > 0 ? absint($limit) : -1,
        'meta_query' => $meta_query,
        'fields' => 'all',
    );
    $meta_posts = get_posts($args_meta);
    if (!empty($meta_posts)) {
        return $meta_posts;
    }

    // 3) Fallback: some agent-linked properties may use owner-style fields in Houzez post meta.
    $owner_meta_keys = array('owner_id', 'property_owner', 'fave_property_owner', 'ms_property_owner_id');
    $owner_meta_query = array('relation' => 'OR');
    foreach ($owner_meta_keys as $key) {
        $owner_meta_query[] = array(
            'key' => $key,
            'value' => $user_id,
            'compare' => '=',
            'type' => 'NUMERIC',
        );
        $owner_meta_query[] = array(
            'key' => $key,
            'value' => '"' . $user_id . '"',
            'compare' => 'LIKE',
        );
    }

    $args_owner_meta = array(
        'post_type' => 'property',
        'post_status' => array('publish', 'pending', 'draft', 'expired', 'houzez_sold', 'disapproved', 'on_hold', 'private', 'future'),
        'posts_per_page' => $limit > 0 ? absint($limit) : -1,
        'meta_query' => $owner_meta_query,
        'fields' => 'all',
    );
    $owner_posts = get_posts($args_owner_meta);
    if (!empty($owner_posts)) {
        return $owner_posts;
    }

    // 4) Final fallback: if the agent is author of the property post.
    return get_posts(array(
        'post_type' => 'property',
        'post_status' => array('publish', 'pending', 'draft', 'expired', 'houzez_sold', 'disapproved', 'on_hold', 'private', 'future'),
        'posts_per_page' => $limit > 0 ? absint($limit) : -1,
        'author' => $user_id,
        'fields' => 'all',
    ));
}

function ms_get_user_invoices($user_id)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ms_invoices';
    $sql = $wpdb->prepare("SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC", $user_id);
    $invoices = $wpdb->get_results($sql);

    $legacy_invoices = array();
    if (function_exists('ms_get_legacy_invoices_for_user')) {
        $legacy_invoices = ms_get_legacy_invoices_for_user($user_id);
    }

    $all = array_merge($invoices ?: array(), $legacy_invoices ?: array());
    $unique = array();
    $seen_keys = array();
    foreach ($all as $invoice) {
        $invoice_key = ms_get_invoice_business_key($invoice);
        if (!$invoice_key) {
            $invoice_key = 'legacy_id:' . intval($invoice->id ?? $invoice->ID ?? 0);
        }
        if ($invoice_key && isset($seen_keys[$invoice_key])) {
            continue;
        }
        if ($invoice_key) {
            $seen_keys[$invoice_key] = true;
        }
        $unique[] = $invoice;
    }
    $all = $unique;

    usort($all, function ($a, $b) {
        $a_date = strtotime($a->created_at ?? $a->due_date ?? '');
        $b_date = strtotime($b->created_at ?? $b->due_date ?? '');
        return $b_date <=> $a_date;
    });

    return $all;
}

function ms_sync_owner_property_units($owner_id)
{
    global $wpdb;
    $owner_id = absint($owner_id);
    if (!$owner_id) return;
    static $done = array();
    if (isset($done[$owner_id])) return;
    $done[$owner_id] = true;
    $unit_table = $wpdb->prefix . 'ms_units';
    $property_ids = get_posts(array(
        'post_type' => 'property',
        'post_status' => array('publish','pending','draft','private','future'),
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => array('relation' => 'OR',
            array('key' => 'owner_id', 'value' => $owner_id, 'compare' => '='),
            array('key' => 'property_owner', 'value' => $owner_id, 'compare' => '='),
            array('key' => 'fave_property_owner', 'value' => $owner_id, 'compare' => '='),
            array('key' => 'ms_property_owner_id', 'value' => $owner_id, 'compare' => '='),
        ),
    ));
    // Legacy owner listings may be linked by post_author rather than owner meta.
    $author_property_ids = get_posts(array(
        'post_type' => 'property',
        'post_status' => array('publish','pending','draft','private','future'),
        'posts_per_page' => -1,
        'fields' => 'ids',
        'author' => $owner_id,
    ));
    $property_ids = array_values(array_unique(array_merge((array) $property_ids, (array) $author_property_ids)));
    foreach ((array) $property_ids as $property_id) {
        $building_id = absint(get_post_meta($property_id, 'building_id', true));
        if (!$building_id) $building_id = absint(get_post_meta($property_id, 'ms_building_id', true));
        if (!$building_id) continue;
        $unit_number = sanitize_text_field((string) get_post_meta($property_id, 'unit_number', true));
        $unit_id = absint(get_post_meta($property_id, 'ms_unit_id', true));
        if (!$unit_id) {
            $unit_id = absint($wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$unit_table} WHERE building_id = %d AND owner_id = %d AND unit_number = %s LIMIT 1",
                $building_id, $owner_id, $unit_number
            )));
        }
        $tenant_id = absint(get_post_meta($property_id, 'tenant_id', true));
        $agent_id = absint(get_post_meta($property_id, 'agent_id', true));
        $listing_status = sanitize_key((string) get_post_meta($property_id, 'ms_listing_status', true));
        $unit_status = in_array($listing_status, array('rented','sold'), true) ? $listing_status : 'available';
        $data = array('building_id'=>$building_id, 'owner_id'=>$owner_id, 'tenant_id'=>$tenant_id, 'agent_id'=>$agent_id, 'unit_number'=>$unit_number, 'status'=>$unit_status, 'updated_at'=>current_time('mysql'));
        if ($unit_id) {
            $wpdb->update($unit_table, $data, array('id'=>$unit_id));
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($unit_table, $data);
            $unit_id = absint($wpdb->insert_id);
        }
        if ($unit_id) {
            update_post_meta($property_id, 'ms_unit_id', $unit_id);
            update_post_meta($property_id, 'unit_id', $unit_id);
        }
    }
}

function ms_get_owner_invoice_scope($owner_id)
{
    global $wpdb;
    $owner_id = absint($owner_id);
    if (!$owner_id) {
        return null;
    }

    ms_sync_owner_property_units($owner_id);

    $unit_tbl = $wpdb->prefix . 'ms_units';
    $unit_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM $unit_tbl WHERE owner_id = %d", $owner_id));
    $building_ids = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT building_id FROM $unit_tbl WHERE owner_id = %d AND building_id > 0", $owner_id));

    // Include invoices explicitly assigned to the owner account. Older records
    // may only contain user_id and have no unit/building relation.
    $clauses = array('user_id = %d');
    $params = array($owner_id);

    if (!empty($unit_ids)) {
        $placeholders = implode(',', array_fill(0, count($unit_ids), '%d'));
        $clauses[] = "unit_id IN ($placeholders)";
        $params = array_merge($params, $unit_ids);
    }

    if (!empty($building_ids)) {
        $expanded_building_ids = array();
        foreach ($building_ids as $id) {
            if (function_exists('ms_get_building_id_values_for_query')) {
                $expanded_building_ids = array_merge($expanded_building_ids, ms_get_building_id_values_for_query($id));
            } else {
                $expanded_building_ids[] = $id;
            }
        }
        $expanded_building_ids = array_values(array_unique(array_filter($expanded_building_ids, 'absint')));
        if (!empty($expanded_building_ids)) {
            $placeholders = implode(',', array_fill(0, count($expanded_building_ids), '%d'));
            $clauses[] = "building_id IN ($placeholders)";
            $params = array_merge($params, $expanded_building_ids);
        }
    }

    if (empty($clauses)) {
        return null;
    }

    return array(
        'where' => '(' . implode(' OR ', $clauses) . ')',
        'params' => $params,
    );
}

function ms_invoice_belongs_to_owner($invoice_id, $owner_id)
{
    $scope = ms_get_owner_invoice_scope($owner_id);
    if (empty($scope) || !$invoice_id) {
        return false;
    }

    global $wpdb;
    $invoice_tbl = $wpdb->prefix . 'ms_invoices';
    $query = "SELECT COUNT(id) FROM $invoice_tbl WHERE id = %d AND {$scope['where']}";
    $params = array_merge(array($invoice_id), $scope['params']);

    return intval($wpdb->get_var($wpdb->prepare($query, ...$params))) > 0;
}

function ms_get_owner_invoices($owner_id, $limit = 0)
{
    global $wpdb;
    $invoice_tbl = $wpdb->prefix . 'ms_invoices';
    $scope = ms_get_owner_invoice_scope($owner_id);
    $invoices = array();

    if (!empty($scope)) {
        $limit_sql = $limit > 0 ? ' LIMIT ' . absint($limit) : '';
        $query = "SELECT * FROM $invoice_tbl WHERE {$scope['where']} ORDER BY created_at DESC{$limit_sql}";
        $invoices = $wpdb->get_results($wpdb->prepare($query, ...$scope['params']));

        // Owner visibility is unit-scoped: show own invoices and invoices
        // belonging to the owner's units, never every invoice in the building.
        $owner_unit_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT u.id, u.unit_number, u.status, u.tenant_id, b.title AS building_title,
                    tenant.display_name AS tenant_name
             FROM {$wpdb->prefix}ms_units u
             LEFT JOIN {$wpdb->prefix}ms_buildings b ON b.id = u.building_id
             LEFT JOIN {$wpdb->users} tenant ON tenant.ID = u.tenant_id
             WHERE u.owner_id = %d",
            $owner_id
        ));
        $owner_units = array();
        foreach ((array) $owner_unit_rows as $unit_row) {
            $owner_units[absint($unit_row->id)] = $unit_row;
        }
        $scoped_invoices = array();
        foreach ((array) $invoices as $invoice) {
            $invoice_user_id = absint($invoice->user_id ?? 0);
            $invoice_unit_id = absint($invoice->unit_id ?? 0);
            if ($invoice_user_id !== $owner_id && (!$invoice_unit_id || !isset($owner_units[$invoice_unit_id]))) {
                continue;
            }
            if (isset($owner_units[$invoice_unit_id])) {
                $unit_row = $owner_units[$invoice_unit_id];
                $is_rent = in_array(sanitize_key($invoice->invoice_type ?? ''), array('rent','property-rent','property-monthly'), true)
                    || sanitize_key($invoice->invoice_category ?? '') === 'property-rent';
                $is_building = in_array(sanitize_key($invoice->invoice_category ?? ''), array('building-maintenance','building-facilities','building-utilities'), true)
                    || sanitize_key($invoice->invoice_type ?? '') === 'maintenance';
                $tenant_name = trim((string) ($unit_row->tenant_name ?? ''));
                $unit_label = $unit_row->unit_number ? 'الشقة ' . $unit_row->unit_number : 'الوحدة #' . $unit_row->id;
                $building_label = $unit_row->building_title ? ' — ' . $unit_row->building_title : '';
                $payer_label = ($unit_row->status === 'rented' && absint($unit_row->tenant_id) && $tenant_name) ? 'المستأجر: ' . $tenant_name : 'المالك: ' . wp_get_current_user()->display_name;
                $invoice->unit_label = $unit_label . $building_label;
                $invoice->tenant_name = $tenant_name;
                $invoice->payer_label = $payer_label;
                if ($is_rent) {
                    $invoice->display_group = 'rent';
                    $invoice->description = 'إيجار ' . $unit_label . $building_label . ' — ' . $payer_label;
                } elseif ($is_building) {
                    $invoice->display_group = 'building';
                    $invoice->description = 'رسوم مرافق البناء ' . $unit_label . $building_label . ' — ' . $payer_label;
                }
            }
            $scoped_invoices[] = $invoice;
        }
        $invoices = $scoped_invoices;
    }

    if (!empty($invoices)) {
        foreach ($invoices as $invoice) {
            if (is_object($invoice)) {
                $invoice->source = 'new';
            }
        }
    }

    $legacy_invoices = array();
    if (function_exists('ms_get_legacy_invoices_for_user')) {
        $legacy_invoices = ms_get_legacy_invoices_for_user($owner_id);
    }

    $all = array_merge($invoices ?: array(), $legacy_invoices ?: array());
    $unique = array();
    $seen_keys = array();
    foreach ($all as $invoice) {
        $invoice_key = ms_get_invoice_business_key($invoice);
        if (!$invoice_key) {
            $invoice_key = 'legacy_id:' . intval($invoice->id ?? $invoice->ID ?? 0);
        }
        if ($invoice_key && isset($seen_keys[$invoice_key])) {
            continue;
        }
        if ($invoice_key) {
            $seen_keys[$invoice_key] = true;
        }
        $unique[] = $invoice;
    }
    $all = $unique;
    usort($all, function ($a, $b) {
        $a_date = strtotime($a->created_at ?? $a->due_date ?? '');
        $b_date = strtotime($b->created_at ?? $b->due_date ?? '');
        return $b_date <=> $a_date;
    });

    if ($limit > 0) {
        $all = array_slice($all, 0, absint($limit));
    }

    return $all;
}

function ms_get_owner_invoices_count_by_status($owner_id, $status)
{
    $invoices = ms_get_owner_invoices($owner_id);
    $count = 0;

    foreach ($invoices as $invoice) {
        if (isset($invoice->status) && $invoice->status === $status) {
            $count++;
        }
    }

    return $count;
}

function ms_get_owner_overdue_count($owner_id)
{
    $invoices = ms_get_owner_invoices($owner_id);
    $count = 0;
    $today = strtotime(current_time('mysql'));

    foreach ($invoices as $invoice) {
        $status = isset($invoice->status) ? $invoice->status : '';
        $due_date = !empty($invoice->due_date) ? strtotime($invoice->due_date) : 0;

        if ($status === 'overdue' || ($status !== 'paid' && $due_date > 0 && $due_date < $today)) {
            $count++;
        }
    }

    return $count;
}

function ms_get_wallet_balance($user_id)
{
    global $wpdb;

    // 1) Preferred source: ms_user_wallet (single source of truth)
    $wallet_tbl = $wpdb->prefix . 'ms_user_wallet';
    $balance = $wpdb->get_var(
        $wpdb->prepare("SELECT balance FROM $wallet_tbl WHERE user_id=%d LIMIT 1", $user_id)
    );

    if ($balance !== null) {
        return floatval($balance);
    }

    // 2) Fallback: compute from transactions table with multiple type names
    $txn_tbl = $wpdb->prefix . 'ms_wallet_transactions';

    // Credits (topups / recharges / credit)
    $credit_types = array('credit', 'topup', 'recharge', 'deposit');
    $debit_types  = array('debit', 'deduct', 'withdraw', 'payment');

    $credit_placeholders = implode(',', array_fill(0, count($credit_types), '%s'));
    $debit_placeholders  = implode(',', array_fill(0, count($debit_types), '%s'));

    $sql_credit = "SELECT COALESCE(SUM(amount),0) FROM {$txn_tbl} WHERE user_id=%d AND type IN ($credit_placeholders)";
    $sql_debit  = "SELECT COALESCE(SUM(amount),0) FROM {$txn_tbl} WHERE user_id=%d AND type IN ($debit_placeholders)";

    $credit_params = array_merge(array($user_id), $credit_types);
    $debit_params  = array_merge(array($user_id), $debit_types);

    $credit = $wpdb->get_var($wpdb->prepare($sql_credit, $credit_params));
    $debit  = $wpdb->get_var($wpdb->prepare($sql_debit, $debit_params));

    return floatval($credit) - floatval($debit);
}

function ms_get_wallet_transactions_for_user($user_id, $limit = 20)
{
    global $wpdb;
    $user_id = absint($user_id);
    $limit = $limit > 0 ? intval($limit) : 20;
    if (!$user_id) {
        return array();
    }

    $table = $wpdb->prefix . 'ms_wallet_transactions';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
        $user_id,
        $limit
    ));
}

function ms_get_wallet_transactions($user_id, $limit = 20)
{
    return ms_get_wallet_transactions_for_user($user_id, $limit);
}

function ms_get_units_count_by_manager($manager_id)
{
    global $wpdb;
    $build_tbl = $wpdb->prefix . 'ms_buildings';
    $unit_tbl = $wpdb->prefix . 'ms_units';

    if ($manager_id && user_can($manager_id, 'manage_options')) {
        $sql = "SELECT COUNT(id) FROM $unit_tbl";
        $total = intval($wpdb->get_var($sql));
    } else {
        $sql = $wpdb->prepare("SELECT COUNT(u.id) FROM $unit_tbl u JOIN $build_tbl b ON u.building_id = b.id WHERE b.manager_id = %d", $manager_id);
        $total = intval($wpdb->get_var($sql));
    }

    if ($total > 0) {
        return $total;
    }

    if (function_exists('ms_get_legacy_manager_property_count')) {
        return ms_get_legacy_manager_property_count($manager_id);
    }

    return 0;
}

function ms_get_active_maintenance_by_manager($manager_id)
{
    global $wpdb;
    $build_tbl = $wpdb->prefix . 'ms_buildings';
    $maint_tbl = $wpdb->prefix . 'ms_maintenance_requests';

    if ($manager_id && user_can($manager_id, 'manage_options')) {
        $sql = $wpdb->prepare("SELECT COUNT(id) FROM $maint_tbl WHERE status != %s", 'closed');
        $count = intval($wpdb->get_var($sql));
    } else {
        $sql = $wpdb->prepare("SELECT COUNT(m.id) FROM $maint_tbl m JOIN $build_tbl b ON (m.building_id = b.id OR (b.wp_post_id > 0 AND m.building_id = b.wp_post_id)) WHERE b.manager_id = %d AND m.status != %s", $manager_id, 'closed');
        $count = intval($wpdb->get_var($sql));
    }

    if ($count > 0) {
        return $count;
    }

    if (function_exists('ms_get_legacy_building_ids_for_manager') && function_exists('ms_get_legacy_expenses_count_by_building_ids')) {
        $building_ids = ms_get_legacy_building_ids_for_manager($manager_id);
        if (!empty($building_ids)) {
            return ms_get_legacy_expenses_count_by_building_ids($building_ids);
        }
    }

    return 0;
}

function ms_get_invoices_count_by_user_and_status($user_id, $status)
{
    $invoices = ms_get_user_invoices($user_id);
    $count = 0;

    foreach ($invoices as $invoice) {
        if (isset($invoice->status) && $invoice->status === $status) {
            $count++;
        }
    }

    return $count;
}

function ms_get_invoices_count_by_user_status_and_property($user_id, $status, $property_id)
{
    $invoices = ms_get_user_invoices($user_id);
    $count = 0;
    $property_id = absint($property_id);

    foreach ($invoices as $invoice) {
        if (!isset($invoice->status) || $invoice->status !== $status) {
            continue;
        }

        if (!isset($invoice->property_id) || intval($invoice->property_id) !== $property_id) {
            continue;
        }

        $count++;
    }

    return $count;
}

function ms_get_paid_invoices_count_for_manager($manager_id)
{
    if (function_exists('ms_get_manager_invoices')) {
        $invoices = ms_get_manager_invoices($manager_id, -1, 'maintenance');
        $paid_count = 0;
        foreach ($invoices as $invoice) {
            if (isset($invoice->status) && $invoice->status === 'paid') {
                $paid_count++;
            }
        }
        return $paid_count;
    }

    global $wpdb;
    $build_tbl = $wpdb->prefix . 'ms_buildings';
    $unit_tbl = $wpdb->prefix . 'ms_units';
    $inv_tbl = $wpdb->prefix . 'ms_invoices';

    if ($manager_id && user_can($manager_id, 'manage_options')) {
        $sql = $wpdb->prepare("SELECT COUNT(id) FROM $inv_tbl WHERE status = %s AND invoice_type = %s", 'paid', 'maintenance');
        $paid_count = intval($wpdb->get_var($sql));
    } else {
        // get owners for manager's buildings
        $owners = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT u.owner_id FROM $unit_tbl u JOIN $build_tbl b ON u.building_id = b.id WHERE b.manager_id = %d AND u.owner_id > 0", $manager_id));
        $paid_count = 0;

        if (!empty($owners)) {
            $placeholders = implode(',', array_fill(0, count($owners), '%d'));
            $query = "SELECT COUNT(id) FROM $inv_tbl WHERE status = 'paid' AND invoice_type = 'maintenance' AND user_id IN ($placeholders)";
            $prepared = $wpdb->prepare($query, $owners);
            $paid_count = intval($wpdb->get_var($prepared));
        }
    }

    if (function_exists('ms_get_legacy_manager_paid_invoice_stats')) {
        $legacy = ms_get_legacy_manager_paid_invoice_stats($manager_id);
        $paid_count += intval($legacy['paid_count']);
    }

    return $paid_count;
}

function ms_get_collection_stats_by_manager($manager_id)
{
    global $wpdb;
    $build_tbl = $wpdb->prefix . 'ms_buildings';
    $unit_tbl = $wpdb->prefix . 'ms_units';
    $inv_tbl = $wpdb->prefix . 'ms_invoices';

    if ($manager_id && user_can($manager_id, 'manage_options')) {
        $sql_total = $wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM $inv_tbl WHERE invoice_type = %s", 'maintenance');
        $sql_collected = $wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM $inv_tbl WHERE status = %s AND invoice_type = %s", 'paid', 'maintenance');
        $total_due = floatval($wpdb->get_var($sql_total));
        $total_collected = floatval($wpdb->get_var($sql_collected));
    } else {
        $owners = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT u.owner_id FROM $unit_tbl u JOIN $build_tbl b ON u.building_id = b.id WHERE b.manager_id = %d AND u.owner_id > 0", $manager_id));
        $total_due = 0;
        $total_collected = 0;

        if (!empty($owners)) {
            $placeholders = implode(',', array_fill(0, count($owners), '%d'));
            $query_total = "SELECT COALESCE(SUM(amount),0) FROM $inv_tbl WHERE invoice_type = 'maintenance' AND user_id IN ($placeholders)";
            $prepared_total = $wpdb->prepare($query_total, $owners);
            $total_due = floatval($wpdb->get_var($prepared_total));

            $query_collected = "SELECT COALESCE(SUM(amount),0) FROM $inv_tbl WHERE status = 'paid' AND invoice_type = 'maintenance' AND user_id IN ($placeholders)";
            $prepared_collected = $wpdb->prepare($query_collected, $owners);
            $total_collected = floatval($wpdb->get_var($prepared_collected));
        }
    }

    if (function_exists('ms_get_legacy_invoice_totals_for_manager')) {
        $legacy = ms_get_legacy_invoice_totals_for_manager($manager_id);
        $total_due += floatval($legacy['total_due']);
        $total_collected += floatval($legacy['total_collected']);
    }

    $percent = $total_due > 0 ? round(($total_collected / $total_due) * 100, 2) : 0;

    return array('total_due' => $total_due, 'total_collected' => $total_collected, 'percent' => $percent);
}

function ms_get_paid_unpaid_units_by_manager($manager_id)
{
    global $wpdb;
    $build_tbl = $wpdb->prefix . 'ms_buildings';
    $unit_tbl = $wpdb->prefix . 'ms_units';
    $inv_tbl = $wpdb->prefix . 'ms_invoices';

    // total units
    $total = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(u.id) FROM $unit_tbl u JOIN $build_tbl b ON u.building_id=b.id WHERE b.manager_id=%d", $manager_id)));

    if ($total === 0 && function_exists('ms_get_legacy_manager_property_count')) {
        $total = ms_get_legacy_manager_property_count($manager_id);
    }

    // paid units heuristic: owner has no pending/overdue invoices
    $owners = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT u.owner_id FROM $unit_tbl u JOIN $build_tbl b ON u.building_id = b.id WHERE b.manager_id = %d AND u.owner_id > 0", $manager_id));
    $paid_units = 0;

    if (empty($owners) && function_exists('ms_get_legacy_manager_owner_ids')) {
        $owners = ms_get_legacy_manager_owner_ids($manager_id);
    }

    if (!empty($owners)) {
        foreach ($owners as $owner_id) {
            $cnt = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $inv_tbl WHERE user_id=%d AND (status='pending' OR status='overdue')", $owner_id)));
            if (function_exists('ms_get_legacy_invoices_count_for_user')) {
                $cnt += ms_get_legacy_invoices_count_for_user($owner_id, 'pending');
                $cnt += ms_get_legacy_invoices_count_for_user($owner_id, 'overdue');
            }
            if ($cnt == 0) {
                if (function_exists('ms_get_legacy_manager_property_ids')) {
                    $legacy_property_ids = ms_get_legacy_manager_property_ids($manager_id);
                    foreach ($legacy_property_ids as $property_id) {
                        $property_owner = absint(get_post_field('post_author', $property_id));
                        if ($property_owner === absint($owner_id)) {
                            $paid_units++;
                        }
                    }
                }

                $owner_units = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $unit_tbl WHERE owner_id = %d", $owner_id)));
                $paid_units += $owner_units;
            }
        }
    }

    $unpaid = max(0, $total - $paid_units);
    return array('total' => $total, 'paid' => $paid_units, 'unpaid' => $unpaid);
}

function ms_get_manager_invoices($manager_id, $limit = 20, $invoice_type = 'maintenance', $building_id = 0)
{
    global $wpdb;

    $invoice_table = $wpdb->prefix . 'ms_invoices';
    $building_table = $wpdb->prefix . 'ms_buildings';
    $params = array();

    $building_id = absint($building_id);

    if ($manager_id && user_can($manager_id, 'manage_options')) {
        $sql = "SELECT i.* FROM {$invoice_table} i";
        $where_added = false;
        if (!empty($invoice_type)) {
            $sql .= " WHERE i.invoice_type = %s";
            $params[] = $invoice_type;
            $where_added = true;
        }
        if ($building_id) {
            $sql .= $where_added ? " AND i.building_id = %d" : " WHERE i.building_id = %d";
            $params[] = $building_id;
        }
        $sql .= " ORDER BY i.created_at DESC LIMIT %d";
        $params[] = $limit;
    } else {
        $sql = "SELECT i.* FROM {$invoice_table} i INNER JOIN {$building_table} b ON (b.id = i.building_id OR (b.wp_post_id > 0 AND b.wp_post_id = i.building_id)) WHERE b.manager_id = %d";
        $params[] = $manager_id;
        if (!empty($invoice_type)) {
            $sql .= " AND i.invoice_type = %s";
            $params[] = $invoice_type;
        }
        if ($building_id) {
            if (function_exists('ms_get_building_id_values_for_query')) {
                $building_values = ms_get_building_id_values_for_query($building_id);
            } else {
                $building_values = array($building_id);
            }
            if (!empty($building_values)) {
                $placeholders = implode(',', array_fill(0, count($building_values), '%d'));
                $sql .= " AND i.building_id IN ($placeholders)";
                $params = array_merge($params, $building_values);
            }
        }
        $sql .= " ORDER BY i.created_at DESC LIMIT %d";
        $params[] = $limit;
    }

    $rows = $wpdb->get_results(call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sql), $params)));

    $legacy = array();
    if (function_exists('ms_get_legacy_invoices_for_manager')) {
        $legacy = ms_get_legacy_invoices_for_manager($manager_id, $limit);
    }

    $all = array_merge($rows ?: array(), $legacy ?: array());
    $unique = array();
    $seen_keys = array();
    foreach ($all as $invoice) {
        $invoice_key = ms_get_invoice_business_key($invoice);
        if (!$invoice_key) {
            $invoice_key = 'legacy_id:' . intval($invoice->id ?? $invoice->ID ?? 0);
        }
        if ($invoice_key && isset($seen_keys[$invoice_key])) {
            continue;
        }
        if ($invoice_key) {
            $seen_keys[$invoice_key] = true;
        }
        $unique[] = $invoice;
    }
    $all = $unique;

    usort($all, function ($a, $b) {
        $a_date = strtotime($a->created_at ?? $a->due_date ?? '');
        $b_date = strtotime($b->created_at ?? $b->due_date ?? '');
        return $b_date <=> $a_date;
    });

    if ($limit > 0) {
        $all = array_slice($all, 0, absint($limit));
    }

    return $all;
}

function ms_get_owner_revenue_summary($owner_id)
{
    global $wpdb;
    $owner_id = absint($owner_id);
    $inv_tbl = $wpdb->prefix . 'ms_invoices';

    $scope = ms_get_owner_invoice_scope($owner_id);
    $total_paid = 0.0;
    $total_due = 0.0;
    $upcoming = null;

    if (!empty($scope)) {
        $total_paid = floatval($wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM $inv_tbl WHERE {$scope['where']} AND status='paid'", ...$scope['params'])));
        $total_due = floatval($wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM $inv_tbl WHERE {$scope['where']}", ...$scope['params'])));
        $upcoming = $wpdb->get_row($wpdb->prepare("SELECT id,amount,due_date FROM $inv_tbl WHERE {$scope['where']} AND status!='paid' ORDER BY due_date ASC LIMIT 1", ...$scope['params']));
    }

    if (function_exists('ms_get_legacy_owner_revenue_summary')) {
        $legacy = ms_get_legacy_owner_revenue_summary($owner_id);
        $total_paid += floatval($legacy['total_paid']);
        $total_due += floatval($legacy['total_due']);

        if (!$upcoming) {
            $upcoming = $legacy['next_due'];
        } elseif (!empty($legacy['next_due']) && !empty($legacy['next_due']->due_date)) {
            $legacy_due = strtotime($legacy['next_due']->due_date);
            $current_due = !empty($upcoming->due_date) ? strtotime($upcoming->due_date) : PHP_INT_MAX;
            if ($legacy_due < $current_due) {
                $upcoming = $legacy['next_due'];
            }
        }
    }

    return array('total_paid' => $total_paid, 'total_due' => $total_due, 'next_due' => $upcoming);
}

function ms_get_user_overdue_count($user_id)
{
    $user_id = absint($user_id);
    if (!$user_id) {
        return 0;
    }

    if (function_exists('ms_get_user_invoices')) {
        $invoices = ms_get_user_invoices($user_id);
        $count = 0;
        $today = strtotime(current_time('mysql'));

        foreach ($invoices as $invoice) {
            $status = isset($invoice->status) ? $invoice->status : '';
            $due_date = !empty($invoice->due_date) ? strtotime($invoice->due_date) : 0;

            if ($status === 'overdue' || ($status !== 'paid' && $due_date > 0 && $due_date < $today)) {
                $count++;
            }
        }

        return $count;
    }

    global $wpdb;
    $inv_tbl = $wpdb->prefix . 'ms_invoices';
    $today = current_time('mysql');
    $sql = $wpdb->prepare("SELECT COUNT(id) FROM $inv_tbl WHERE user_id=%d AND (status='overdue' OR (status!='paid' AND due_date < %s))", $user_id, $today);
    $count = intval($wpdb->get_var($sql));

    if (function_exists('ms_get_legacy_user_overdue_count')) {
        $count += ms_get_legacy_user_overdue_count($user_id);
    }

    return $count;
}

function ms_get_building_wallet($building_id)
{
    global $wpdb;
    $building_id = absint($building_id);
    if (!$building_id) {
        return null;
    }

    $table = $wpdb->prefix . 'ms_building_wallet';
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE building_id=%d LIMIT 1", $building_id));
    if ($row) {
        return $row;
    }

    $inserted = $wpdb->insert($table, [
        'building_id' => $building_id,
        'balance' => 0.00,
        'frozen_balance' => 0.00,
        'target_amount' => 0.00,
        'status' => 'active',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ], ['%d', '%f', '%f', '%f', '%s', '%s', '%s']);

    if ($inserted === false) {
        return null;
    }

    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE building_id=%d LIMIT 1", $building_id));
}

function ms_update_building_wallet_balance($building_id, $amount)
{
    global $wpdb;
    $building_id = absint($building_id);
    $amount = floatval($amount);
    if (!$building_id || $amount == 0) {
        return false;
    }

    ms_get_building_wallet($building_id);

    $table = $wpdb->prefix . 'ms_building_wallet';
    return (bool) $wpdb->query($wpdb->prepare(
        "UPDATE $table SET balance = balance + %f, updated_at=%s WHERE building_id=%d",
        $amount,
        current_time('mysql'),
        $building_id
    ));
}

function ms_update_building_wallet_target($building_id, $amount)
{
    global $wpdb;
    $building_id = absint($building_id);
    $amount = floatval($amount);
    if (!$building_id) {
        return false;
    }

    ms_get_building_wallet($building_id);

    $table = $wpdb->prefix . 'ms_building_wallet';
    return (bool) $wpdb->query($wpdb->prepare(
        "UPDATE $table SET target_amount = target_amount + %f, updated_at=%s WHERE building_id=%d",
        $amount,
        current_time('mysql'),
        $building_id
    ));
}

function ms_get_building_wallet_transactions($building_id, $limit = 20)
{
    global $wpdb;
    $building_id = absint($building_id);
    $limit = absint($limit);
    if (!$building_id) {
        return [];
    }

    $table = $wpdb->prefix . 'ms_building_wallet_transactions';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE building_id=%d ORDER BY created_at DESC LIMIT %d",
        $building_id,
        $limit > 0 ? $limit : 20
    ));
}

function ms_get_properties_by_building($building_id, $limit = 0)
{
    $building_id = absint($building_id);
    if (!$building_id) {
        return [];
    }

    $linked_wp_id = ms_get_linked_wp_post_id_for_building($building_id);
    $meta_values = array();
    if ($linked_wp_id) {
        $meta_values[] = intval($linked_wp_id);
    }
    $meta_values[] = $building_id;

    $normalized_meta_values = array_values(array_unique(array_filter(array_map('intval', $meta_values))));
    if (empty($normalized_meta_values)) {
        return [];
    }

    $meta_query = array('relation' => 'OR');
    foreach ($normalized_meta_values as $val) {
        $meta_query[] = array(
            'key' => 'building_id',
            'value' => $val,
            'compare' => '=',
            'type' => 'NUMERIC',
        );
    }

    foreach (array('_ms_building_id', 'ms_building_id', 'building_id') as $meta_key) {
        foreach ($normalized_meta_values as $val) {
            $meta_query[] = array(
                'key' => $meta_key,
                'value' => $val,
                'compare' => '=',
                'type' => 'NUMERIC',
            );
        }
    }

    if (post_type_exists('property')) {
        $args = [
            'post_type' => 'property',
            'post_status' => array('publish', 'pending', 'draft', 'expired', 'houzez_sold', 'disapproved', 'on_hold', 'private', 'future'),
            'posts_per_page' => $limit > 0 ? absint($limit) : -1,
            'meta_query' => $meta_query,
            'orderby' => 'title',
            'order' => 'ASC',
            'fields' => 'all',
        ];
        return get_posts($args);
    }

    return [];
}

function ms_get_property_contacts($property_id)
{
    $property_id = absint($property_id);
    if (!$property_id) {
        return [];
    }

    $owner_id = absint(get_post_meta($property_id, 'owner_id', true));
    if (!$owner_id) {
        $owner_id = absint(get_post_meta($property_id, 'property_owner', true));
    }
    if (!$owner_id) {
        $owner_id = absint(get_post_meta($property_id, 'ms_property_owner_id', true));
    }
    if (!$owner_id) {
        $owner_id = absint(get_post_field('post_author', $property_id));
    }

    $agent_id = absint(get_post_meta($property_id, 'fave_agents', true));
    if (!$agent_id) {
        $agent_id = absint(get_post_meta($property_id, 'fave_property_agent', true));
    }

    $tenant_id = absint(get_post_meta($property_id, 'tenant_id', true));

    return [
        'owner' => $owner_id ? get_userdata($owner_id) : null,
        'agent' => $agent_id ? get_userdata($agent_id) : null,
        'tenant' => $tenant_id ? get_userdata($tenant_id) : null,
    ];
}

function ms_add_notification($user_id, $type, $message, $building_id = 0, $related_id = 0)
{
    global $wpdb;
    $user_id = absint($user_id);
    if (!$user_id) {
        return false;
    }

    $type = sanitize_key($type);
    // Decode URL-encoded characters and sanitize message.
    $message = wp_kses_post(urldecode($message));
    $building_id = absint($building_id);
    $related_id = absint($related_id);

    $table = $wpdb->prefix . 'ms_notifications';
    $created_at = current_time('mysql');
    $inserted = $wpdb->insert($table, [
        'user_id' => $user_id,
        'type' => $type,
        'message' => $message,
        'building_id' => $building_id,
        'related_id' => $related_id,
        'is_read' => 0,
        'created_at' => $created_at,
    ], ['%d', '%s', '%s', '%d', '%d', '%d', '%s']);

    if (!$inserted) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG && !empty($wpdb->last_error)) {
            error_log('[Mostaager] Notification insert failed: ' . $wpdb->last_error);
        }
        return false;
    }

    $notification_id = absint($wpdb->insert_id);
    do_action('ms_notification_created', $notification_id, $user_id, array(
        'type' => $type,
        'message' => $message,
        'building_id' => $building_id,
        'related_id' => $related_id,
        'created_at' => $created_at,
    ));

    return $notification_id;
}

/**
 * Return notifications created after a notification ID owned by the current user.
 * The ID cursor avoids duplicate/missed records when several notifications share a timestamp.
 */
function ms_get_notifications_since($user_id, $after_id = 0, $limit = 20)
{
    global $wpdb;
    $user_id = absint($user_id);
    $after_id = absint($after_id);
    $limit = min(max(absint($limit), 1), 50);

    if (!$user_id) {
        return array();
    }

    $table = $wpdb->prefix . 'ms_notifications';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT id, user_id, type, message, building_id, related_id, is_read, created_at
         FROM {$table}
         WHERE user_id = %d AND id > %d
         ORDER BY id ASC
         LIMIT %d",
        $user_id,
        $after_id,
        $limit
    ));
}

function ms_add_maintenance_timeline_entry($request_id, $status, $title = '', $description = '', $changed_by = 0)
{
    global $wpdb;
    $request_id = absint($request_id);
    $changed_by = absint($changed_by ?: get_current_user_id());
    $status = sanitize_key($status);
    $title = sanitize_text_field($title);
    $description = sanitize_textarea_field($description);
    if (!$request_id || !$status) return false;

    $table = $wpdb->prefix . 'ms_maintenance_timeline';
    $inserted = $wpdb->insert($table, array(
        'request_id' => $request_id,
        'status' => $status,
        'title' => $title ?: $status,
        'description' => $description,
        'changed_by' => $changed_by,
        'created_at' => current_time('mysql'),
    ), array('%d', '%s', '%s', '%s', '%d', '%s'));
    return $inserted ? absint($wpdb->insert_id) : false;
}

function ms_get_notifications_by_user($user_id, $limit = 20)
{

    global $wpdb;
    $user_id = absint($user_id);
    $limit = absint($limit);
    if (!$user_id) {
        return [];
    }

    $table = $wpdb->prefix . 'ms_notifications';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE user_id=%d ORDER BY created_at DESC LIMIT %d",
        $user_id,
        $limit > 0 ? $limit : 20
    ));
}

function ms_get_unread_notifications_count($user_id)
{
    global $wpdb;
    $user_id = absint($user_id);
    if (!$user_id) {
        return 0;
    }

    $table = $wpdb->prefix . 'ms_notifications';
    return intval($wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(id) FROM $table WHERE user_id=%d AND is_read=0",
        $user_id
    )));
}

function ms_mark_notifications_read($user_id)
{
    global $wpdb;
    $user_id = absint($user_id);
    if (!$user_id) {
        return false;
    }

    $table = $wpdb->prefix . 'ms_notifications';
    return (bool) $wpdb->query($wpdb->prepare(
        "UPDATE $table SET is_read=1 WHERE user_id=%d",
        $user_id
    ));
}

function ms_get_buildings_by_manager_cpt($user_id)
{
    $user_id = absint($user_id);
    if (!$user_id) {
        return [];
    }

    // legacy fallback: ms_buildings table is preferred by current code.
    // but we provide CPT lookup for spec.
    $q = new WP_Query([
        'post_type' => 'building',
        'post_status' => ['publish', 'pending', 'draft', 'private', 'future', 'expired'],
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'manager_id',
                'value' => $user_id,
                'compare' => '=',
                'type' => 'NUMERIC',
            ],
        ],
    ]);

    return $q->have_posts() ? $q->posts : [];
}

// Database Migration Functions

function ms_migrate_add_frozen_balance_column()
{
    global $wpdb;
    $wallet_table = $wpdb->prefix . 'ms_building_wallet';
    
    // Check if column already exists
    $column_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
         WHERE TABLE_SCHEMA = %s 
         AND TABLE_NAME = %s 
         AND COLUMN_NAME = 'frozen_balance'",
        DB_NAME,
        $wallet_table
    ));
    
    if ($column_exists > 0) {
        return true; // Column already exists
    }
    
    // Add the column
    $result = $wpdb->query(
        "ALTER TABLE {$wallet_table} 
         ADD COLUMN frozen_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00 
         AFTER balance"
    );
    
    return $result !== false;
}

function ms_migrate_create_security_deposits_table()
{
    global $wpdb;
    $table = $wpdb->prefix . 'ms_security_deposits';
    
    // Check if table already exists
    $table_exists = $wpdb->get_var($wpdb->prepare(
        "SHOW TABLES LIKE %s",
        $table
    ));
    
    if ($table_exists) {
        return true; // Table already exists
    }
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE {$table} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        lease_id BIGINT(20) UNSIGNED NOT NULL,
        unit_id BIGINT(20) UNSIGNED NOT NULL,
        building_id BIGINT(20) UNSIGNED NOT NULL,
        tenant_id BIGINT(20) UNSIGNED NOT NULL,
        owner_id BIGINT(20) UNSIGNED NOT NULL,
        amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        status VARCHAR(30) NOT NULL DEFAULT 'pending',
        frozen_at DATETIME NULL,
        released_at DATETIME NULL,
        deduction_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        deduction_reason TEXT NULL,
        deduction_documents TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY lease_id (lease_id)
    ) {$charset_collate}";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    return true;
}

// Security Deposit Functions

function ms_create_security_deposit($lease_id, $unit_id, $building_id, $tenant_id, $owner_id, $amount)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ms_security_deposits';
    
    $inserted = $wpdb->insert($table, [
        'lease_id' => intval($lease_id),
        'unit_id' => intval($unit_id),
        'building_id' => intval($building_id),
        'tenant_id' => intval($tenant_id),
        'owner_id' => intval($owner_id),
        'amount' => floatval($amount),
        'status' => 'pending',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ], ['%d', '%d', '%d', '%d', '%d', '%f', '%s', '%s']);
    
    return $inserted ? $wpdb->insert_id : false;
}

function ms_get_security_deposit_by_lease($lease_id)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ms_security_deposits';
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE lease_id = %d LIMIT 1", intval($lease_id)));
}

function ms_freeze_security_deposit($deposit_id)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ms_security_deposits';
    
    $deposit = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d LIMIT 1", intval($deposit_id)));
    if (!$deposit || $deposit->status !== 'pending') {
        return false;
    }
    
    // Move amount to frozen balance in wallet
    if (!function_exists('ms_freeze_wallet_balance')) {
        return false;
    }
    
    if (!ms_freeze_wallet_balance($deposit->building_id, $deposit->amount, $deposit->id)) {
        return false;
    }
    
    // Update deposit status
    $updated = $wpdb->update($table, [
        'status' => 'frozen',
        'frozen_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ], ['id' => intval($deposit_id)], ['%s', '%s', '%s'], ['%d']);
    
    return $updated !== false;
}

function ms_release_security_deposit($deposit_id, $deduction_amount = 0, $deduction_reason = '', $deduction_documents = '')
{
    global $wpdb;
    $table = $wpdb->prefix . 'ms_security_deposits';
    
    $deposit = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d LIMIT 1", intval($deposit_id)));
    if (!$deposit || $deposit->status !== 'frozen') {
        return false;
    }
    
    // Release frozen balance minus deductions
    $release_amount = $deposit->amount - floatval($deduction_amount);
    
    if (!function_exists('ms_release_wallet_balance')) {
        return false;
    }
    
    if (!ms_release_wallet_balance($deposit->building_id, $deposit->amount, $release_amount, $deposit->id, $deduction_amount, $deduction_reason)) {
        return false;
    }
    
    // Update deposit status
    $updated = $wpdb->update($table, [
        'status' => 'released',
        'released_at' => current_time('mysql'),
        'deduction_amount' => floatval($deduction_amount),
        'deduction_reason' => $deduction_reason,
        'deduction_documents' => $deduction_documents,
        'updated_at' => current_time('mysql'),
    ], ['id' => intval($deposit_id)], ['%s', '%s', '%f', '%s', '%s', '%s'], ['%d']);
    
    return $updated !== false;
}

function ms_freeze_wallet_balance($building_id, $amount, $reference_id = 0)
{
    global $wpdb;
    $wallet_table = $wpdb->prefix . 'ms_building_wallet';
    
    // Check if wallet has enough balance
    $wallet = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wallet_table WHERE building_id = %d LIMIT 1", intval($building_id)));
    if (!$wallet || floatval($wallet->balance) < floatval($amount)) {
        return false;
    }
    
    // Deduct from balance and add to frozen balance
    $updated = $wpdb->query($wpdb->prepare(
        "UPDATE $wallet_table SET balance = balance - %f, frozen_balance = frozen_balance + %f, updated_at = %s WHERE building_id = %d",
        floatval($amount),
        floatval($amount),
        current_time('mysql'),
        intval($building_id)
    ));
    
    if ($updated === false) {
        return false;
    }
    
    // Add transaction record
    $tx_table = $wpdb->prefix . 'ms_building_wallet_transactions';
    $wpdb->insert($tx_table, [
        'building_id' => intval($building_id),
        'amount' => floatval($amount),
        'type' => 'freeze',
        'description' => 'تجميد تأمين أمانة',
        'reference_id' => intval($reference_id),
        'created_at' => current_time('mysql'),
    ], ['%d', '%f', '%s', '%s', '%d', '%s']);
    
    return true;
}

function ms_release_wallet_balance($building_id, $frozen_amount, $release_amount, $reference_id = 0, $deduction_amount = 0, $deduction_reason = '')
{
    global $wpdb;
    $wallet_table = $wpdb->prefix . 'ms_building_wallet';
    
    // Deduct from frozen balance and return release amount to balance
    $updated = $wpdb->query($wpdb->prepare(
        "UPDATE $wallet_table SET frozen_balance = frozen_balance - %f, balance = balance + %f, updated_at = %s WHERE building_id = %d",
        floatval($frozen_amount),
        floatval($release_amount),
        current_time('mysql'),
        intval($building_id)
    ));
    
    if ($updated === false) {
        return false;
    }
    
    // Add transaction record for release
    $tx_table = $wpdb->prefix . 'ms_building_wallet_transactions';
    $wpdb->insert($tx_table, [
        'building_id' => intval($building_id),
        'amount' => floatval($release_amount),
        'type' => 'release',
        'description' => 'إطلاق تأمين أمانة' . ($deduction_amount > 0 ? ' (خصم: ' . number_format_i18n($deduction_amount, 2) . ' ج.م)' : ''),
        'reference_id' => intval($reference_id),
        'created_at' => current_time('mysql'),
    ], ['%d', '%f', '%s', '%s', '%d', '%s']);
    
    // Add transaction record for deduction if any
    if ($deduction_amount > 0) {
        $wpdb->insert($tx_table, [
            'building_id' => intval($building_id),
            'amount' => floatval($deduction_amount),
            'type' => 'deduction',
            'description' => 'خصم من التأمين: ' . $deduction_reason,
            'reference_id' => intval($reference_id),
            'created_at' => current_time('mysql'),
        ], ['%d', '%f', '%s', '%s', '%d', '%s']);
    }
    
    return true;
}


// ========== نظام موافقة الوسيط على تغيير الحالة ==========

function ms_create_status_change_request($property_id, $unit_id, $owner_id, $agent_id, $requested_status, $current_status)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ms_status_change_requests';

    $inserted = $wpdb->insert($table, [
        'property_id' => intval($property_id),
        'unit_id' => intval($unit_id),
        'owner_id' => intval($owner_id),
        'agent_id' => intval($agent_id),
        'requested_status' => sanitize_key($requested_status),
        'current_status' => sanitize_key($current_status),
        'status' => 'pending',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ], ['%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s']);

    if ($inserted) {
        ms_add_notification($agent_id, 'status_change_request', "طلب تغيير حالة العقار إلى {$requested_status}", 0, $wpdb->insert_id);
        return $wpdb->insert_id;
    }

    return false;
}

function ms_get_pending_status_requests($agent_id)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ms_status_change_requests';

    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE agent_id = %d AND status = 'pending' ORDER BY created_at DESC",
        intval($agent_id)
    ));
}

function ms_approve_status_change_request($request_id, $agent_id, $reason = '')
{
    global $wpdb;
    $table = $wpdb->prefix . 'ms_status_change_requests';

    $request = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d AND agent_id = %d LIMIT 1",
        intval($request_id),
        intval($agent_id)
    ));

    if (!$request || $request->status !== 'pending') {
        return array('success' => false, 'message' => 'الطلب غير موجود أو تمت معالجته');
    }

    $unit_table = $wpdb->prefix . 'ms_units';
    $wpdb->update($unit_table, [
        'status' => $request->requested_status,
        'updated_at' => current_time('mysql'),
    ], ['id' => intval($request->unit_id)], ['%s', '%s'], ['%d']);

    $wpdb->update($table, [
        'status' => 'approved',
        'decision' => 'approved',
        'decision_reason' => $reason,
        'decision_made_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ], ['id' => intval($request_id)], ['%s', '%s', '%s', '%s', '%s'], ['%d']);

    ms_add_notification($request->owner_id, 'status_change_approved', "تمت الموافقة على تغيير حالة العقار إلى {$request->requested_status}", 0, $request_id);

    return array('success' => true, 'message' => 'تمت الموافقة على الطلب بنجاح');
}

function ms_reject_status_change_request($request_id, $agent_id, $reason = '')
{
    global $wpdb;
    $table = $wpdb->prefix . 'ms_status_change_requests';

    $request = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d AND agent_id = %d LIMIT 1",
        intval($request_id),
        intval($agent_id)
    ));

    if (!$request || $request->status !== 'pending') {
        return array('success' => false, 'message' => 'الطلب غير موجود أو تمت معالجته');
    }

    $wpdb->update($table, [
        'status' => 'rejected',
        'decision' => 'rejected',
        'decision_reason' => $reason,
        'decision_made_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ], ['id' => intval($request_id)], ['%s', '%s', '%s', '%s', '%s'], ['%d']);

    ms_add_notification($request->owner_id, 'status_change_rejected', "تم رفض تغيير حالة العقار. السبب: {$reason}", 0, $request_id);

    return array('success' => true, 'message' => 'تم رفض الطلب بنجاح');
}

function ms_get_status_request_by_property($property_id)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ms_status_change_requests';

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE property_id = %d ORDER BY created_at DESC LIMIT 1",
        intval($property_id)
    ));
}

// ========== نظام المحفظة لجميع المستخدمين ==========

function ms_ensure_user_wallet($user_id)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ms_user_wallet';

    $wallet = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d LIMIT 1",
        intval($user_id)
    ));

    if (!$wallet) {
        $wpdb->insert($table, [
            'user_id' => intval($user_id),
            'balance' => 0.00,
            'currency' => 'EGP',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ], ['%d', '%f', '%s', '%s', '%s']);

        return $wpdb->insert_id;
    }

    return $wallet->id;
}

function ms_get_user_wallet_balance($user_id)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ms_user_wallet';

    $wallet = $wpdb->get_row($wpdb->prepare(
        "SELECT balance FROM $table WHERE user_id = %d LIMIT 1",
        intval($user_id)
    ));

    return $wallet ? floatval($wallet->balance) : 0.00;
}

function ms_add_wallet_transaction($user_id, $type, $amount, $meta = '')
{
    global $wpdb;
    $table = $wpdb->prefix . 'ms_wallet_transactions';

    return $wpdb->insert($table, [
        'user_id' => intval($user_id),
        'type' => sanitize_key($type),
        'amount' => floatval($amount),
        'meta' => is_array($meta) ? json_encode($meta) : $meta,
        'created_at' => current_time('mysql'),
    ], ['%d', '%s', '%f', '%s', '%s']);
}

function ms_topup_wallet($user_id, $amount, $payment_method = '', $reference = '')
{
    global $wpdb;
    $wallet_table = $wpdb->prefix . 'ms_user_wallet';
    $user_id = absint($user_id);
    $amount = round((float) $amount, 2);

    if (!$user_id || $amount <= 0) {
        return array('success' => false, 'message' => 'بيانات تعبئة المحفظة غير صالحة');
    }

    ms_ensure_user_wallet($user_id);

    $wpdb->query('START TRANSACTION');
    $updated = $wpdb->query($wpdb->prepare(
        "UPDATE $wallet_table SET balance = balance + %f, updated_at = %s WHERE user_id = %d",
        $amount,
        current_time('mysql'),
        $user_id
    ));

    if ($updated !== 1 || !ms_add_wallet_transaction($user_id, 'topup', $amount, array(
        'payment_method' => sanitize_key($payment_method),
        'reference' => sanitize_text_field($reference),
    ))) {
        $wpdb->query('ROLLBACK');
        return array('success' => false, 'message' => 'فشل تحديث المحفظة');
    }

    $wpdb->query('COMMIT');

    ms_add_notification($user_id, 'wallet_topup', "تم تعبئة محفظتك بمبلغ {$amount} ج.م", 0, 0);

    return array('success' => true, 'message' => 'تم تعبئة المحفظة بنجاح', 'new_balance' => ms_get_user_wallet_balance($user_id));
}

function ms_deduct_wallet($user_id, $amount, $reason = '', $reference_id = 0)
{
    global $wpdb;
    $wallet_table = $wpdb->prefix . 'ms_user_wallet';
    $user_id = absint($user_id);
    $amount = round((float) $amount, 2);

    if (!$user_id || $amount <= 0) {
        return array('success' => false, 'message' => 'بيانات الخصم غير صالحة');
    }

    ms_ensure_user_wallet($user_id);
    $wpdb->query('START TRANSACTION');
    $updated = $wpdb->query($wpdb->prepare(
        "UPDATE $wallet_table SET balance = balance - %f, updated_at = %s WHERE user_id = %d AND balance >= %f",
        $amount,
        current_time('mysql'),
        $user_id,
        $amount
    ));

    if ($updated !== 1) {
        $wpdb->query('ROLLBACK');
        return array('success' => false, 'message' => 'رصيد المحفظة غير كافٍ');
    }

    if (!ms_add_wallet_transaction($user_id, 'deduction', $amount, array(
        'reason' => sanitize_text_field($reason),
        'reference_id' => absint($reference_id),
    ))) {
        $wpdb->query('ROLLBACK');
        return array('success' => false, 'message' => 'فشل خصم الرصيد');
    }

    $wpdb->query('COMMIT');

    return array('success' => true, 'message' => 'تم خصم الرصيد بنجاح', 'new_balance' => ms_get_user_wallet_balance($user_id));
}

function ms_transfer_wallet($from_user_id, $to_user_id, $amount, $reason = '')
{
    global $wpdb;
    $from_user_id = absint($from_user_id);
    $to_user_id = absint($to_user_id);
    $amount = round((float) $amount, 2);

    if (!$from_user_id || !$to_user_id || $from_user_id === $to_user_id || $amount <= 0) {
        return array('success' => false, 'message' => 'بيانات التحويل غير صالحة');
    }

    ms_ensure_user_wallet($from_user_id);
    ms_ensure_user_wallet($to_user_id);

    $wpdb->query('START TRANSACTION');
    $first_id = min($from_user_id, $to_user_id);
    $second_id = max($from_user_id, $to_user_id);
    $wallet_table = $wpdb->prefix . 'ms_user_wallet';
    $locked_wallets = $wpdb->get_results($wpdb->prepare(
        "SELECT user_id FROM $wallet_table WHERE user_id IN (%d, %d) ORDER BY user_id FOR UPDATE",
        $first_id,
        $second_id
    ));

    if (count($locked_wallets) !== 2) {
        $wpdb->query('ROLLBACK');
        return array('success' => false, 'message' => 'تعذر تأمين المحافظ للتحويل');
    }

    $deducted = $wpdb->query($wpdb->prepare(
        "UPDATE $wallet_table SET balance = balance - %f, updated_at = %s WHERE user_id = %d AND balance >= %f",
        $amount,
        current_time('mysql'),
        $from_user_id,
        $amount
    ));

    $credited = $deducted === 1 && $wpdb->query($wpdb->prepare(
        "UPDATE $wallet_table SET balance = balance + %f, updated_at = %s WHERE user_id = %d",
        $amount,
        current_time('mysql'),
        $to_user_id
    )) === 1;

    $sent_transaction = $credited && ms_add_wallet_transaction($from_user_id, 'transfer_sent', $amount, array(
        'to_user_id' => $to_user_id,
        'reason' => sanitize_text_field($reason),
    ));
    $received_transaction = $sent_transaction && ms_add_wallet_transaction($to_user_id, 'transfer_received', $amount, array(
        'from_user_id' => $from_user_id,
        'reason' => sanitize_text_field($reason),
    ));

    if (!$received_transaction) {
        $wpdb->query('ROLLBACK');
        return array('success' => false, 'message' => 'رصيد المحفظة غير كافٍ أو فشل تسجيل التحويل');
    }

    $wpdb->query('COMMIT');

    ms_add_notification($from_user_id, 'wallet_transfer_sent', "تم تحويل {$amount} ج.م بنجاح", 0, 0);
    ms_add_notification($to_user_id, 'wallet_transfer_received', "استلمت {$amount} ج.م من مستخدم آخر", 0, 0);

    return array('success' => true, 'message' => 'تم التحويل بنجاح');
}

function ms_ensure_universal_wallet_system()
{
    $users = get_users(array('number' => -1));
    foreach ($users as $user) {
        ms_ensure_user_wallet($user->ID);
    }
}
