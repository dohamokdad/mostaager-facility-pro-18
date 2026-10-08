<?php
/**
 * Mostaager — Property ⇄ Building/Unit link (Phase 5)
 *
 * المشكلة التي يحلّها: تسعة معالجات مختلفة تستمع لحفظ العقار، ولكل منها طريقته
 * في قراءة وكتابة الربط:
 *   - مفاتيح ميتا متضاربة: ms_building_id · _ms_building_id · building_id
 *     و ms_unit_id · unit_id
 *   - مساران مختلفان ينشئان مبنى جديداً من نفس العقار (صندوقان مختلفان) فينتج مبنيان
 *   - ms_sync_unit_with_property كانت تعامل رقم منشور العقار كأنه رقم صف الوحدة
 *     (WHERE id = property_id) فتعدّل وحدة لا علاقة لها بالعقار أو تنشئ وحدة خاطئة
 *
 * الحل: مصدر واحد للقراءة والكتابة. المفتاح المعتمد هو ms_building_id / ms_unit_id
 * مع كتابة المفاتيح القديمة كمرآة حتى لا ينكسر أي كود قائم.
 */

if (!defined('ABSPATH')) {
    exit;
}

const MS_BUILDING_META_KEYS = array('ms_building_id', '_ms_building_id', 'building_id');
const MS_UNIT_META_KEYS     = array('ms_unit_id', '_ms_unit_id', 'unit_id');

/**
 * حارس موحّد لمعالجات حفظ العقار: يمنع الحفظ التلقائي والمراجعات والاستيراد،
 * ويضمن أن الجزء الثقيل لا يتكرر في الطلب نفسه.
 *
 * @param string $context اسم فريد للمعالج حتى يبقى لكل معالج تشغيلة واحدة
 */
function ms_property_save_guard($post_id, $post = null, $context = 'default') {
    static $done = array();

    $post_id = absint($post_id);
    if (!$post_id) {
        return false;
    }
    $post = $post ?: get_post($post_id);
    if (!$post || $post->post_type !== 'property') {
        return false;
    }
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return false;
    }
    if (in_array($post->post_status, array('auto-draft', 'trash'), true)) {
        return false;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return false;
    }

    $key = $context . ':' . $post_id;
    if (isset($done[$key])) {
        return false;
    }
    $done[$key] = true;

    return true;
}

/** رقم المبنى المرتبط بالعقار من أي مفتاح معروف، أو من جدول المباني نفسه */
function ms_property_building_id($post_id) {
    global $wpdb;

    $post_id = absint($post_id);
    if (!$post_id) {
        return 0;
    }
    foreach (MS_BUILDING_META_KEYS as $key) {
        $value = absint(get_post_meta($post_id, $key, true));
        if ($value) {
            return $value;
        }
    }
    // العقار قد يكون هو نفسه منشور المبنى في جدول ms_buildings
    $building_id = absint($wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}ms_buildings WHERE wp_post_id = %d LIMIT 1",
        $post_id
    )));

    if ($building_id) {
        ms_link_property_building($post_id, $building_id);
    }

    return $building_id;
}

/** يكتب الربط بالمبنى على كل المفاتيح المعروفة (مفتاح واحد معتمد + مرايا) */
function ms_link_property_building($post_id, $building_id) {
    $post_id = absint($post_id);
    $building_id = absint($building_id);
    if (!$post_id || !$building_id) {
        return false;
    }
    foreach (MS_BUILDING_META_KEYS as $key) {
        update_post_meta($post_id, $key, $building_id);
    }
    do_action('ms_property_building_linked', $post_id, $building_id);

    return true;
}

/** رقم الوحدة المرتبطة بالعقار (ميتا أولاً ثم عمود property_id في جدول الوحدات) */
function ms_property_unit_id($post_id) {
    global $wpdb;

    $post_id = absint($post_id);
    if (!$post_id) {
        return 0;
    }
    foreach (MS_UNIT_META_KEYS as $key) {
        $value = absint(get_post_meta($post_id, $key, true));
        if ($value) {
            return $value;
        }
    }

    $units = $wpdb->prefix . 'ms_units';
    $suppress = $wpdb->suppress_errors(true);
    $unit_id = absint($wpdb->get_var($wpdb->prepare("SELECT id FROM {$units} WHERE property_id = %d LIMIT 1", $post_id)));
    $wpdb->suppress_errors($suppress);

    if ($unit_id) {
        ms_link_property_unit($post_id, $unit_id);
    }

    return $unit_id;
}

