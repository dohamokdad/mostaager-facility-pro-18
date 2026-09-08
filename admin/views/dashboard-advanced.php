<?php
/**
 * Mostaager Facility PRO - Advanced Dashboard Template
 * Modern Grid-based design with animations and live updates
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get dashboard data
$stats = $this->get_dashboard_stats();
$recent_activity = $this->get_recent_activity();
$charts_data = $this->get_charts_data();
?>

<div class="ms-dashboard-advanced">
    <!-- ===== SIDEBAR ===== -->
    <aside class="ms-sidebar-advanced">
        <div class="ms-sidebar-brand">
            <div class="ms-sidebar-logo">
                <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                    <rect width="32" height="32" rx="8" fill="#f5af02"/>
                    <path d="M8 16L14 22L24 10" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h2>مستأجر العقاري</h2>
        </div>
        
        <nav class="ms-sidebar-nav">
            <a href="#" class="ms-sidebar-nav-item active" data-page="dashboard">
                <span class="ms-sidebar-nav-icon">📊</span>
                <span>لوحة التحكم</span>
            </a>
            <a href="#" class="ms-sidebar-nav-item" data-page="buildings">
                <span class="ms-sidebar-nav-icon">🏢</span>
                <span>المباني</span>
            </a>
            <a href="#" class="ms-sidebar-nav-item" data-page="units">
                <span class="ms-sidebar-nav-icon">🏠</span>
                <span>الوحدات</span>
            </a>
            <a href="#" class="ms-sidebar-nav-item" data-page="tenants">
                <span class="ms-sidebar-nav-icon">👥</span>
                <span>المستأجرين</span>
            </a>
            <a href="#" class="ms-sidebar-nav-item" data-page="maintenance">
                <span class="ms-sidebar-nav-icon">🔧</span>
                <span>الصيانة</span>
            </a>
            <a href="#" class="ms-sidebar-nav-item" data-page="invoices">
                <span class="ms-sidebar-nav-icon">📄</span>
                <span>الفواتير</span>
            </a>
            <a href="#" class="ms-sidebar-nav-item" data-page="reports">
                <span class="ms-sidebar-nav-icon">📈</span>
                <span>التقارير</span>
            </a>
            <a href="#" class="ms-sidebar-nav-item" data-page="import">
                <span class="ms-sidebar-nav-icon">📥</span>
                <span>الاستيراد</span>
            </a>
            <a href="#" class="ms-sidebar-nav-item" data-page="automation">
                <span class="ms-sidebar-nav-icon">⚙️</span>
                <span>الأتمتة</span>
            </a>
            <a href="#" class="ms-sidebar-nav-item" data-page="settings">
                <span class="ms-sidebar-nav-icon">⚙️</span>
                <span>الإعدادات</span>
            </a>
        </nav>
    </aside>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="ms-main-content">
        <!-- Header -->
        <header class="ms-dashboard-header">
            <div class="ms-header-title">
                <h1>لوحة التحكم الرئيسية</h1>
                <p>نظرة عامة على إدارة المرافق والمباني</p>
            </div>
            <div class="ms-header-actions">
                <button class="ms-header-button ms-header-button-secondary">
                    <span>📅</span>
                    <span>اليوم</span>
                </button>
                <button class="ms-header-button ms-header-button-secondary">
                    <span>🔔</span>
                    <span>الإشعارات</span>
                </button>
                <button class="ms-header-button ms-header-button-primary">
                    <span>➕</span>
                    <span>طلب صيانة جديد</span>
                </button>
            </div>
        </header>

        <!-- Stats Grid -->
        <div class="ms-stats-grid">
            <div class="ms-stat-card" data-stat="total_buildings">
                <div class="ms-stat-header">
                    <div class="ms-stat-icon">🏢</div>
                    <div class="ms-stat-trend positive">
                        <span>↑</span>
                        <span>12%</span>
                    </div>
                </div>
                <div class="ms-stat-value"><?php echo number_format($stats['total_buildings']); ?></div>
                <div class="ms-stat-label">إجمالي المباني</div>
            </div>
            
            <div class="ms-stat-card" data-stat="total_units">
                <div class="ms-stat-header">
                    <div class="ms-stat-icon">🏠</div>
                    <div class="ms-stat-trend positive">
                        <span>↑</span>
                        <span>8%</span>
                    </div>
                </div>
                <div class="ms-stat-value"><?php echo number_format($stats['total_units']); ?></div>
                <div class="ms-stat-label">إجمالي الوحدات</div>
            </div>
            
            <div class="ms-stat-card" data-stat="active_tenants">
                <div class="ms-stat-header">
                    <div class="ms-stat-icon">👥</div>
                    <div class="ms-stat-trend positive">
                        <span>↑</span>
                        <span>15%</span>
                    </div>
                </div>
                <div class="ms-stat-value"><?php echo number_format($stats['active_tenants']); ?></div>
                <div class="ms-stat-label">المستأجرين النشطين</div>
            </div>
            
            <div class="ms-stat-card" data-stat="pending_maintenance">
                <div class="ms-stat-header">
                    <div class="ms-stat-icon">🔧</div>
                    <div class="ms-stat-trend negative">
                        <span>↓</span>
                        <span>5%</span>
                    </div>
                </div>
                <div class="ms-stat-value"><?php echo number_format($stats['pending_maintenance']); ?></div>
                <div class="ms-stat-label">طلبات الصيانة المعلقة</div>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="ms-charts-grid">
            <div class="ms-chart-card" data-chart="revenue">
                <div class="ms-chart-header">
                    <div class="ms-chart-title">
                        <span>📈</span>
                        <span>تحليل الإيرادات</span>
                    </div>
                    <div class="ms-chart-actions">
                        <button class="ms-chart-action-btn" data-action="refresh" title="تحديث">🔄</button>
                        <button class="ms-chart-action-btn" data-action="export" title="تصدير">📥</button>
                        <button class="ms-chart-action-btn" data-action="fullscreen" title="ملء الشاشة">⛶</button>
                    </div>
                </div>
                <div class="ms-chart-container">
                    <canvas id="ms-main-chart"></canvas>
                </div>
            </div>
            
            <div class="ms-chart-card" data-chart="occupancy">
                <div class="ms-chart-header">
                    <div class="ms-chart-title">
                        <span>📊</span>
                        <span>نسبة الإشغال</span>
                    </div>
                    <div class="ms-chart-actions">
                        <button class="ms-chart-action-btn" data-action="refresh" title="تحديث">🔄</button>
                    </div>
                </div>
                <div class="ms-chart-container">
                    <canvas id="ms-occupancy-chart" class="ms-secondary-chart" data-type="doughnut" data-chart='<?php echo json_encode($charts_data['occupancy']); ?>'></canvas>
                </div>
            </div>
        </div>

        <!-- Activity Grid -->
        <div class="ms-activity-grid">
            <div class="ms-activity-card">
                <div class="ms-activity-header">
                    <div class="ms-activity-title">
                        <span>🕐</span>
                        <span>النشاط الأخير</span>
                    </div>
                    <button class="ms-chart-action-btn" data-action="refresh" title="تحديث">🔄</button>
                </div>
                <div class="ms-activity-list">
                    <?php foreach ($recent_activity as $activity) : ?>
                        <div class="ms-activity-item">
                            <div class="ms-activity-icon <?php echo $activity['type']; ?>">
                                <?php echo $activity['icon']; ?>
                            </div>
                            <div class="ms-activity-content">
                                <div class="ms-activity-text"><?php echo $activity['text']; ?></div>
                                <div class="ms-activity-time"><?php echo $activity['time']; ?></div>
                            </div>
                            <?php if (isset($activity['amount'])) : ?>
                                <div class="ms-activity-amount"><?php echo $activity['amount']; ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="ms-activity-card">
                <div class="ms-activity-header">
                    <div class="ms-activity-title">
                        <span>⚡</span>
                        <span>إجراءات سريعة</span>
                    </div>
                </div>
                <div class="ms-quick-actions">
                    <a href="#" class="ms-quick-action" data-action="new_maintenance">
                        <div class="ms-quick-action-icon">🔧</div>
                        <div class="ms-quick-action-label">طلب صيانة</div>
                    </a>
                    <a href="#" class="ms-quick-action" data-action="new_invoice">
                        <div class="ms-quick-action-icon">📄</div>
                        <div class="ms-quick-action-label">فاتورة جديدة</div>
                    </a>
                    <a href="#" class="ms-quick-action" data-action="import_data">
                        <div class="ms-quick-action-icon">📥</div>
                        <div class="ms-quick-action-label">استيراد بيانات</div>
                    </a>
                    <a href="#" class="ms-quick-action" data-action="generate_report">
                        <div class="ms-quick-action-icon">📊</div>
                        <div class="ms-quick-action-label">تقرير سريع</div>
                    </a>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Notifications Container -->
<div class="ms-notifications-container"></div>

<!-- Back to Top Button -->
<button class="ms-back-to-top" style="display: none;">
    <span>⬆️</span>
</button>

<style>
/* Additional inline styles for specific elements */
.ms-back-to-top {
    position: fixed;
    bottom: 30px;
    left: 30px;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: var(--ms-primary-color);
    color: white;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    z-index: 9999;
    transition: all 0.3s ease;
}

