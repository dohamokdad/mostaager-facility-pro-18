<?php
/**
 * Advanced Settings View
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class="ms-advanced-settings-page">
    <h1>الإعدادات المتقدمة</h1>
    <p>إدارة شاملة لإعدادات نظام إدارة المرافق</p>
    
    <div class="ms-settings-container">
        <!-- Settings Navigation -->
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
                        <?php wp_nonce_field('ms_save_settings', 'nonce'); ?>
                        
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
                                            <?php echo isset($field['description']) ? 'title="' . esc_attr($field['description']) . '"' : ''; ?>
                                        >
                                        <?php break;
                                    
                                    case 'email': ?>
                                        <input 
                                            type="email" 
                                            id="<?php echo $section_key . '_' . $field_key; ?>" 
                                            name="<?php echo $field_key; ?>" 
                                            value="<?php echo esc_attr($field_value); ?>"
                                            <?php echo isset($field['description']) ? 'title="' . esc_attr($field['description']) . '"' : ''; ?>
                                        >
                                        <?php break;
                                    
                                    case 'password': ?>
                                        <input 
                                            type="password" 
                                            id="<?php echo $section_key . '_' . $field_key; ?>" 
                                            name="<?php echo $field_key; ?>" 
                                            value="<?php echo esc_attr($field_value); ?>"
                                            <?php echo isset($field['description']) ? 'title="' . esc_attr($field['description']) . '"' : ''; ?>
                                        >
                                        <?php break;
                                    
                                    case 'number': ?>
                                        <input 
                                            type="number" 
                                            id="<?php echo $section_key . '_' . $field_key; ?>" 
                                            name="<?php echo $field_key; ?>" 
                                            value="<?php echo esc_attr($field_value); ?>"
                                            <?php echo isset($field['description']) ? 'title="' . esc_attr($field['description']) . '"' : ''; ?>
                                        >
                                        <?php break;
                                    
                                    case 'textarea': ?>
                                        <textarea 
                                            id="<?php echo $section_key . '_' . $field_key; ?>" 
                                            name="<?php echo $field_key; ?>" 
                                            rows="4"
                                            <?php echo isset($field['description']) ? 'title="' . esc_attr($field['description']) . '"' : ''; ?>
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
                                            <?php echo isset($field['description']) ? 'title="' . esc_attr($field['description']) . '"' : ''; ?>
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
        <button class="ms-btn ms-btn-secondary" id="ms-export-settings">تصدير الإعدادات</button>
        <button class="ms-btn ms-btn-secondary" id="ms-import-settings">استيراد الإعدادات</button>
    </div>
</div>

<style>
.ms-advanced-settings-page {
    max-width: 1200px;
    margin: 20px 0;
}

.ms-settings-container {
    display: grid;
    grid-template-columns: 250px 1fr;
    gap: 30px;
    margin: 30px 0;
}

.ms-settings-nav {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.ms-settings-nav-btn {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px 20px;
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: right;
}

.ms-settings-nav-btn:hover {
    border-color: #3b82f6;
    background: #f8fafc;
}

.ms-settings-nav-btn.active {
    border-color: #3b82f6;
    background: #eff6ff;
}

.ms-section-icon {
    font-size: 20px;
}

.ms-section-title {
    font-weight: 600;
    color: #1e293b;
}

.ms-settings-content {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.ms-settings-section {
    display: none;
    padding: 30px;
}

.ms-settings-section.active {
    display: block;
}

.ms-section-header {
    margin-bottom: 30px;
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
    margin-bottom: 25px;
}

.ms-form-group label {
    display: block;
    color: #475569;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 8px;
}

.ms-form-group input[type="text"],
.ms-form-group input[type="email"],
.ms-form-group input[type="password"],
.ms-form-group input[type="number"],
.ms-form-group select,
.ms-form-group textarea {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.2s;
}

.ms-form-group input:focus,
.ms-form-group select:focus,
.ms-form-group textarea:focus {
    outline: none;
    border-color: #3b82f6;
}

.ms-checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
}

.ms-checkbox-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.ms-field-description {
    color: #64748b;
    font-size: 12px;
    margin: 5px 0 0 0;
}

.ms-form-actions {
    display: flex;
    gap: 15px;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 2px solid #e2e8f0;
}

.ms-btn {
    padding: 12px 24px;
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

.ms-global-actions {
    display: flex;
    gap: 15px;
    margin-top: 30px;
}

@media (max-width: 768px) {
    .ms-settings-container {
        grid-template-columns: 1fr;
    }
    
    .ms-settings-nav {
        flex-direction: row;
        overflow-x: auto;
    }
    
    .ms-settings-nav-btn {
        min-width: 150px;
    }
}
</style>

<script>
(function($) {
    'use strict';
    
    const AdvancedSettings = {
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
                    action: 'ms_save_settings',
                    nonce: formData.get('nonce'),
                    section: section,
                    settings: JSON.stringify(settings)
                };
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: data,
                    beforeSend: function() {
                        form.find('button[type="submit"]').prop('disabled', true).text('جاري الحفظ...');
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('تم حفظ الإعدادات بنجاح!');
                        } else {
                            alert('حدث خطأ أثناء الحفظ');
                        }
                    },
                    error: function() {
                        alert('حدث خطأ في الاتصال');
                    },
                    complete: function() {
                        form.find('button[type="submit"]').prop('disabled', false).text('حفظ الإعدادات');
                    }
                });
            });
            
            $('.ms-reset-settings').on('click', function() {
                const form = $(this).closest('.ms-settings-form');
                const section = form.data('section');
                
                if (confirm('هل أنت متأكد من إعادة تعيين إعدادات هذا القسم؟')) {
                    const data = {
                        action: 'ms_reset_settings',
                        nonce: form.find('input[name="nonce"]').val(),
                        section: section
                    };
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: data,
                        success: function(response) {
                            if (response.success) {
                                alert('تم إعادة تعيين الإعدادات بنجاح!');
                                location.reload();
                            } else {
                                alert('حدث خطأ أثناء إعادة التعيين');
                            }
                        },
                        error: function() {
                            alert('حدث خطأ في الاتصال');
                        }
                    });
                }
            });
        },
        
        initGlobalActions: function() {
            $('#ms-export-settings').on('click', function() {
                const data = {
                    action: 'ms_export_settings',
                    nonce: $('.ms-settings-form input[name="nonce"]').first().val()
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
            });
            
            $('#ms-import-settings').on('click', function() {
                const input = $('<input type="file" accept=".json">');
                
                input.on('change', function() {
                    const file = this.files[0];
                    if (!file) return;
                    
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        const settings = JSON.parse(e.target.result);
                        
                        const data = {
                            action: 'ms_import_settings',
                            nonce: $('.ms-settings-form input[name="nonce"]').first().val(),
                            settings: JSON.stringify(settings)
                        };
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: data,
                            success: function(response) {
                                if (response.success) {
                                    alert('تم استيراد الإعدادات بنجاح!');
                                    location.reload();
                                } else {
                                    alert('حدث خطأ أثناء الاستيراد');
                                }
                            },
                            error: function() {
                                alert('حدث خطأ في الاتصال');
                            }
                        });
                    };
                    
                    reader.readAsText(file);
                });
                
                input.click();
            });
        }
    };
    
    $(document).ready(function() {
        AdvancedSettings.init();
    });
    
})(jQuery);
</script>