<?php
/**
 * Logs View - Mostaager Facility PRO Add-On
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="ms-admin-container">
    <div class="ms-header">
        <h1>📊 سجلات الاستيراد</h1>
        <p class="ms-header-description">سجل كامل لعمليات الاستيراد والأداء</p>
    </div>

    <div class="ms-card">
        <div class="ms-card-header">
            <h2 class="ms-card-title">سجل العمليات</h2>
            <div class="ms-card-actions">
                <select class="ms-form-select" id="ms-log-filter">
                    <option value="all">جميع العمليات</option>
                    <option value="success">العمليات الناجحة</option>
                    <option value="failed">العمليات الفاشلة</option>
                    <option value="pending">العمليات قيد المعالجة</option>
                </select>
                <button class="ms-button ms-button-outline" id="ms-export-logs">تصدير</button>
            </div>
        </div>
        <div class="ms-card-body">
            <?php if (!empty($logs)): ?>
                <table class="ms-table">
                    <thead>
                        <tr>
                            <th>رقم العملية</th>
                            <th>المستخدم</th>
                            <th>الملف</th>
                            <th>السجلات</th>
                            <th>الحالة</th>
                            <th>المدة</th>
                            <th>التاريخ</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>#<?php echo $log->id; ?></td>
                                <td><?php echo esc_html($log->user_id ? get_userdata($log->user_id)->display_name : 'نظام'); ?></td>
                                <td><?php echo esc_html($log->file_name); ?></td>
                                <td>
                                    <?php echo $log->records_processed; ?> معالجة
                                    <?php if ($log->success_count > 0): ?>
                                        <span class="ms-badge ms-badge-success"><?php echo $log->success_count; ?> نجح</span>
                                    <?php endif; ?>
                                    <?php if ($log->errors_count > 0): ?>
                                        <span class="ms-badge ms-badge-error"><?php echo $log->errors_count; ?> خطأ</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($log->status === 'success'): ?>
                                        <span class="ms-badge ms-badge-success">نجح</span>
                                    <?php elseif ($log->status === 'failed'): ?>
                                        <span class="ms-badge ms-badge-error">فشل</span>
                                    <?php else: ?>
                                        <span class="ms-badge ms-badge-warning">قيد المعالجة</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $log->duration; ?> ثانية</td>
                                <td><?php echo $log->created_at; ?></td>
                                <td>
                                    <button class="ms-button ms-button-outline ms-view-log" data-log-id="<?php echo $log->id; ?>">
                                        عرض التفاصيل
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="ms-empty-state">
                    <div class="ms-empty-state-icon">📭</div>
                    <h3 class="ms-empty-state-title">لا توجد سجلات</h3>
                    <p class="ms-empty-state-description">ابدأ بأول عملية استيراد لإنشاء سجلات</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Log Details Modal -->
<div class="ms-modal ms-log-modal" style="display: none;">
    <div class="ms-modal-content">
        <div class="ms-modal-header">
            <h3>تفاصيل العملية</h3>
            <button class="ms-modal-close">&times;</button>
        </div>
        <div class="ms-modal-body">
            <div id="ms-log-details">
                <!-- Log details will be loaded here -->
            </div>
        </div>
        <div class="ms-modal-footer">
            <button class="ms-button ms-button-outline ms-modal-close">إغلاق</button>
        </div>
    </div>
</div>