/** يربط العقار بوحدة موجودة: ميتا + عمود property_id إن كان موجوداً */
function ms_link_property_unit($post_id, $unit_id) {
    global $wpdb;

    $post_id = absint($post_id);
    $unit_id = absint($unit_id);
    if (!$post_id || !$unit_id) {
        return false;
    }
    foreach (MS_UNIT_META_KEYS as $key) {
        update_post_meta($post_id, $key, $unit_id);
    }

    $suppress = $wpdb->suppress_errors(true);
    $wpdb->update($wpdb->prefix . 'ms_units', array('property_id' => $post_id), array('id' => $unit_id), array('%d'), array('%d'));
    $wpdb->suppress_errors($suppress);

    do_action('ms_property_unit_linked', $post_id, $unit_id);

    return true;
}

/**
 * ينشئ مبنى من عقار مرة واحدة فقط — كل مسارات «إنشاء مبنى جديد» تمر من هنا.
 *
 * @param array $args title · manager_id · supervisor_phone
 * @return int رقم المبنى (الموجود أو الجديد)، أو 0 عند الفشل
 */
function ms_create_building_from_property($post_id, $args = array()) {
    global $wpdb;

    $post_id = absint($post_id);
    if (!$post_id) {
        return 0;
    }

    // مرتبط أصلاً؟ لا تنشئ مبنى ثانياً
    $existing = ms_property_building_id($post_id);
    if ($existing) {
        return $existing;
    }

    $table = $wpdb->prefix . 'ms_buildings';
    $data = array(
        'title'      => !empty($args['title']) ? sanitize_text_field($args['title']) : get_the_title($post_id),
        'wp_post_id' => $post_id,
        'created_at' => current_time('mysql'),
    );
    if (!empty($args['manager_id'])) {
        $data['manager_id'] = absint($args['manager_id']);
    }
    if (!empty($args['supervisor_phone'])) {
        $data['supervisor_phone'] = sanitize_text_field($args['supervisor_phone']);
    }
    $data['company_id'] = 1;

    // الأعمدة تختلف بين التنصيبات (company_id و supervisor_phone تُضافان لاحقاً)،
    // فلا نُدرج إلا ما هو موجود فعلاً وإلا فشل الإدراج بصمت
    $data = array_intersect_key($data, array_flip(ms_table_columns($table)));
    if (empty($data['title'])) {
        return 0;
    }

    $inserted = $wpdb->insert($table, $data);
    if ($inserted === false) {
        return 0;
    }

    $building_id = absint($wpdb->insert_id);
    ms_link_property_building($post_id, $building_id);
    do_action('ms_building_created_from_property', $building_id, $post_id, $args);

    return $building_id;
}

/** أسماء أعمدة جدول (MySQL أو SQLite) مع كاش لكل طلب */
function ms_table_columns($table) {
    global $wpdb;
    static $cache = array();
    if (isset($cache[$table])) {
        return $cache[$table];
    }
    $suppress = $wpdb->suppress_errors(true);
    $columns = (array) $wpdb->get_col("SELECT name FROM pragma_table_info('{$table}')");
    if (empty(array_filter($columns))) {
        $columns = array();
        foreach ((array) $wpdb->get_results("SHOW COLUMNS FROM {$table}") as $row) {
            if (isset($row->Field)) {
                $columns[] = $row->Field;
            }
        }
    }
    $wpdb->suppress_errors($suppress);

    return $cache[$table] = array_values(array_filter($columns));
}

/**
 * يزامن الوحدة المرتبطة بالعقار (ولا ينشئ وحدة عشوائية).
 *
 * @param array $fields أعمدة جدول الوحدات المراد تحديثها (agent_id، owner_id، status…)
 */
function ms_sync_property_unit_fields($post_id, array $fields) {
    global $wpdb;

    $post_id = absint($post_id);
    $unit_id = ms_property_unit_id($post_id);
    if (!$post_id || !$unit_id || empty($fields)) {
        return false;
    }

    $units = $wpdb->prefix . 'ms_units';
    $columns = ms_table_columns($units);

    $data = array();
    foreach ($fields as $column => $value) {
        if (in_array($column, $columns, true)) {
            $data[$column] = $value;
        }
    }
    if (empty($data)) {
        return false;
    }

    return false !== $wpdb->update($units, $data, array('id' => $unit_id), null, array('%d'));
}

/**
 * تسجيل حقول الربط في REST بدل كشف كل post meta.
 * القراءة لمن يستطيع قراءة العقار، والكتابة لمن يستطيع تعديله.
 */
add_action('init', function () {
    if (!post_type_exists('property')) {
        return;
    }

    $fields = array(
        'ms_building_id' => 'integer',
        'ms_unit_id'     => 'integer',
        'ms_unit_number' => 'string',
        'ms_floor'       => 'string',
        'ms_unit_status' => 'string',
    );

    foreach ($fields as $key => $type) {
        register_post_meta('property', $key, array(
            'type'              => $type,
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => $type === 'integer' ? 'absint' : 'sanitize_text_field',
            'auth_callback'     => function ($allowed, $meta_key, $post_id) {
                return current_user_can('edit_post', $post_id);
            },
        ));
    }
}, 20);
