<?php
/**
 * Mostaager Advanced Financial Intelligence Module
 * Handles ROI calculations, financial analytics, and advanced reporting
 * 
 * @package Mostaager_Facility_Pro
 * @version 15.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Calculate ROI for a property
 */
function msfp_calculate_property_roi($property_id, $year = null)
{
    global $wpdb;
    
    $property_id = absint($property_id);
    $year = $year ? absint($year) : date('Y');
    
    // Get property details
    $property = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}posts WHERE ID = %d AND post_type = 'property'",
        $property_id
    ));
    
    if (!$property) {
        return null;
    }
    
    $property_value = (float) get_post_meta($property_id, 'property_price', true);
    
    // Calculate annual income
    $start_date = "{$year}-01-01";
    $end_date = "{$year}-12-31";
    
    $annual_income = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}ms_invoices 
         WHERE post_id = %d AND status = 'paid' 
         AND DATE(paid_date) BETWEEN %s AND %s",
        $property_id,
        $start_date,
        $end_date
    ));
    
    // Calculate annual expenses
    $annual_expenses = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}ms_expenses 
         WHERE reference_id = %d AND status IN ('approved', 'paid')
         AND DATE(expense_date) BETWEEN %s AND %s",
        $property_id,
        $start_date,
        $end_date
    ));
    
    $net_profit = $annual_income - $annual_expenses;
    $roi_percentage = $property_value > 0 ? ($net_profit / $property_value) * 100 : 0;
    
    return [
        'property_id' => $property_id,
        'property_value' => $property_value,
        'annual_income' => $annual_income,
        'annual_expenses' => $annual_expenses,
        'net_profit' => $net_profit,
        'roi_percentage' => $roi_percentage,
        'year' => $year,
    ];
}

/**
 * Get owner financial summary
 */
function msfp_get_owner_financial_summary($owner_id, $year = null)
{
    global $wpdb;
    
    $owner_id = absint($owner_id);
    $year = $year ? absint($year) : date('Y');
    
    $start_date = "{$year}-01-01";
    $end_date = "{$year}-12-31";
    
    // Get all properties owned by this user
    $properties = $wpdb->get_results($wpdb->prepare(
        "SELECT ID FROM {$wpdb->prefix}posts 
         WHERE post_type = 'property' AND post_author = %d",
        $owner_id
    ));
    
    $total_income = 0;
    $total_expenses = 0;
    $property_rois = [];
    
    foreach ($properties as $property) {
        $roi = msfp_calculate_property_roi($property->ID, $year);
        if ($roi) {
            $property_rois[] = $roi;
            $total_income += $roi['annual_income'];
            $total_expenses += $roi['annual_expenses'];
        }
    }
    
    $net_profit = $total_income - $total_expenses;
    $total_property_value = array_sum(array_column($property_rois, 'property_value'));
    $overall_roi = $total_property_value > 0 ? ($net_profit / $total_property_value) * 100 : 0;
    
    return [
        'owner_id' => $owner_id,
        'year' => $year,
        'total_income' => $total_income,
        'total_expenses' => $total_expenses,
        'net_profit' => $net_profit,
        'total_property_value' => $total_property_value,
        'overall_roi' => $overall_roi,
        'properties' => $property_rois,
    ];
}

/**
 * Generate monthly financial report
 */
