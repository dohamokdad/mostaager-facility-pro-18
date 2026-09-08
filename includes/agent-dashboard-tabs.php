<?php
if (!defined('ABSPATH')) exit;

function msfp_agent_unit_ids($user_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'ms_units';
    return array_map('absint', (array) $wpdb->get_col($wpdb->prepare("SELECT id FROM {$table} WHERE agent_id = %d", absint($user_id))));
}

function msfp_agent_render_maintenance($user_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'ms_maintenance_requests';
    $unit_ids = msfp_agent_unit_ids($user_id);
    $where = array('m.manager_id = %d OR m.assigned_to = %d');
    $params = array(absint($user_id), absint($user_id));
    if ($unit_ids) { $where[] = 'm.unit_id IN (' . implode(',', array_map('absint', $unit_ids)) . ')'; }
    $sql = "SELECT m.*, b.title AS building_title FROM {$table} m LEFT JOIN {$wpdb->prefix}ms_buildings b ON b.id=m.building_id WHERE (" . implode(' OR ', $where) . ") ORDER BY m.created_at DESC LIMIT 100";
    $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params));
    $groups = array('active' => array(), 'completed' => array(), 'cancelled' => array());
    foreach ((array) $rows as $row) {
        $status = sanitize_key($row->status ?? 'open');
        if (in_array($status, array('cancelled','canceled','rejected','deleted'), true)) $groups['cancelled'][] = $row;
        elseif (in_array($status, array('completed','complete','closed','done'), true)) $groups['completed'][] = $row;
        else $groups['active'][] = $row;
    }
    ob_start(); ?>
    <section class="msfp-agent-tab-panel" dir="rtl"><div class="msfp-agent-tab-heading"><div><span class="msfp-eyebrow">إدارة المرافق</span><h2>طلبات الصيانة</h2><p>تابع الطلبات النشطة والمنتهية والملغية من مكان واحد.</p></div><div class="msfp-tab-stat"><strong><?php echo count($rows); ?></strong><span>إجمالي الطلبات</span></div></div>
    <div class="msfp-status-sections">
    <?php foreach (array('active'=>'الصيانات النشطة','completed'=>'الصيانات المنتهية','cancelled'=>'الصيانات الملغية') as $key=>$label) : ?><div class="msfp-maintenance-section"><div class="msfp-section-title"><h3><?php echo esc_html($label); ?></h3><span><?php echo count($groups[$key]); ?></span></div><?php if (!$groups[$key]) : ?><div class="msfp-empty-row">لا توجد طلبات في هذا القسم.</div><?php else : ?><div class="msfp-maintenance-list"><?php foreach ($groups[$key] as $row) : ?><article class="msfp-maintenance-row"><div><strong><?php echo esc_html($row->title ?: 'طلب صيانة'); ?></strong><small><?php echo esc_html($row->building_title ?: 'مبنى غير محدد'); ?> · <?php echo esc_html($row->created_at); ?></small></div><div class="msfp-maintenance-row__right"><b><?php echo esc_html(number_format_i18n((float) $row->cost, 2)); ?> ج.م</b><span class="msfp-status-pill msfp-status-<?php echo esc_attr($key); ?>"><?php echo esc_html($row->status); ?></span></div></article><?php endforeach; ?></div><?php endif; ?></div><?php endforeach; ?></div></section>
    <?php return ob_get_clean();
}

