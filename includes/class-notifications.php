<?php
/**
 * Multi Channel Notifications - Mostaager Facility PRO Add-On
 * Advanced notification system with templates, scheduling, and tracking
 */

if (!defined('ABSPATH')) {
    exit;
}

class MS_Multi_Channel_Notifications {
    
    private $notification_templates;
    private $notification_stats;
    
    public function __construct() {
        add_action('wp_ajax_ms_send_notification', array($this, 'send_notification_ajax'));
        add_action('wp_ajax_ms_get_notification_stats', array($this, 'get_notification_stats_ajax'));
        add_action('wp_ajax_ms_schedule_notification', array($this, 'schedule_notification_ajax'));
        add_action('wp_ajax_ms_get_notification_templates', array($this, 'get_notification_templates_ajax'));
        add_action('wp_ajax_ms_save_notification_template', array($this, 'save_notification_template_ajax'));
        
        // Initialize templates
        $this->init_notification_templates();
        
        // Initialize stats tracking
        $this->init_notification_stats();
    }
    
    /**
     * Initialize notification templates
     */
    private function init_notification_templates() {
        $this->notification_templates = array(
            'maintenance_completed' => array(
                'title' => 'إتمام الصيانة',
                'subject' => 'تم إتمال طلب الصيانة #{$request_id}',
                'message' => 'مرحباً {$name}، نود إبلاغك بأن طلب الصيانة #{$request_id} تم إتماله بنجاح. الموعد: {$date}. التكلفة: {$cost}.'
            ),
            'payment_received' => array(
                'title' => 'استلام الدفعة',
                'subject' => 'تم استلام دفعتك #{$payment_id}',
                'message' => 'مرحباً {$name}، تم استلام دفعتك #{$payment_id} بنجاح. المبلغ: {$amount}. شكراً لك.'
            ),
            'invoice_created' => array(
                'title' => 'إنشاء فاتورة',
                'subject' => 'فاتورة جديدة #{$invoice_id}',
                'message' => 'مرحباً {$name}، تم إنشاء فاتورة جديدة #{$invoice_id} بمبلغ {$amount}. تاريخ الاستحقاق: {$due_date}.'
            ),
            'maintenance_scheduled' => array(
                'title' => 'جدولة الصيانة',
                'subject' => 'تم جدولة الصيانة #{$request_id}',
                'message' => 'مرحباً {$name}، تم جدولة الصيانة #{$request_id} في تاريخ {$date}. الرجاء التأكد من تواجدك.'
            ),
            'payment_reminder' => array(
                'title' => 'تذكير بالدفع',
                'subject' => 'تذكير بالدفع للفاتورة #{$invoice_id}',
                'message' => 'مرحباً {$name}، هذا تذكير بأن الفاتورة #{$invoice_id} بمبلغ {$amount} تستحق الدفع في {$due_date}.'
            ),
            'building_announcement' => array(
                'title' => 'إعلان المبنى',
                'subject' => 'إعلان من إدارة المبنى',
                'message' => 'مرحباً {$name}، {$announcement_message}. شكراً لتعاونك.'
            )
        );
    }
    
    /**
     * Initialize notification stats tracking
     */
    private function init_notification_stats() {
        $this->notification_stats = get_option('ms_notification_stats', array(
            'total_sent' => 0,
            'email_sent' => 0,
            'sms_sent' => 0,
            'whatsapp_sent' => 0,
            'push_sent' => 0,
            'failed' => 0,
            'by_type' => array()
        ));
    }
    
