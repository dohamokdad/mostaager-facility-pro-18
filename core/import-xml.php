<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * استيراد البيانات من ملف XML التجريبي
 */

function ms_import_demo_data_from_xml($xml_file_path)
{
    if (!file_exists($xml_file_path)) {
        return array('success' => false, 'message' => 'ملف XML غير موجود');
    }

    $xml = simplexml_load_file($xml_file_path);
    if ($xml === false) {
        return array('success' => false, 'message' => 'فشل قراءة ملف XML');
    }

    global $wpdb;
    $results = array();
    $errors = array();

    // استيراد المستخدمين
    if (isset($xml->users->user)) {
        foreach ($xml->users->user as $user_data) {
            $username = (string) $user_data->username;
            $email = (string) $user_data->email;
            
            // التحقق من وجود المستخدم
            $user_id = username_exists($username);
            if (!$user_id) {
                $user_id = wp_insert_user(array(
                    'user_login' => $username,
                    'user_email' => $email,
                    'user_pass' => wp_generate_password(16, true),
                    'display_name' => (string) $user_data->display_name,
                    'role' => (string) $user_data->role,
                ));
                
                if (is_wp_error($user_id)) {
                    $errors[] = 'فشل إنشاء المستخدم: ' . $username;
                    continue;
                }
                
                $results[] = 'تم إنشاء المستخدم: ' . $username;
            } else {
                $results[] = 'المستخدم موجود بالفعل: ' . $username;
            }

            // إنشاء محفظة للمستخدم
            if ($user_id && function_exists('ms_get_or_create_user_wallet')) {
                ms_get_or_create_user_wallet($user_id);
                
                // تحديث الرصيد إذا كان محدداً
                $wallet_balance = (float) $user_data->wallet_balance;
                if ($wallet_balance > 0) {
                    $wallet_table = $wpdb->prefix . 'ms_user_wallet';
                    $wpdb->update($wallet_table, 
                        array('balance' => $wallet_balance),
                        array('user_id' => $user_id),
                        array('%f'),
                        array('%d')
                    );
                }
            }
        }
    }

    // استيراد الأبنية
    if (isset($xml->buildings->building)) {
        foreach ($xml->buildings->building as $building_data) {
            $title = (string) $building_data->title;
            $manager_id = (int) $building_data->manager_id;
            
            // التحقق من وجود المبنى
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ms_buildings WHERE title = %s",
                $title
            ));
            
            if (!$existing) {
                $wpdb->insert($wpdb->prefix . 'ms_buildings', array(
                    'title' => $title,
                    'manager_id' => $manager_id,
                    'created_at' => current_time('mysql')
                ));
                $building_id = $wpdb->insert_id;
                $results[] = 'تم إنشاء المبنى: ' . $title;
            } else {
                $building_id = $existing->id;
                $results[] = 'المبنى موجود بالفعل: ' . $title;
            }

            // إنشاء محفظة المبنى
            if ($building_id && isset($building_data->wallet)) {
                $wallet_data = $building_data->wallet;
                $wallet_table = $wpdb->prefix . 'ms_building_wallet';
                
                $existing_wallet = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM $wallet_table WHERE building_id = %d",
                    $building_id
                ));
                
                if (!$existing_wallet) {
                    $wpdb->insert($wallet_table, array(
                        'building_id' => $building_id,
                        'balance' => (float) $wallet_data->balance,
                        'target_amount' => (float) $wallet_data->target_amount,
                        'status' => (string) $wallet_data->status,
                        'created_at' => current_time('mysql')
                    ));
                    $results[] = 'تم إنشاء محفظة المبنى: ' . $title;
                }
            }
        }
    }

    // استيراد الوحدات
    if (isset($xml->units->unit)) {
        foreach ($xml->units->unit as $unit_data) {
            $building_id = (int) $unit_data->building_id;
            $unit_number = (string) $unit_data->unit_number;
            
            // التحقق من وجود الوحدة
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ms_units WHERE building_id = %d AND unit_number = %s",
                $building_id,
                $unit_number
            ));
            
            if (!$existing) {
                $wpdb->insert($wpdb->prefix . 'ms_units', array(
                    'building_id' => $building_id,
                    'unit_number' => $unit_number,
                    'floor' => (string) $unit_data->floor,
                    'owner_id' => (int) $unit_data->owner_id,
                    'tenant_id' => (int) $unit_data->tenant_id,
                    'agent_id' => (int) $unit_data->agent_id,
                    'status' => (string) $unit_data->status,
                    'created_at' => current_time('mysql')
                ));
                $unit_id = $wpdb->insert_id;
                $results[] = 'تم إنشاء الوحدة: ' . $unit_number;
            } else {
                $unit_id = $existing->id;
                $results[] = 'الوحدة موجودة بالفعل: ' . $unit_number;
            }
        }
    }

    // استيراد الفواتير
    if (isset($xml->invoices->invoice)) {
        foreach ($xml->invoices->invoice as $invoice_data) {
            $wpdb->insert($wpdb->prefix . 'ms_invoices', array(
                'user_id' => (int) $invoice_data->user_id,
                'building_id' => (int) $invoice_data->building_id,
                'unit_id' => (int) $invoice_data->unit_id,
                'property_id' => (int) $invoice_data->property_id,
                'description' => (string) $invoice_data->description,
                'amount' => (float) $invoice_data->amount,
                'status' => (string) $invoice_data->status,
                'due_date' => (string) $invoice_data->due_date,
                'paid_date' => !empty($invoice_data->paid_date) ? (string) $invoice_data->paid_date : null,
                'invoice_type' => (string) $invoice_data->invoice_type,
                'created_at' => current_time('mysql')
            ));
            $results[] = 'تم إنشاء الفاتورة: ' . $invoice_data->description;
        }
    }

    // استيراد العقود
    if (isset($xml->contracts->contract)) {
        foreach ($xml->contracts->contract as $contract_data) {
            $property_id = (int) $contract_data->property_id;
            
            // إنشاء منشور عقد
            $contract_post_id = wp_insert_post(array(
                'post_type' => 'ms_contract',
                'post_title' => (string) $contract_data->title,
                'post_content' => (string) $contract_data->file_url,
                'post_status' => 'publish',
                'post_author' => (int) $contract_data->agent_id,
            ));
            
            if ($contract_post_id && !is_wp_error($contract_post_id)) {
                // حفظ البيانات الوصفية
                update_post_meta($contract_post_id, '_ms_property_id', $property_id);
                update_post_meta($contract_post_id, '_ms_contract_type', (string) $contract_data->contract_type);
                update_post_meta($contract_post_id, '_ms_contract_file', (string) $contract_data->file_url);
                update_post_meta($contract_post_id, '_ms_security_deposit_amount', (float) $contract_data->security_deposit);
                update_post_meta($contract_post_id, '_ms_security_deposit_status', (string) $contract_data->security_deposit_status);
                update_post_meta($contract_post_id, '_ms_security_deposit_held_date', current_time('mysql'));
                
                $results[] = 'تم إنشاء العقد: ' . $contract_data->title;
            }
        }
    }

    // استيراد طلبات الصيانة
    if (isset($xml->maintenance_requests->maintenance)) {
        foreach ($xml->maintenance_requests->maintenance as $maintenance_data) {
            $wpdb->insert($wpdb->prefix . 'ms_maintenance_requests', array(
                'building_id' => (int) $maintenance_data->building_id,
                'unit_id' => (int) $maintenance_data->unit_id,
                'title' => (string) $maintenance_data->title,
                'description' => (string) $maintenance_data->description,
                'cost' => (float) $maintenance_data->cost,
                'status' => (string) $maintenance_data->status,
                'priority' => (string) $maintenance_data->priority,
                'maintenance_type' => (string) $maintenance_data->maintenance_type,
                'manager_id' => (int) $maintenance_data->manager_id,
                'start_date' => (string) $maintenance_data->start_date,
                'due_date' => (string) $maintenance_data->due_date,
                'is_recurring' => (int) $maintenance_data->is_recurring,
                'recurrence_day' => (int) $maintenance_data->recurrence_day,
                'created_at' => current_time('mysql')
            ));
            $results[] = 'تم إنشاء طلب الصيانة: ' . $maintenance_data->title;
        }
    }

    // استيراد الإشعارات
    if (isset($xml->notifications->notification)) {
        foreach ($xml->notifications->notification as $notification_data) {
            $wpdb->insert($wpdb->prefix . 'ms_notifications', array(
                'user_id' => (int) $notification_data->user_id,
                'type' => (string) $notification_data->type,
                'message' => (string) $notification_data->message,
                'building_id' => (int) $notification_data->building_id,
                'related_id' => (int) $notification_data->related_id,
                'is_read' => (int) $notification_data->is_read,
                'created_at' => current_time('mysql')
            ));
            $results[] = 'تم إنشاء الإشعار: ' . $notification_data->type;
        }
    }

    // استيراد حركات المحفظة
    if (isset($xml->wallet_transactions->transaction)) {
        foreach ($xml->wallet_transactions->transaction as $txn_data) {
            $wpdb->insert($wpdb->prefix . 'ms_wallet_transactions', array(
                'user_id' => (int) $txn_data->user_id,
                'type' => (string) $txn_data->type,
                'amount' => (float) $txn_data->amount,
                'meta' => json_encode(array(
                    'transaction_type' => (string) $txn_data->transaction_type,
                    'description' => (string) $txn_data->description,
                    'reference_id' => (int) $txn_data->reference_id
                )),
                'created_at' => current_time('mysql')
            ));
            $results[] = 'تم إنشاء حركة المحفظة: ' . $txn_data->transaction_type;
        }
    }

    return array(
        'success' => true,
        'message' => 'تم استيراد البيانات بنجاح',
        'results' => $results,
        'errors' => $errors
    );
}

