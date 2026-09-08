<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Houzez Reports Integration
 * Integrates Mostaager reporting system with Houzez
 */

// Add reports menu to admin
// The reports screen is retired; keep the renderer for backwards compatibility.

function ms_houzez_add_reports_menu() {
    add_menu_page(
        'تقارير Mostaager',
        'تقارير Mostaager',
        'manage_options',
        'ms-houzez-reports',
        'ms_houzez_render_reports_page',
        'dashicons-chart-bar',
        30
    );
}

function ms_houzez_render_reports_page() {
    ?>
    <div class="wrap">
        <h1>📊 تقارير Mostaager المتكاملة</h1>
        
        <div class="ms-reports-nav" style="margin: 20px 0;">
            <button class="button button-primary" data-report="revenue">تقرير الإيرادات</button>
            <button class="button" data-report="expenses">تقرير المصروفات</button>
            <button class="button" data-report="occupancy">تقرير الإشغال</button>
            <button class="button" data-report="collection">تقرير التحصيل</button>
            <button class="button" data-report="maintenance">تقرير الصيانة</button>
        </div>
        
        <div id="ms-reports-content" style="margin-top: 20px;">
            <p>اختر تقريراً لعرضه.</p>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('.ms-reports-nav button').on('click', function() {
            var report = $(this).data('report');
            
            $('.ms-reports-nav button').removeClass('button-primary');
            $(this).addClass('button-primary');
            
            $('#ms-reports-content').html('<p>جاري التحميل...</p>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ms_get_houzez_report',
                    report_type: report,
                    security: '<?php echo wp_create_nonce('ms_houzez_reports_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $('#ms-reports-content').html(response.data.html);
                    } else {
                        $('#ms-reports-content').html('<p>حدث خطأ: ' + (response.data.message || 'غير معروف') + '</p>');
                    }
                },
                error: function() {
                    $('#ms-reports-content').html('<p>حدث خطأ في الاتصال.</p>');
                }
            });
        });
    });
    </script>
    <?php
}

// AJAX handler for reports
add_action('wp_ajax_ms_get_houzez_report', 'ms_houzez_ajax_get_report');

function ms_houzez_ajax_get_report() {
    check_ajax_referer('ms_houzez_reports_nonce', 'security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'غير مصرح']);
    }
    
    $report_type = isset($_POST['report_type']) ? sanitize_text_field($_POST['report_type']) : '';
    
    $html = '';
    
    switch ($report_type) {
        case 'revenue':
            $html = ms_houzez_get_revenue_report();
            break;
        case 'expenses':
            $html = ms_houzez_get_expenses_report();
            break;
        case 'occupancy':
            $html = ms_houzez_get_occupancy_report();
            break;
        case 'collection':
            $html = ms_houzez_get_collection_report();
            break;
        case 'maintenance':
            $html = ms_houzez_get_maintenance_report();
            break;
        default:
            $html = '<p>نوع تقرير غير معروف.</p>';
    }
    
    wp_send_json_success(['html' => $html]);
}

// Revenue Report
function ms_houzez_get_revenue_report() {
    global $wpdb;
    
    $inv_table = $wpdb->prefix . 'ms_invoices';
    
    // Get revenue by month
    $monthly_revenue = $wpdb->get_results(
        "SELECT 
            DATE_FORMAT(created_at, '%Y-%m') AS month,
            SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) AS paid,
            SUM(CASE WHEN status != 'paid' AND status != 'canceled' THEN amount ELSE 0 END) AS pending
        FROM {$inv_table}
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY month
        ORDER BY month DESC"
    );
    
    ob_start();
    ?>
    <div class="ms-report-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h2>📈 تقرير الإيرادات (آخر 12 شهر)</h2>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>الشهر</th>
                    <th>المدفوع</th>
                    <th>المعلق</th>
                    <th>الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($monthly_revenue as $row): ?>
                    <tr>
                        <td><?php echo esc_html($row->month); ?></td>
                        <td style="color: #10b981; font-weight: bold;"><?php echo number_format_i18n($row->paid, 2); ?> ج.م</td>
                        <td style="color: #f59e0b; font-weight: bold;"><?php echo number_format_i18n($row->pending, 2); ?> ج.م</td>
                        <td style="font-weight: bold;"><?php echo number_format_i18n($row->paid + $row->pending, 2); ?> ج.م</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div style="margin-top: 20px;">
            <button class="button" onclick="ms_export_report('revenue', 'csv')">تصدير CSV</button>
            <button class="button" onclick="ms_export_report('revenue', 'excel')">تصدير Excel</button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Expenses Report
