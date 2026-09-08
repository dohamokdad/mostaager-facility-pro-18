<?php
/**
 * Mostaager Facility PRO - Advanced Automation & Monitoring Template
 * Task scheduling, automation rules, and real-time monitoring
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get automation data
$scheduled_tasks = $this->get_scheduled_tasks();
$automation_rules = $this->get_automation_rules();
$monitoring_data = $this->get_monitoring_data();
$activity_log = $this->get_activity_log();
?>

<div class="ms-automation-dashboard">
    <!-- ===== SIDEBAR ===== -->
    <aside class="ms-automation-sidebar">
        <div class="ms-automation-sidebar-title">
            <span>⚙️</span>
            <span>الأتمتة والمراقبة</span>
        </div>
        
        <nav class="ms-automation-nav">
            <div class="ms-automation-nav-item active" data-tab="dashboard">
                <span class="ms-automation-nav-icon">📊</span>
                <span>لوحة التحكم</span>
            </div>
            <div class="ms-automation-nav-item" data-tab="tasks">
                <span class="ms-automation-nav-icon">📅</span>
                <span>المهام المجدولة</span>
            </div>
            <div class="ms-automation-nav-item" data-tab="rules">
                <span class="ms-automation-nav-icon">🔗</span>
                <span>قواعد الأتمتة</span>
            </div>
            <div class="ms-automation-nav-item" data-tab="monitoring">
                <span class="ms-automation-nav-icon">📡</span>
                <span>المراقبة المباشرة</span>
            </div>
            <div class="ms-automation-nav-item" data-tab="logs">
                <span class="ms-automation-nav-icon">📝</span>
                <span>سجلات النشاط</span>
            </div>
            <div class="ms-automation-nav-item" data-tab="integrations">
                <span class="ms-automation-nav-icon">🔌</span>
                <span>التكاملات</span>
            </div>
        </nav>
    </aside>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="ms-automation-main">
        <!-- Header -->
        <header class="ms-automation-header">
            <div class="ms-automation-title">
                <h1>لوحة الأتمتة والمراقبة</h1>
                <p>إدارة المهام المجدولة وقواعد الأتمتة والمراقبة المباشرة</p>
            </div>
            <div class="ms-automation-actions">
                <button class="ms-automation-button ms-automation-button-secondary" id="ms-refresh-automation">
                    <span>🔄</span>
                    <span>تحديث</span>
                </button>
                <button class="ms-automation-button ms-automation-button-primary" id="ms-create-rule">
                    <span>➕</span>
                    <span>قاعدة جديدة</span>
                </button>
            </div>
        </header>

        <!-- Scheduled Tasks Section -->
        <section class="ms-automation-section">
            <div class="ms-automation-section-header">
                <div class="ms-automation-section-title">
                    <span>📅</span>
                    <span>المهام المجدولة</span>
                </div>
                <button class="ms-automation-button ms-automation-button-secondary" id="ms-add-task">
                    <span>➕</span>
                    <span>إضافة مهمة</span>
                </button>
            </div>
            
            <div class="ms-automation-tasks-grid">
                <?php foreach ($scheduled_tasks as $task) : ?>
                    <div class="ms-automation-task-card <?php echo $task['status']; ?>">
                        <div class="ms-automation-task-header">
                            <div class="ms-automation-task-icon">
                                <?php echo $task['icon']; ?>
                            </div>
                            <div class="ms-automation-task-status"></div>
                        </div>
                        <div class="ms-automation-task-name">
                            <?php echo esc_html($task['name']); ?>
                        </div>
                        <div class="ms-automation-task-description">
                            <?php echo esc_html($task['description']); ?>
                        </div>
                        <div class="ms-automation-task-schedule">
                            <span>⏰</span>
                            <span><?php echo esc_html($task['schedule']); ?></span>
                        </div>
                        <div class="ms-automation-task-stats">
                            <div class="ms-automation-task-stat">
                                <div class="ms-automation-task-stat-value">
                                    <?php echo number_format($task['executions']); ?>
                                </div>
                                <div class="ms-automation-task-stat-label">تنفيذ</div>
                            </div>
                            <div class="ms-automation-task-stat">
                                <div class="ms-automation-task-stat-value">
                                    <?php echo number_format($task['success_rate']); ?>%
                                </div>
                                <div class="ms-automation-task-stat-label">نجاح</div>
                            </div>
                        </div>
                        <div class="ms-automation-task-actions">
                            <button class="ms-automation-task-action" data-action="run" data-id="<?php echo $task['id']; ?>">
                                تشغيل
                            </button>
                            <button class="ms-automation-task-action" data-action="pause" data-id="<?php echo $task['id']; ?>">
                                إيقاف
                            </button>
                            <button class="ms-automation-task-action" data-action="edit" data-id="<?php echo $task['id']; ?>">
                                تعديل
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Automation Rules Section -->
        <section class="ms-automation-section">
            <div class="ms-automation-section-header">
                <div class="ms-automation-section-title">
                    <span>🔗</span>
                    <span>قواعد الأتمتة</span>
                </div>
                <button class="ms-automation-button ms-automation-button-primary" id="ms-create-automation-rule">
                    <span>➕</span>
                    <span>إنشاء قاعدة</span>
                </button>
            </div>
            
            <div class="ms-automation-rules-container">
                <?php foreach ($automation_rules as $rule) : ?>
                    <div class="ms-automation-rule-card">
                        <div class="ms-automation-rule-header">
                            <div class="ms-automation-rule-name">
                                <?php echo esc_html($rule['name']); ?>
                            </div>
                            <div class="ms-automation-rule-toggle <?php echo $rule['active'] ? 'active' : ''; ?>" 
                                 data-rule="<?php echo $rule['id']; ?>"></div>
                        </div>
                        <div class="ms-automation-rule-content">
                            <div class="ms-automation-rule-trigger">
                                <div class="ms-automation-rule-label">المحفز</div>
                                <div class="ms-automation-rule-value">
                                    <?php echo esc_html($rule['trigger']); ?>
                                </div>
                            </div>
                            <div class="ms-automation-rule-arrow">→</div>
                            <div class="ms-automation-rule-action">
                                <div class="ms-automation-rule-label">الإجراء</div>
                                <div class="ms-automation-rule-value">
                                    <?php echo esc_html($rule['action']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Monitoring Dashboard -->
        <section class="ms-automation-section">
            <div class="ms-automation-section-header">
                <div class="ms-automation-section-title">
                    <span>📡</span>
                    <span>المراقبة المباشرة</span>
                </div>
                <div class="ms-automation-actions">
                    <button class="ms-automation-button ms-automation-button-secondary" id="ms-set-alerts">
                        <span>🔔</span>
                        <span>التنبيهات</span>
                    </button>
                </div>
            </div>
            
            <div class="ms-monitoring-dashboard">
                <?php foreach ($monitoring_data as $metric) : ?>
                    <div class="ms-monitoring-card status-<?php echo $metric['status']; ?>">
                        <div class="ms-monitoring-card-header">
                            <div class="ms-monitoring-card-title">
                                <span><?php echo $metric['icon']; ?></span>
                                <span><?php echo esc_html($metric['name']); ?></span>
                            </div>
                            <div class="ms-monitoring-card-status"></div>
                        </div>
                        <div class="ms-monitoring-metric">
                            <?php echo esc_html($metric['value']); ?>
                        </div>
                        <div class="ms-monitoring-label">
                            <?php echo esc_html($metric['label']); ?>
                        </div>
                        <?php if (isset($metric['chart'])) : ?>
                            <div class="ms-monitoring-chart">
                                <canvas id="ms-monitoring-chart-<?php echo $metric['id']; ?>"></canvas>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($metric['alerts']) && !empty($metric['alerts'])) : ?>
                            <div class="ms-monitoring-alerts">
                                <?php foreach ($metric['alerts'] as $alert) : ?>
                                    <div class="ms-monitoring-alert <?php echo $alert['type']; ?>">
                                        <span><?php echo $alert['icon']; ?></span>
                                        <span><?php echo esc_html($alert['message']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Activity Log -->
        <section class="ms-automation-activity-log">
            <div class="ms-activity-log-header">
                <div class="ms-automation-section-title">
                    <span>📝</span>
                    <span>سجلات النشاط</span>
                </div>
                <button class="ms-automation-button ms-automation-button-secondary" id="ms-export-logs">
                    <span>📥</span>
                    <span>تصدير</span>
                </button>
            </div>
            
            <div class="ms-activity-log-list">
                <?php foreach ($activity_log as $log) : ?>
                    <div class="ms-activity-log-item">
                        <div class="ms-activity-log-icon <?php echo $log['type']; ?>">
                            <?php echo $log['icon']; ?>
                        </div>
                        <div class="ms-activity-log-content">
                            <div class="ms-activity-log-text">
                                <?php echo esc_html($log['message']); ?>
                            </div>
                            <div class="ms-activity-log-time">
                                <?php echo esc_html($log['time']); ?>
                            </div>
                        </div>
                        <?php if (isset($log['details'])) : ?>
                            <button class="ms-automation-task-action" data-action="details" data-id="<?php echo $log['id']; ?>">
                                التفاصيل
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
</div>

<style>
/* Additional inline styles */
.ms-automation-section {
    animation: fadeIn 0.5s ease;
}

.ms-automation-section:nth-child(1) { animation-delay: 0.1s; }
.ms-automation-section:nth-child(2) { animation-delay: 0.2s; }
.ms-automation-section:nth-child(3) { animation-delay: 0.3s; }
.ms-automation-section:nth-child(4) { animation-delay: 0.4s; }

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

<script>
// Automation configuration
const msAutomationConfig = {
    ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
    nonce: '<?php echo wp_create_nonce('ms_automation_nonce'); ?>',
    refreshInterval: 30000 // 30 seconds
};
</script>