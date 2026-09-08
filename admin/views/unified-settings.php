<?php
/**
 * Unified Settings View - Mostaager Facility PRO
 * صفحة الإعدادات الموحدة مع تبويبات منظمة
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap ms-unified-settings">
    <div class="ms-header">
        <h1>⚙️ إعدادات إدارة المرافق</h1>
        <p class="ms-header-description">إدارة شاملة لجميع إعدادات النظام في مكان واحد</p>
    </div>
    
    <div class="ms-settings-container">
        <!-- Settings Navigation Tabs -->
        <div class="ms-settings-nav">
            <?php foreach ($settings_sections as $section_key => $section): ?>
                <button class="ms-settings-nav-btn <?php echo $section_key === 'general' ? 'active' : ''; ?>" data-section="<?php echo $section_key; ?>">
                    <span class="ms-section-icon"><?php echo $section['icon']; ?></span>
                    <span class="ms-section-title"><?php echo $section['title']; ?></span>
                </button>
            <?php endforeach; ?>
        </div>
        
        <!-- Settings Content -->
        <div class="ms-settings-content">
            <?php foreach ($settings_sections as $section_key => $section): ?>
                <div class="ms-settings-section <?php echo $section_key === 'general' ? 'active' : ''; ?>" id="ms-section-<?php echo $section_key; ?>">
                    <div class="ms-section-header">
                        <h2><?php echo $section['icon'] . ' ' . $section['title']; ?></h2>
                        <p><?php echo $section['description']; ?></p>
                    </div>
                    
                    <form class="ms-settings-form" data-section="<?php echo $section_key; ?>">
                        <?php wp_nonce_field('ms_save_unified_settings', 'nonce'); ?>
                        
                        <?php foreach ($section['fields'] as $field_key => $field): ?>
                            <div class="ms-form-group">
                                <label for="<?php echo $section_key . '_' . $field_key; ?>">
                                    <?php echo $field['label']; ?>
                                </label>
                                
                                <?php
                                $field_value = isset($current_settings[$section_key . '_' . $field_key]) 
                                    ? $current_settings[$section_key . '_' . $field_key] 
                                    : $field['default'];
                                ?>
                                
                                <?php switch ($field['type']): 
                                    case 'text': ?>
                                        <input 
                                            type="text" 
                                            id="<?php echo $section_key . '_' . $field_key; ?>" 
                                            name="<?php echo $field_key; ?>" 
                                            value="<?php echo esc_attr($field_value); ?>"
                                            class="ms-form-input"
                                        >
                                        <?php break;
                                    
                                    case 'email': ?>
                                        <input 
                                            type="email" 
                                            id="<?php echo $section_key . '_' . $field_key; ?>" 
                                            name="<?php echo $field_key; ?>" 
                                            value="<?php echo esc_attr($field_value); ?>"
                                            class="ms-form-input"
                                        >
                                        <?php break;
                                    
                                    case 'password': ?>
                                        <input 
                                            type="password" 
                                            id="<?php echo $section_key . '_' . $field_key; ?>" 
                                            name="<?php echo $field_key; ?>" 
                                            value="<?php echo esc_attr($field_value); ?>"
                                            class="ms-form-input"
                                        >
                                        <?php break;
                                    
                                    case 'number': ?>
                                        <input 
                                            type="number" 
                                            id="<?php echo $section_key . '_' . $field_key; ?>" 
                                            name="<?php echo $field_key; ?>" 
                                            value="<?php echo esc_attr($field_value); ?>"
                                            class="ms-form-input"
                                        >
                                        <?php break;
                                    
                                    case 'textarea': ?>
                                        <textarea 
                                            id="<?php echo $section_key . '_' . $field_key; ?>" 
                                            name="<?php echo $field_key; ?>" 
                                            rows="4"
                                            class="ms-form-textarea"
                                        ><?php echo esc_textarea($field_value); ?></textarea>
                                        <?php break;
                                    
                                    case 'checkbox': ?>
                                        <label class="ms-checkbox-label">
                                            <input 
                                                type="checkbox" 
                                                id="<?php echo $section_key . '_' . $field_key; ?>" 
                                                name="<?php echo $field_key; ?>" 
                                                <?php echo $field_value ? 'checked' : ''; ?>
                                            >
                                            <span><?php echo isset($field['description']) ? $field['description'] : ''; ?></span>
                                        </label>
                                        <?php break;
                                    
                                    case 'select': ?>
                                        <select 
                                            id="<?php echo $section_key . '_' . $field_key; ?>" 
                                            name="<?php echo $field_key; ?>"
                                            class="ms-form-select"
                                        >
                                            <?php foreach ($field['options'] as $option_value => $option_label): ?>
                                                <option value="<?php echo $option_value; ?>" <?php echo $field_value === $option_value ? 'selected' : ''; ?>>
                                                    <?php echo $option_label; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php break;
                                    
                                    default: ?>
                                        <input 
                                            type="text" 
                                            id="<?php echo $section_key . '_' . $field_key; ?>" 
                                            name="<?php echo $field_key; ?>" 
                                            value="<?php echo esc_attr($field_value); ?>"
                                            class="ms-form-input"
                                        >
                                <?php endswitch; ?>
                                
                                <?php if (isset($field['description']) && $field['type'] !== 'checkbox'): ?>
                                    <p class="ms-field-description"><?php echo $field['description']; ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="ms-form-actions">
                            <button type="submit" class="ms-btn ms-btn-primary">حفظ الإعدادات</button>
                            <button type="button" class="ms-btn ms-btn-secondary ms-reset-settings">إعادة تعيين</button>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Global Actions -->
    <div class="ms-global-actions">
        <button class="ms-btn ms-btn-secondary" id="ms-export-settings">
            <span class="dashicons dashicons-download"></span>
            تصدير الإعدادات
        </button>
    </div>
</div>

<style>
.ms-unified-settings {
    max-width: 1400px;
    margin: 20px 0;
}

.ms-header {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #e2e8f0;
}

.ms-header h1 {
    color: #1e293b;
    font-size: 28px;
    margin: 0 0 10px 0;
}

.ms-header-description {
    color: #64748b;
    font-size: 14px;
    margin: 0;
}

.ms-settings-container {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 30px;
    margin: 30px 0;
}

.ms-settings-nav {
    display: flex;
    flex-direction: column;
    gap: 8px;
    position: sticky;
    top: 20px;
    align-self: start;
}

.ms-settings-nav-btn {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 20px;
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-align: right;
    font-size: 14px;
    font-weight: 500;
    color: #475569;
}

.ms-settings-nav-btn:hover {
    border-color: #3b82f6;
    background: #f8fafc;
    transform: translateX(-4px);
}

.ms-settings-nav-btn.active {
    border-color: #3b82f6;
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    color: #1e40af;
    font-weight: 600;
}

.ms-section-icon {
    font-size: 22px;
}

.ms-section-title {
    font-weight: 600;
}

.ms-settings-content {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    overflow: hidden;
}

.ms-settings-section {
    display: none;
    padding: 40px;
}

.ms-settings-section.active {
    display: block;
    animation: fadeIn 0.3s ease;
}

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

.ms-section-header {
    margin-bottom: 35px;
    padding-bottom: 20px;
    border-bottom: 2px solid #e2e8f0;
}

.ms-section-header h2 {
    color: #1e293b;
    font-size: 24px;
    margin: 0 0 10px 0;
}

.ms-section-header p {
    color: #64748b;
    font-size: 14px;
    margin: 0;
}

.ms-form-group {
    margin-bottom: 30px;
}

.ms-form-group label {
    display: block;
    color: #334155;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 10px;
}

.ms-form-input,
.ms-form-select,
.ms-form-textarea {
    width: 100%;
    padding: 14px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: #f8fafc;
}

.ms-form-input:focus,
.ms-form-select:focus,
.ms-form-textarea:focus {
    outline: none;
    border-color: #3b82f6;
    background: white;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.ms-form-textarea {
    resize: vertical;
    min-height: 100px;
}

.ms-checkbox-label {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    padding: 12px 16px;
    background: #f8fafc;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    transition: all 0.3s ease;
}

.ms-checkbox-label:hover {
    border-color: #3b82f6;
    background: #eff6ff;
}

.ms-checkbox-label input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
    accent-color: #3b82f6;
}

.ms-field-description {
    color: #64748b;
    font-size: 13px;
    margin: 8px 0 0 0;
    line-height: 1.5;
}

.ms-form-actions {
    display: flex;
    gap: 15px;
    margin-top: 40px;
    padding-top: 25px;
    border-top: 2px solid #e2e8f0;
}

.ms-btn {
    padding: 14px 28px;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.ms-btn-primary {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.ms-btn-primary:hover {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4);
}

.ms-btn-secondary {
    background: #64748b;
    color: white;
}

.ms-btn-secondary:hover {
    background: #475569;
    transform: translateY(-2px);
}

.ms-global-actions {
    display: flex;
    gap: 15px;
    margin-top: 30px;
    padding: 25px;
    background: #f8fafc;
    border-radius: 12px;
    border: 2px solid #e2e8f0;
}

.ms-global-actions .dashicons {
    font-size: 18px;
}

@media (max-width: 1024px) {
    .ms-settings-container {
        grid-template-columns: 1fr;
    }
    
    .ms-settings-nav {
        flex-direction: row;
        overflow-x: auto;
        padding-bottom: 10px;
        position: static;
    }
    
    .ms-settings-nav-btn {
        min-width: 180px;
        white-space: nowrap;
    }
}

@media (max-width: 768px) {
    .ms-unified-settings {
        margin: 10px 0;
    }
    
    .ms-header h1 {
        font-size: 22px;
    }
    
    .ms-settings-section {
        padding: 25px;
    }
    
    .ms-form-actions {
        flex-direction: column;
    }
    
    .ms-btn {
        width: 100%;
        justify-content: center;
    }
    
    .ms-global-actions {
        flex-direction: column;
    }
}
</style>

<script>
(function($) {
    'use strict';
    
    const UnifiedSettings = {
        init: function() {
            this.initNavigation();
            this.initForms();
            this.initGlobalActions();
        },
        
        initNavigation: function() {
            $('.ms-settings-nav-btn').on('click', function() {
                const section = $(this).data('section');
                
                $('.ms-settings-nav-btn').removeClass('active');
                $(this).addClass('active');
                
                $('.ms-settings-section').removeClass('active');
                $('#ms-section-' + section).addClass('active');
            });
        },
        
        initForms: function() {
            $('.ms-settings-form').on('submit', function(e) {
                e.preventDefault();
                
                const form = $(this);
                const section = form.data('section');
                const formData = new FormData(this);
                const settings = {};
                
                form.find('input, select, textarea').each(function() {
                    const name = $(this).attr('name');
                    const value = $(this).attr('type') === 'checkbox' 
                        ? $(this).prop('checked') 
                        : $(this).val();
                    
                    if (name) {
                        settings[name] = value;
                    }
                });
                
                const data = {
                    action: 'ms_save_unified_settings',
                    nonce: formData.get('nonce'),
                    section: section,
                    settings: JSON.stringify(settings)
                };
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: data,
                    beforeSend: function() {
                        form.find('button[type="submit"]').prop('disabled', true).html('<span class="dashicons dashicons-spinner dashicons-spin"></span> جاري الحفظ...');
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('✅ تم حفظ الإعدادات بنجاح!');
                        } else {
                            alert('❌ حدث خطأ أثناء الحفظ: ' + (response.data?.message || 'خطأ غير معروف'));
                        }
                    },
                    error: function() {
                        alert('❌ حدث خطأ في الاتصال بالخادم');
                    },
                    complete: function() {
                        form.find('button[type="submit"]').prop('disabled', false).text('حفظ الإعدادات');
                    }
                });
            });
            
            $('.ms-reset-settings').on('click', function() {
                const form = $(this).closest('.ms-settings-form');
                const section = form.data('section');
                
                if (confirm('⚠️ هل أنت متأكد من إعادة تعيين إعدادات هذا القسم؟\nسيتم فقدان جميع الإعدادات الحالية في هذا القسم.')) {
                    const data = {
                        action: 'ms_reset_unified_settings',
                        nonce: form.find('input[name="nonce"]').val(),
                        section: section
                    };
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: data,
                        success: function(response) {
                            if (response.success) {
                                alert('✅ تم إعادة تعيين الإعدادات بنجاح!');
                                location.reload();
                            } else {
                                alert('❌ حدث خطأ أثناء إعادة التعيين');
                            }
                        },
                        error: function() {
                            alert('❌ حدث خطأ في الاتصال بالخادم');
                        }
                    });
                }
            });
        },
        
        initGlobalActions: function() {
            $('#ms-export-settings').on('click', function() {
                const data = {
                    action: 'ms_export_unified_settings',
                    nonce: $('.ms-settings-form input[name="nonce"]').first().val()
                };
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: data,
                    beforeSend: function() {
                        $(this).prop('disabled', true).html('<span class="dashicons dashicons-spinner dashicons-spin"></span> جاري التصدير...');
                    }.bind(this),
                    success: function(response) {
                        if (response.success) {
                            window.location.href = response.data.download_url;
                        } else {
                            alert('❌ حدث خطأ أثناء التصدير');
                        }
                    },
                    error: function() {
                        alert('❌ حدث خطأ في الاتصال بالخادم');
                    },
                    complete: function() {
                        $(this).prop('disabled', false).html('<span class="dashicons dashicons-download"></span> تصدير الإعدادات');
                    }.bind(this)
                });
            });
            
            $('#ms-import-settings').on('click', function() {
                const input = $('<input type="file" accept=".json">');
                
                input.on('change', function() {
                    const file = this.files[0];
                    if (!file) return;
                    
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        try {
                            const settings = JSON.parse(e.target.result);
                            
                            if (confirm('⚠️ هل أنت متأكد من استيراد هذه الإعدادات؟\nسيتم استبدال الإعدادات الحالية بالبيانات المستوردة.')) {
                                const data = {
                                    action: 'ms_import_unified_settings',
                                    nonce: $('.ms-settings-form input[name="nonce"]').first().val(),
                                    settings: JSON.stringify(settings)
                                };
                                
                                $.ajax({
                                    url: ajaxurl,
                                    type: 'POST',
                                    data: data,
                                    success: function(response) {
                                        if (response.success) {
                                            alert('✅ تم استيراد الإعدادات بنجاح!');
                                            location.reload();
                                        } else {
                                            alert('❌ حدث خطأ أثناء الاستيراد');
                                        }
                                    },
                                    error: function() {
                                        alert('❌ حدث خطأ في الاتصال بالخادم');
                                    }
                                });
                            }
                        } catch (err) {
                            alert('❌ ملف الإعدادات غير صالح. تأكد من أنه ملف JSON صحيح.');
                        }
                    };
                    
                    reader.readAsText(file);
                });
                
                input.click();
            });
        }
    };
    
    $(document).ready(function() {
        UnifiedSettings.init();
    });
    
})(jQuery);
</script>