function ms_houzez_get_expenses_report() {
    global $wpdb;
    
    $exp_table = $wpdb->prefix . 'ms_expenses';
    
    // Get expenses by category
    $expenses_by_category = $wpdb->get_results(
        "SELECT 
            expense_type,
            SUM(amount) AS total,
            COUNT(*) AS count
        FROM {$exp_table}
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY expense_type
        ORDER BY total DESC"
    );
    
    ob_start();
    ?>
    <div class="ms-report-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h2>💰 تقرير المصروفات (آخر 12 شهر)</h2>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>النوع</th>
                    <th>العدد</th>
                    <th>الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expenses_by_category as $row): ?>
                    <tr>
                        <td><?php echo esc_html($row->expense_type); ?></td>
                        <td><?php echo intval($row->count); ?></td>
                        <td style="font-weight: bold; color: #ef4444;"><?php echo number_format_i18n($row->total, 2); ?> ج.م</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div style="margin-top: 20px;">
            <button class="button" onclick="ms_export_report('expenses', 'csv')">تصدير CSV</button>
            <button class="button" onclick="ms_export_report('expenses', 'excel')">تصدير Excel</button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Occupancy Report
function ms_houzez_get_occupancy_report() {
    global $wpdb;
    
    $units_table = $wpdb->prefix . 'ms_units';
    
    // Get occupancy stats
    $occupancy_stats = $wpdb->get_results(
        "SELECT 
            status,
            COUNT(*) AS count
        FROM {$units_table}
        GROUP BY status"
    );
    
    $total_units = array_sum(array_column($occupancy_stats, 'count'));
    
    ob_start();
    ?>
    <div class="ms-report-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h2>🏢 تقرير الإشغال</h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
            <?php foreach ($occupancy_stats as $stat): ?>
                <div style="padding: 20px; background: #f8fafc; border-radius: 8px; text-align: center;">
                    <div style="font-size: 14px; color: #64748b;"><?php echo esc_html($stat->status); ?></div>
                    <div style="font-size: 32px; font-weight: bold; color: #0f172a;"><?php echo intval($stat->count); ?></div>
                    <div style="font-size: 12px; color: #94a3b8;">
                        <?php echo round(($stat->count / $total_units) * 100, 1); ?>% من الإجمالي
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div style="margin-top: 20px;">
            <button class="button" onclick="ms_export_report('occupancy', 'csv')">تصدير CSV</button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Collection Report
function ms_houzez_get_collection_report() {
    global $wpdb;
    
    $inv_table = $wpdb->prefix . 'ms_invoices';
    
    // Get collection stats
    $collection_stats = $wpdb->get_row(
        "SELECT 
            SUM(amount) AS total_invoiced,
            SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) AS total_collected,
            SUM(CASE WHEN status != 'paid' AND status != 'canceled' THEN amount ELSE 0 END) AS total_pending,
            COUNT(*) AS total_invoices,
            SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid_invoices
        FROM {$inv_table}
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)"
    );
    
    $collection_rate = $collection_stats->total_invoiced > 0 
        ? round(($collection_stats->total_collected / $collection_stats->total_invoiced) * 100, 2) 
        : 0;
    
    ob_start();
    ?>
    <div class="ms-report-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h2>💵 تقرير التحصيل (آخر 12 شهر)</h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
            <div style="padding: 20px; background: #dcfce7; border-radius: 8px; text-align: center;">
                <div style="font-size: 14px; color: #166534;">إجمالي الفواتير</div>
                <div style="font-size: 32px; font-weight: bold; color: #166534;"><?php echo number_format_i18n($collection_stats->total_invoices); ?></div>
            </div>
            <div style="padding: 20px; background: #dbeafe; border-radius: 8px; text-align: center;">
                <div style="font-size: 14px; color: #1e40af;">إجمالي المبلغ</div>
                <div style="font-size: 32px; font-weight: bold; color: #1e40af;"><?php echo number_format_i18n($collection_stats->total_invoiced, 2); ?> ج.م</div>
            </div>
            <div style="padding: 20px; background: #d1fae5; border-radius: 8px; text-align: center;">
                <div style="font-size: 14px; color: #065f46;">المبلغ المحصل</div>
                <div style="font-size: 32px; font-weight: bold; color: #065f46;"><?php echo number_format_i18n($collection_stats->total_collected, 2); ?> ج.م</div>
            </div>
            <div style="padding: 20px; background: #fef3c7; border-radius: 8px; text-align: center;">
                <div style="font-size: 14px; color: #92400e;">نسبة التحصيل</div>
                <div style="font-size: 32px; font-weight: bold; color: #92400e;"><?php echo $collection_rate; ?>%</div>
            </div>
        </div>
        
        <div style="margin-top: 20px;">
            <button class="button" onclick="ms_export_report('collection', 'csv')">تصدير CSV</button>
            <button class="button" onclick="ms_export_report('collection', 'excel')">تصدير Excel</button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Maintenance Report
