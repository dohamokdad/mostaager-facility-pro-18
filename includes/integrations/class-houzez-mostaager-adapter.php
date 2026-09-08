<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Canonical adapter between Houzez property posts and Mostaager operational tables.
 * Houzez remains the presentation layer; Mostaager remains the source of truth.
 */
final class MS_Houzez_Mostaager_Adapter
{
    const PROPERTY_POST_TYPE = 'property';
    const PROPERTY_META_UNIT_ID = 'ms_unit_id';
    const PROPERTY_META_BUILDING_ID = 'ms_building_id';
    const PROPERTY_META_CONTRACT_ID = 'ms_contract_id';
    const PROPERTY_META_PROPERTY_ID = 'ms_mostaager_property_id';

    public static function init()
    {
        add_action('save_post_property', array(__CLASS__, 'sync_property'), 30, 3);
        add_action('ms_maintenance_status_changed', array(__CLASS__, 'sync_maintenance_status'), 20, 3);
    }

    public static function sync_property($post_id, $post = null, $update = false)
    {
        $post_id = absint($post_id);
        if (!$post_id || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
        if (!$post) $post = get_post($post_id);
        if (!$post || $post->post_type !== self::PROPERTY_POST_TYPE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $building_id = absint(self::first_meta($post_id, array('ms_building_id', 'building_id')));
        $unit_id = absint(self::first_meta($post_id, array('ms_unit_id', 'unit_id')));
        $unit_number = sanitize_text_field(self::first_meta($post_id, array('ms_unit_number', 'fave_property_id')));

        global $wpdb;
        $units_table = $wpdb->prefix . 'ms_units';
        if (!$unit_id && $building_id && self::has_table($units_table)) {
            $where = array('building_id = %d');
            $params = array($building_id);
            if ($unit_number && self::has_column($units_table, 'unit_number')) {
                $where[] = 'unit_number = %s';
                $params[] = $unit_number;
            }
            $unit_id = absint($wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$units_table} WHERE " . implode(' AND ', $where) . " ORDER BY id ASC LIMIT 1",
                ...$params
            )));
        }

        update_post_meta($post_id, self::PROPERTY_META_PROPERTY_ID, $post_id);
        if ($building_id) update_post_meta($post_id, self::PROPERTY_META_BUILDING_ID, $building_id);
        if ($unit_id) update_post_meta($post_id, self::PROPERTY_META_UNIT_ID, $unit_id);

        $contract_id = absint(self::first_meta($post_id, array('ms_contract_id', 'ms_property_contract_id', 'lease_id')));
        if ($contract_id) update_post_meta($post_id, self::PROPERTY_META_CONTRACT_ID, $contract_id);

        do_action('ms_houzez_property_mapped', $post_id, array(
            'property_id' => $post_id,
            'building_id' => $building_id,
            'unit_id' => $unit_id,
            'contract_id' => $contract_id,
        ));
    }

    public static function get_context($property_id)
    {
        $property_id = absint($property_id);
        if (!$property_id || get_post_type($property_id) !== self::PROPERTY_POST_TYPE) return array();
        $building_id = absint(get_post_meta($property_id, self::PROPERTY_META_BUILDING_ID, true));
        $unit_id = absint(get_post_meta($property_id, self::PROPERTY_META_UNIT_ID, true));
        $contract_id = absint(get_post_meta($property_id, self::PROPERTY_META_CONTRACT_ID, true));

        global $wpdb;
        $context = array(
            'property_id' => $property_id,
            'building_id' => $building_id,
            'unit_id' => $unit_id,
            'contract_id' => $contract_id,
            'property_title' => get_the_title($property_id),
            'unit' => null,
            'contract' => null,
        );
        if ($unit_id) {
            $units_table = $wpdb->prefix . 'ms_units';
            if (self::has_table($units_table)) {
                $context['unit'] = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$units_table} WHERE id = %d LIMIT 1", $unit_id));
            }
        }
        if ($contract_id) $context['contract'] = self::find_contract($contract_id);
        return $context;
    }

    public static function find_contract($contract_id)
    {
        global $wpdb;
        $contract_id = absint($contract_id);
        foreach (array($wpdb->prefix . 'ms_contracts', $wpdb->prefix . 'ms_leases') as $table) {
            if (self::has_table($table)) {
                return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $contract_id));
            }
        }
        $deposits = $wpdb->prefix . 'ms_security_deposits';
        if (self::has_table($deposits)) {
            return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$deposits} WHERE lease_id = %d LIMIT 1", $contract_id));
        }
        return null;
    }

    public static function sync_maintenance_status($maintenance_id, $new_status, $old_status = '')
    {
        $maintenance_id = absint($maintenance_id);
        if (!$maintenance_id || !defined('MS_HOUZEZ_SYNC_MAINTENANCE') || !MS_HOUZEZ_SYNC_MAINTENANCE) return;
        global $wpdb;
        $table = $wpdb->prefix . 'ms_maintenance_requests';
        $ticket = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $maintenance_id));
        if (!$ticket) return;

        $existing = get_posts(array(
            'post_type' => 'houzez_maintenance',
            'post_status' => array('publish', 'draft', 'private'),
            'posts_per_page' => 1,
            'meta_key' => '_ms_maintenance_id',
            'meta_value' => $maintenance_id,
        ));
        $post_data = array(
            'post_title' => sanitize_text_field($ticket->title ?: ('Maintenance #' . $maintenance_id)),
            'post_content' => wp_kses_post($ticket->description ?? ''),
            'post_status' => 'publish',
            'post_type' => 'houzez_maintenance',
        );
        $post_id = !empty($existing) ? wp_update_post(array_merge($post_data, array('ID' => $existing[0]->ID)), true) : wp_insert_post($post_data, true);
        if (is_wp_error($post_id)) return;
        update_post_meta($post_id, '_ms_maintenance_id', $maintenance_id);
        update_post_meta($post_id, 'ms_building_id', absint($ticket->building_id));
        update_post_meta($post_id, 'ms_unit_id', absint($ticket->unit_id));
        update_post_meta($post_id, 'ms_status', sanitize_key($new_status));
        update_post_meta($post_id, 'ms_priority', sanitize_key($ticket->priority ?? 'medium'));
        update_post_meta($post_id, 'ms_cost', (float) ($ticket->cost ?? 0));
    }

    private static function first_meta($post_id, $keys)
    {
        foreach ($keys as $key) {
            $value = get_post_meta($post_id, $key, true);
            if ($value !== '' && $value !== null) return $value;
        }
        return '';
    }

    private static function has_table($table)
    {
        global $wpdb;
        static $cache = array();
        if (!isset($cache[$table])) {
            $cache[$table] = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        }
        return $cache[$table];
    }

    private static function has_column($table, $column)
    {
        global $wpdb;
        static $cache = array();
        $key = $table . ':' . $column;
        if (!isset($cache[$key])) {
            $cache[$key] = (bool) $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column));
        }
        return $cache[$key];
    }
}

MS_Houzez_Mostaager_Adapter::init();
