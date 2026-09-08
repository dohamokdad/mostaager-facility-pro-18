<?php
/**
 * Templates View - Mostaager Facility PRO Add-On
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="ms-admin-container">
    <div class="ms-header">
        <h1>📋 القوالب الجاهزة</h1>
        <p class="ms-header-description">إدارة واستخدام قوالب الاستيراد الجاهزة</p>
    </div>

    <div class="ms-card">
        <div class="ms-card-header">
            <h2 class="ms-card-title">القوالب المتاحة</h2>
            <button class="ms-button ms-button-primary ms-create-template">إنشاء قالب جديد</button>
        </div>
        <div class="ms-card-body">
            <?php if (!empty($templates)): ?>
                <div class="ms-template-grid">
                    <?php foreach ($templates as $template): ?>
                        <div class="ms-template-card">
                            <div class="ms-template-icon">📋</div>
                            <h3 class="ms-template-title"><?php echo esc_html($template['name']); ?></h3>
                            <p class="ms-template-description"><?php echo esc_html($template['description']); ?></p>
                            <div class="ms-template-meta">
                                <span>الحقول: <?php echo count($template['fields']); ?></span>
                            </div>
                            <div class="ms-template-actions">
                                <button class="ms-button ms-button-primary ms-template-action" data-action="use" data-template-id="<?php echo esc_attr($template['id']); ?>">
                                    استخدام
                                </button>
                                <button class="ms-button ms-button-outline ms-template-action" data-action="edit" data-template-id="<?php echo esc_attr($template['id']); ?>">
                                    تعديل
                                </button>
                                <button class="ms-button ms-button-danger ms-template-action" data-action="delete" data-template-id="<?php echo esc_attr($template['id']); ?>">
                                    حذف
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="ms-empty-state">
                    <div class="ms-empty-state-icon">📋</div>
                    <h3 class="ms-empty-state-title">لا توجد قوالب مخصصة</h3>
                    <p class="ms-empty-state-description">أنشئ قالبك الأول للاستيراد السريع</p>
                    <button class="ms-button ms-button-primary ms-create-template">
                        إنشاء قالب جديد
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Template Modal -->
    <div class="ms-modal ms-template-modal" style="display: none;">
        <div class="ms-modal-content">
            <div class="ms-modal-header">
                <h3>إنشاء/تعديل قالب</h3>
                <button class="ms-modal-close">&times;</button>
            </div>
            <div class="ms-modal-body">
                <form id="ms-template-form">
                    <div class="ms-form-group">
                        <label class="ms-form-label">اسم القالب</label>
                        <input type="text" id="ms_template_name" class="ms-form-input" required>
                    </div>
                    <div class="ms-form-group">
                        <label class="ms-form-label">الوصف</label>
                        <textarea id="ms_template_description" class="ms-form-textarea"></textarea>
                    </div>
                    <div class="ms-form-group">
                        <label class="ms-form-label">الحقول (JSON)</label>
                        <textarea id="ms_template_fields" class="ms-form-textarea" rows="5"></textarea>
                        <p class="ms-form-help">أدخل الحقول بصيغة JSON</p>
                    </div>
                </form>
            </div>
            <div class="ms-modal-footer">
                <button class="ms-button ms-button-outline ms-modal-cancel">إلغاء</button>
                <button class="ms-button ms-button-primary ms-save-template">حفظ</button>
            </div>
        </div>
    </div>
</div>
