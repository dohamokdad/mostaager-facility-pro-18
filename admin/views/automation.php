<?php
/**
 * Automation Dashboard View - Mostaager Facility PRO Add-On
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="ms-admin-container">
    <div class="ms-header">
        <h1>🤖 لوحة تحكم الأتمتة</h1>
        <p class="ms-header-description">إدارة المهام المجدولة وقواعد الأتمتة</p>
    </div>

    <!-- Automation Statistics -->
    <div class="ms-dashboard-grid">
        <div class="ms-stat-card">
            <div class="ms-stat-icon">⏰</div>
            <div class="ms-stat-value"><?php echo count($scheduled_tasks); ?></div>
            <div class="ms-stat-label">المهام المجدولة</div>
        </div>
        
        <div class="ms-stat-card">
            <div class="ms-stat-icon">📋</div>
            <div class="ms-stat-value"><?php echo count($automation_rules); ?></div>
            <div class="ms-stat-label">قواعد الأتمتة</div>
        </div>
        
        <div class="ms-stat-card">
            <div class="ms-stat-icon">✅</div>
            <div class="ms-stat-value"><?php echo $successful_runs; ?></div>
            <div class="ms-stat-label">العمليات الناجحة</div>
        </div>
        
        <div class="ms-stat-card">
            <div class="ms-stat-icon">⚡</div>
            <div class="ms-stat-value"><?php echo $avg_duration; ?>ث</div>
            <div class="ms-stat-label">متوسط المدة</div>
        </div>
    </div>

    <!-- Scheduled Tasks -->
    <div class="ms-card">
        <div class="ms-card-header">
            <h2 class="ms-card-title">⏰ المهام المجدولة</h2>
            <button class="ms-button ms-button-primary" id="ms-create-task">إنشاء مهمة جديدة</button>
        </div>
        <div class="ms-card-body">
            <?php if (!empty($scheduled_tasks)): ?>
                <table class="ms-table">
                    <thead>
                        <tr>
                            <th>اسم المهمة</th>
                            <th>النوع</th>
                            <th>التكرار</th>
                            <th>التشغيل القادم</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scheduled_tasks as $task): ?>
                            <tr>
                                <td><?php echo esc_html($task['name']); ?></td>
                                <td>
                                    <?php
                                    $task_types = array(
                                        'import' => 'استيراد',
                                        'sync' => 'مزامنة',
                                        'notification' => 'إشعار',
                                        'report' => 'تقرير',
                                        'backup' => 'نسخ احتياطي'
                                    );
                                    echo esc_html($task_types[$task['type']] ?? $task['type']);
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $frequencies = array(
                                        'hourly' => 'كل ساعة',
                                        'daily' => 'يومياً',
                                        'weekly' => 'أسبوعياً',
                                        'monthly' => 'شهرياً'
                                    );
                                    echo esc_html($frequencies[$task['frequency']] ?? $task['frequency']);
                                    ?>
                                </td>
                                <td><?php echo esc_html($task['next_run']); ?></td>
                                <td>
                                    <span class="ms-badge <?php echo $task['status'] === 'active' ? 'ms-badge-success' : 'ms-badge-warning'; ?>">
                                        <?php echo $task['status'] === 'active' ? 'نشط' : 'معطل'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="ms-button ms-button-outline ms-run-task" data-task-id="<?php echo esc_attr($task['id']); ?>">
                                        تشغيل
                                    </button>
                                    <button class="ms-button ms-button-outline ms-edit-task" data-task-id="<?php echo esc_attr($task['id']); ?>">
                                        تعديل
                                    </button>
                                    <button class="ms-button ms-button-danger ms-delete-task" data-task-id="<?php echo esc_attr($task['id']); ?>">
                                        حذف
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="ms-empty-state">
                    <div class="ms-empty-state-icon">⏰</div>
                    <h3 class="ms-empty-state-title">لا توجد مهام مجدولة</h3>
                    <p class="ms-empty-state-description">أنشئ مهمتك الأولى للأتمتة</p>
                    <button class="ms-button ms-button-primary" id="ms-create-task-empty">
                        إنشاء مهمة جديدة
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Automation Rules -->
    <div class="ms-card">
        <div class="ms-card-header">
            <h2 class="ms-card-title">📋 قواعد الأتمتة</h2>
            <button class="ms-button ms-button-primary" id="ms-create-rule">إنشاء قاعدة جديدة</button>
        </div>
        <div class="ms-card-body">
            <?php if (!empty($automation_rules)): ?>
                <table class="ms-table">
                    <thead>
                        <tr>
                            <th>اسم القاعدة</th>
                            <th>المحفز</th>
                            <th>الشروط</th>
                            <th>الإجراءات</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($automation_rules as $rule): ?>
                            <tr>
                                <td><?php echo esc_html($rule['name']); ?></td>
                                <td>
                                    <?php
                                    $triggers = array(
                                        'property_updated' => 'تحديث العقار',
                                        'import_complete' => 'اكتمال الاستيراد',
                                        'tenant_assigned' => 'تعيين مستأجر',
                                        'invoice_created' => 'إنشاء فاتورة'
                                    );
                                    echo esc_html($triggers[$rule['trigger']] ?? $rule['trigger']);
                                    ?>
                                </td>
                                <td><?php echo count($rule['conditions']); ?> شرط</td>
                                <td><?php echo count($rule['actions']); ?> إجراء</td>
                                <td>
                                    <span class="ms-badge <?php echo $rule['active'] ? 'ms-badge-success' : 'ms-badge-warning'; ?>">
                                        <?php echo $rule['active'] ? 'نشط' : 'معطل'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="ms-button ms-button-outline ms-trigger-rule" data-rule-id="<?php echo esc_attr($rule['id']); ?>">
                                        تشغيل
                                    </button>
                                    <button class="ms-button ms-button-outline ms-edit-rule" data-rule-id="<?php echo esc_attr($rule['id']); ?>">
                                        تعديل
                                    </button>
                                    <button class="ms-button ms-button-danger ms-delete-rule" data-rule-id="<?php echo esc_attr($rule['id']); ?>">
                                        حذف
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="ms-empty-state">
                    <div class="ms-empty-state-icon">📋</div>
                    <h3 class="ms-empty-state-title">لا توجد قواعد أتمتة</h3>
                    <p class="ms-empty-state-description">أنشئ قاعدة أتمتتك الأولى</p>
                    <button class="ms-button ms-button-primary" id="ms-create-rule-empty">
                        إنشاء قاعدة جديدة
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Automation Logs -->
    <div class="ms-card">
        <div class="ms-card-header">
            <h2 class="ms-card-title">📊 سجلات الأتمتة</h2>
            <button class="ms-button ms-button-outline" id="ms-clear-logs">مسح السجلات</button>
        </div>
        <div class="ms-card-body">
            <?php if (!empty($automation_logs)): ?>
                <table class="ms-table">
                    <thead>
                        <tr>
                            <th>المهمة/القاعدة</th>
                            <th>النوع</th>
                            <th>وقت التنفيذ</th>
                            <th>الحالة</th>
                            <th>الرسائل</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($automation_logs, 0, 10) as $log): ?>
                            <tr>
                                <td><?php echo esc_html($log['task_name']); ?></td>
                                <td><?php echo esc_html($log['task_type']); ?></td>
                                <td><?php echo esc_html($log['executed_at']); ?></td>
                                <td>
                                    <span class="ms-badge <?php echo $log['success'] ? 'ms-badge-success' : 'ms-badge-error'; ?>">
                                        <?php echo $log['success'] ? 'نجح' : 'فشل'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo implode(', ', array_slice($log['messages'], 0, 2)); ?>
                                    <?php if (count($log['messages']) > 2): ?>
                                        <span class="ms-more-messages">+<?php echo count($log['messages']) - 2; ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="ms-empty-state">
                    <div class="ms-empty-state-icon">📊</div>
                    <h3 class="ms-empty-state-title">لا توجد سجلات</h3>
                    <p class="ms-empty-state-description">ستظهر سجلات الأتمتة هنا</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Automation Charts -->
    <div class="ms-dashboard-grid" style="margin-top: 20px;">
        <div class="ms-card">
            <div class="ms-card-header">
                <h2 class="ms-card-title">📈 أداء الأتمتة</h2>
            </div>
            <div class="ms-card-body">
                <canvas id="msAutomationPerformance" height="200"></canvas>
            </div>
        </div>
        
        <div class="ms-card">
            <div class="ms-card-header">
                <h2 class="ms-card-title">📊 توزيع المهام</h2>
            </div>
            <div class="ms-card-body">
                <canvas id="msTaskDistribution" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Task Modal -->
<div class="ms-modal ms-task-modal" style="display: none;">
    <div class="ms-modal-content">
        <div class="ms-modal-header">
            <h3>إنشاء/تعديل مهمة مجدولة</h3>
            <button class="ms-modal-close">&times;</button>
        </div>
        <div class="ms-modal-body">
            <form id="ms-task-form">
                <?php wp_nonce_field('ms_create_task', 'nonce'); ?>
                
                <div class="ms-form-group">
                    <label class="ms-form-label">اسم المهمة</label>
                    <input type="text" name="task_name" class="ms-form-input" required>
                </div>
                
                <div class="ms-form-group">
                    <label class="ms-form-label">نوع المهمة</label>
                    <select name="task_type" class="ms-form-select" required>
                        <option value="import">استيراد</option>
                        <option value="sync">مزامنة</option>
                        <option value="notification">إشعار</option>
                        <option value="report">تقرير</option>
                        <option value="backup">نسخ احتياطي</option>
                    </select>
                </div>
                
                <div class="ms-form-group">
                    <label class="ms-form-label">التكرار</label>
                    <select name="frequency" class="ms-form-select" required>
                        <option value="hourly">كل ساعة</option>
                        <option value="daily">يومياً</option>
                        <option value="weekly">أسبوعياً</option>
                        <option value="monthly">شهرياً</option>
                    </select>
                </div>
                
                <div class="ms-form-group">
                    <label class="ms-form-label">الإعدادات (JSON)</label>
                    <textarea name="task_config" class="ms-form-textarea" rows="5"></textarea>
                </div>
            </form>
        </div>
        <div class="ms-modal-footer">
            <button class="ms-button ms-button-outline ms-modal-cancel">إلغاء</button>
            <button class="ms-button ms-button-primary ms-save-task">حفظ</button>
        </div>
    </div>
</div>

<!-- Rule Modal -->
<div class="ms-modal ms-rule-modal" style="display: none;">
    <div class="ms-modal-content">
        <div class="ms-modal-header">
            <h3>إنشاء/تعديل قاعدة أتمتة</h3>
            <button class="ms-modal-close">&times;</button>
        </div>
        <div class="ms-modal-body">
            <form id="ms-rule-form">
                <?php wp_nonce_field('ms_create_automation_rule', 'nonce'); ?>
                
                <div class="ms-form-group">
                    <label class="ms-form-label">اسم القاعدة</label>
                    <input type="text" name="rule_name" class="ms-form-input" required>
                </div>
                
                <div class="ms-form-group">
                    <label class="ms-form-label">المحفز</label>
                    <select name="trigger" class="ms-form-select" required>
                        <option value="property_updated">تحديث العقار</option>
                        <option value="import_complete">اكتمال الاستيراد</option>
                        <option value="tenant_assigned">تعيين مستأجر</option>
                        <option value="invoice_created">إنشاء فاتورة</option>
                    </select>
                </div>
                
                <div class="ms-form-group">
                    <label class="ms-form-label">الشروط (JSON)</label>
                    <textarea name="conditions" class="ms-form-textarea" rows="5"></textarea>
                </div>
                
                <div class="ms-form-group">
                    <label class="ms-form-label">الإجراءات (JSON)</label>
                    <textarea name="actions" class="ms-form-textarea" rows="5"></textarea>
                </div>
            </form>
        </div>
        <div class="ms-modal-footer">
            <button class="ms-button ms-button-outline ms-modal-cancel">إلغاء</button>
            <button class="ms-button ms-button-primary ms-save-rule">حفظ</button>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Load automation data
    let scheduledTasks = <?php echo json_encode($scheduled_tasks); ?>;
    let automationRules = <?php echo json_encode($automation_rules); ?>;
    let automationLogs = <?php echo json_encode($automation_logs); ?>;
    
    // Task modal
    $('#ms-create-task, #ms-create-task-empty').on('click', function() {
        $('.ms-task-modal').show();
    });
    
    // Rule modal
    $('#ms-create-rule, #ms-create-rule-empty').on('click', function() {
        $('.ms-rule-modal').show();
    });
    
    // Close modals
    $('.ms-modal-close, .ms-modal-cancel').on('click', function() {
        $('.ms-modal').hide();
    });
    
    // Run task
    $('.ms-run-task').on('click', function() {
        const taskId = $(this).data('task-id');
        
        $.ajax({
            url: msAddonData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ms_trigger_automation',
                nonce: msAddonData.nonce,
                task_id: taskId
            },
            success: function(response) {
                if (response.success) {
                    msShowNotification('تم تشغيل المهمة بنجاح', 'success');
                } else {
                    msShowNotification('فشل تشغيل المهمة', 'error');
                }
            }
        });
    });
    
    // Trigger rule
    $('.ms-trigger-rule').on('click', function() {
        const ruleId = $(this).data('rule-id');
        
        $.ajax({
            url: msAddonData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ms_trigger_automation',
                nonce: msAddonData.nonce,
                rule_id: ruleId
            },
            success: function(response) {
                if (response.success) {
                    msShowNotification('تم تشغيل القاعدة بنجاح', 'success');
                } else {
                    msShowNotification('فشل تشغيل القاعدة', 'error');
                }
            }
        });
    });
    
    // Initialize charts
    if (typeof Chart !== 'undefined') {
        // Automation performance chart
        const performanceCtx = document.getElementById('msAutomationPerformance');
        if (performanceCtx) {
            new Chart(performanceCtx, {
                type: 'line',
                data: {
                    labels: ['00:00', '04:00', '08:00', '12:00', '16:00', '20:00'],
                    datasets: [{
                        label: 'المهام المنفذة',
                        data: [5, 12, 8, 15, 10, 7],
                        borderColor: '#0073aa',
                        backgroundColor: 'rgba(0, 115, 170, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'top' }
                    }
                }
            });
        }
        
        // Task distribution chart
        const distributionCtx = document.getElementById('msTaskDistribution');
        if (distributionCtx) {
            new Chart(distributionCtx, {
                type: 'doughnut',
                data: {
                    labels: ['استيراد', 'مزامنة', 'إشعار', 'تقرير', 'نسخ احتياطي'],
                    datasets: [{
                        data: [8, 5, 12, 3, 2],
                        backgroundColor: [
                            '#0073aa',
                            '#00a0d2',
                            '#46b450',
                            '#ffb900',
                            '#dc3232'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'right' }
                    }
                }
            });
        }
    }
});
</script>
