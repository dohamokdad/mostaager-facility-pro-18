<?php
/**
 * Analytics Dashboard View
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class="ms-analytics-dashboard">
    <div class="ms-dashboard-header">
        <h2>لوحة تحليل البيانات المتقدمة</h2>
        <p>رؤى وتحليلات شاملة لأداء نظام إدارة المرافق</p>
    </div>
    
    <!-- Key Metrics -->
    <div class="ms-key-metrics">
        <div class="ms-metric-card">
            <div class="ms-metric-icon">💰</div>
            <div class="ms-metric-info">
                <h3>الإيرادات الكلية</h3>
                <p class="ms-metric-value" id="ms-total-revenue">Loading...</p>
                <p class="ms-metric-trend" id="ms-revenue-trend">--</p>
            </div>
        </div>
        
        <div class="ms-metric-card">
            <div class="ms-metric-icon">🛠️</div>
            <div class="ms-metric-info">
                <h3>إجمالي الصيانة</h3>
                <p class="ms-metric-value" id="ms-total-maintenance">Loading...</p>
                <p class="ms-metric-trend" id="ms-maintenance-trend">--</p>
            </div>
        </div>
        
        <div class="ms-metric-card">
            <div class="ms-metric-icon">🏠</div>
            <div class="ms-metric-info">
                <h3>نسبة الإشغال</h3>
                <p class="ms-metric-value" id="ms-occupancy-rate">Loading...</p>
                <p class="ms-metric-trend" id="ms-occupancy-trend">--</p>
            </div>
        </div>
        
        <div class="ms-metric-card">
            <div class="ms-metric-icon">👥</div>
            <div class="ms-metric-info">
                <h3>نشاط المستخدمين</h3>
                <p class="ms-metric-value" id="ms-user-activity">Loading...</p>
                <p class="ms-metric-trend" id="ms-user-activity-trend">--</p>
            </div>
        </div>
        
        <div class="ms-metric-card">
            <div class="ms-metric-icon">🧾</div>
            <div class="ms-metric-info">
                <h3>معدل تحصيل الفواتير</h3>
                <p class="ms-metric-value" id="ms-collection-rate">Loading...</p>
                <p class="ms-metric-trend">--</p>
            </div>
        </div>
        
        <div class="ms-metric-card">
            <div class="ms-metric-icon">⚡</div>
            <div class="ms-metric-info">
                <h3>كفاءة المباني</h3>
                <p class="ms-metric-value" id="ms-building-efficiency">Loading...</p>
                <p class="ms-metric-trend">--</p>
            </div>
        </div>
    </div>
    
    <!-- Controls -->
    <div class="ms-analytics-controls">
        <div class="ms-control-group">
            <label>الفترة الزمنية</label>
            <select id="ms-period-selector">
                <option value="week">أسبوع</option>
                <option value="month" selected>شهر</option>
                <option value="quarter">ربع سنوي</option>
                <option value="year">سنة</option>
            </select>
        </div>
        
        <div class="ms-control-group">
            <label>نوع الرسم البياني</label>
            <select id="ms-chart-type-selector">
                <option value="revenue_chart">الإيرادات</option>
                <option value="maintenance_chart">الصيانة</option>
                <option value="occupancy_chart">الإشغال</option>
                <option value="user_activity_chart">نشاط المستخدمين</option>
                <option value="comparison_chart">المقارنات</option>
                <option value="trend_chart">الاتجاهات</option>
            </select>
        </div>
        
        <div class="ms-control-group">
            <button class="ms-btn ms-btn-primary" id="ms-refresh-analytics">تحديث البيانات</button>
            <button class="ms-btn ms-btn-secondary" id="ms-export-analytics">تصدير البيانات</button>
        </div>
    </div>
    
    <!-- Charts Section -->
    <div class="ms-charts-section">
        <div class="ms-chart-container">
            <h3>الرسم البياني الرئيسي</h3>
            <canvas id="ms-main-chart"></canvas>
        </div>
        
        <div class="ms-chart-container">
            <h3>الاتجاهات</h3>
            <canvas id="ms-trend-chart"></canvas>
        </div>
    </div>
    
    <!-- Predictions Section -->
    <div class="ms-predictions-section">
        <h3>التنبؤات الذكية</h3>
        
        <div class="ms-prediction-cards">
            <div class="ms-prediction-card">
                <h4>تنبؤ الإيرادات</h4>
                <p class="ms-prediction-value" id="ms-revenue-prediction">Loading...</p>
                <p class="ms-prediction-confidence" id="ms-revenue-confidence">--</p>
                <p class="ms-prediction-trend" id="ms-revenue-prediction-trend">--</p>
            </div>
            
            <div class="ms-prediction-card">
                <h4>تنبؤ الصيانة</h4>
                <p class="ms-prediction-value" id="ms-maintenance-prediction">Loading...</p>
                <p class="ms-prediction-confidence" id="ms-maintenance-confidence">--</p>
                <p class="ms-prediction-trend" id="ms-maintenance-prediction-trend">--</p>
            </div>
            
            <div class="ms-prediction-card">
                <h4>تنبؤ الإشغال</h4>
                <p class="ms-prediction-value" id="ms-occupancy-prediction">Loading...</p>
                <p class="ms-prediction-confidence" id="ms-occupancy-confidence">--</p>
                <p class="ms-prediction-trend" id="ms-occupancy-prediction-trend">--</p>
            </div>
            
            <div class="ms-prediction-card">
                <h4>تنبؤ نشاط المستخدمين</h4>
                <p class="ms-prediction-value" id="ms-user-activity-prediction">Loading...</p>
                <p class="ms-prediction-confidence" id="ms-user-activity-confidence">--</p>
                <p class="ms-prediction-trend" id="ms-user-activity-prediction-trend">--</p>
            </div>
        </div>
    </div>
    
    <!-- Detailed Analysis -->
    <div class="ms-detailed-analysis">
        <h3>التحليل التفصيلي</h3>
        
        <div class="ms-analysis-tabs">
            <button class="ms-analysis-tab active" data-tab="performance">الأداء</button>
            <button class="ms-analysis-tab" data-tab="comparisons">المقارنات</button>
            <button class="ms-analysis-tab" data-tab="trends">الاتجاهات</button>
        </div>
        
        <div class="ms-analysis-content">
            <div class="ms-analysis-pane active" id="performance">
                <div id="ms-performance-data"></div>
            </div>
            
            <div class="ms-analysis-pane" id="comparisons">
                <div id="ms-comparisons-data"></div>
            </div>
            
            <div class="ms-analysis-pane" id="trends">
                <div id="ms-trends-data"></div>
            </div>
        </div>
    </div>
</div>

<style>
.ms-analytics-dashboard {
    padding: 20px;
    background: #f8fafc;
    border-radius: 12px;
    margin: 20px 0;
}

.ms-dashboard-header {
    text-align: center;
    margin-bottom: 30px;
}

.ms-dashboard-header h2 {
    color: #1e293b;
    font-size: 28px;
    margin-bottom: 10px;
}

.ms-dashboard-header p {
    color: #64748b;
    font-size: 16px;
}

.ms-key-metrics {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.ms-metric-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    display: flex;
    align-items: center;
    gap: 15px;
    transition: transform 0.2s;
}

.ms-metric-card:hover {
    transform: translateY(-5px);
}

.ms-metric-icon {
    font-size: 32px;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f1f5f9;
    border-radius: 50%;
}

.ms-metric-info h3 {
    color: #1e293b;
    font-size: 14px;
    margin: 0 0 5px 0;
    font-weight: 600;
}

.ms-metric-value {
    color: #3b82f6;
    font-size: 24px;
    font-weight: 700;
    margin: 5px 0;
}

.ms-metric-trend {
    color: #64748b;
    font-size: 12px;
    margin: 0;
}

.ms-analytics-controls {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.ms-control-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.ms-control-group label {
    color: #475569;
    font-size: 14px;
    font-weight: 600;
}

.ms-control-group select {
    padding: 10px 15px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    min-width: 150px;
}

.ms-btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.ms-btn-primary {
    background: #3b82f6;
    color: white;
}

.ms-btn-primary:hover {
    background: #2563eb;
}

.ms-btn-secondary {
    background: #64748b;
    color: white;
}

.ms-btn-secondary:hover {
    background: #475569;
}

.ms-charts-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-bottom: 30px;
}

.ms-chart-container {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.ms-chart-container h3 {
    color: #1e293b;
    font-size: 18px;
    margin-bottom: 20px;
}

.ms-predictions-section {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    margin-bottom: 30px;
}

.ms-predictions-section h3 {
    color: #1e293b;
    font-size: 20px;
    margin-bottom: 20px;
}

.ms-prediction-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.ms-prediction-card {
    background: #f8fafc;
    padding: 20px;
    border-radius: 8px;
    border-left: 4px solid #3b82f6;
}

.ms-prediction-card h4 {
    color: #1e293b;
    font-size: 16px;
    margin: 0 0 10px 0;
}

.ms-prediction-value {
    color: #3b82f6;
    font-size: 24px;
    font-weight: 700;
    margin: 10px 0;
}

.ms-prediction-confidence {
    color: #64748b;
    font-size: 12px;
    margin: 5px 0;
}

.ms-prediction-trend {
    color: #10b981;
    font-size: 12px;
    margin: 5px 0;
}

.ms-detailed-analysis {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.ms-detailed-analysis h3 {
    color: #1e293b;
    font-size: 20px;
    margin-bottom: 20px;
}

.ms-analysis-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 10px;
}

.ms-analysis-tab {
    padding: 10px 20px;
    background: transparent;
    border: none;
    color: #64748b;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.ms-analysis-tab.active {
    color: #3b82f6;
    border-bottom: 2px solid #3b82f6;
}

.ms-analysis-content {
    min-height: 200px;
}

.ms-analysis-pane {
    display: none;
}

.ms-analysis-pane.active {
    display: block;
}

@media (max-width: 768px) {
    .ms-charts-section {
        grid-template-columns: 1fr;
    }
    
    .ms-key-metrics {
        grid-template-columns: 1fr 1fr;
    }
    
    .ms-analytics-controls {
        flex-direction: column;
    }
}
</style>

<script>
(function($) {
    'use strict';
    
    const AnalyticsDashboard = {
        charts: {},
        
        init: function() {
            this.initControls();
            this.initCharts();
            this.initTabs();
            this.loadAnalyticsData();
        },
        
        initControls: function() {
            $('#ms-period-selector').on('change', function() {
                AnalyticsDashboard.loadAnalyticsData();
            });
            
            $('#ms-chart-type-selector').on('change', function() {
                AnalyticsDashboard.loadChartData();
            });
            
            $('#ms-refresh-analytics').on('click', function() {
                AnalyticsDashboard.loadAnalyticsData();
            });
            
            $('#ms-export-analytics').on('click', function() {
                AnalyticsDashboard.exportAnalytics();
            });
        },
        
        initCharts: function() {
            // Initialize main chart
            const mainCtx = document.getElementById('ms-main-chart').getContext('2d');
            this.charts.main = new Chart(mainCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: []
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: true
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
            
            // Initialize trend chart
            const trendCtx = document.getElementById('ms-trend-chart').getContext('2d');
            this.charts.trend = new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: []
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: true
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },
        
        initTabs: function() {
            $('.ms-analysis-tab').on('click', function() {
                $('.ms-analysis-tab').removeClass('active');
                $(this).addClass('active');
                
                $('.ms-analysis-pane').removeClass('active');
                $('#' + $(this).data('tab')).addClass('active');
            });
        },
        
        loadAnalyticsData: function() {
            const period = $('#ms-period-selector').val();
            
            const data = {
                action: 'ms_get_analytics_data',
                nonce: '<?php echo esc_js(wp_create_nonce('ms_get_analytics_data')); ?>',
                period: period,
                filters: JSON.stringify({})
            };
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        AnalyticsDashboard.updateMetrics(response.data.key_metrics);
                        AnalyticsDashboard.updateDetailedAnalysis(response.data);
                        AnalyticsDashboard.loadChartData();
                        AnalyticsDashboard.loadPredictions();
                    }
                },
                error: function() {
                    console.error('Failed to load analytics data');
                }
            });
        },
        
        updateMetrics: function(metrics) {
            $('#ms-total-revenue').text(metrics.total_revenue.toLocaleString() + ' ر.س');
            $('#ms-total-maintenance').text(metrics.total_maintenance);
            $('#ms-occupancy-rate').text(metrics.occupancy_rate + '%');
            $('#ms-user-activity').text(metrics.user_activity);
            $('#ms-collection-rate').text(metrics.invoice_collection_rate + '%');
            $('#ms-building-efficiency').text(metrics.building_efficiency + '%');
        },
        
        updateDetailedAnalysis: function(data) {
            $('#ms-performance-data').html('<pre>' + JSON.stringify(data.performance, null, 2) + '</pre>');
            $('#ms-comparisons-data').html('<pre>' + JSON.stringify(data.comparisons, null, 2) + '</pre>');
            $('#ms-trends-data').html('<pre>' + JSON.stringify(data.trends, null, 2) + '</pre>');
        },
        
        loadChartData: function() {
            const chartType = $('#ms-chart-type-selector').val();
            const period = $('#ms-period-selector').val();
            
            const data = {
                action: 'ms_get_chart_data',
                nonce: '<?php echo esc_js(wp_create_nonce('ms_get_chart_data')); ?>',
                chart_type: chartType,
                period: period,
                filters: JSON.stringify({})
            };
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        AnalyticsDashboard.updateMainChart(response.data);
                    }
                },
                error: function() {
                    console.error('Failed to load chart data');
                }
            });
        },
        
        updateMainChart: function(chartData) {
            if (this.charts.main) {
                this.charts.main.data = chartData.data;
                this.charts.main.options = chartData.options;
                this.charts.main.update();
            }
        },
        
        loadPredictions: function() {
            const period = $('#ms-period-selector').val();
            
            const predictionTypes = ['revenue_prediction', 'maintenance_prediction', 'occupancy_prediction', 'user_activity_prediction'];
            
            predictionTypes.forEach(function(type) {
                const data = {
                    action: 'ms_get_predictions',
                    nonce: '<?php echo esc_js(wp_create_nonce('ms_get_predictions')); ?>',
                    prediction_type: type,
                    period: period
                };
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: data,
                    success: function(response) {
                        if (response.success) {
                            AnalyticsDashboard.updatePrediction(type, response.data);
                        }
                    },
                    error: function() {
                        console.error('Failed to load prediction for ' + type);
                    }
                });
            });
        },
        
        updatePrediction: function(type, prediction) {
            const elementMap = {
                'revenue_prediction': {
                    value: '#ms-revenue-prediction',
                    confidence: '#ms-revenue-confidence',
                    trend: '#ms-revenue-prediction-trend'
                },
                'maintenance_prediction': {
                    value: '#ms-maintenance-prediction',
                    confidence: '#ms-maintenance-confidence',
                    trend: '#ms-maintenance-prediction-trend'
                },
                'occupancy_prediction': {
                    value: '#ms-occupancy-prediction',
                    confidence: '#ms-occupancy-confidence',
                    trend: '#ms-occupancy-prediction-trend'
                },
                'user_activity_prediction': {
                    value: '#ms-user-activity-prediction',
                    confidence: '#ms-user-activity-confidence',
                    trend: '#ms-user-activity-prediction-trend'
                }
            };
            
            const elements = elementMap[type];
            if (elements) {
                $(elements.value).text(prediction.predicted_value.toLocaleString());
                $(elements.confidence).text('الثقة: ' + prediction.confidence + '%');
                $(elements.trend).text('الاتجاه: ' + prediction.trend);
            }
        },
        
        exportAnalytics: function() {
            const data = {
                action: 'ms_export_analytics',
                nonce: '<?php echo esc_js(wp_create_nonce('ms_export_analytics')); ?>',
                format: 'json',
                data: JSON.stringify(this.getCurrentAnalyticsData())
            };
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        window.location.href = response.data.download_url;
                    } else {
                        alert('حدث خطأ أثناء التصدير');
                    }
                },
                error: function() {
                    alert('حدث خطأ في الاتصال');
                }
            });
        },
        
        getCurrentAnalyticsData: function() {
            return {
                metrics: {
                    total_revenue: $('#ms-total-revenue').text(),
                    total_maintenance: $('#ms-total-maintenance').text(),
                    occupancy_rate: $('#ms-occupancy-rate').text(),
                    user_activity: $('#ms-user-activity').text()
                },
                period: $('#ms-period-selector').val(),
                chart_type: $('#ms-chart-type-selector').val()
            };
        }
    };
    
    $(document).ready(function() {
        if (typeof Chart !== 'undefined') {
            AnalyticsDashboard.init();
        } else {
            console.error('Chart.js library not loaded');
        }
    });
    
})(jQuery);
</script>