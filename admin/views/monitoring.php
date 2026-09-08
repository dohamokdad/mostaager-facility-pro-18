<?php
/**
 * Monitoring View - Mostaager Facility PRO Add-On
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="ms-admin-container">
    <div class="ms-header">
        <h1>📊 المراقبة والتحليلات</h1>
        <p class="ms-header-description">مراقبة أداء النظام وتحليل البيانات في الوقت الفعلي</p>
    </div>

    <!-- Real-time Stats -->
    <div class="ms-dashboard-grid">
        <div class="ms-stat-card">
            <div class="ms-stat-icon">💾</div>
            <div class="ms-stat-value" id="ms-memory-usage">--</div>
            <div class="ms-stat-label">استخدام الذاكرة</div>
        </div>
        
        <div class="ms-stat-card">
            <div class="ms-stat-icon">⚡</div>
            <div class="ms-stat-value" id="ms-execution-time">--</div>
            <div class="ms-stat-label">وقت التنفيذ</div>
        </div>
        
        <div class="ms-stat-card">
            <div class="ms-stat-icon">🖥️</div>
            <div class="ms-stat-value" id="ms-server-load">--</div>
            <div class="ms-stat-label">حمل الخادم</div>
        </div>
        
        <div class="ms-stat-card">
            <div class="ms-stat-icon">👥</div>
            <div class="ms-stat-value" id="ms-active-users">--</div>
            <div class="ms-stat-label">المستخدمين النشطين</div>
        </div>
    </div>

    <!-- Analytics Controls -->
    <div class="ms-card">
        <div class="ms-card-header">
            <h2 class="ms-card-title">📈 التحليلات</h2>
            <div class="ms-card-actions">
                <select class="ms-form-select" id="ms-analytics-period">
                    <option value="1h">آخر ساعة</option>
                    <option value="24h" selected>آخر 24 ساعة</option>
                    <option value="7d">آخر 7 أيام</option>
                    <option value="30d">آخر 30 يوم</option>
                </select>
                <button class="ms-button ms-button-primary" id="ms-refresh-analytics">تحديث</button>
            </div>
        </div>
        <div class="ms-card-body">
            <div class="ms-analytics-charts">
                <div class="ms-chart-container">
                    <canvas id="msSystemPerformance" height="200"></canvas>
                </div>
                <div class="ms-chart-container">
                    <canvas id="msDatabasePerformance" height="200"></canvas>
                </div>
                <div class="ms-chart-container">
                    <canvas id="msApplicationMetrics" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Alerts Management -->
    <div class="ms-card">
        <div class="ms-card-header">
            <h2 class="ms-card-title">🔔 إدارة التنبيهات</h2>
            <button class="ms-button ms-button-primary" id="ms-create-alert">إنشاء تنبيه جديد</button>
        </div>
        <div class="ms-card-body">
            <?php if (!empty($alerts)): ?>
                <table class="ms-table">
                    <thead>
                        <tr>
                            <th>اسم التنبيه</th>
                            <th>المؤشر</th>
                            <th>الشرط</th>
                            <th>الحد</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alerts as $alert): ?>
                            <tr>
                                <td><?php echo esc_html($alert['name']); ?></td>
                                <td><?php echo esc_html($alert['metric']); ?></td>
                                <td>
                                    <?php
                                    $operators = array(
                                        'greater_than' => 'أكبر من',
                                        'less_than' => 'أقل من',
                                        'equals' => 'يساوي'
                                    );
                                    echo esc_html($operators[$alert['operator']] ?? $alert['operator']);
                                    ?>
                                </td>
                                <td><?php echo esc_html($alert['threshold']); ?></td>
                                <td>
                                    <span class="ms-badge <?php echo $alert['active'] ? 'ms-badge-success' : 'ms-badge-warning'; ?>">
                                        <?php echo $alert['active'] ? 'نشط' : 'معطل'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="ms-button ms-button-outline ms-toggle-alert" data-alert-id="<?php echo esc_attr($alert['id']); ?>">
                                        <?php echo $alert['active'] ? 'تعطيل' : 'تفعيل'; ?>
                                    </button>
                                    <button class="ms-button ms-button-danger ms-delete-alert" data-alert-id="<?php echo esc_attr($alert['id']); ?>">
                                        حذف
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="ms-empty-state">
                    <div class="ms-empty-state-icon">🔔</div>
                    <h3 class="ms-empty-state-title">لا توجد تنبيهات</h3>
                    <p class="ms-empty-state-description">أنشئ تنبيهك الأول لمراقبة النظام</p>
                    <button class="ms-button ms-button-primary" id="ms-create-alert-empty">
                        إنشاء تنبيه جديد
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- System Health -->
    <div class="ms-card">
        <div class="ms-card-header">
            <h2 class="ms-card-title">🏥 صحة النظام</h2>
        </div>
        <div class="ms-card-body">
            <div class="ms-health-check">
                <div class="ms-health-item">
                    <div class="ms-health-label">حالة الخادم</div>
                    <div class="ms-health-status ms-health-good">جيد</div>
                </div>
                <div class="ms-health-item">
                    <div class="ms-health-label">قاعدة البيانات</div>
                    <div class="ms-health-status ms-health-good">جيد</div>
                </div>
                <div class="ms-health-item">
                    <div class="ms-health-label">التخزين المؤقت</div>
                    <div class="ms-health-status ms-health-warning">تحتاج تحسين</div>
                </div>
                <div class="ms-health-item">
                    <div class="ms-health-label">الأمان</div>
                    <div class="ms-health-status ms-health-good">جيد</div>
                </div>
            </div>
        </div>
    </div>

    <!-- External Integrations Status -->
    <div class="ms-card">
        <div class="ms-card-header">
            <h2 class="ms-card-title">🔗 حالة التكاملات الخارجية</h2>
        </div>
        <div class="ms-card-body">
            <div class="ms-integrations-status">
                <div class="ms-integration-item">
                    <div class="ms-integration-icon">🎯</div>
                    <div class="ms-integration-name">HubSpot CRM</div>
                    <div class="ms-integration-status ms-status-connected">متصل</div>
                </div>
                <div class="ms-integration-item">
                    <div class="ms-integration-icon">💳</div>
                    <div class="ms-integration-name">Stripe</div>
                    <div class="ms-integration-status ms-status-connected">متصل</div>
                </div>
                <div class="ms-integration-item">
                    <div class="ms-integration-icon">🏢</div>
                    <div class="ms-integration-name">Salesforce</div>
                    <div class="ms-integration-status ms-status-disconnected">غير متصل</div>
                </div>
                <div class="ms-integration-item">
                    <div class="ms-integration-icon">💰</div>
                    <div class="ms-integration-name">QuickBooks</div>
                    <div class="ms-integration-status ms-status-disconnected">غير متصل</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Alert Modal -->
<div class="ms-modal ms-alert-modal" style="display: none;">
    <div class="ms-modal-content">
        <div class="ms-modal-header">
            <h3>إنشاء تنبيه جديد</h3>
            <button class="ms-modal-close">&times;</button>
        </div>
        <div class="ms-modal-body">
            <form id="ms-alert-form">
                <?php wp_nonce_field('ms_set_alert', 'nonce'); ?>
                
                <div class="ms-form-group">
                    <label class="ms-form-label">اسم التنبيه</label>
                    <input type="text" name="alert_name" class="ms-form-input" required>
                </div>
                
                <div class="ms-form-group">
                    <label class="ms-form-label">المؤشر</label>
                    <select name="metric" class="ms-form-select" required>
                        <option value="memory_usage">استخدام الذاكرة</option>
                        <option value="server_load">حمل الخادم</option>
                        <option value="slow_queries">الاستعلامات البطيئة</option>
                        <option value="error_rate">معدل الأخطاء</option>
                        <option value="response_time">وقت الاستجابة</option>
                    </select>
                </div>
                
                <div class="ms-form-group">
                    <label class="ms-form-label">الشرط</label>
                    <select name="operator" class="ms-form-select" required>
                        <option value="greater_than">أكبر من</option>
                        <option value="less_than">أقل من</option>
                        <option value="equals">يساوي</option>
                    </select>
                </div>
                
                <div class="ms-form-group">
                    <label class="ms-form-label">الحد</label>
                    <input type="number" name="threshold" class="ms-form-input" required>
                </div>
                
                <div class="ms-form-group">
                    <label class="ms-form-label">المستلمين (فواصل بفاصلة)</label>
                    <input type="text" name="recipients" class="ms-form-input" placeholder="admin@example.com,manager@example.com">
                </div>
            </form>
        </div>
        <div class="ms-modal-footer">
            <button class="ms-button ms-button-outline ms-modal-cancel">إلغاء</button>
            <button class="ms-button ms-button-primary ms-save-alert">حفظ</button>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Load real-time stats
    function loadRealTimeStats() {
        $.ajax({
            url: msAddonData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ms_get_real_time_stats',
                nonce: msAddonData.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#ms-memory-usage').text(formatBytes(response.data.memory_usage));
                    $('#ms-execution-time').text(response.data.execution_time.toFixed(3) + 's');
                    $('#ms-server-load').text(response.data.server_load['1min'].toFixed(2));
                    $('#ms-active-users').text(response.data.active_users);
                }
            }
        });
    }
    
    // Format bytes
    function formatBytes(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }
    
    // Load analytics
    function loadAnalytics() {
        const period = $('#ms-analytics-period').val();
        const metrics = ['system.memory_usage', 'database.query_time', 'application.imports_today'];
        
        $.ajax({
            url: msAddonData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ms_get_analytics',
                nonce: msAddonData.nonce,
                period: period,
                metrics: JSON.stringify(metrics)
            },
            success: function(response) {
                if (response.success) {
                    updateAnalyticsCharts(response.data);
                }
            }
        });
    }
    
    // Update analytics charts
    function updateAnalyticsCharts(data) {
        if (typeof Chart === 'undefined') return;
        
        // System performance chart
        const performanceCtx = document.getElementById('msSystemPerformance');
        if (performanceCtx) {
            const performanceChart = new Chart(performanceCtx, {
                type: 'line',
                data: {
                    labels: data.map(d => new Date(d.timestamp).toLocaleTimeString()),
                    datasets: [{
                        label: 'استخدام الذاكرة',
                        data: data.map(d => d.system_memory_usage),
                        borderColor: '#0073aa',
                        backgroundColor: 'rgba(0, 115, 170, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'top' } }
                }
            });
        }
        
        // Database performance chart
        const dbCtx = document.getElementById('msDatabasePerformance');
        if (dbCtx) {
            const dbChart = new Chart(dbCtx, {
                type: 'bar',
                data: {
                    labels: data.map(d => new Date(d.timestamp).toLocaleTimeString()),
                    datasets: [{
                        label: 'وقت الاستعلام',
                        data: data.map(d => d.database_query_time),
                        backgroundColor: '#00a0d2'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'top' } }
                }
            });
        }
        
        // Application metrics chart
        const appCtx = document.getElementById('msApplicationMetrics');
        if (appCtx) {
            const appChart = new Chart(appCtx, {
                type: 'line',
                data: {
                    labels: data.map(d => new Date(d.timestamp).toLocaleTimeString()),
                    datasets: [{
                        label: 'عمليات الاستيراد',
                        data: data.map(d => d.application_imports_today),
                        borderColor: '#46b450',
                        backgroundColor: 'rgba(70, 180, 80, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'top' } }
                }
            });
        }
    }
    
    // Alert modal
    $('#ms-create-alert, #ms-create-alert-empty').on('click', function() {
        $('.ms-alert-modal').show();
    });
    
    // Close modals
    $('.ms-modal-close, .ms-modal-cancel').on('click', function() {
        $('.ms-modal').hide();
    });
    
    // Save alert
    $('.ms-save-alert').on('click', function() {
        const formData = $('#ms-alert-form').serialize();
        
        $.ajax({
            url: msAddonData.ajaxUrl,
            type: 'POST',
            data: formData + '&action=ms_set_alert&nonce=' + msAddonData.nonce,
            success: function(response) {
                if (response.success) {
                    msShowNotification('تم إنشاء التنبيه بنجاح', 'success');
                    $('.ms-alert-modal').hide();
                    location.reload();
                }
            }
        });
    });
    
    // Refresh analytics
    $('#ms-refresh-analytics').on('click', function() {
        loadAnalytics();
    });
    
    // Initial load
    loadRealTimeStats();
    loadAnalytics();
    
    // Auto-refresh every 30 seconds
    setInterval(loadRealTimeStats, 30000);
});
</script>