function msfp_agent_render_invoices($user_id) {
    $user_id = absint($user_id);
    $rows = function_exists('ms_get_agent_invoices') ? ms_get_agent_invoices($user_id, 100, 'subscription') : array();
    $renewal_cancelled = (bool) get_user_meta($user_id, 'ms_agent_renewal_cancelled', true);
    $renewal_nonce = wp_create_nonce('ms_agent_renewal_action');
    ob_start(); ?>
    <section class="msfp-agent-tab-panel" dir="rtl"><div class="msfp-agent-tab-heading"><div><span class="msfp-eyebrow">المدفوعات</span><h2>فواتير الاشتراك</h2><p>هذه الفواتير تخص الاشتراك الأول أو التجديد أو ترقية الباقة فقط، ولا تشمل فواتير الإيجار أو الصيانة.</p></div><div class="msfp-tab-stat"><strong><?php echo count($rows); ?></strong><span>فاتورة اشتراك</span></div></div>
    <div class="ms-renewal-control" data-renewal-state="<?php echo $renewal_cancelled ? 'cancelled' : 'active'; ?>" style="margin:16px 0;padding:14px 16px;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;"><div><strong>التجديد التلقائي</strong><p style="margin:5px 0 0;color:#64748b;font-size:13px;"><?php echo $renewal_cancelled ? 'تم إلغاء التجديد. يبقى اشتراكك فعالًا حتى تاريخ انتهائه.' : 'التجديد مفعّل. يمكنك إلغاؤه مع بقاء اشتراكك فعالًا حتى تاريخ الانتهاء.'; ?></p><span class="ms-renewal-status" style="font-weight:700;color:<?php echo $renewal_cancelled ? '#b91c1c' : '#15803d'; ?>;">الحالة: <?php echo $renewal_cancelled ? 'ملغى' : 'مفعّل'; ?></span></div><button type="button" class="ms-renewal-toggle" data-renewal-action="<?php echo $renewal_cancelled ? 'restore' : 'cancel'; ?>" data-renewal-nonce="<?php echo esc_attr($renewal_nonce); ?>" style="border:1px solid <?php echo $renewal_cancelled ? '#16a34a' : '#dc2626'; ?>;background:#fff;color:<?php echo $renewal_cancelled ? '#15803d' : '#b91c1c'; ?>;padding:9px 14px;border-radius:8px;cursor:pointer;"><?php echo $renewal_cancelled ? 'إعادة تفعيل التجديد' : 'إلغاء التجديد التلقائي'; ?></button></div>
    <?php if (!$rows) : ?><div class="msfp-empty-row">لا توجد فواتير اشتراك حاليًا.</div><?php else : ?><div class="msfp-invoice-list"><?php foreach ($rows as $row) : $status = sanitize_key($row->status); ?><div class="msfp-invoice-row"><div><strong>#<?php echo intval($row->id); ?></strong><span><?php echo esc_html($row->description); ?></span><small><?php echo esc_html($row->created_at); ?></small></div><div><b><?php echo esc_html(number_format_i18n((float) $row->amount, 2)); ?> ج.م</b><span class="msfp-status-pill msfp-invoice-<?php echo esc_attr($status); ?>"><?php echo esc_html($status === 'paid' ? 'مدفوعة' : ($status === 'canceled' ? 'ملغاة' : 'معلقة')); ?></span><?php if ($status !== 'paid' && $status !== 'canceled') : ?><button class="msfp-pay-subscription" data-invoice-id="<?php echo intval($row->id); ?>" data-payment-nonce="<?php echo esc_attr(wp_create_nonce('ms_pay_invoice_' . intval($row->id))); ?>">دفع الفاتورة</button><?php endif; ?></div></div><?php endforeach; ?></div><?php endif; ?></section>
    <?php return ob_get_clean();
}

function msfp_agent_render_discussions($user_id) {
    global $wpdb;
    $unit_ids = msfp_agent_unit_ids($user_id);
    $building_ids = $unit_ids ? array_map('absint', (array) $wpdb->get_col("SELECT DISTINCT building_id FROM {$wpdb->prefix}ms_units WHERE id IN (" . implode(',', array_map('absint', $unit_ids)) . ")")) : array();
    $property_ids = array();
    $agent_properties = function_exists('ms_get_properties_by_agent') ? ms_get_properties_by_agent($user_id) : array();
    foreach ((array) $agent_properties as $property) {
        $property_ids[] = absint(is_object($property) ? ($property->ID ?? $property->post_id ?? 0) : (is_array($property) ? ($property['ID'] ?? $property['post_id'] ?? 0) : $property));
    }
    $property_ids = array_values(array_filter(array_unique($property_ids)));
    $meta_query = array('relation'=>'OR', array('key'=>'ms_agent_id','value'=>absint($user_id),'compare'=>'='), array('key'=>'agent_id','value'=>absint($user_id),'compare'=>'='));
    if ($property_ids) {
        $meta_query[] = array('key'=>'property_id','value'=>$property_ids,'compare'=>'IN');
        $meta_query[] = array('key'=>'ms_property_id','value'=>$property_ids,'compare'=>'IN');
    }
    if ($unit_ids) $meta_query[] = array('key'=>'unit_id','value'=>$unit_ids,'compare'=>'IN');
    if ($building_ids) {
        $meta_query[] = array('key'=>'building_id','value'=>$building_ids,'compare'=>'IN');
        $meta_query[] = array('key'=>'ms_building_id','value'=>$building_ids,'compare'=>'IN');
    }
    $posts = get_posts(array('post_type'=>array('ms_discussion','discussions'),'post_status'=>array('publish','private'),'posts_per_page'=>50,'orderby'=>'date','order'=>'DESC','meta_query'=>$meta_query));
    ob_start(); ?><section class="msfp-agent-tab-panel" dir="rtl"><div class="msfp-agent-tab-heading"><div><span class="msfp-eyebrow">التواصل</span><h2>المناقشات</h2><p>مواضيع التواصل المرتبطة بعقاراتك أو مشاركاتك.</p></div></div><?php if (!$posts) : ?><div class="msfp-empty-row">لا توجد مناقشات مرتبطة بحسابك حتى الآن.</div><?php else : ?><div class="msfp-discussion-list"><?php foreach ($posts as $post) : ?><a class="msfp-discussion-row" href="<?php echo esc_url(get_permalink($post)); ?>" target="_blank" rel="noopener"><div><strong><?php echo esc_html(get_the_title($post)); ?></strong><small><?php echo esc_html(get_the_date('Y-m-d H:i',$post)); ?></small></div><span>فتح المناقشة ←</span></a><?php endforeach; ?></div><?php endif; ?></section><?php return ob_get_clean();
}

