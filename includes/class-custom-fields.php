<?php
/**
 * Custom Fields Manager - Mostaager Facility PRO Add-On
 */

if (!defined('ABSPATH')) {
    exit;
}

class MS_Custom_Fields_Manager {
    
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_custom_meta_boxes'));
        add_action('save_post', array($this, 'save_custom_fields'));
        add_filter('houzez_property_meta_fields', array($this, 'add_custom_fields_to_meta'));
        add_action('wp_ajax_ms_add_custom_field', array($this, 'add_custom_field'));
        add_action('wp_ajax_ms_delete_custom_field', array($this, 'delete_custom_field'));
    }
    
    /**
     * Add custom meta boxes
     */
    public function add_custom_meta_boxes() {
        add_meta_box(
            'ms_custom_fields',
            '⚙️ الحقول المخصصة',
            array($this, 'render_custom_fields_metabox'),
            'property',
            'normal',
            'default'
        );
    }
    
    /**
     * Render custom fields metabox
     */
    public function render_custom_fields_metabox($post) {
        wp_nonce_field('ms_custom_fields_nonce', 'ms_custom_fields_nonce');
        
        $custom_fields = $this->get_custom_fields();
        $saved_values = $this->get_saved_custom_values($post->ID);
        
        ?>
        <div class="ms-custom-fields-container">
            <?php if (!empty($custom_fields)): ?>
                <?php foreach ($custom_fields as $field): ?>
                    <div class="ms-custom-field-group">
                        <label for="ms_field_<?php echo esc_attr($field['id']); ?>">
                            <?php echo esc_html($field['label']); ?>
                            <?php if ($field['required']): ?>
                                <span class="ms-required">*</span>
                            <?php endif; ?>
                        </label>
                        
                        <?php
                        $value = isset($saved_values[$field['id']]) ? $saved_values[$field['id']] : $field['default'];
                        $this->render_field_input($field, $value);
                        ?>
                        
                        <?php if (!empty($field['description'])): ?>
                            <p class="ms-field-description"><?php echo esc_html($field['description']); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="ms-no-custom-fields">لا توجد حقول مخصصة</p>
                <a href="<?php echo admin_url('admin.php?page=ms-import-settings'); ?>" class="ms-button ms-button-primary">
                    إضافة حقول مخصصة
                </a>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render field input based on type
     */
    private function render_field_input($field, $value) {
        $field_id = 'ms_field_' . esc_attr($field['id']);
        $field_name = 'ms_custom_fields[' . esc_attr($field['id']) . ']';
        
        switch ($field['type']) {
            case 'text':
                ?>
                <input type="text" id="<?php echo $field_id; ?>" name="<?php echo $field_name; ?>" 
                       value="<?php echo esc_attr($value); ?>" class="ms-form-input"
                       <?php echo $field['required'] ? 'required' : ''; ?>>
                <?php
                break;
                
            case 'number':
                ?>
                <input type="number" id="<?php echo $field_id; ?>" name="<?php echo $field_name; ?>" 
                       value="<?php echo esc_attr($value); ?>" class="ms-form-input"
                       <?php echo $field['required'] ? 'required' : ''; ?>>
                <?php
                break;
                
            case 'email':
                ?>
                <input type="email" id="<?php echo $field_id; ?>" name="<?php echo $field_name; ?>" 
                       value="<?php echo esc_attr($value); ?>" class="ms-form-input"
                       <?php echo $field['required'] ? 'required' : ''; ?>>
                <?php
                break;
                
            case 'tel':
                ?>
                <input type="tel" id="<?php echo $field_id; ?>" name="<?php echo $field_name; ?>" 
                       value="<?php echo esc_attr($value); ?>" class="ms-form-input"
                       <?php echo $field['required'] ? 'required' : ''; ?>>
                <?php
                break;
                
            case 'url':
                ?>
                <input type="url" id="<?php echo $field_id; ?>" name="<?php echo $field_name; ?>" 
                       value="<?php echo esc_attr($value); ?>" class="ms-form-input"
                       <?php echo $field['required'] ? 'required' : ''; ?>>
                <?php
                break;
                
            case 'textarea':
                ?>
                <textarea id="<?php echo $field_id; ?>" name="<?php echo $field_name; ?>" 
                          class="ms-form-textarea" rows="4"
                          <?php echo $field['required'] ? 'required' : ''; ?>><?php echo esc_textarea($value); ?></textarea>
                <?php
                break;
                
            case 'select':
                ?>
                <select id="<?php echo $field_id; ?>" name="<?php echo $field_name; ?>" class="ms-form-select"
                        <?php echo $field['required'] ? 'required' : ''; ?>>
                    <?php foreach ($field['options'] as $option): ?>
                        <option value="<?php echo esc_attr($option['value']); ?>" 
                                <?php selected($value, $option['value']); ?>>
                            <?php echo esc_html($option['label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php
                break;
                
            case 'multiselect':
                ?>
                <select id="<?php echo $field_id; ?>" name="<?php echo $field_name; ?>[]" class="ms-form-select" multiple
                        <?php echo $field['required'] ? 'required' : ''; ?>>
                    <?php foreach ($field['options'] as $option): ?>
                        <option value="<?php echo esc_attr($option['value']); ?>" 
                                <?php echo is_array($value) && in_array($option['value'], $value) ? 'selected' : ''; ?>>
                            <?php echo esc_html($option['label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php
                break;
                
            case 'checkbox':
                ?>
                <label>
                    <input type="checkbox" id="<?php echo $field_id; ?>" name="<?php echo $field_name; ?>" 
                           value="1" <?php checked($value, '1'); ?>>
                    <?php echo esc_html($field['label']); ?>
                </label>
                <?php
                break;
                
            case 'radio':
                ?>
                <?php foreach ($field['options'] as $option): ?>
                    <label>
                        <input type="radio" id="<?php echo $field_id . '_' . esc_attr($option['value']); ?>" 
                               name="<?php echo $field_name; ?>" 
                               value="<?php echo esc_attr($option['value']); ?>"
                               <?php checked($value, $option['value']); ?>>
                        <?php echo esc_html($option['label']); ?>
                    </label>
                    <br>
                <?php endforeach; ?>
                <?php
                break;
                
            case 'date':
                ?>
                <input type="date" id="<?php echo $field_id; ?>" name="<?php echo $field_name; ?>" 
                       value="<?php echo esc_attr($value); ?>" class="ms-form-input"
                       <?php echo $field['required'] ? 'required' : ''; ?>>
                <?php
                break;
                
            case 'time':
                ?>
                <input type="time" id="<?php echo $field_id; ?>" name="<?php echo $field_name; ?>" 
                       value="<?php echo esc_attr($value); ?>" class="ms-form-input"
                       <?php echo $field['required'] ? 'required' : ''; ?>>
                <?php
                break;
                
            case 'datetime':
                ?>
                <input type="datetime-local" id="<?php echo $field_id; ?>" name="<?php echo $field_name; ?>" 
                       value="<?php echo esc_attr($value); ?>" class="ms-form-input"
                       <?php echo $field['required'] ? 'required' : ''; ?>>
                <?php
                break;
                
            case 'file':
                ?>
                <input type="file" id="<?php echo $field_id; ?>" name="<?php echo $field_name; ?>" 
                       class="ms-form-input"
                       <?php echo $field['required'] ? 'required' : ''; ?>>
                <?php if (!empty($value)): ?>
                    <a href="<?php echo esc_url($value); ?>" target="_blank">عرض الملف</a>
                <?php endif; ?>
                <?php
                break;
                
            case 'color':
                ?>
                <input type="color" id="<?php echo $field_id; ?>" name="<?php echo $field_name; ?>" 
                       value="<?php echo esc_attr($value); ?>" class="ms-form-input"
                       <?php echo $field['required'] ? 'required' : ''; ?>>
                <?php
                break;
                
            default:
                ?>
                <input type="text" id="<?php echo $field_id; ?>" name="<?php echo $field_name; ?>" 
                       value="<?php echo esc_attr($value); ?>" class="ms-form-input"
                       <?php echo $field['required'] ? 'required' : ''; ?>>
                <?php
                break;
        }
    }
    
    /**
     * Save custom fields
     */
    public function save_custom_fields($post_id) {
        if (!isset($_POST['ms_custom_fields_nonce']) || !wp_verify_nonce($_POST['ms_custom_fields_nonce'], 'ms_custom_fields_nonce')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        if (isset($_POST['ms_custom_fields'])) {
            foreach ($_POST['ms_custom_fields'] as $field_id => $value) {
                if (is_array($value)) {
                    $value = array_map('sanitize_text_field', $value);
                } else {
                    $value = sanitize_text_field($value);
                }
                
                update_post_meta($post_id, 'ms_custom_' . $field_id, $value);
            }
        }
    }
    
    /**
     * Add custom fields to Houzez meta
     */
    public function add_custom_fields_to_meta($meta_fields) {
        $custom_fields = $this->get_custom_fields();
        
        foreach ($custom_fields as $field) {
            $meta_fields['ms_custom_' . $field['id']] = array(
                'label' => $field['label'],
                'type' => $field['type'],
                'icon' => 'fas fa-cog'
            );
        }
        
        return $meta_fields;
    }
    
    /**
     * Get custom fields
     */
    public function get_custom_fields() {
        $default_fields = $this->get_default_custom_fields();
        $custom_fields = get_option('ms_custom_fields_config', array());
        
        return array_merge($default_fields, $custom_fields);
    }
    
    /**
     * Get default custom fields
     */
    private function get_default_custom_fields() {
        return array(
            'property_amenities' => array(
                'id' => 'property_amenities',
                'label' => 'وسائل الراحة',
                'type' => 'multiselect',
                'required' => false,
                'default' => array(),
                'description' => 'اختر وسائل الراحة المتوفرة',
                'options' => array(
                    array('value' => 'parking', 'label' => 'موقف سيارات'),
                    array('value' => 'gym', 'label' => 'صالة رياضية'),
                    array('value' => 'pool', 'label' => 'مسبح'),
                    array('value' => 'security', 'label' => 'أمن'),
                    array('value' => 'elevator', 'label' => 'مصعد'),
                    array('value' => 'garden', 'label' => 'حديقة')
                )
            ),
            'nearby_facilities' => array(
                'id' => 'nearby_facilities',
                'label' => 'المرافق القريبة',
                'type' => 'multiselect',
                'required' => false,
                'default' => array(),
                'description' => 'المرافق القريبة من العقار',
                'options' => array(
                    array('value' => 'school', 'label' => 'مدرسة'),
                    array('value' => 'hospital', 'label' => 'مستشفى'),
                    array('value' => 'mall', 'label' => 'مول'),
                    array('value' => 'park', 'label' => 'حديقة عامة'),
                    array('value' => 'metro', 'label' => 'محطة مترو'),
                    array('value' => 'bus', 'label' => 'محطة حافلات')
                )
            ),
            'year_built' => array(
                'id' => 'year_built',
                'label' => 'سنة البناء',
                'type' => 'number',
                'required' => false,
                'default' => '',
                'description' => 'سنة بناء العقار'
            ),
            'last_renovation' => array(
                'id' => 'last_renovation',
                'label' => 'آخر تجديد',
                'type' => 'date',
                'required' => false,
                'default' => '',
                'description' => 'تاريخ آخر تجديد للعقار'
            ),
            'property_condition' => array(
                'id' => 'property_condition',
                'label' => 'حالة العقار',
                'type' => 'select',
                'required' => false,
                'default' => 'good',
                'description' => 'حالة العقار الحالية',
                'options' => array(
                    array('value' => 'excellent', 'label' => 'ممتاز'),
                    array('value' => 'good', 'label' => 'جيد'),
                    array('value' => 'fair', 'label' => 'متوسط'),
                    array('value' => 'needs_renovation', 'label' => 'يحتاج تجديد')
                )
            ),
            'furnished' => array(
                'id' => 'furnished',
                'label' => 'مفروش',
                'type' => 'checkbox',
                'required' => false,
                'default' => '0',
                'description' => 'هل العقار مفروش؟'
            ),
            'pet_friendly' => array(
                'id' => 'pet_friendly',
                'label' => 'يسمح بالحيوانات الأليفة',
                'type' => 'checkbox',
                'required' => false,
                'default' => '0',
                'description' => 'هل يسمح بتربية الحيوانات الأليفة؟'
            ),
            'min_lease_period' => array(
                'id' => 'min_lease_period',
                'label' => 'أقل فترة إيجار',
                'type' => 'number',
                'required' => false,
                'default' => '12',
                'description' => 'أقل فترة إيجار بالأشهر'
            ),
            'utilities_included' => array(
                'id' => 'utilities_included',
                'label' => 'المرافق المشمولة',
                'type' => 'multiselect',
                'required' => false,
                'default' => array(),
                'description' => 'المرافق المشمولة في الإيجار',
                'options' => array(
                    array('value' => 'electricity', 'label' => 'الكهرباء'),
                    array('value' => 'water', 'label' => 'المياه'),
                    array('value' => 'gas', 'label' => 'الغاز'),
                    array('value' => 'internet', 'label' => 'الإنترنت'),
                    array('value' => 'cable', 'label' => 'التلفزيون')
                )
            )
        );
    }
    
    /**
     * Get saved custom values
     */
    private function get_saved_custom_values($post_id) {
        $custom_fields = $this->get_custom_fields();
        $values = array();
        
        foreach ($custom_fields as $field) {
            $values[$field['id']] = get_post_meta($post_id, 'ms_custom_' . $field['id'], true);
        }
        
        return $values;
    }
    
    /**
     * Add custom field via AJAX
     */
    public function add_custom_field() {
        check_ajax_referer('ms_add_custom_field', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('error' => 'ليس لديك الصلاحية'));
        }
        
        $field_data = array(
            'id' => sanitize_title($_POST['field_name']),
            'label' => sanitize_text_field($_POST['field_label']),
            'type' => sanitize_text_field($_POST['field_type']),
            'required' => isset($_POST['field_required']) ? 1 : 0,
            'default' => sanitize_text_field($_POST['field_default']),
            'description' => sanitize_textarea_field($_POST['field_description'])
        );
        
        if (in_array($field_data['type'], array('select', 'multiselect', 'radio'))) {
            $options = array();
            if (isset($_POST['field_options'])) {
                $option_lines = explode("\n", $_POST['field_options']);
                foreach ($option_lines as $line) {
                    $parts = explode('|', $line);
                    if (count($parts) == 2) {
                        $options[] = array(
                            'value' => sanitize_text_field($parts[0]),
                            'label' => sanitize_text_field($parts[1])
                        );
                    }
                }
            }
            $field_data['options'] = $options;
        }
        
        $custom_fields = get_option('ms_custom_fields_config', array());
        $custom_fields[$field_data['id']] = $field_data;
        update_option('ms_custom_fields_config', $custom_fields);
        
        wp_send_json_success(array('field' => $field_data));
    }
    
    /**
     * Delete custom field via AJAX
     */
    public function delete_custom_field() {
        check_ajax_referer('ms_delete_custom_field', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('error' => 'ليس لديك الصلاحية'));
        }
        
        $field_id = sanitize_text_field($_POST['field_id']);
        
        $custom_fields = get_option('ms_custom_fields_config', array());
        if (isset($custom_fields[$field_id])) {
            unset($custom_fields[$field_id]);
            update_option('ms_custom_fields_config', $custom_fields);
            wp_send_json_success();
        } else {
            wp_send_json_error(array('error' => 'الحقل غير موجود'));
        }
    }
}

// Initialize custom fields manager - skip during installation
if (!defined('WP_INSTALLING') || !WP_INSTALLING) {
    new MS_Custom_Fields_Manager();
}
