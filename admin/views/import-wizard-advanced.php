<?php
/**
 * Mostaager Facility PRO - Advanced Import Wizard Template
 * Step-by-step import process with validation and preview
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get import data
$templates = $this->get_import_templates();
$file_columns = $this->get_file_columns();
$target_fields = $this->get_target_fields();
$preview_data = $this->get_preview_data();
?>

<div class="ms-import-wizard-advanced">
    <!-- ===== WIZARD HEADER ===== -->
    <header class="ms-import-wizard-header">
        <h1 class="ms-import-wizard-title">معالج الاستيراد المتقدم</h1>
        <p class="ms-import-wizard-description">استيراد البيانات بسهولة وأمان عبر 4 خطوات بسيطة</p>
    </header>

    <!-- ===== WIZARD STEPS ===== -->
    <div class="ms-import-wizard-steps">
        <div class="ms-import-wizard-step active" data-step="1">
            <div class="ms-import-step-number">1</div>
            <div class="ms-import-step-label">رفع الملف</div>
        </div>
        <div class="ms-import-wizard-step" data-step="2">
            <div class="ms-import-step-number">2</div>
            <div class="ms-import-step-label">اختيار القالب</div>
        </div>
        <div class="ms-import-wizard-step" data-step="3">
            <div class="ms-import-step-number">3</div>
            <div class="ms-import-step-label">ربط الحقول</div>
        </div>
        <div class="ms-import-wizard-step" data-step="4">
            <div class="ms-import-step-number">4</div>
            <div class="ms-import-step-label">المعاينة</div>
        </div>
    </div>

    <!-- ===== WIZARD CONTENT ===== -->
    <div class="ms-import-wizard-content">
        <!-- Step 1: File Upload -->
        <div class="ms-import-step-content active" data-step="1">
            <div class="ms-import-file-upload" id="ms-file-upload">
                <div class="ms-import-file-upload-icon">📁</div>
                <div class="ms-import-file-upload-title">اسحب الملف هنا أو انقر للاختيار</div>
                <div class="ms-import-file-upload-description">
                    يدعم ملفات CSV, Excel, XML بحجم حتى 10MB
                </div>
                <button class="ms-import-file-upload-button" id="ms-select-file">
                    <span>📂</span>
                    <span>اختيار ملف</span>
                </button>
                <input type="file" id="ms-file-input" accept=".csv,.xlsx,.xls,.xml" style="display: none;">
            </div>
            
            <div class="ms-import-file-info" id="ms-file-info">
                <div class="ms-import-file-info-icon">📄</div>
                <div class="ms-import-file-info-name" id="ms-file-name">اسم الملف.csv</div>
                <div class="ms-import-file-info-size" id="ms-file-size">2.5 MB</div>
                <button class="ms-import-file-info-remove" id="ms-remove-file">×</button>
            </div>
        </div>

        <!-- Step 2: Template Selection -->
        <div class="ms-import-step-content" data-step="2">
            <div class="ms-import-templates-grid">
                <?php foreach ($templates as $template) : ?>
                    <div class="ms-import-template-card" data-template="<?php echo esc_attr($template['id']); ?>">
                        <div class="ms-import-template-icon"><?php echo $template['icon']; ?></div>
                        <div class="ms-import-template-name"><?php echo esc_html($template['name']); ?></div>
                        <div class="ms-import-template-description">
                            <?php echo esc_html($template['description']); ?>
                        </div>
                        <div class="ms-import-template-features">
                            <?php foreach ($template['features'] as $feature) : ?>
                                <span class="ms-import-template-feature">
                                    <?php echo esc_html($feature); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Step 3: Field Mapping -->
        <div class="ms-import-step-content" data-step="3">
            <div class="ms-import-auto-map">
                <div class="ms-import-auto-map-icon">🔗</div>
                <div class="ms-import-auto-map-text">
                    الربط التلقائي للحقول المتطابقة
                </div>
                <button class="ms-import-auto-map-button" id="ms-auto-map">
                    ربط تلقائي
                </button>
            </div>
            
            <div class="ms-import-field-mapping">
                <div class="ms-import-mapping-columns">
                    <div class="ms-import-mapping-title">
                        <span>📋</span>
                        <span>أعمدة الملف</span>
                    </div>
                    <div id="ms-file-columns">
                        <?php foreach ($file_columns as $column) : ?>
                            <div class="ms-import-mapping-item" draggable="true" data-column="<?php echo esc_attr($column); ?>">
                                <span class="ms-import-mapping-item-icon">⋮⋮</span>
                                <span class="ms-import-mapping-item-name"><?php echo esc_html($column); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="ms-import-mapping-targets">
                    <div class="ms-import-mapping-title">
                        <span>🎯</span>
                        <span>حقول النظام</span>
                    </div>
                    <div id="ms-target-fields">
                        <?php foreach ($target_fields as $field) : ?>
                            <div class="ms-import-mapping-item">
                                <span class="ms-import-mapping-item-name"><?php echo esc_html($field['label']); ?></span>
                                <select class="ms-import-mapping-select" data-field="<?php echo esc_attr($field['name']); ?>">
                                    <option value="">-- اختر العمود --</option>
                                    <?php foreach ($file_columns as $column) : ?>
                                        <option value="<?php echo esc_attr($column); ?>">
                                            <?php echo esc_html($column); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 4: Preview -->
        <div class="ms-import-step-content" data-step="4">
            <div class="ms-import-preview-stats">
                <div class="ms-import-preview-stat">
                    <div class="ms-import-preview-stat-value">
                        <?php echo number_format($preview_data['total_rows']); ?>
                    </div>
                    <div class="ms-import-preview-stat-label">إجمالي الصفوف</div>
                </div>
                <div class="ms-import-preview-stat">
                    <div class="ms-import-preview-stat-value">
                        <?php echo number_format($preview_data['valid_rows']); ?>
                    </div>
                    <div class="ms-import-preview-stat-label">صفوف صالحة</div>
                </div>
                <div class="ms-import-preview-stat">
                    <div class="ms-import-preview-stat-value">
                        <?php echo number_format($preview_data['invalid_rows']); ?>
                    </div>
                    <div class="ms-import-preview-stat-label">صفوف غير صالحة</div>
                </div>
                <div class="ms-import-preview-stat">
                    <div class="ms-import-preview-stat-value">
                        <?php echo number_format($preview_data['duplicates']); ?>
                    </div>
                    <div class="ms-import-preview-stat-label">مكررات</div>
                </div>
            </div>
            
            <div class="ms-import-validation-results <?php echo $preview_data['has_errors'] ? 'has-errors' : ''; ?>">
                <div class="ms-import-validation-title">
                    <span><?php echo $preview_data['has_errors'] ? '⚠️' : '✓'; ?></span>
                    <span>نتائج التحقق</span>
                </div>
                <?php foreach ($preview_data['validation'] as $item) : ?>
                    <div class="ms-import-validation-item <?php echo $item['type']; ?>">
                        <span><?php echo $item['type'] === 'error' ? '✕' : '✓'; ?></span>
                        <span><?php echo esc_html($item['message']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="ms-import-preview-table-container">
                <table class="ms-import-preview-table">
                    <thead>
                        <tr>
                            <?php foreach ($preview_data['columns'] as $column) : ?>
                                <th><?php echo esc_html($column); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($preview_data['rows'] as $row) : ?>
                            <tr>
                                <?php foreach ($row as $cell) : ?>
                                    <td><?php echo esc_html($cell); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ===== WIZARD ACTIONS ===== -->
    <div class="ms-import-wizard-actions">
        <button class="ms-import-wizard-button ms-import-wizard-button-secondary" id="ms-prev-step" disabled>
            السابق
        </button>
        
        <div class="ms-import-wizard-progress">
            <div class="ms-import-progress-bar">
                <div class="ms-import-progress-fill" style="width: 25%;"></div>
            </div>
            <div class="ms-import-progress-text">الخطوة 1 من 4</div>
        </div>
        
        <div class="ms-import-wizard-buttons">
            <button class="ms-import-wizard-button ms-import-wizard-button-secondary" id="ms-cancel-import">
                إلغاء
            </button>
            <button class="ms-import-wizard-button ms-import-wizard-button-primary" id="ms-next-step">
                التالي
            </button>
            <button class="ms-import-wizard-button ms-import-wizard-button-primary" id="ms-start-import" style="display: none;">
                بدء الاستيراد
            </button>
        </div>
    </div>
</div>

<style>
.ms-import-preview-table-container {
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid var(--ms-import-border);
    border-radius: 10px;
}
</style>

<script>
// Import wizard configuration
const msImportConfig = {
    maxFileSize: 10 * 1024 * 1024, // 10MB
    allowedTypes: ['csv', 'xlsx', 'xls', 'xml'],
    ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
    nonce: '<?php echo wp_create_nonce('ms_import_nonce'); ?>'
};
</script>