function msfp_agent_render_analytics($user_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'ms_analytics_events';
    $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    $rows = $table_exists ? $wpdb->get_results($wpdb->prepare("SELECT event_name, COUNT(*) AS total FROM {$table} WHERE user_id=%d AND created_at >= DATE_SUB(%s, INTERVAL 30 DAY) GROUP BY event_name ORDER BY total DESC", absint($user_id), current_time('mysql')), ARRAY_A) : array();
    $total = 0; foreach ((array)$rows as $r) $total += intval($r['total']);
    $event_labels = array(
        'dashboard_tab_open' => 'فتح تبويب من لوحة التحكم',
        'notification_open' => 'فتح إشعار',
        'invoice_payment_start' => 'بدء دفع فاتورة',
        'invoice_payment_complete' => 'إتمام دفع فاتورة',
        'property_create_start' => 'بدء إضافة عقار',
        'property_create_complete' => 'إتمام إضافة عقار',
        'agent_form_step' => 'التفاعل مع نموذج العقار',
        'maintenance_filter_apply' => 'تصفية طلبات الصيانة',
    );
    ob_start(); ?><section class="msfp-agent-tab-panel" dir="rtl"><div class="msfp-agent-tab-heading"><div><span class="msfp-eyebrow">قياس الأداء</span><h2>التحليلات</h2><p>ملخص تفاعل حسابك خلال آخر 30 يومًا.</p></div></div><div class="msfp-analytics-cards"><div><strong><?php echo $total; ?></strong><span>إجمالي الأحداث</span></div><div><strong><?php echo count($rows); ?></strong><span>أنواع التفاعل</span></div><div><strong>30</strong><span>يومًا</span></div></div><?php if (!$rows) : ?><div class="msfp-empty-row">لم تُسجل أحداث تفاعل بعد. افتح التبويبات أو ابدأ إجراءً لتظهر البيانات.</div><?php else : ?><div class="msfp-analytics-list"><?php foreach ($rows as $r) : ?><div><span><?php echo esc_html($event_labels[$r['event_name']] ?? 'نشاط'); ?></span><b><?php echo intval($r['total']); ?></b></div><?php endforeach; ?></div><?php endif; ?></section><?php return ob_get_clean();
}

function msfp_agent_tab_html($tab, $user_id) {
    switch (sanitize_key($tab)) {
        case 'maintenance': return msfp_agent_render_maintenance($user_id);
        case 'invoices': return msfp_agent_render_invoices($user_id);
        case 'discussions': return msfp_agent_render_discussions($user_id);
        case 'analytics': return msfp_agent_render_analytics($user_id);
        case 'profile':
            $profile_html = function_exists('ms_render_unified_profile') ? ms_render_unified_profile() : '<div class="msfp-empty-row">تعذر تحميل الملف الشخصي.</div>';
            $preferences_html = shortcode_exists('ms_notification_preferences') ? do_shortcode('[ms_notification_preferences]') : '';
            return '<div class="msfp-profile-tab" dir="rtl">' . $preferences_html . $profile_html . '</div>';
    }
    return '';
}

add_action('wp_ajax_ms_get_agent_tab', function () {
    if (!is_user_logged_in() || !check_ajax_referer('mostaager-ajax-nonce','security',false)) wp_send_json_error('forbidden',403);
    $user = wp_get_current_user();
    if (!function_exists('ms_user_can_view_dashboard') || !ms_user_can_view_dashboard($user->ID,'agent')) wp_send_json_error('forbidden',403);
    $tab = isset($_POST['tab']) ? sanitize_key(wp_unslash($_POST['tab'])) : '';
    $allowed = array('maintenance','invoices','discussions','analytics','profile');
    if (!in_array($tab,$allowed,true)) wp_send_json_error('invalid_tab',400);
    wp_send_json_success(array('html'=>msfp_agent_tab_html($tab,$user->ID)));
});
