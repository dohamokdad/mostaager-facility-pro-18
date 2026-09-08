<?php
if (!defined('ABSPATH')) exit;

function ms_render_action_center($role = '')
{
    if (!is_user_logged_in()) return '';
    global $wpdb;
    $user_id = get_current_user_id();
    $items = array();
    $invoice_table = $wpdb->prefix . 'ms_invoices';
    $pending_invoices = 0;
    if ($role === 'agent' && function_exists('ms_get_agent_invoices')) {
        $agent_scope_invoices = ms_get_agent_invoices($user_id, 200, '');
        foreach ((array) $agent_scope_invoices as $agent_invoice) {
            if (in_array(strtolower((string) ($agent_invoice->status ?? '')), array('pending', 'overdue'), true)) {
                $pending_invoices++;
            }
        }
    } else {
        $pending_invoices = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$invoice_table} WHERE user_id = %d AND status IN ('pending','overdue')", $user_id));
    }
    if ($pending_invoices > 0) $items[] = array('label' => 'فواتير تحتاج مراجعة', 'count' => $pending_invoices, 'tab' => 'invoices', 'tone' => 'warning');

    if ($role === 'owner') {
        $owner_buildings = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}ms_buildings WHERE manager_id = %d OR id IN (SELECT building_id FROM {$wpdb->prefix}ms_units WHERE owner_id = %d)", $user_id, $user_id));
        $ids = array_values(array_filter(array_map('absint', (array) $owner_buildings)));
        if ($ids) {
            $csv = implode(',', $ids);
            $maintenance_table = $wpdb->prefix . 'ms_maintenance_requests';
            $open = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$maintenance_table} WHERE building_id IN ({$csv}) AND status IN ('open','in_progress')");
            if ($open > 0) $items[] = array('label' => 'طلبات صيانة مفتوحة', 'count' => $open, 'tab' => 'maintenance', 'tone' => 'info');
        }
    } elseif ($role === 'agent') {
        $maintenance_table = $wpdb->prefix . 'ms_maintenance_requests';
        $open = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$maintenance_table} WHERE status IN ('open','in_progress') AND (assigned_to = %d OR unit_id IN (SELECT id FROM {$wpdb->prefix}ms_units WHERE agent_id = %d))", $user_id, $user_id));
        if ($open > 0) $items[] = array('label' => 'طلبات صيانة تحتاج متابعة', 'count' => $open, 'tab' => 'maintenance', 'tone' => 'info');
    }

    ob_start();
    ?>
    <section class="ms-action-center" aria-labelledby="ms-action-center-title">
        <div class="ms-action-center__header"><div><p class="ms-eyebrow">الأولوية الآن</p><h2 id="ms-action-center-title">يتطلب إجراءً</h2></div><span><?php echo count($items); ?> عناصر</span></div>
        <?php if (!$items) : ?><div class="ms-action-center__empty">لا توجد مهام عاجلة. أحسنت، كل شيء تحت السيطرة.</div><?php else : ?>
            <div class="ms-action-center__grid">
                <?php foreach ($items as $item) : ?><button type="button" class="ms-action-item ms-action-item--<?php echo esc_attr($item['tone']); ?>" data-tab-target="<?php echo esc_attr($item['tab']); ?>"><span><strong><?php echo esc_html($item['label']); ?></strong><small>افتح القسم للمراجعة والمتابعة</small></span><b><?php echo absint($item['count']); ?></b></button><?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php
    return ob_get_clean();
}

add_shortcode('ms_action_center_owner', function () { return ms_render_action_center('owner'); });
add_shortcode('ms_action_center_agent', function () { return ms_render_action_center('agent'); });