.ms-back-to-top:hover {
    transform: translateY(-4px);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.3);
}

.ms-notifications-container {
    position: fixed;
    top: 20px;
    left: 20px;
    z-index: 10000;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.ms-notification {
    background: white;
    padding: 16px 20px;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    display: flex;
    align-items: center;
    gap: 16px;
    min-width: 350px;
    animation: slideInLeft 0.3s ease;
    position: relative;
}

.ms-notification-removing {
    animation: slideOutLeft 0.3s ease;
}

.ms-notification-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    font-weight: bold;
}

.ms-notification-success .ms-notification-icon {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.ms-notification-error .ms-notification-icon {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.ms-notification-warning .ms-notification-icon {
    background: rgba(245, 175, 2, 0.1);
    color: #f59e0b;
}

.ms-notification-info .ms-notification-icon {
    background: rgba(37, 99, 235, 0.1);
    color: #2563eb;
}

.ms-notification-content {
    flex: 1;
}

.ms-notification-title {
    font-weight: 700;
    color: var(--ms-text-primary);
    margin-bottom: 4px;
}

.ms-notification-message {
    color: var(--ms-text-secondary);
    font-size: 0.9rem;
}

.ms-notification-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: var(--ms-text-secondary);
    cursor: pointer;
    padding: 0;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.ms-notification-close:hover {
    color: var(--ms-text-primary);
}

@keyframes slideInLeft {
    from {
        transform: translateX(-100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOutLeft {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(-100%);
        opacity: 0;
    }
}

.ms-fullscreen {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 9998;
    padding: 40px;
    background: var(--ms-bg-light);
}
</style>

<script>
// Localize script for PHP values
const ms_ajax = {
    ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
    nonce: '<?php echo wp_create_nonce('ms_dashboard_nonce'); ?>',
    websocket_url: '<?php echo $this->get_websocket_url(); ?>',
    import_url: '<?php echo admin_url('admin.php?page=ms-import'); ?>'
};
</script>