function msfp_generate_monthly_report($building_id, $month = null)
{
    global $wpdb;
    
    $building_id = absint($building_id);
    $month = $month ?: current_time('Y-m');
    
    $start_date = $month . '-01';
    $end_date = date('Y-m-t', strtotime($start_date));
    
    // Income (all paid invoices)
    $income = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}ms_invoices
         WHERE building_id = %d AND status = 'paid'
         AND DATE(paid_date) BETWEEN %s AND %s",
        $building_id,
        $start_date,
        $end_date
    ));

    // Maintenance collection: the maintenance invoice stream is explicitly typed as maintenance.
    $maintenance_collected = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}ms_invoices
         WHERE building_id = %d AND invoice_type = 'maintenance' AND status = 'paid'
         AND DATE(paid_date) BETWEEN %s AND %s",
        $building_id,
        $start_date,
        $end_date
    ));

    // A withdrawal is counted in the month it was approved/processed. This is the system's
    // financial withdrawal record; the actual bank settlement remains an administrative step.
    $withdrawn_amount = 0.0;
    $withdrawal_count = 0;
    $transfer_table = $wpdb->prefix . 'ms_transfer_requests';
    $transfer_exists = $wpdb->get_var($wpdb->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
        $transfer_table
    ));
    if ($transfer_exists) {
        $withdrawn_amount = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM {$transfer_table}
             WHERE building_id = %d AND status = 'approved'
             AND DATE(COALESCE(processed_at, created_at)) BETWEEN %s AND %s",
            $building_id,
            $start_date,
            $end_date
        ));
        $withdrawal_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$transfer_table}
             WHERE building_id = %d AND status = 'approved'
             AND DATE(COALESCE(processed_at, created_at)) BETWEEN %s AND %s",
            $building_id,
            $start_date,
            $end_date
        ));
    }
    
    // Expenses by category
    $expenses_by_type = $wpdb->get_results($wpdb->prepare(
        "SELECT expense_type, SUM(amount) as total 
         FROM {$wpdb->prefix}ms_expenses 
         WHERE building_id = %d AND status IN ('approved', 'paid')
         AND DATE(expense_date) BETWEEN %s AND %s
         GROUP BY expense_type",
        $building_id,
        $start_date,
        $end_date
    ));
    
    $total_expenses = 0;
    $expenses_breakdown = [];
    
    foreach ($expenses_by_type as $expense) {
        $expenses_breakdown[$expense->expense_type] = (float) $expense->total;
        $total_expenses += (float) $expense->total;
    }
    
    // Maintenance costs
    $maintenance_costs = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(actual_cost), 0) FROM {$wpdb->prefix}ms_work_orders 
         WHERE building_id = %d AND status IN ('completed', 'closed')
         AND DATE(completed_at) BETWEEN %s AND %s",
        $building_id,
        $start_date,
        $end_date
    ));
    
    // Occupancy rate
    $total_units = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ms_units WHERE building_id = %d",
        $building_id
    ));
    
    $occupied_units = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ms_units 
         WHERE building_id = %d AND tenant_id > 0",
        $building_id
    ));
    
    $occupancy_rate = $total_units > 0 ? ($occupied_units / $total_units) * 100 : 0;
    
    return [
        'building_id' => $building_id,
        'month' => $month,
        'income' => $income,
        'maintenance_collected' => $maintenance_collected,
        'withdrawn_amount' => $withdrawn_amount,
        'withdrawal_count' => $withdrawal_count,
        'maintenance_balance' => $maintenance_collected - $withdrawn_amount,
        'total_expenses' => $total_expenses,
        'maintenance_costs' => $maintenance_costs,
        'expenses_breakdown' => $expenses_breakdown,
        'net_profit' => $income - $total_expenses,
        'occupancy_rate' => $occupancy_rate,
        'occupied_units' => $occupied_units,
        'total_units' => $total_units,
    ];
}

/**
 * Render ROI Dashboard for Owner Portal
 */
