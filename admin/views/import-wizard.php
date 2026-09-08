<?php
/**
 * Import Wizard View - Mostaager Facility PRO Add-On
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="ms-admin-container">
    <div class="ms-header">
        <h1>📥 استيراد بيانات جديدة</h1>
        <p class="ms-header-description">معالج مبسط لاستيراد البيانات من ملفات XML أو CSV</p>
    </div>

    <div class="ms-card ms-import-wizard">
        <!-- Wizard Steps -->
        <div class="ms-wizard-steps">
            <div class="ms-wizard-step active" data-step="1">
                <div class="ms-wizard-step-number">1</div>
                <div class="ms-wizard-step-label">اختيار المصدر</div>
            </div>
            <div class="ms-wizard-step" data-step="2">
                <div class="ms-wizard-step-number">2</div>
                <div class="ms-wizard-step-label">ربط الحقول</div>
            </div>
            <div class="ms-wizard-step" data-step="3">
                <div class="ms-wizard-step-number">3</div>
                <div class="ms-wizard-step-label">المعاينة</div>
            </div>
            <div class="ms-wizard-step" data-step="4">
                <div class="ms-wizard-step-number">4</div>
                <div class="ms-wizard-step-label">الاستيراد</div>
            </div>
        </div>

        <!-- Step 1: Source Selection -->
        <div class="ms-wizard-content" data-step="1">
            <h2>اختر مصدر البيانات</h2>
            
            <div class="ms-form-group">
                <label class="ms-form-label">طريقة الاستيراد</label>
                <div class="ms-source-options">
                    <label class="ms-source-option">
                        <input type="radio" name="import_method" value="file" checked>
                        <span class="ms-source-icon">📁</span>
                        <span class="ms-source-text">رفع ملف (XML/CSV)</span>
                    </label>
                    <label class="ms-source-option">
                        <input type="radio" name="import_method" value="url">
                        <span class="ms-source-icon">🔗</span>
                        <span class="ms-source-text">رابط URL خارجي</span>
                    </label>
                </div>
            </div>

            <div class="ms-form-group" id="ms-file-upload">
                <label class="ms-form-label">اختر الملف</label>
                <input type="file" class="ms-form-input ms-file-input" accept=".xml,.csv,.json">
                <p class="ms-form-help">يدعم ملفات XML و CSV و JSON</p>
            </div>

            <div class="ms-form-group" id="ms-url-input" style="display: none;">
                <label class="ms-form-label">رابط URL</label>
                <input type="url" class="ms-form-input" placeholder="https://example.com/data.xml">
                <p class="ms-form-help">أدخل رابط مباشر للملف</p>
            </div>

            <div class="ms-form-group">
                <label class="ms-form-label">أو اختر قالب جاهز</label>
                <div class="ms-template-grid">
                    <?php foreach ($templates as $template): ?>
                        <div class="ms-template-card" data-template-id="<?php echo esc_attr($template['id']); ?>">
                            <div class="ms-template-icon"><?php echo $template['icon']; ?></div>
                            <h3 class="ms-template-title"><?php echo esc_html($template['name']); ?></h3>
                            <p class="ms-template-description"><?php echo esc_html($template['description']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="ms-wizard-actions">
                <button class="ms-button ms-button-primary ms-wizard-next">التالي</button>
            </div>
        </div>

        <!-- Step 2: Field Mapping -->
        <div class="ms-wizard-content" data-step="2" style="display: none;">
            <h2>ربط الحقول</h2>
            
            <div class="ms-form-group">
                <button class="ms-button ms-button-secondary ms-auto-map">
                    🔗 ربط تلقائي
                </button>
            </div>

            <div class="ms-field-mapping">
                <!-- Field mapping will be dynamically generated -->
                <div class="ms-field-row">
                    <div class="ms-field-source">
                        <select class="ms-form-select ms-source-field">
                            <option value="">اختر الحقل من الملف</option>
                            <option value="building_name">اسم المبنى</option>
                            <option value="unit_number">رقم الوحدة</option>
                            <option value="floor">الطابق</option>
                            <option value="unit_type">نوع الوحدة</option>
                            <option value="tenant_name">اسم المستأجر</option>
                            <option value="monthly_rent">الإيجار الشهري</option>
                        </select>
                    </div>
                    <div class="ms-field-arrow">→</div>
                    <div class="ms-field-destination">
                        <span class="ms-field-name ms-required-field">ms_building_title</span>
                    </div>
                </div>
                
                <div class="ms-field-row">
                    <div class="ms-field-source">
                        <select class="ms-form-select ms-source-field">
                            <option value="">اختر الحقل من الملف</option>
                            <option value="building_name">اسم المبنى</option>
                            <option value="unit_number">رقم الوحدة</option>
                            <option value="floor">الطابق</option>
                            <option value="unit_type">نوع الوحدة</option>
                            <option value="tenant_name">اسم المستأجر</option>
                            <option value="monthly_rent">الإيجار الشهري</option>
                        </select>
                    </div>
                    <div class="ms-field-arrow">→</div>
                    <div class="ms-field-destination">
                        <span class="ms-field-name ms-required-field">ms_unit_number</span>
                    </div>
                </div>
                
                <div class="ms-field-row">
                    <div class="ms-field-source">
                        <select class="ms-form-select ms-source-field">
                            <option value="">اختر الحقل من الملف</option>
                            <option value="building_name">اسم المبنى</option>
                            <option value="unit_number">رقم الوحدة</option>
                            <option value="floor">الطابق</option>
                            <option value="unit_type">نوع الوحدة</option>
                            <option value="tenant_name">اسم المستأجر</option>
                            <option value="monthly_rent">الإيجار الشهري</option>
                        </select>
                    </div>
                    <div class="ms-field-arrow">→</div>
                    <div class="ms-field-destination">
                        <span class="ms-field-name">ms_floor</span>
                    </div>
                </div>
            </div>

            <div class="ms-wizard-actions">
                <button class="ms-button ms-button-outline ms-wizard-prev">السابق</button>
                <button class="ms-button ms-button-primary ms-wizard-next">التالي</button>
            </div>
        </div>

        <!-- Step 3: Preview -->
        <div class="ms-wizard-content" data-step="3" style="display: none;">
            <h2>معاينة والتحقق من البيانات</h2>
            
            <div class="ms-import-preview">
                <p class="ms-text-center">سيتم عرض معاينة البيانات هنا بعد ربط الحقول</p>
            </div>

            <div class="ms-validation-summary" style="margin-top: 20px;">
                <h3>ملخص التحقق</h3>
                <div class="ms-validation-stats">
                    <div class="ms-validation-item">
                        <span class="ms-validation-icon ms-success">✅</span>
                        <span class="ms-validation-text">0 سجل صحيح</span>
                    </div>
                    <div class="ms-validation-item">
                        <span class="ms-validation-icon ms-warning">⚠️</span>
                        <span class="ms-validation-text">0 سجل مع تنبيهات</span>
                    </div>
                    <div class="ms-validation-item">
                        <span class="ms-validation-icon ms-error">❌</span>
                        <span class="ms-validation-text">0 سجل غير صحيح</span>
                    </div>
                </div>
            </div>

            <div class="ms-wizard-actions">
                <button class="ms-button ms-button-outline ms-wizard-prev">السابق</button>
                <button class="ms-button ms-button-primary ms-wizard-next">التالي</button>
            </div>
        </div>

        <!-- Step 4: Import -->
        <div class="ms-wizard-content" data-step="4" style="display: none;">
            <h2>بدء الاستيراد</h2>
            
            <div class="ms-import-summary">
                <h3>ملخص الاستيراد</h3>
                <ul class="ms-summary-list">
                    <li><strong>الملف:</strong> <span id="ms-summary-file">-</span></li>
                    <li><strong>القالب:</strong> <span id="ms-summary-template">-</span></li>
                    <li><strong>عدد السجلات:</strong> <span id="ms-summary-records">-</span></li>
                    <li><strong>الحقول المربوطة:</strong> <span id="ms-summary-fields">-</span></li>
                </ul>
            </div>

            <div class="ms-import-progress" style="display: none; margin: 30px 0;">
                <div class="ms-progress-bar">
                    <div class="ms-progress-bar-fill" style="width: 0%"></div>
                </div>
                <p class="ms-import-status text-center" style="margin-top: 10px;">جاري الاستيراد...</p>
            </div>

            <div class="ms-import-complete" style="display: none; text-align: center;">
                <div class="ms-import-success-icon" style="font-size: 64px; margin-bottom: 20px;">✅</div>
                <h3>تم الاستيراد بنجاح!</h3>
                <p>تم استيراد البيانات بنجاح إلى النظام</p>
                <div style="margin-top: 20px;">
                    <a href="<?php echo admin_url('admin.php?page=ms-import-dashboard'); ?>" class="ms-button ms-button-primary">
                        العودة للوحة التحكم
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=ms-import-new'); ?>" class="ms-button ms-button-outline">
                        استيراد جديد
                    </a>
                </div>
            </div>

            <div class="ms-wizard-actions" id="ms-import-actions">
                <button class="ms-button ms-button-outline ms-wizard-prev">السابق</button>
                <button class="ms-button ms-button-success ms-start-import">بدء الاستيراد</button>
            </div>
        </div>
    </div>
</div>
