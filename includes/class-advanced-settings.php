<?php
/**
 * Advanced Settings - Mostaager Facility PRO Add-On
 * Comprehensive settings management with validation and presets
 */

if (!defined('ABSPATH')) {
    exit;
}

class MS_Advanced_Settings {
    
    private $settings_sections;
    private $settings_defaults;
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_menu'));
        add_action('wp_ajax_ms_save_settings', array($this, 'save_settings'));
        add_action('wp_ajax_ms_reset_settings', array($this, 'reset_settings'));
        add_action('wp_ajax_ms_export_settings', array($this, 'export_settings'));
        add_action('wp_ajax_ms_import_settings', array($this, 'import_settings'));
        add_action('wp_ajax_ms_get_settings', array($this, 'get_settings'));
        
        // Initialize settings
        $this->init_settings_sections();
        $this->init_settings_defaults();
    }
    
    /**
     * Initialize settings sections
     */
    private function init_settings_sections() {
        $this->settings_sections = array(
            'general' => array(
                'title' => 'الإعدادات العامة',
                'icon' => '⚙️',
                'description' => 'الإعدادات الأساسية للنظام',
                'fields' => array(
                    'site_name' => array(
                        'type' => 'text',
                        'label' => 'اسم الموقع',
                        'default' => get_option('blogname'),
                        'description' => 'اسم موقع إدارة المرافق'
                    ),
                    'site_description' => array(
                        'type' => 'textarea',
                        'label' => 'وصف الموقع',
                        'default' => get_option('blogdescription'),
                        'description' => 'وصف موقع إدارة المرافق'
                    ),
                    'admin_email' => array(
                        'type' => 'email',
                        'label' => 'البريد الإلكتروني للمدير',
                        'default' => get_option('admin_email'),
                        'description' => 'البريد الإلكتروني للمدير العام'
                    ),
                    'timezone' => array(
                        'type' => 'select',
                        'label' => 'المنطقة الزمنية',
                        'default' => get_option('timezone_string'),
                        'options' => $this->get_timezone_options(),
                        'description' => 'المنطقة الزمنية للموقع'
                    ),
                    'date_format' => array(
                        'type' => 'select',
                        'label' => 'تنسيق التاريخ',
                        'default' => 'Y-m-d',
                        'options' => array(
                            'Y-m-d' => 'YYYY-MM-DD',
                            'd/m/Y' => 'DD/MM/YYYY',
                            'm/d/Y' => 'MM/DD/YYYY',
                            'd M Y' => 'DD Mon YYYY'
                        ),
                        'description' => 'تنسيق عرض التاريخ'
                    ),
                    'time_format' => array(
                        'type' => 'select',
                        'label' => 'تنسيق الوقت',
                        'default' => 'H:i',
                        'options' => array(
                            'H:i' => '24 Hours',
                            'h:i A' => '12 Hours'
                        ),
                        'description' => 'تنسيق عرض الوقت'
                    ),
                    'language' => array(
                        'type' => 'select',
                        'label' => 'اللغة',
                        'default' => 'ar',
                        'options' => array(
                            'ar' => 'العربية',
                            'en' => 'English'
                        ),
                        'description' => 'لغة واجهة النظام'
                    ),
                    'currency' => array(
                        'type' => 'select',
                        'label' => 'العملة',
                        'default' => 'SAR',
                        'options' => array(
                            'SAR' => 'ريال سعودي',
                            'EGP' => 'جنيه مصري',
                            'AED' => 'درهم إماراتي',
                            'USD' => 'دولار أمريكي'
                        ),
                        'description' => 'العملة المستخدمة في النظام'
                    )
                )
            ),
            'notifications' => array(
                'title' => 'إعدادات الإشعارات',
                'icon' => '🔔',
                'description' => 'إعدادات نظام الإشعارات',
                'fields' => array(
                    'email_notifications' => array(
                        'type' => 'checkbox',
                        'label' => 'تفعيل إشعارات البريد الإلكتروني',
                        'default' => true,
                        'description' => 'إرسال الإشعارات عبر البريد الإلكتروني'
                    ),
                    'sms_notifications' => array(
                        'type' => 'checkbox',
                        'label' => 'تفعيل إشعارات SMS',
                        'default' => false,
                        'description' => 'إرسال الإشعارات عبر رسائل SMS'
                    ),
                    'whatsapp_notifications' => array(
                        'type' => 'checkbox',
                        'label' => 'تفعيل إشعارات WhatsApp',
                        'default' => true,
                        'description' => 'إرسال الإشعارات عبر WhatsApp'
                    ),
                    'push_notifications' => array(
                        'type' => 'checkbox',
                        'label' => 'تفعيل الإشعارات Push',
                        'default' => false,
                        'description' => 'إرسال الإشعارات Push'
                    ),
                    'notification_frequency' => array(
                        'type' => 'select',
                        'label' => 'تكرار الإشعارات',
                        'default' => 'immediate',
                        'options' => array(
                            'immediate' => 'فوري',
                            'hourly' => 'كل ساعة',
                            'daily' => 'يومي',
                            'weekly' => 'أسبوعي'
                        ),
                        'description' => 'تكرار إرسال الإشعارات'
                    ),
                    'sms_gateway' => array(
                        'type'        => 'select',
                        'label'       => 'بوابة SMS',
                        'default'     => 'twilio',
                        'options'     => array(
                            'twilio' => 'Twilio',
                            'nexmo'  => 'Vonage (Nexmo)',
                        ),
                        'description' => 'اختر مزود خدمة SMS'
                    ),
                    'twilio_account_sid' => array(
                        'type'        => 'text',
                        'label'       => 'Twilio Account SID',
                        'option_name' => 'ms_twilio_account_sid',
                        'default'     => '',
                        'description' => 'معرّف حساب Twilio - احصل عليه من twilio.com/console'
                    ),
                    'twilio_auth_token' => array(
                        'type'        => 'password',
                        'label'       => 'Twilio Auth Token',
                        'option_name' => 'ms_twilio_auth_token',
                        'default'     => '',
                        'description' => 'رمز مصادقة Twilio'
                    ),
                    'twilio_from_number' => array(
                        'type'        => 'text',
                        'label'       => 'رقم هاتف Twilio',
                        'option_name' => 'ms_twilio_from_number',
                        'default'     => '',
                        'description' => 'رقم الهاتف المرسل من Twilio (مثال: +12345678901)'
                    ),
                    'fcm_server_key' => array(
                        'type'        => 'password',
                        'label'       => 'Firebase FCM Server Key',
                        'option_name' => 'ms_fcm_server_key',
                        'default'     => '',
                        'description' => 'مفتاح خادم Firebase Cloud Messaging للإشعارات Push'
                    ),
                    'fcm_sender_id' => array(
                        'type'        => 'text',
                        'label'       => 'Firebase Sender ID',
                        'option_name' => 'ms_fcm_sender_id',
                        'default'     => '',
                        'description' => 'معرّف المرسل في Firebase'
                    )
                )
            ),

            'security' => array(
                'title' => 'إعدادات الأمان',
                'icon' => '🔒',
                'description' => 'إعدادات حماية النظام',
                'fields' => array(
                    'enable_2fa' => array(
                        'type' => 'checkbox',
                        'label' => 'تفعيل المصادقة الثنائية',
                        'default' => false,
                        'description' => 'تفعيل المصادقة الثنائية للمستخدمين'
                    ),
                    'session_timeout' => array(
                        'type' => 'number',
                        'label' => 'مهلة الجلسة (دقائق)',
                        'default' => 30,
                        'description' => 'مدة الجلسة قبل تسجيل الخروج التلقائي'
                    ),
                    'max_login_attempts' => array(
                        'type' => 'number',
                        'label' => 'محاولات تسجيل الدخول القصوى',
                        'default' => 5,
                        'description' => 'عدد محاولات تسجيل الدخول قبل الحظر'
                    ),
                    'password_min_length' => array(
                        'type' => 'number',
                        'label' => 'الحد الأدنى لطول كلمة المرور',
                        'default' => 8,
                        'description' => 'الحد الأدنى لعدد أحرف كلمة المرور'
                    ),
                    'require_strong_password' => array(
                        'type' => 'checkbox',
                        'label' => 'طلب كلمة مرور قوية',
                        'default' => true,
                        'description' => 'طلب كلمات مرور قوية تحتوي على أحرف وأرقام ورموز'
                    ),
                    'ip_whitelist' => array(
                        'type' => 'textarea',
                        'label' => 'القائمة البيضاء للعناوين IP',
                        'default' => '',
                        'description' => 'عناوين IP المسموح بها (عنوان في كل سطر)'
                    ),
                    'ip_blacklist' => array(
                        'type' => 'textarea',
                        'label' => 'القائمة السوداء للعناوين IP',
                        'default' => '',
                        'description' => 'عناوين IP المحظورة (عنوان في كل سطر)'
                    )
                )
            ),
            'maintenance' => array(
                'title' => 'إعدادات الصيانة',
                'icon' => '🛠️',
                'description' => 'إعدادات نظام الصيانة',
                'fields' => array(
                    'auto_assign_maintenance' => array(
                        'type' => 'checkbox',
                        'label' => 'تعيين تلقائي لطلبات الصيانة',
                        'default' => true,
                        'description' => 'تعيين تلقائي لطلبات الصيانة للفنيين'
                    ),
                    'maintenance_priority_levels' => array(
                        'type' => 'select',
                        'label' => 'مستويات أولوية الصيانة',
                        'default' => '3',
                        'options' => array(
                            '2' => 'مستويان (عالي، منخفض)',
                            '3' => '3 مستويات (عالي، متوسط، منخفض)',
                            '4' => '4 مستويات (عالي، متوسط، منخفض، طارئ)',
                            '5' => '5 مستويات (عالي جداً، عالي، متوسط، منخفض، طارئ)'
                        ),
                        'description' => 'عدد مستويات أولوية الصيانة'
                    ),
                    'maintenance_sla_hours' => array(
                        'type' => 'number',
                        'label' => 'SLA للصيانة (ساعات)',
                        'default' => 24,
                        'description' => 'الوقت المستهدف للرد على طلبات الصيانة'
                    ),
                    'auto_close_maintenance_days' => array(
                        'type' => 'number',
                        'label' => 'إغلاق تلقائي للصيانة (أيام)',
                        'default' => 7,
                        'description' => 'إغلاق تلقائي لطلبات الصيانة بعد عدد الأيام'
                    ),
                    'maintenance_reminder_hours' => array(
                        'type' => 'number',
                        'label' => 'تذكير الصيانة (ساعات)',
                        'default' => 4,
                        'description' => 'إرسال تذكير قبل موعد الصيانة'
                    )
                )
            ),
            'financial' => array(
                'title' => 'إعدادات مالية',
                'icon' => '💰',
                'description' => 'إعدادات النظام المالي',
                'fields' => array(
                    'invoice_prefix' => array(
                        'type' => 'text',
                        'label' => 'بادئة الفاتورة',
                        'default' => 'INV-',
                        'description' => 'بادئة أرقام الفواتير'
                    ),
                    'invoice_start_number' => array(
                        'type' => 'number',
                        'label' => 'رقم بداية الفاتورة',
                        'default' => 1001,
                        'description' => 'رقم بداية تسلسل الفواتير'
                    ),
                    'payment_terms_days' => array(
                        'type' => 'number',
                        'label' => 'شروط الدفع (أيام)',
                        'default' => 30,
                        'description' => 'عدد الأيام لدفع الفاتورة'
                    ),
                    'late_fee_percentage' => array(
                        'type' => 'number',
                        'label' => 'نسبة رسوم التأخير',
                        'default' => 5,
                        'description' => 'نسبة رسوم التأخير عن الدفع'
                    ),
                    'tax_rate' => array(
                        'type' => 'number',
                        'label' => 'نسبة الضريبة',
                        'default' => 15,
                        'description' => 'نسبة الضريبة المضافة'
                    ),
                    'enable_automatic_invoicing' => array(
                        'type' => 'checkbox',
                        'label' => 'تفعيل الفوترة التلقائية',
                        'default' => true,
                        'description' => 'إنشاء فواتير تلقائياً للرسومات الدورية'
                    ),
                    'payment_gateway' => array(
                        'type' => 'select',
                        'label' => 'بوابة الدفع',
                        'default' => 'telr',
                        'options' => array(
                            'telr' => 'Telr',
                            'stripe' => 'Stripe',
                            'paypal' => 'PayPal',
                            'manual' => 'يدوي'
                        ),
                        'description' => 'بوابة الدفع الافتراضية'
                    )
                )
            ),
            'integrations' => array(
                'title' => 'التكاملات',
                'icon' => '🔗',
                'description' => 'إعدادات التكامل مع الخدمات الخارجية',
                'fields' => array(
                    'enable_houzez_integration' => array(
                        'type' => 'checkbox',
                        'label' => 'تفعيل تكامل Houzez',
                        'default' => true,
                        'description' => 'تفعيل التكامل مع قالب Houzez'
                    ),
                    'enable_woocommerce_integration' => array(
                        'type' => 'checkbox',
                        'label' => 'تفعيل تكامل WooCommerce',
                        'default' => true,
                        'description' => 'تفعيل التكامل مع WooCommerce'
                    ),
                    'enable_whatsapp_integration' => array(
                        'type' => 'checkbox',
                        'label' => 'تفعيل تكامل WhatsApp',
                        'default' => true,
                        'description' => 'تفعيل التكامل مع WhatsApp API'
                    ),
                    'enable_sms_integration' => array(
                        'type' => 'checkbox',
                        'label' => 'تفعيل تكامل SMS',
                        'default' => false,
                        'description' => 'تفعيل التكامل مع SMS API'
                    ),
                    'api_key' => array(
                        'type' => 'password',
                        'label' => 'مفتاح API',
                        'default' => '',
                        'description' => 'مفتاح API للتكاملات الخارجية'
                    ),
                    'webhook_url' => array(
                        'type' => 'text',
                        'label' => 'رابط Webhook',
                        'default' => '',
                        'description' => 'رابط Webhook للإشعارات الخارجية'
                    )
                )
            ),
            'performance' => array(
                'title' => 'إعدادات الأداء',
                'icon' => '⚡',
                'description' => 'إعدادات تحسين أداء النظام',
                'fields' => array(
                    'enable_caching' => array(
                        'type' => 'checkbox',
                        'label' => 'تفعيل التخزين المؤقت',
                        'default' => true,
                        'description' => 'تفعيل التخزين المؤقت لتحسين الأداء'
                    ),
                    'cache_duration' => array(
                        'type' => 'number',
                        'label' => 'مدة التخزين المؤقت (دقائق)',
                        'default' => 60,
                        'description' => 'مدة التخزين المؤقت للبيانات'
                    ),
                    'enable_lazy_loading' => array(
                        'type' => 'checkbox',
                        'label' => 'تفعيل التحميل البطيء',
                        'default' => true,
                        'description' => 'تفعيل التحميل البطيء للصور والمحتوى'
                    ),
                    'enable_compression' => array(
                        'type' => 'checkbox',
                        'label' => 'تفعيل الضغط',
                        'default' => true,
                        'description' => 'تفعيل ضغط الملفات'
                    ),
                    'max_upload_size' => array(
                        'type' => 'number',
                        'label' => 'حجم الرفع الأقصى (MB)',
                        'default' => 10,
                        'description' => 'الحد الأقصى لحجم الملفات المرفوعة'
                    ),
                    'enable_performance_monitoring' => array(
                        'type' => 'checkbox',
                        'label' => 'تفعيل مراقبة الأداء',
                        'default' => true,
                        'description' => 'تفعيل مراقبة أداء النظام'
                    )
                )
            )
        );
    }
    
    /**
     * Initialize settings defaults
     */
    private function init_settings_defaults() {
        $this->settings_defaults = array();
        
        foreach ($this->settings_sections as $section_key => $section) {
            foreach ($section['fields'] as $field_key => $field) {
                $this->settings_defaults[$section_key . '_' . $field_key] = $field['default'];
            }
        }
    }
    
    /**
     * Get timezone options
     */
    private function get_timezone_options() {
        $timezones = array();
        $timezone_identifiers = DateTimeZone::listIdentifiers();
        
        foreach ($timezone_identifiers as $identifier) {
            $timezones[$identifier] = $identifier;
        }
        
        return $timezones;
    }
    
    /**
     * Add settings menu
     */
    public function add_settings_menu() {
        add_submenu_page(
            'options-general.php',
            'الإعدادات المتقدمة',
            'الإعدادات المتقدمة',
            'manage_options',
            'ms-advanced-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        $settings_sections = $this->settings_sections;
        $current_settings = $this->get_all_settings();
        
        include dirname(__FILE__) . '/../admin/views/advanced-settings.php';
    }
    
    /**
     * Get all settings
     */
    private function get_all_settings() {
        $settings = array();
        
        foreach ($this->settings_defaults as $key => $default) {
            $settings[$key] = get_option('ms_' . $key, $default);
        }
        
        return $settings;
    }

    private function require_settings_capability() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'ليس لديك الصلاحية'), 403);
        }
    }
    
    /**
     * Save settings via AJAX
     */
    public function save_settings() {
        check_ajax_referer('ms_save_settings', 'nonce');
        $this->require_settings_capability();
        
        $section = sanitize_text_field($_POST['section']);
        $settings_data = json_decode(stripslashes($_POST['settings']), true);
        
        // Validate settings
        $validated_data = $this->validate_settings($section, $settings_data);
        
        if (is_wp_error($validated_data)) {
            wp_send_json_error(array('message' => $validated_data->get_error_message()));
        }
        
        // Save settings
        foreach ($validated_data as $key => $value) {
            update_option('ms_' . $section . '_' . $key, $value);
        }
        
        // Clear cache
        $this->clear_settings_cache();
        
        wp_send_json_success(array(
            'message' => 'تم حفظ الإعدادات بنجاح',
            'section' => $section
        ));
    }
    
    /**
     * Validate settings
     */
    private function validate_settings($section, $data) {
        $errors = new WP_Error();
        $validated = array();
        
        if (!isset($this->settings_sections[$section])) {
            $errors->add('invalid_section', 'قسم الإعدادات غير صالح');
            return $errors;
        }
        
        $section_config = $this->settings_sections[$section];
        
        foreach ($data as $key => $value) {
            if (!isset($section_config['fields'][$key])) {
                continue; // Skip unknown fields
            }
            
            $field_config = $section_config['fields'][$key];
            
            // Type-specific validation
            switch ($field_config['type']) {
                case 'email':
                    if (!empty($value) && !is_email($value)) {
                        $errors->add('invalid_email', 'البريد الإلكتروني غير صالح: ' . $key);
                    }
                    break;
                case 'number':
                    if (!is_numeric($value)) {
                        $errors->add('invalid_number', 'القيمة يجب أن تكون رقم: ' . $key);
                    }
                    $value = floatval($value);
                    break;
                case 'checkbox':
                    $value = (bool) $value;
                    break;
                case 'select':
                    if (!isset($field_config['options'][$value])) {
                        $errors->add('invalid_option', 'الخيار غير صالح: ' . $key);
                    }
                    break;
            }
            
            $validated[$key] = $value;
        }
        
        if ($errors->has_errors()) {
            return $errors;
        }
        
        return $validated;
    }
    
    /**
     * Reset settings
     */
    public function reset_settings() {
        check_ajax_referer('ms_reset_settings', 'nonce');
        $this->require_settings_capability();
        
        $section = sanitize_text_field($_POST['section']);
        
        if (!isset($this->settings_sections[$section])) {
            wp_send_json_error(array('message' => 'قسم الإعدادات غير صالح'));
        }
        
        // Reset to defaults
        foreach ($this->settings_sections[$section]['fields'] as $key => $field) {
            delete_option('ms_' . $section . '_' . $key);
        }
        
        // Clear cache
        $this->clear_settings_cache();
        
        wp_send_json_success(array(
            'message' => 'تم إعادة تعيين الإعدادات بنجاح',
            'section' => $section
        ));
    }
    
    /**
     * Export settings
     */
    public function export_settings() {
        check_ajax_referer('ms_export_settings', 'nonce');
        $this->require_settings_capability();
        
        $settings = $this->get_all_settings();
        
        $filename = 'mostaager_settings_' . date('Y-m-d') . '.json';
        $filepath = wp_upload_dir()['path'] . '/' . $filename;
        
        file_put_contents($filepath, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        wp_send_json_success(array(
            'download_url' => wp_upload_dir()['url'] . '/' . $filename,
            'filename' => $filename
        ));
    }
    
    /**
     * Import settings
     */
    public function import_settings() {
        check_ajax_referer('ms_import_settings', 'nonce');
        $this->require_settings_capability();
        
        $settings_data = json_decode(stripslashes($_POST['settings']), true);
        
        if (!$settings_data) {
            wp_send_json_error(array('message' => 'بيانات الإعدادات غير صالحة'));
        }
        
        // Import settings
        foreach ($settings_data as $key => $value) {
            update_option('ms_' . $key, $value);
        }
        
        // Clear cache
        $this->clear_settings_cache();
        
        wp_send_json_success(array(
            'message' => 'تم استيراد الإعدادات بنجاح'
        ));
    }
    
    /**
     * Get settings via AJAX
     */
    public function get_settings() {
        check_ajax_referer('ms_get_settings', 'nonce');
        $this->require_settings_capability();
        
        $section = isset($_POST['section']) ? sanitize_text_field($_POST['section']) : '';
        
        if ($section) {
            $settings = $this->get_section_settings($section);
        } else {
            $settings = $this->get_all_settings();
        }
        
        wp_send_json_success($settings);
    }
    
    /**
     * Get section settings
     */
    private function get_section_settings($section) {
        $settings = array();
        
        if (isset($this->settings_sections[$section])) {
            foreach ($this->settings_sections[$section]['fields'] as $key => $field) {
                $settings[$key] = get_option('ms_' . $section . '_' . $key, $field['default']);
            }
        }
        
        return $settings;
    }
    
    /**
     * Clear settings cache
     */
    private function clear_settings_cache() {
        wp_cache_delete('ms_settings', 'options');
        wp_cache_delete('ms_settings_sections', 'options');
    }
}