/**
 * حذف جميع البيانات التجريبية
 */
function ms_delete_demo_data()
{
    global $wpdb;
    $results = array();

    // حذف الإشعارات التجريبية
    $wpdb->query("DELETE FROM {$wpdb->prefix}ms_notifications WHERE type IN ('status_change_request', 'status_change_decision', 'wallet_topup', 'invoice_due')");
    $results[] = 'تم حذف الإشعارات التجريبية';

    // حذف طلبات الصيانة التجريبية
    $wpdb->query("DELETE FROM {$wpdb->prefix}ms_maintenance_requests WHERE title LIKE '%تجريبي%'");
    $results[] = 'تم حذف طلبات الصيانة التجريبية';

    // حذف الفواتير التجريبية
    $wpdb->query("DELETE FROM {$wpdb->prefix}ms_invoices WHERE description LIKE '%تجريبي%'");
    $results[] = 'تم حذف الفواتير التجريبية';

    // حذف المستخدمين التجريبيين
    $demo_users = get_users(array('role__in' => array('owner', 'tenant', 'agent', 'building_manager'), 'search' => 'demo', 'search_columns' => array('user_login')));
    foreach ($demo_users as $user) {
        wp_delete_user($user->ID);
        $results[] = 'تم حذف المستخدم التجريبي: ' . $user->user_login;
    }

    return array(
        'success' => true,
        'message' => 'تم حذف البيانات التجريبية',
        'results' => $results
    );
}
