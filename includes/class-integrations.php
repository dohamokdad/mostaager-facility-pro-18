<?php
/**
 * Additional Integrations - Mostaager Facility PRO Add-On
 */

if (!defined('ABSPATH')) {
    exit;
}

class MS_Additional_Integrations {
    
    public function __construct() {
        // WhatsApp integration
        add_action('wp_ajax_ms_send_whatsapp', array($this, 'send_whatsapp_message'));
        
        // Email integration
        add_action('wp_ajax_ms_send_email', array($this, 'send_email_notification'));
        
        // Payment gateway integration
        add_action('wp_ajax_ms_process_payment', array($this, 'process_payment'));
        
        // Backup integration
        add_action('wp_ajax_ms_create_backup', array($this, 'create_backup'));
        add_action('wp_ajax_ms_restore_backup', array($this, 'restore_backup'));
    }
    
    /**
     * Send WhatsApp message
     */
    public function send_whatsapp_message() {
        check_ajax_referer('ms_send_whatsapp', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('error' => 'ليس لديك الصلاحية'), 403);
        }
        
        $phone = sanitize_text_field($_POST['phone']);
        $message = sanitize_textarea_field($_POST['message']);
        
        // Check if Mostaager WhatsApp integration is available
        if (class_exists('Mostager_WhatsApp_Integration')) {
            $whatsapp = new Mostager_WhatsApp_Integration();
            $result = $whatsapp->send_message($phone, $message);
            
            if ($result['success']) {
                wp_send_json_success(array('message' => 'تم إرسال الرسالة بنجاح'));
            } else {
                wp_send_json_error(array('error' => $result['error']));
            }
        } else {
            // Fallback: Open WhatsApp web
            $encoded_message = urlencode($message);
            $formatted_phone = preg_replace('/[^0-9]/', '', $phone);
            $whatsapp_url = "https://wa.me/$formatted_phone?text=$encoded_message";
            
            wp_send_json_success(array(
                'message' => 'فتح WhatsApp',
                'url' => $whatsapp_url
            ));
        }
    }
    
    /**
     * Send email notification
     */
    public function send_email_notification() {
        check_ajax_referer('ms_send_email', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('error' => 'ليس لديك الصلاحية'), 403);
        }
        
        $to = sanitize_email($_POST['to']);
        $subject = sanitize_text_field($_POST['subject']);
        $message = wp_kses_post($_POST['message']);
        $template = isset($_POST['template']) ? sanitize_text_field($_POST['template']) : 'default';
        
        // Apply template
        $message = $this->apply_email_template($template, $message);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_option('blogname') . ' <' . get_option('admin_email') . '>'
        );
        
        $result = wp_mail($to, $subject, $message, $headers);
        
        if ($result) {
            wp_send_json_success(array('message' => 'تم إرسال البريد الإلكتروني بنجاح'));
        } else {
            wp_send_json_error(array('error' => 'فشل إرسال البريد الإلكتروني'));
        }
    }
    
    /**
     * Apply email template
     */
    private function apply_email_template($template, $content) {
        $templates = array(
            'default' => $this->get_default_email_template(),
            'notification' => $this->get_notification_email_template(),
            'invoice' => $this->get_invoice_email_template()
        );
        
        $template_html = isset($templates[$template]) ? $templates[$template] : $templates['default'];
        
        return str_replace('{content}', $content, $template_html);
    }
    
    /**
     * Get default email template
     */
    private function get_default_email_template() {
        return '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: #0073aa; color: white; padding: 20px; text-align: center;">
                <h2 style="margin: 0;">' . get_option('blogname') . '</h2>
            </div>
            <div style="padding: 20px; background: #f9f9f9;">
                {content}
            </div>
            <div style="text-align: center; padding: 20px; color: #666; font-size: 12px;">
                <p>تم إرسال هذه الرسالة من ' . get_option('blogname') . '</p>
                <p><a href="' . home_url() . '">' . home_url() . '</a></p>
            </div>
        </div>';
    }
    
    /**
     * Get notification email template
     */
    private function get_notification_email_template() {
        return '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: #46b450; color: white; padding: 20px; text-align: center;">
                <h2 style="margin: 0;">🔔 إشعار جديد</h2>
            </div>
            <div style="padding: 20px; background: #f9f9f9;">
                {content}
            </div>
            <div style="text-align: center; padding: 20px; color: #666; font-size: 12px;">
                <p>تم إرسال هذه الرسالة من ' . get_option('blogname') . '</p>
            </div>
        </div>';
    }
    
    /**
     * Get invoice email template
     */
    private function get_invoice_email_template() {
        return '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: #0073aa; color: white; padding: 20px; text-align: center;">
                <h2 style="margin: 0;">💰 فاتورة جديدة</h2>
            </div>
            <div style="padding: 20px; background: #f9f9f9;">
                {content}
            </div>
            <div style="text-align: center; padding: 20px; color: #666; font-size: 12px;">
                <p>تم إرسال هذه الرسالة من ' . get_option('blogname') . '</p>
            </div>
        </div>';
    }
    
    /**
     * Process payment
     */
    public function process_payment() {
        check_ajax_referer('ms_process_payment', 'nonce');
        
        $invoice_id = intval($_POST['invoice_id']);
        $payment_method = sanitize_text_field($_POST['payment_method']);
        $amount = floatval($_POST['amount']);
        
        // Get invoice details
        global $wpdb;
        $invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ms_invoices WHERE id = %d",
            $invoice_id
        ));
        
        if (!$invoice) {
            wp_send_json_error(array('error' => 'الفاتورة غير موجودة'));
        }

        if (!current_user_can('manage_options') && intval($invoice->user_id) !== get_current_user_id()) {
            wp_send_json_error(array('error' => 'غير مصرح لك بدفع هذه الفاتورة'), 403);
        }

        $invoice_amount = max(0, floatval($invoice->amount));
        $paid_amount = max(0, floatval($invoice->paid_amount ?? 0));
        $remaining_amount = max(0, $invoice_amount - $paid_amount);
        if ($amount <= 0 || ($remaining_amount > 0 && $amount > $remaining_amount)) {
            wp_send_json_error(array('error' => 'مبلغ الدفع غير صالح'), 422);
        }
        
        // Process payment based on method
        switch ($payment_method) {
            case 'telr':
                $result = $this->process_telr_payment($invoice, $amount);
                break;
            case 'stripe':
                $result = $this->process_stripe_payment($invoice, $amount);
                break;
            case 'paypal':
                $result = $this->process_paypal_payment($invoice, $amount);
                break;
            case 'cash':
                $result = $this->process_cash_payment($invoice, $amount);
                break;
            default:
                $result = array('success' => false, 'error' => 'طريقة الدفع غير مدعومة');
        }
        
        if ($result['success']) {
            // Update invoice status
            $wpdb->update(
                $wpdb->prefix . 'ms_invoices',
                array(
                    'status' => 'paid',
                    'paid_amount' => $amount,
                    'paid_date' => current_time('mysql')
                ),
                array('id' => $invoice_id)
            );
            
            // Send confirmation email
            $this->send_payment_confirmation($invoice, $amount);
            
            wp_send_json_success(array('message' => 'تم معالجة الدفع بنجاح'));
        } else {
            wp_send_json_error(array('error' => $result['error']));
        }
    }
    
    /**
     * Process Telr payment
     */
    private function process_telr_payment($invoice, $amount) {
        // Placeholder for Telr integration
        return array('success' => false, 'error' => 'تكامل Telr يتطلب إعداد API');
    }
    
    /**
     * Process Stripe payment
     */
    private function process_stripe_payment($invoice, $amount) {
        // Placeholder for Stripe integration
        return array('success' => false, 'error' => 'تكامل Stripe يتطلب إعداد API');
    }
    
    /**
     * Process PayPal payment
     */
    private function process_paypal_payment($invoice, $amount) {
        // Placeholder for PayPal integration
        return array('success' => false, 'error' => 'تكامل PayPal يتطلب إعداد API');
    }
    
    /**
     * Process cash payment
     */
    private function process_cash_payment($invoice, $amount) {
        // Cash payment doesn't need external processing
        return array('success' => true);
    }
    
    /**
     * Send payment confirmation
     */
    private function send_payment_confirmation($invoice, $amount) {
        $user = get_user_by('id', $invoice->user_id);
        
        if ($user) {
            $subject = 'تأكيد الدفع - فاتورة #' . $invoice->id;
            $message = "
            <p>مرحباً {$user->display_name},</p>
            <p>تم استلام دفعك بنجاح:</p>
            <ul>
                <li>رقم الفاتورة: {$invoice->id}</li>
                <li>المبلغ: {$amount} EGP</li>
                <li>تاريخ الدفع: " . current_time('mysql') . "</li>
            </ul>
            <p>شكراً لتعاملكم معنا.</p>
            ";
            
            wp_mail($user->user_email, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
        }
    }
    
    /**
     * Create backup
     */
    public function create_backup() {
        check_ajax_referer('ms_create_backup', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('error' => 'ليس لديك الصلاحية'));
        }
        
        $backup_data = $this->collect_backup_data();
        
        $backup_file = $this->save_backup_file($backup_data);
        
        wp_send_json_success(array(
            'message' => 'تم إنشاء النسخة الاحتياطية بنجاح',
            'file' => $backup_file,
            'size' => filesize($backup_file)
        ));
    }
    
    /**
     * Collect backup data
     */
    private function collect_backup_data() {
        global $wpdb;
        
        return array(
            'properties' => $this->backup_properties(),
            'buildings' => $this->backup_buildings(),
            'tenants' => $this->backup_tenants(),
            'invoices' => $this->backup_invoices(),
            'settings' => $this->backup_settings(),
            'metadata' => array(
                'backup_date' => current_time('mysql'),
                'version' => MOSTAAGER_ADDON_VERSION,
                'wordpress_version' => get_bloginfo('version'),
                'php_version' => phpversion()
            )
        );
    }
    
    /**
     * Backup properties
     */
    private function backup_properties() {
        $properties = get_posts(array(
            'post_type' => 'property',
            'posts_per_page' => -1,
            'post_status' => 'any'
        ));
        
        $backup = array();
        
        foreach ($properties as $property) {
            $backup[] = array(
                'id' => $property->ID,
                'title' => $property->post_title,
                'content' => $property->post_content,
                'status' => $property->post_status,
                'meta' => get_post_meta($property->ID)
            );
        }
        
        return $backup;
    }
    
    /**
     * Backup buildings
     */
    private function backup_buildings() {
        global $wpdb;
        
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ms_buildings", ARRAY_A);
    }
    
    /**
     * Backup tenants
     */
    private function backup_tenants() {
        global $wpdb;
        
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ms_unit_tenants", ARRAY_A);
    }
    
    /**
     * Backup invoices
     */
    private function backup_invoices() {
        global $wpdb;
        
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ms_invoices", ARRAY_A);
    }
    
    /**
     * Backup settings
     */
    private function backup_settings() {
        return array(
            'ms_import_settings' => get_option('ms_import_settings'),
            'ms_custom_fields_config' => get_option('ms_custom_fields_config'),
            'ms_custom_templates' => get_option('ms_custom_templates')
        );
    }
    
    /**
     * Save backup file
     */
    private function save_backup_file($backup_data) {
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/ms-backups';
        
        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }
        
        $filename = 'ms-backup-' . date('Y-m-d-H-i-s') . '.json';
        $filepath = $backup_dir . '/' . $filename;
        
        file_put_contents($filepath, json_encode($backup_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        return $filepath;
    }
    
    /**
     * Restore backup
     */
    public function restore_backup() {
        check_ajax_referer('ms_restore_backup', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('error' => 'ليس لديك الصلاحية'));
        }
        
        $backup_file = sanitize_text_field($_POST['backup_file']);
        
        if (!file_exists($backup_file)) {
            wp_send_json_error(array('error' => 'ملف النسخة الاحتياطية غير موجود'));
        }
        
        $backup_data = json_decode(file_get_contents($backup_file), true);
        
        if (!$backup_data) {
            wp_send_json_error(array('error' => 'ملف النسخة الاحتياطية تالف'));
        }
        
        $results = $this->restore_from_backup($backup_data);
        
        wp_send_json_success(array(
            'message' => 'تم استعادة النسخة الاحتياطية بنجاح',
            'results' => $results
        ));
    }
    
    /**
     * Restore from backup
     */
    private function restore_from_backup($backup_data) {
        $results = array();
        
        // Restore settings
        if (isset($backup_data['settings'])) {
            foreach ($backup_data['settings'] as $key => $value) {
                update_option($key, $value);
            }
            $results['settings'] = 'success';
        }
        
        // Restore buildings
        if (isset($backup_data['buildings'])) {
            $results['buildings'] = $this->restore_buildings($backup_data['buildings']);
        }
        
        // Restore tenants
        if (isset($backup_data['tenants'])) {
            $results['tenants'] = $this->restore_tenants($backup_data['tenants']);
        }
        
        // Restore invoices
        if (isset($backup_data['invoices'])) {
            $results['invoices'] = $this->restore_invoices($backup_data['invoices']);
        }
        
        return $results;
    }
    
    /**
     * Restore buildings
     */
    private function restore_buildings($buildings) {
        global $wpdb;
        
        foreach ($buildings as $building) {
            $wpdb->replace(
                $wpdb->prefix . 'ms_buildings',
                $building
            );
        }
        
        return 'success';
    }
    
    /**
     * Restore tenants
     */
    private function restore_tenants($tenants) {
        global $wpdb;
        
        foreach ($tenants as $tenant) {
            $wpdb->replace(
                $wpdb->prefix . 'ms_unit_tenants',
                $tenant
            );
        }
        
        return 'success';
    }
    
    /**
     * Restore invoices
     */
    private function restore_invoices($invoices) {
        global $wpdb;
        
        foreach ($invoices as $invoice) {
            $wpdb->replace(
                $wpdb->prefix . 'ms_invoices',
                $invoice
            );
        }
        
        return 'success';
    }
}

// Initialize additional integrations - skip during installation
if (!defined('WP_INSTALLING') || !WP_INSTALLING) {
    new MS_Additional_Integrations();
}