function msfp_render_roi_dashboard($owner_id = 0)
{
    $owner_id = absint($owner_id ?: get_current_user_id());
    
    if (!$owner_id || !is_user_logged_in()) {
        return '<div class="ms-card"><p>يرجى تسجيل الدخول لعرض لوحة العائد على الاستثمار.</p></div>';
    }
    
    $summary = msfp_get_owner_financial_summary($owner_id);
    
    ob_start();
    ?>
    <div class="ms-card msfp-roi-dashboard">
        <h3>لوحة العائد على الاستثمار (ROI) - <?php echo esc_html($summary['year']); ?></h3>
        
        <div class="msfp-roi-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-top:16px;">
            <div class="msfp-roi-card" style="padding:16px;background:#f0f9ff;border-radius:8px;border-left:4px solid #0284c7;">
                <div style="font-size:12px;color:#64748b;text-transform:uppercase;">إجمالي الدخل</div>
                <div style="font-size:24px;font-weight:bold;color:#0284c7;margin-top:8px;">
                    <?php echo number_format_i18n($summary['total_income'], 2); ?> ج.م
                </div>
            </div>
            
            <div class="msfp-roi-card" style="padding:16px;background:#fef3c7;border-radius:8px;border-left:4px solid #f59e0b;">
                <div style="font-size:12px;color:#64748b;text-transform:uppercase;">إجمالي المصروفات</div>
                <div style="font-size:24px;font-weight:bold;color:#f59e0b;margin-top:8px;">
                    <?php echo number_format_i18n($summary['total_expenses'], 2); ?> ج.م
                </div>
            </div>
            
            <div class="msfp-roi-card" style="padding:16px;background:#dcfce7;border-radius:8px;border-left:4px solid #22c55e;">
                <div style="font-size:12px;color:#64748b;text-transform:uppercase;">صافي الربح</div>
                <div style="font-size:24px;font-weight:bold;color:#22c55e;margin-top:8px;">
                    <?php echo number_format_i18n($summary['net_profit'], 2); ?> ج.م
                </div>
            </div>
            
            <div class="msfp-roi-card" style="padding:16px;background:#ede9fe;border-radius:8px;border-left:4px solid #a855f7;">
                <div style="font-size:12px;color:#64748b;text-transform:uppercase;">العائد على الاستثمار</div>
                <div style="font-size:24px;font-weight:bold;color:#a855f7;margin-top:8px;">
                    <?php echo number_format_i18n($summary['overall_roi'], 2); ?>%
                </div>
            </div>
        </div>
        
        <h4 style="margin-top:24px;">تفصيل العقارات</h4>
        <?php if (empty($summary['properties'])) : ?>
            <p>لا توجد عقارات مسجلة.</p>
        <?php else : ?>
            <table style="width:100%;border-collapse:collapse;margin-top:12px;">
                <thead>
                    <tr style="background:#f8fafc;text-align:right;">
                        <th style="padding:12px;">العقار</th>
                        <th style="padding:12px;">القيمة</th>
                        <th style="padding:12px;">الدخل</th>
                        <th style="padding:12px;">المصروفات</th>
                        <th style="padding:12px;">الربح</th>
                        <th style="padding:12px;">العائد %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($summary['properties'] as $prop) : ?>
                        <tr style="border-top:1px solid #e5e7eb;">
                            <td style="padding:12px;">العقار #<?php echo esc_html($prop['property_id']); ?></td>
                            <td style="padding:12px;">ج.م <?php echo number_format_i18n($prop['property_value'], 2); ?></td>
                            <td style="padding:12px;">ج.م <?php echo number_format_i18n($prop['annual_income'], 2); ?></td>
                            <td style="padding:12px;">ج.م <?php echo number_format_i18n($prop['annual_expenses'], 2); ?></td>
                            <td style="padding:12px;color:#22c55e;font-weight:bold;">ج.م <?php echo number_format_i18n($prop['net_profit'], 2); ?></td>
                            <td style="padding:12px;color:#a855f7;font-weight:bold;"><?php echo number_format_i18n($prop['roi_percentage'], 2); ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode('mostaager_roi_dashboard', function ($atts) {
    $atts = shortcode_atts(['owner_id' => 0], $atts, 'mostaager_roi_dashboard');
    return msfp_render_roi_dashboard(absint($atts['owner_id']));
});

/**
 * Render Financial Analytics Dashboard
 */
function msfp_render_financial_analytics($building_id = 0)
{
    $building_id = absint($building_id ?: ($_GET['building_id'] ?? 0));
    
    if (!$building_id) {
        return '<div class="ms-card"><p>يرجى اختيار مبنى لعرض التحليلات المالية.</p></div>';
    }
    
    if (!msfp_current_user_can_manage_building($building_id)) {
        return '<div class="ms-card"><p>ليس لديك صلاحية لعرض هذه البيانات.</p></div>';
    }
    
    $requested_month = isset($_GET['report_month']) ? sanitize_text_field(wp_unslash($_GET['report_month'])) : current_time('Y-m');
    $current_month = preg_match('/^\\d{4}-(0[1-9]|1[0-2])$/', $requested_month) ? $requested_month : current_time('Y-m');
    $report = msfp_generate_monthly_report($building_id, $current_month);
    
    ob_start();
    ?>
    <div class="ms-card msfp-financial-analytics" dir="rtl">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
            <h3 style="margin:0;">التقرير المالي الشهري للمبنى #<?php echo esc_html($building_id); ?></h3>
            <form method="get" style="display:flex;align-items:center;gap:8px;">
                <?php foreach ($_GET as $key => $value) { if (in_array($key, array('report_month','building_id'), true) || is_array($value)) continue; echo '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr(sanitize_text_field(wp_unslash($value))).'">'; } ?>
                <input type="hidden" name="building_id" value="<?php echo esc_attr($building_id); ?>">
                <label for="msfp-report-month">الشهر</label>
                <input id="msfp-report-month" type="month" name="report_month" value="<?php echo esc_attr($current_month); ?>">
                <button type="submit" class="ms-action-button ms-action-button-primary">عرض التقرير</button>
            </form>
        </div>
        <p style="color:#64748b;margin:8px 0 0;">الفترة: <?php echo esc_html($report['month']); ?> — السحب يمثل طلبات السحب التي اعتمدتها الإدارة خلال الفترة.</p>
        
        <div class="msfp-analytics-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-top:16px;">
            <div style="padding:12px;background:#ecfdf5;border-radius:6px;border-right:4px solid #059669;">
                <div style="font-size:11px;color:#047857;font-weight:bold;">صيانة محصلة</div>
                <div style="font-size:20px;font-weight:bold;color:#059669;margin-top:4px;"><?php echo number_format_i18n($report['maintenance_collected'], 2); ?> ج.م</div>
            </div>
            <div style="padding:12px;background:#eff6ff;border-radius:6px;border-right:4px solid #2563eb;">
                <div style="font-size:11px;color:#1d4ed8;font-weight:bold;">صيانة مسحوبة / معتمدة</div>
                <div style="font-size:20px;font-weight:bold;color:#2563eb;margin-top:4px;"><?php echo number_format_i18n($report['withdrawn_amount'], 2); ?> ج.م</div>
                <small><?php echo intval($report['withdrawal_count']); ?> طلب</small>
            </div>
            <div style="padding:12px;background:#fff7ed;border-radius:6px;border-right:4px solid #ea580c;">
                <div style="font-size:11px;color:#c2410c;font-weight:bold;">رصيد صيانة غير مسحوب</div>
                <div style="font-size:20px;font-weight:bold;color:#ea580c;margin-top:4px;"><?php echo number_format_i18n($report['maintenance_balance'], 2); ?> ج.م</div>
            </div>
            <div style="padding:12px;background:#e0f2fe;border-radius:6px;">
                <div style="font-size:11px;color:#0369a1;font-weight:bold;">الدخل الشهري</div>
                <div style="font-size:20px;font-weight:bold;color:#0284c7;margin-top:4px;">
                    <?php echo number_format_i18n($report['income'], 2); ?> ج.م
                </div>
            </div>
            
            <div style="padding:12px;background:#fef3c7;border-radius:6px;">
                <div style="font-size:11px;color:#b45309;font-weight:bold;">المصروفات الشهرية</div>
                <div style="font-size:20px;font-weight:bold;color:#f59e0b;margin-top:4px;">
                    <?php echo number_format_i18n($report['total_expenses'], 2); ?> ج.م
                </div>
            </div>
            
            <div style="padding:12px;background:#dcfce7;border-radius:6px;">
                <div style="font-size:11px;color:#15803d;font-weight:bold;">صافي الربح</div>
                <div style="font-size:20px;font-weight:bold;color:#22c55e;margin-top:4px;">
                    <?php echo number_format_i18n($report['net_profit'], 2); ?> ج.م
                </div>
            </div>
            
            <div style="padding:12px;background:#f3e8ff;border-radius:6px;">
                <div style="font-size:11px;color:#6b21a8;font-weight:bold;">معدل الإشغال</div>
                <div style="font-size:20px;font-weight:bold;color:#a855f7;margin-top:4px;">
                    <?php echo number_format_i18n($report['occupancy_rate'], 1); ?>%
                </div>
            </div>
        </div>
        
        <h4 style="margin-top:20px;">ملخص الحساب</h4>
        <table style="width:100%;border-collapse:collapse;margin-top:12px;">
            <thead><tr style="background:#f8fafc;text-align:right;"><th style="padding:12px;">البند</th><th style="padding:12px;">المبلغ</th><th style="padding:12px;">ملاحظة</th></tr></thead>
            <tbody>
                <tr style="border-top:1px solid #e5e7eb;"><td style="padding:12px;">إجمالي صيانة محصلة</td><td style="padding:12px;">ج.م <?php echo number_format_i18n($report['maintenance_collected'], 2); ?></td><td style="padding:12px;">فواتير maintenance بحالة paid وتاريخ الدفع داخل الشهر</td></tr>
                <tr style="border-top:1px solid #e5e7eb;"><td style="padding:12px;">إجمالي صيانة مسحوبة / معتمدة</td><td style="padding:12px;">ج.م <?php echo number_format_i18n($report['withdrawn_amount'], 2); ?></td><td style="padding:12px;">طلبات ms_transfer_requests بحالة approved</td></tr>
                <tr style="border-top:2px solid #cbd5e1;font-weight:bold;background:#f8fafc;"><td style="padding:12px;">المتبقي</td><td style="padding:12px;">ج.م <?php echo number_format_i18n($report['maintenance_balance'], 2); ?></td><td style="padding:12px;">المحصّل ناقص المعتمد للسحب</td></tr>
            </tbody>
        </table>

        <h4 style="margin-top:20px;">ملخص آخر 12 شهرًا</h4>
        <div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;margin-top:12px;min-width:620px;">
            <thead><tr style="background:#f8fafc;text-align:right;"><th style="padding:10px;">الشهر</th><th style="padding:10px;">المحصّل</th><th style="padding:10px;">المسحوب / المعتمد</th><th style="padding:10px;">الرصيد</th></tr></thead><tbody>
            <?php for ($month_offset = 11; $month_offset >= 0; $month_offset--) : $trend_month = date('Y-m', strtotime('-' . $month_offset . ' months', strtotime($current_month . '-01'))); $trend = msfp_generate_monthly_report($building_id, $trend_month); ?>
                <tr style="border-top:1px solid #e5e7eb;<?php echo $trend_month === $current_month ? 'background:#eff6ff;font-weight:bold;' : ''; ?>"><td style="padding:10px;"><?php echo esc_html($trend_month); ?></td><td style="padding:10px;">ج.م <?php echo number_format_i18n($trend['maintenance_collected'], 2); ?></td><td style="padding:10px;">ج.م <?php echo number_format_i18n($trend['withdrawn_amount'], 2); ?></td><td style="padding:10px;">ج.م <?php echo number_format_i18n($trend['maintenance_balance'], 2); ?></td></tr>
            <?php endfor; ?>
            </tbody></table></div>

        <h4 style="margin-top:20px;">تفصيل المصروفات</h4>
        <table style="width:100%;border-collapse:collapse;margin-top:12px;">
            <thead>
                <tr style="background:#f8fafc;text-align:right;">
                    <th style="padding:12px;">النوع</th>
                    <th style="padding:12px;">المبلغ</th>
                    <th style="padding:12px;">النسبة</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report['expenses_breakdown'] as $type => $amount) : ?>
                    <tr style="border-top:1px solid #e5e7eb;">
                        <td style="padding:12px;"><?php echo esc_html($type); ?></td>
                        <td style="padding:12px;">ج.م <?php echo number_format_i18n($amount, 2); ?></td>
                        <td style="padding:12px;">
                            <?php echo number_format_i18n(($amount / $report['total_expenses']) * 100, 1); ?>%
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr style="border-top:2px solid #cbd5e1;font-weight:bold;background:#f8fafc;">
                    <td style="padding:12px;">الإجمالي</td>
                    <td style="padding:12px;">ج.م <?php echo number_format_i18n($report['total_expenses'], 2); ?></td>
                    <td style="padding:12px;">100%</td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode('mostaager_financial_analytics', function ($atts) {
    $atts = shortcode_atts(['building_id' => 0], $atts, 'mostaager_financial_analytics');
    return msfp_render_financial_analytics(absint($atts['building_id']));
});
