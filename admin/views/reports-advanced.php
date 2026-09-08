<?php
/**
 * Mostaager Facility PRO - Advanced Reports Template
 * Interactive charts, filtering, and export functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get reports data
$report_types = $this->get_report_types();
$current_report = isset($_GET['report_type']) ? sanitize_text_field($_GET['report_type']) : 'summary';
$report_data = $this->get_report_data($current_report);
$filters = $this->get_report_filters();
?>

<div class="ms-reports-advanced">
    <!-- ===== SIDEBAR ===== -->
    <aside class="ms-reports-sidebar">
        <div class="ms-reports-sidebar-title">
            <span>📊</span>
            <span>أنواع التقارير</span>
        </div>
        
        <nav class="ms-reports-categories">
            <?php foreach ($report_types as $type => $label) : ?>
                <div class="ms-reports-category <?php echo $current_report === $type ? 'active' : ''; ?>" 
                     data-report="<?php echo esc_attr($type); ?>">
                    <span class="ms-reports-category-icon"><?php echo $this->get_report_icon($type); ?></span>
                    <span><?php echo esc_html($label); ?></span>
                </div>
            <?php endforeach; ?>
        </nav>
    </aside>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="ms-reports-main">
        <!-- Header -->
        <header class="ms-reports-header">
            <div class="ms-reports-title">
                <h1><?php echo esc_html($report_types[$current_report]); ?></h1>
                <p>تقارير تفصيلية وتحليلية لإدارة المرافق</p>
            </div>
            <div class="ms-reports-actions">
                <button class="ms-reports-button ms-reports-button-secondary" id="ms-refresh-report">
                    <span>🔄</span>
                    <span>تحديث</span>
                </button>
                <button class="ms-reports-button ms-reports-button-secondary" id="ms-schedule-report">
                    <span>📅</span>
                    <span>جدولة</span>
                </button>
                <button class="ms-reports-button ms-reports-button-primary" id="ms-export-report">
                    <span>📥</span>
                    <span>تصدير</span>
                </button>
            </div>
        </header>

        <!-- Filters -->
        <div class="ms-reports-filters">
            <div class="ms-filter-group">
                <label class="ms-filter-label">الفترة الزمنية</label>
                <select class="ms-filter-select" id="ms-date-range">
                    <option value="today">اليوم</option>
                    <option value="week">هذا الأسبوع</option>
                    <option value="month" selected>هذا الشهر</option>
                    <option value="quarter">هذا الربع</option>
                    <option value="year">هذا العام</option>
                    <option value="custom">مخصص</option>
                </select>
            </div>
            
            <div class="ms-filter-group">
                <label class="ms-filter-label">من تاريخ</label>
                <input type="date" class="ms-filter-input" id="ms-date-from" 
                       value="<?php echo date('Y-m-01'); ?>">
            </div>
            
            <div class="ms-filter-group">
                <label class="ms-filter-label">إلى تاريخ</label>
                <input type="date" class="ms-filter-input" id="ms-date-to" 
                       value="<?php echo date('Y-m-t'); ?>">
            </div>
            
            <div class="ms-filter-group">
                <label class="ms-filter-label">المبنى</label>
                <select class="ms-filter-select" id="ms-building-filter">
                    <option value="all">جميع المباني</option>
                    <?php foreach ($filters['buildings'] as $building) : ?>
                        <option value="<?php echo esc_attr($building['id']); ?>">
                            <?php echo esc_html($building['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="ms-filter-group">
                <label class="ms-filter-label">الحالة</label>
                <select class="ms-filter-select" id="ms-status-filter">
                    <option value="all">جميع الحالات</option>
                    <option value="active">نشط</option>
                    <option value="pending">معلق</option>
                    <option value="completed">مكتمل</option>
                </select>
            </div>
            
            <button class="ms-filter-button" id="ms-apply-filters">
                تطبيق الفلاتر
            </button>
        </div>

        <!-- Summary Cards -->
        <div class="ms-reports-summary">
            <div class="ms-report-summary-card">
                <div class="ms-report-summary-icon">💰</div>
                <div class="ms-report-summary-value">
                    <?php echo number_format($report_data['summary']['total_revenue'], 2); ?> ر.س
                </div>
                <div class="ms-report-summary-label">إجمالي الإيرادات</div>
                <div class="ms-report-summary-trend positive">
                    <span>↑</span>
                    <span><?php echo $report_data['summary']['revenue_trend']; ?>%</span>
                </div>
            </div>
            
            <div class="ms-report-summary-card">
                <div class="ms-report-summary-icon">📄</div>
                <div class="ms-report-summary-value">
                    <?php echo number_format($report_data['summary']['total_invoices']); ?>
                </div>
                <div class="ms-report-summary-label">إجمالي الفواتير</div>
                <div class="ms-report-summary-trend positive">
                    <span>↑</span>
                    <span><?php echo $report_data['summary']['invoices_trend']; ?>%</span>
                </div>
            </div>
            
            <div class="ms-report-summary-card">
                <div class="ms-report-summary-icon">🏢</div>
                <div class="ms-report-summary-value">
                    <?php echo number_format($report_data['summary']['active_buildings']); ?>
                </div>
                <div class="ms-report-summary-label">المباني النشطة</div>
                <div class="ms-report-summary-trend <?php echo $report_data['summary']['buildings_trend'] >= 0 ? 'positive' : 'negative'; ?>">
                    <span><?php echo $report_data['summary']['buildings_trend'] >= 0 ? '↑' : '↓'; ?></span>
                    <span><?php echo abs($report_data['summary']['buildings_trend']); ?>%</span>
                </div>
            </div>
            
            <div class="ms-report-summary-card">
                <div class="ms-report-summary-icon">👥</div>
                <div class="ms-report-summary-value">
                    <?php echo number_format($report_data['summary']['active_tenants']); ?>
                </div>
                <div class="ms-report-summary-label">المستأجرين النشطين</div>
                <div class="ms-report-summary-trend positive">
                    <span>↑</span>
                    <span><?php echo $report_data['summary']['tenants_trend']; ?>%</span>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="ms-reports-charts">
            <div class="ms-report-chart-card">
                <div class="ms-report-chart-header">
                    <div class="ms-report-chart-title">
                        <span>📈</span>
                        <span>تحليل الإيرادات الشهرية</span>
                    </div>
                    <div class="ms-report-chart-actions">
                        <button class="ms-report-chart-action" data-action="refresh" title="تحديث">🔄</button>
                        <button class="ms-report-chart-action" data-action="export" title="تصدير">📥</button>
                        <button class="ms-report-chart-action" data-action="fullscreen" title="ملء الشاشة">⛶</button>
                    </div>
                </div>
                <div class="ms-report-chart-container">
                    <canvas id="ms-revenue-chart"></canvas>
                </div>
            </div>
            
            <div class="ms-report-chart-card">
                <div class="ms-report-chart-header">
                    <div class="ms-report-chart-title">
                        <span>📊</span>
                        <span>توزيع الإيرادات حسب المبنى</span>
                    </div>
                    <div class="ms-report-chart-actions">
                        <button class="ms-report-chart-action" data-action="refresh" title="تحديث">🔄</button>
                        <button class="ms-report-chart-action" data-action="export" title="تصدير">📥</button>
                    </div>
                </div>
                <div class="ms-report-chart-container">
                    <canvas id="ms-building-chart"></canvas>
                </div>
            </div>
            
            <div class="ms-report-chart-card">
                <div class="ms-report-chart-header">
                    <div class="ms-report-chart-title">
                        <span>🔧</span>
                        <span>طلبات الصيانة حسب النوع</span>
                    </div>
                    <div class="ms-report-chart-actions">
                        <button class="ms-report-chart-action" data-action="refresh" title="تحديث">🔄</button>
                        <button class="ms-report-chart-action" data-action="export" title="تصدير">📥</button>
                    </div>
                </div>
                <div class="ms-report-chart-container">
                    <canvas id="ms-maintenance-chart"></canvas>
                </div>
            </div>
            
            <div class="ms-report-chart-card">
                <div class="ms-report-chart-header">
                    <div class="ms-report-chart-title">
                        <span>📉</span>
                        <span>معدلات الإشغال</span>
                    </div>
                    <div class="ms-report-chart-actions">
                        <button class="ms-report-chart-action" data-action="refresh" title="تحديث">🔄</button>
                        <button class="ms-report-chart-action" data-action="export" title="تصدير">📥</button>
                    </div>
                </div>
                <div class="ms-report-chart-container">
                    <canvas id="ms-occupancy-chart"></canvas>
                </div>
            </div>
        </div>

        <!-- Detailed Table -->
        <div class="ms-reports-table-container">
            <table class="ms-reports-table">
                <thead>
                    <tr>
                        <th>المبنى</th>
                        <th>الوحدة</th>
                        <th>المستأجر</th>
                        <th>الفاتورة</th>
                        <th>المبلغ</th>
                        <th>الحالة</th>
                        <th>التاريخ</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data['details'] as $item) : ?>
                        <tr>
                            <td><?php echo esc_html($item['building_name']); ?></td>
                            <td><?php echo esc_html($item['unit_number']); ?></td>
                            <td><?php echo esc_html($item['tenant_name']); ?></td>
                            <td><?php echo esc_html($item['invoice_number']); ?></td>
                            <td><?php echo number_format($item['amount'], 2); ?> ر.س</td>
                            <td>
                                <span class="ms-status-badge ms-status-badge-<?php echo $item['status']; ?>">
                                    <?php echo esc_html($item['status_label']); ?>
                                </span>
                            </td>
                            <td><?php echo date('Y-m-d', strtotime($item['date'])); ?></td>
                            <td>
                                <button class="ms-reports-button ms-reports-button-secondary" 
                                        data-action="view" data-id="<?php echo $item['id']; ?>">
                                    عرض
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<!-- Export Modal -->
<div class="ms-export-modal" id="ms-export-modal">
    <div class="ms-export-modal-content">
        <div class="ms-export-modal-title">تصدير التقرير</div>
        <div class="ms-export-options">
            <div class="ms-export-option selected" data-format="pdf">
                <span class="ms-export-option-icon">📄</span>
                <div class="ms-export-option-info">
                    <div class="ms-export-option-title">PDF</div>
                    <div class="ms-export-option-description">مناسبة للطباعة والمشاركة</div>
                </div>
            </div>
            <div class="ms-export-option" data-format="excel">
                <span class="ms-export-option-icon">📊</span>
                <div class="ms-export-option-info">
                    <div class="ms-export-option-title">Excel</div>
                    <div class="ms-export-option-description">مناسبة للتحليل والتعديل</div>
                </div>
            </div>
            <div class="ms-export-option" data-format="csv">
                <span class="ms-export-option-icon">📋</span>
                <div class="ms-export-option-info">
                    <div class="ms-export-option-title">CSV</div>
                    <div class="ms-export-option-description">مناسبة للاستيراد في أنظمة أخرى</div>
                </div>
            </div>
        </div>
        <div class="ms-export-modal-actions">
            <button class="ms-reports-button ms-reports-button-secondary" id="ms-cancel-export">
                إلغاء
            </button>
            <button class="ms-reports-button ms-reports-button-primary" id="ms-confirm-export">
                تصدير
            </button>
        </div>
    </div>
</div>

<style>
.ms-status-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}

.ms-status-badge-paid {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.ms-status-badge-pending {
    background: rgba(245, 175, 2, 0.1);
    color: #f59e0b;
}

.ms-status-badge-overdue {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}
</style>

<script>
// Initialize charts with data
const reportData = <?php echo json_encode($report_data); ?>;
</script>