    /**
     * Send notification via AJAX
     */
    public function send_notification_ajax() {
        check_ajax_referer('ms_send_notification', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'ليس لديك الصلاحية'), 403);
        }
        
        $recipient = sanitize_text_field($_POST['recipient']);
        $message = sanitize_textarea_field($_POST['message']);
        $channels = isset($_POST['channels']) ? json_decode(stripslashes($_POST['channels']), true) : array('email');
        $notification_type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'custom';
        
        $result = $this->send_notification($recipient, $message, $channels, $notification_type);
        
        wp_send_json_success($result);
    }
    
    /**
     * Get notification statistics
     */
    public function get_notification_stats_ajax() {
        check_ajax_referer('ms_notification_stats', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'ليس لديك الصلاحية'), 403);
        }
        
        $stats = $this->get_notification_stats();
        
        wp_send_json_success($stats);
    }
    
    /**
     * Schedule notification via AJAX
     */
    public function schedule_notification_ajax() {
        check_ajax_referer('ms_schedule_notification', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'ليس لديك الصلاحية'), 403);
        }
        
        $recipient = sanitize_text_field($_POST['recipient']);
        $message = sanitize_textarea_field($_POST['message']);
        $channels = isset($_POST['channels']) ? json_decode(stripslashes($_POST['channels']), true) : array('email');
        $schedule_time = sanitize_text_field($_POST['schedule_time']);
        $notification_type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'custom';
        
        $result = $this->schedule_notification($recipient, $message, $channels, $schedule_time, $notification_type);
        
        wp_send_json_success($result);
    }
    
    /**
     * Get notification templates
     */
    public function get_notification_templates_ajax() {
        check_ajax_referer('ms_notification_templates', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'ليس لديك الصلاحية'), 403);
        }
        
        wp_send_json_success($this->notification_templates);
    }
    
    /**
     * Save notification template
     */
    public function save_notification_template_ajax() {
        check_ajax_referer('ms_save_template', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'ليس لديك الصلاحية'), 403);
        }
        
        $template_id = sanitize_text_field($_POST['template_id']);
        $template_data = json_decode(stripslashes($_POST['template_data']), true);
        
        $result = $this->save_notification_template($template_id, $template_data);
        
        wp_send_json_success($result);
    }
    
    /**
     * Send notification
     */
    public function send_notification($recipient, $message, $channels, $notification_type = 'custom') {
        $results = array();
        $notification_id = $this->create_notification_record($recipient, $message, $channels, $notification_type);
        
        foreach ($channels as $channel) {
            switch ($channel) {
                case 'email':
                    $results['email'] = $this->send_email_notification($recipient, $message);
                    break;
                case 'sms':
                    $results['sms'] = $this->send_sms_notification($recipient, $message);
                    break;
                case 'whatsapp':
                    $results['whatsapp'] = $this->send_whatsapp_notification($recipient, $message);
                    break;
                case 'push':
                    $results['push'] = $this->send_push_notification($recipient, $message);
                    break;
                default:
                    $results[$channel] = array('success' => false, 'error' => 'القناة غير مدعومة');
            }
        }
        
        // Update stats
        $this->update_notification_stats($results, $notification_type);
        
        // Update notification record
        $this->update_notification_record($notification_id, $results);
        
        return array(
            'success' => $this->check_overall_success($results),
            'results' => $results,
            'notification_id' => $notification_id
        );
    }
    
    /**
     * Create notification record
     */
    private function create_notification_record($recipient, $message, $channels, $type) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ms_notifications';
        
        $wpdb->insert(
            $table_name,
            array(
                'recipient' => $recipient,
                'message' => $message,
                'channels' => json_encode($channels),
                'type' => $type,
                'status' => 'pending',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update notification record
     */
    private function update_notification_record($notification_id, $results) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ms_notifications';
        
        $status = $this->check_overall_success($results) ? 'sent' : 'failed';
        
        $wpdb->update(
            $table_name,
            array(
                'status' => $status,
                'results' => json_encode($results),
                'updated_at' => current_time('mysql')
            ),
            array('id' => $notification_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Update notification statistics
     */
    private function update_notification_stats($results, $type) {
        $any_success = false;
        
        foreach ($results as $channel => $result) {
            if (isset($result['success']) && $result['success']) {
                $channel_key = $channel . '_sent';
                if (!isset($this->notification_stats[$channel_key])) {
                    $this->notification_stats[$channel_key] = 0;
                }
                $this->notification_stats[$channel_key]++;
                $any_success = true;
            } else {
                if (!isset($this->notification_stats['failed'])) {
                    $this->notification_stats['failed'] = 0;
                }
                $this->notification_stats['failed']++;
            }
        }
        
        // Only count total_sent if at least one channel succeeded
        if ($any_success) {
            $this->notification_stats['total_sent']++;
        }
        
        if (!isset($this->notification_stats['by_type'][$type])) {
            $this->notification_stats['by_type'][$type] = 0;
        }
        $this->notification_stats['by_type'][$type]++;
        
        update_option('ms_notification_stats', $this->notification_stats);
    }
    
    /**
     * Get notification statistics
     */
    public function get_notification_stats() {
        return $this->notification_stats;
    }
    
    /**
     * Schedule notification
     */
    public function schedule_notification($recipient, $message, $channels, $schedule_time, $notification_type = 'custom') {
        $timestamp = strtotime($schedule_time);
        
        if ($timestamp <= current_time('timestamp')) {
            return array('success' => false, 'error' => 'وقت الجدولة يجب أن يكون في المستقبل');
        }
        
        $args = array(
            'recipient' => $recipient,
            'message' => $message,
            'channels' => $channels,
            'type' => $notification_type
        );
        
        $hook = 'ms_send_scheduled_notification';
        $scheduled = wp_schedule_single_event($timestamp, $hook, array($args));
        
        if (is_wp_error($scheduled)) {
            return array('success' => false, 'error' => $scheduled->get_error_message());
        }
        
        return array('success' => true, 'scheduled_time' => $schedule_time);
    }
    
    /**
     * Save notification template
     */
    public function save_notification_template($template_id, $template_data) {
        if (isset($this->notification_templates[$template_id])) {
            $this->notification_templates[$template_id] = array_merge(
                $this->notification_templates[$template_id],
                $template_data
            );
        } else {
            $this->notification_templates[$template_id] = $template_data;
        }
        
        update_option('ms_notification_templates', $this->notification_templates);
        
        return array('success' => true, 'template_id' => $template_id);
    }
    
    /**
     * Send email notification
     */
    private function send_email_notification($recipient, $message) {
        $subject = 'إشعار من ' . get_option('blogname');
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        $result = wp_mail($recipient, $subject, $message, $headers);
        
        return array('success' => $result);
    }
    
    /**
     * Send SMS notification via Twilio (or fallback if not configured)
     */
    private function send_sms_notification($recipient, $message) {
        $account_sid = get_option('ms_twilio_account_sid', '');
        $auth_token  = get_option('ms_twilio_auth_token', '');
        $from_number = get_option('ms_twilio_from_number', '');
        
        if (empty($account_sid) || empty($auth_token) || empty($from_number)) {
            return array(
                'success' => false,
                'error'   => 'Twilio غير مُعدّ. أضف بيانات API في الإعدادات المتقدمة.'
            );
        }
        
        // Format phone number (ensure + prefix)
        $to_number = preg_replace('/[^0-9+]/', '', $recipient);
        if (substr($to_number, 0, 1) !== '+') {
            $to_number = '+' . $to_number;
        }
        
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$account_sid}/Messages.json";
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode("{$account_sid}:{$auth_token}"),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ),
            'body' => array(
                'To'   => $to_number,
                'From' => $from_number,
                'Body' => $message,
            ),
            'timeout' => 15,
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $http_code = wp_remote_retrieve_response_code($response);
        
        if ($http_code === 201 && isset($body['sid'])) {
            return array('success' => true, 'message_sid' => $body['sid']);
        }
        
        $error_msg = isset($body['message']) ? $body['message'] : 'خطأ في إرسال SMS';
        return array('success' => false, 'error' => $error_msg);
    }
    
    /**
     * Send WhatsApp notification
     */
    private function send_whatsapp_notification($recipient, $message) {
        if (class_exists('Mostager_WhatsApp_Integration')) {
            $whatsapp = new Mostager_WhatsApp_Integration();
            return $whatsapp->send_message($recipient, $message);
        }
        
        // Fallback: open WhatsApp web
        $encoded_message = urlencode($message);
        $formatted_phone = preg_replace('/[^0-9]/', '', $recipient);
        $whatsapp_url = "https://wa.me/$formatted_phone?text=$encoded_message";
        
        return array('success' => true, 'url' => $whatsapp_url);
    }
    
    /**
     * Send push notification via Firebase Cloud Messaging (FCM)
     * $recipient here should be an FCM device token or topic name
     */
    private function send_push_notification($recipient, $message) {
        $fcm_server_key = get_option('ms_fcm_server_key', '');
        $fcm_sender_id  = get_option('ms_fcm_sender_id', '');
        
        if (empty($fcm_server_key)) {
            return array(
                'success' => false,
                'error'   => 'Firebase (FCM) غير مُعدّ. أضف Server Key في الإعدادات المتقدمة.'
            );
        }
        
        $title = get_option('blogname') . ' - إشعار جديد';
        
        // Support both device token and topic
        $is_topic = (strpos($recipient, '/topics/') === 0);
        $target_key = $is_topic ? 'to' : 'registration_ids';
        $target_value = $is_topic ? $recipient : array($recipient);
        
        $payload = array(
            $target_key  => $target_value,
            'notification' => array(
                'title' => $title,
                'body'  => $message,
                'icon'  => 'notification_icon',
                'sound' => 'default',
            ),
            'data' => array(
                'message'   => $message,
                'timestamp' => current_time('timestamp'),
            ),
        );
        
        $response = wp_remote_post('https://fcm.googleapis.com/fcm/send', array(
            'headers' => array(
                'Authorization' => 'key=' . $fcm_server_key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => json_encode($payload),
            'timeout' => 15,
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $http_code = wp_remote_retrieve_response_code($response);
        
        if ($http_code === 200 && isset($body['success']) && $body['success'] > 0) {
            return array('success' => true, 'fcm_response' => $body);
        }
        
        $error_msg = isset($body['error']) ? $body['error'] : 'خطأ في إرسال إشعار Push';
        return array('success' => false, 'error' => $error_msg, 'fcm_response' => $body);
    }
    
    /**
     * Check overall success
     */
    private function check_overall_success($results) {
        foreach ($results as $result) {
            if ($result['success']) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Render notification dashboard
     */
    public function render_notification_dashboard() {
        ob_start();
        ?>
        <div class="ms-notification-dashboard">
            <div class="ms-dashboard-header">
                <h2>نظام الإشعارات المتقدم</h2>
                <p>إدارة وإرسال الإشعارات عبر قنوات متعددة</p>
            </div>
            
            <div class="ms-notification-stats">
                <div class="ms-stat-card">
                    <div class="ms-stat-icon">📧</div>
                    <div class="ms-stat-info">
                        <h3><?php echo $this->notification_stats['email_sent']; ?></h3>
                        <p>إشعارات البريد الإلكتروني</p>
                    </div>
                </div>
                
                <div class="ms-stat-card">
                    <div class="ms-stat-icon">📱</div>
                    <div class="ms-stat-info">
                        <h3><?php echo $this->notification_stats['sms_sent']; ?></h3>
                        <p>إشعارات SMS</p>
                    </div>
                </div>
                
                <div class="ms-stat-card">
                    <div class="ms-stat-icon">💬</div>
                    <div class="ms-stat-info">
                        <h3><?php echo $this->notification_stats['whatsapp_sent']; ?></h3>
                        <p>إشعارات WhatsApp</p>
                    </div>
                </div>
                
                <div class="ms-stat-card">
                    <div class="ms-stat-icon">🔔</div>
                    <div class="ms-stat-info">
                        <h3><?php echo $this->notification_stats['push_sent']; ?></h3>
                        <p>إشعارات Push</p>
                    </div>
                </div>
                
                <div class="ms-stat-card">
                    <div class="ms-stat-icon">❌</div>
                    <div class="ms-stat-info">
                        <h3><?php echo $this->notification_stats['failed']; ?></h3>
                        <p>فشل الإرسال</p>
                    </div>
                </div>
            </div>
            
            <div class="ms-notification-forms">
                <div class="ms-form-section">
                    <h3>إرسال إشعار جديد</h3>
                    <form id="ms-send-notification-form">
                        <?php wp_nonce_field('ms_send_notification', 'nonce'); ?>
                        
                        <div class="ms-form-group">
                            <label>المستلم</label>
                            <input type="text" name="recipient" required placeholder="البريد الإلكتروني أو رقم الهاتف">
                        </div>
                        
                        <div class="ms-form-group">
                            <label>نوع الإشعار</label>
                            <select name="type" id="ms-notification-type">
                                <option value="custom">مخصص</option>
                                <?php foreach ($this->notification_templates as $key => $template): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $template['title']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="ms-form-group">
                            <label>الرسالة</label>
                            <textarea name="message" rows="5" required></textarea>
                        </div>
                        
                        <div class="ms-form-group">
                            <label>القنوات</label>
                            <div class="ms-checkbox-group">
                                <label><input type="checkbox" name="channels[]" value="email" checked> البريد الإلكتروني</label>
                                <label><input type="checkbox" name="channels[]" value="sms"> SMS</label>
                                <label><input type="checkbox" name="channels[]" value="whatsapp"> WhatsApp</label>
                                <label><input type="checkbox" name="channels[]" value="push"> Push</label>
                            </div>
                        </div>
                        
                        <button type="submit" class="ms-btn ms-btn-primary">إرسال الإشعار</button>
                    </form>
                </div>
                
                <div class="ms-form-section">
                    <h3>جدولة إشعار</h3>
                    <form id="ms-schedule-notification-form">
                        <?php wp_nonce_field('ms_schedule_notification', 'nonce'); ?>
                        
                        <div class="ms-form-group">
                            <label>المستلم</label>
                            <input type="text" name="recipient" required placeholder="البريد الإلكتروني أو رقم الهاتف">
                        </div>
                        
                        <div class="ms-form-group">
                            <label>وقت الجدولة</label>
                            <input type="datetime-local" name="schedule_time" required>
                        </div>
                        
                        <div class="ms-form-group">
                            <label>الرسالة</label>
                            <textarea name="message" rows="5" required></textarea>
                        </div>
                        
                        <div class="ms-form-group">
                            <label>القنوات</label>
                            <div class="ms-checkbox-group">
                                <label><input type="checkbox" name="channels[]" value="email" checked> البريد الإلكتروني</label>
                                <label><input type="checkbox" name="channels[]" value="sms"> SMS</label>
                                <label><input type="checkbox" name="channels[]" value="whatsapp"> WhatsApp</label>
                                <label><input type="checkbox" name="channels[]" value="push"> Push</label>
                            </div>
                        </div>
                        
                        <button type="submit" class="ms-btn ms-btn-secondary">جدولة الإشعار</button>
                    </form>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
