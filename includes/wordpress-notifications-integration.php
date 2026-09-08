<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress Notifications Integration
 * Integrates Mostaager notifications with WordPress notification system
 */

// Notification categories for Mostaager
add_action('init', 'ms_register_notification_taxonomies');

function ms_register_notification_taxonomies() {
    // Register notification type taxonomy (for categorizing notifications)
    if (!taxonomy_exists('ms_notification_type')) {
        register_taxonomy('ms_notification_type', 'user', [
            'labels' => [
                'name' => 'أنواع الإشعارات',
                'singular_name' => 'نوع الإشعار',
                'search_items' => 'بحث في الأنواع',
                'all_items' => 'جميع الأنواع',
                'edit_item' => 'تعديل النوع',
                'update_item' => 'تحديث النوع',
                'add_new_item' => 'إضافة نوع جديد',
                'new_item_name' => 'اسم النوع الجديد',
                'menu_name' => 'أنواع الإشعارات',
            ],
            'hierarchical' => true,
            'show_ui' => false,
            'show_in_menu' => false,
            'query_var' => true,
            'rewrite' => false,
        ]);
    }
    
    // Create default notification types
    $default_types = [
        'invoice' => 'فواتير',
        'maintenance' => 'صيانة',
        'discussion' => 'مناقشات',
        'payment' => 'مدفوعات',
        'wallet' => 'محفظة',
        'system' => 'نظام',
    ];
    
    foreach ($default_types as $slug => $name) {
        if (!term_exists($slug, 'ms_notification_type')) {
            wp_insert_term($name, 'ms_notification_type', ['slug' => $slug]);
        }
    }
}

// Override ms_add_notification to use WordPress notifications
if (!function_exists('ms_add_notification')) {
    function ms_add_notification($user_id, $message, $type = 'system', $reference_id = 0, $building_id = 0) {
        // First, try to use WordPress notifications if available
        if (function_exists('wp_notify_postauthor') || class_exists('WP_Internal_Pointers')) {
            // Store notification as user meta for compatibility
            $notifications = get_user_meta($user_id, 'ms_notifications', true);
            if (!is_array($notifications)) {
                $notifications = [];
            }
            
            $notification = [
                'id' => uniqid('notif_'),
                'message' => $message,
                'type' => $type,
                'reference_id' => $reference_id,
                'building_id' => $building_id,
                'created_at' => current_time('mysql'),
                'is_read' => 0,
            ];
            
            array_unshift($notifications, $notification);
            
            // Keep only last 100 notifications
            if (count($notifications) > 100) {
                $notifications = array_slice($notifications, 0, 100);
            }
            
            update_user_meta($user_id, 'ms_notifications', $notifications);
            
            // Also trigger WordPress notification if applicable
            do_action('ms_notification_added', $user_id, $notification);
            
            return true;
        }
        
        // Fallback to old database method
        global $wpdb;
        $table = $wpdb->prefix . 'ms_notifications';
        
        $wpdb->insert($table, [
            'user_id' => $user_id,
            'type' => $type,
            'message' => $message,
            'building_id' => $building_id,
            'reference_id' => $reference_id,
            'is_read' => 0,
            'created_at' => current_time('mysql'),
        ]);
        
        return $wpdb->insert_id;
    }
}

// Get notifications using WordPress user meta
if (!function_exists('ms_get_user_notifications')) {
    function ms_get_user_notifications($user_id, $limit = 50) {
        $notifications = get_user_meta($user_id, 'ms_notifications', true);
        
        if (!is_array($notifications)) {
            // Fallback to database
            global $wpdb;
            $table = $wpdb->prefix . 'ms_notifications';
            $notifications = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
                $user_id,
                $limit
            ));
        } else {
            // Convert array to objects for compatibility
            $notifications = array_map(function($n) {
                return (object) $n;
            }, array_slice($notifications, 0, $limit));
        }
        
        return $notifications;
    }
}

// Mark notification as read
if (!function_exists('ms_mark_notification_read')) {
    function ms_mark_notification_read($user_id, $notification_id) {
        $notifications = get_user_meta($user_id, 'ms_notifications', true);
        
        if (is_array($notifications)) {
            foreach ($notifications as &$notif) {
                if ($notif['id'] === $notification_id) {
                    $notif['is_read'] = 1;
                    break;
                }
            }
            update_user_meta($user_id, 'ms_notifications', $notifications);
            return true;
        }
        
        // Fallback to database
        global $wpdb;
        $table = $wpdb->prefix . 'ms_notifications';
        $wpdb->update($table, ['is_read' => 1], [
            'user_id' => $user_id,
            'id' => $notification_id,
        ]);
        
        return true;
    }
}

// Get unread notification count
if (!function_exists('ms_get_unread_notification_count')) {
    function ms_get_unread_notification_count($user_id) {
        $notifications = get_user_meta($user_id, 'ms_notifications', true);
        
        if (is_array($notifications)) {
            return count(array_filter($notifications, function($n) {
                return intval($n['is_read']) === 0;
            }));
        }
        
        // Fallback to database
        global $wpdb;
        $table = $wpdb->prefix . 'ms_notifications';
        return intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND is_read = 0",
            $user_id
        )));
    }
}

// Add notification badge to admin bar
add_action('admin_bar_menu', 'ms_add_notification_badge', 100);

function ms_add_notification_badge($wp_admin_bar) {
    if (!is_user_logged_in()) {
        return;
    }
    
    $user_id = get_current_user_id();
    $unread_count = ms_get_unread_notification_count($user_id);
    
    if ($unread_count > 0) {
        $wp_admin_bar->add_node([
            'id' => 'ms-notifications',
            'title' => '<span class="ab-icon"></span><span class="ab-label">' . $unread_count . '</span>',
            'href' => home_url('/building-dashboard/#notifications'),
            'meta' => [
                'title' => 'لديك ' . $unread_count . ' إشعار غير مقروء',
                'class' => 'ms-notification-badge',
            ],
        ]);
    }
}

// Add CSS for notification badge
add_action('admin_head', 'ms_notification_badge_css');
add_action('wp_head', 'ms_notification_badge_css');

function ms_notification_badge_css() {
    ?>
    <style>
        #wpadminbar .ms-notification-badge .ab-label {
            background: #dc2626;
            color: #fff;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 11px;
            margin-left: 5px;
        }
        #wpadminbar .ms-notification-badge:hover .ab-label {
            background: #b91c1c;
        }
    </style>
    <?php
}

// Send email notification for important events
add_action('ms_notification_added', 'ms_send_email_notification', 10, 2);

function ms_send_email_notification($user_id, $notification) {
    $user = get_userdata($user_id);
    if (!$user || !$user->user_email) {
        return;
    }
    
    // Only send email for important types
    $important_types = ['invoice', 'payment', 'maintenance'];
    if (!in_array($notification['type'], $important_types)) {
        return;
    }
    
    $subject = 'إشعار جديد - ' . get_bloginfo('name');
    $message = $notification['message'];
    
    wp_mail($user->user_email, $subject, $message);
}