function ms_houzez_get_maintenance_report() {
    global $wpdb;
    
    $maint_table = $wpdb->prefix . 'ms_maintenance_requests';
    
    // Get maintenance stats
    $maintenance_stats = $wpdb->get_results(
        "SELECT 
            status,
            COUNT(*) AS count,
            SUM(cost) AS total_cost
        FROM {$maint_table}
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY status"
    );
    
    ob_start();
    ?>
    <div class="ms-report-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h2>🔧 تقرير الصيانة (آخر 12 شهر)</h2>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>الحالة</th>
                    <th>العدد</th>
                    <th>التكلفة الإجمالية</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($maintenance_stats as $stat): ?>
                    <tr>
                        <td><?php echo esc_html($stat->status); ?></td>
                        <td><?php echo intval($stat->count); ?></td>
                        <td style="font-weight: bold;"><?php echo number_format_i18n($stat->total_cost, 2); ?> ج.م</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div style="margin-top: 20px;">
            <button class="button" onclick="ms_export_report('maintenance', 'csv')">تصدير CSV</button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Export report function
add_action('wp_ajax_ms_export_report', 'ms_houzez_ajax_export_report');

function ms_houzez_ajax_export_report() {
    check_ajax_referer('ms_houzez_reports_nonce', 'security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'غير مصرح']);
    }
    
    $report_type = isset($_POST['report_type']) ? sanitize_text_field($_POST['report_type']) : '';
    $format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : 'csv';
    
    // Generate CSV
    $filename = 'mostaager-' . $report_type . '-report-' . date('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    
    // Write headers
    fputcsv($output, ['التقرير', $report_type, 'التاريخ', date('Y-m-d H:i:s')]);
    
    // Write data based on report type
    switch ($report_type) {
        case 'revenue':
            fputcsv($output, ['الشهر', 'المدفوع', 'المعلق', 'الإجمالي']);
            // Add data...
            break;
        case 'expenses':
            fputcsv($output, ['النوع', 'العدد', 'الإجمالي']);
            break;
        case 'occupancy':
            fputcsv($output, ['الحالة', 'العدد', 'النسبة']);
            break;
        case 'collection':
            fputcsv($output, ['إجمالي الفواتير', 'إجمالي المبلغ', 'المبلغ المحصل', 'نسبة التحصيل']);
            break;
        case 'maintenance':
            fputcsv($output, ['الحالة', 'العدد', 'التكلفة']);
            break;
    }
    
    fclose($output);
    exit;
}

// Add export script to admin
add_action('admin_footer', 'ms_houzez_add_export_script');

function ms_houzez_add_export_script() {
    if (!isset($_GET['page']) || $_GET['page'] !== 'ms-houzez-reports') {
        return;
    }
    ?>
    <script>
    function ms_export_report(reportType, format) {
        var formData = new FormData();
        formData.append('action', 'ms_export_report');
        formData.append('report_type', reportType);
        formData.append('format', format);
        formData.append('security', '<?php echo wp_create_nonce('ms_houzez_reports_nonce'); ?>');
        
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.blob())
        .then(blob => {
            var url = window.URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'mostaager-' + reportType + '-report-' + new Date().toISOString().slice(0,10) + '.csv';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        });
    }
    </script>
    <?php
}
