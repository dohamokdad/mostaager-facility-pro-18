/**
 * Mostaager Facility PRO Add-On - Admin JavaScript
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        msInitDashboard();
        msInitWizard();
        msInitTemplates();
        msInitNotifications();
        msInitCharts();
    });

    /**
     * Initialize Dashboard
     */
    function msInitDashboard() {
        // Animate stat cards on load
        $('.ms-stat-card').each(function(index) {
            const $card = $(this);
            setTimeout(function() {
                $card.addClass('ms-animate-in');
            }, index * 100);
        });

        // Handle refresh buttons
        $('.ms-refresh-stats').on('click', function(e) {
            e.preventDefault();
            const $button = $(this);
            const originalText = $button.html();
            
            $button.html('<span class="ms-spinner-small"></span>');
            
            // Simulate refresh
            setTimeout(function() {
                $button.html(originalText);
                msShowNotification('تم تحديث الإحصائيات بنجاح', 'success');
            }, 1500);
        });
    }

    /**
     * Initialize Import Wizard
     */
    function msInitWizard() {
        const $wizard = $('.ms-import-wizard');
        if (!$wizard.length) return;

        let currentStep = 1;
        const totalSteps = 4;

        // Step navigation
        $wizard.on('click', '.ms-wizard-next', function(e) {
            e.preventDefault();
            if (msValidateStep(currentStep)) {
                msShowStep(currentStep + 1);
                currentStep++;
            }
        });

        $wizard.on('click', '.ms-wizard-prev', function(e) {
            e.preventDefault();
            msShowStep(currentStep - 1);
            currentStep--;
        });

        // Template selection
        $wizard.on('click', '.ms-template-card', function() {
            $('.ms-template-card').removeClass('selected');
            $(this).addClass('selected');
            
            const templateId = $(this).data('template-id');
            msLoadTemplateFields(templateId);
        });

        // File upload
        $wizard.on('change', '.ms-file-input', function(e) {
            const file = e.target.files[0];
            if (file) {
                msPreviewFile(file);
            }
        });

        // Auto-mapping
        $wizard.on('click', '.ms-auto-map', function(e) {
            e.preventDefault();
            msAutoMapFields();
        });

        // Import action
        $wizard.on('click', '.ms-start-import', function(e) {
            e.preventDefault();
            msStartImport();
        });
    }

    /**
     * Show wizard step
     */
    function msShowStep(step) {
        $('.ms-wizard-step').removeClass('active completed');
        
        for (let i = 1; i <= step; i++) {
            if (i < step) {
                $(`.ms-wizard-step[data-step="${i}"]`).addClass('completed');
            } else {
                $(`.ms-wizard-step[data-step="${i}"]`).addClass('active');
            }
        }

        $('.ms-wizard-content').hide();
        $(`.ms-wizard-content[data-step="${step}"]`).show();
    }

    /**
     * Validate current step
     */
    function msValidateStep(step) {
        let isValid = true;
        const $currentContent = $(`.ms-wizard-content[data-step="${step}"]`);

        // Step 1: Source selection
        if (step === 1) {
            const source = $currentContent.find('.ms-source-select').val();
            if (!source) {
                msShowNotification('الرجاء اختيار مصدر البيانات', 'error');
                isValid = false;
            }
        }

        // Step 2: Field mapping
        if (step === 2) {
            const requiredFields = $currentContent.find('.ms-required-field');
            requiredFields.each(function() {
                if (!$(this).val()) {
                    $(this).addClass('ms-error');
                    isValid = false;
                } else {
                    $(this).removeClass('ms-error');
                }
            });

            if (!isValid) {
                msShowNotification('الرجاء ربط جميع الحقول المطلوبة', 'error');
            }
        }

        return isValid;
    }

    /**
     * Load template fields
     */
    function msLoadTemplateFields(templateId) {
        const templateFields = {
            'buildings': ['building_title', 'manager_id', 'supervisor_name'],
            'properties': ['unit_number', 'floor', 'unit_type', 'unit_status'],
            'tenants': ['tenant_name', 'tenant_email', 'tenant_phone'],
            'full': ['building_title', 'unit_number', 'tenant_name', 'monthly_rent']
        };

        const fields = templateFields[templateId] || [];
        
        // Update field mapping based on template
        $('.ms-field-mapping').html('');
        
        fields.forEach(function(field) {
            const fieldHtml = `
                <div class="ms-field-row">
                    <div class="ms-field-source">
                        <select class="ms-form-select ms-source-field">
                            <option value="">اختر الحقل</option>
                        </select>
                    </div>
                    <div class="ms-field-arrow">→</div>
                    <div class="ms-field-destination">
                        <span class="ms-field-name">${field}</span>
                    </div>
                </div>
            `;
            $('.ms-field-mapping').append(fieldHtml);
        });

        msShowNotification('تم تحميل حقول القالب بنجاح', 'success');
    }

    /**
     * Preview uploaded file
     */
    function msPreviewFile(file) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            try {
                if (file.name.endsWith('.json')) {
                    const data = JSON.parse(e.target.result);
                    msDisplayPreview(data);
                } else if (file.name.endsWith('.csv')) {
                    const data = msParseCSV(e.target.result);
                    msDisplayPreview(data);
                }
            } catch (error) {
                msShowNotification('خطأ في قراءة الملف', 'error');
            }
        };

        reader.readAsText(file);
    }

    /**
     * Parse CSV data
     */
    function msParseCSV(csvText) {
        const lines = csvText.split('\n');
        const headers = lines[0].split(',');
        const data = [];

        for (let i = 1; i < lines.length; i++) {
            if (lines[i].trim()) {
                const values = lines[i].split(',');
                const row = {};
                headers.forEach((header, index) => {
                    row[header.trim()] = values[index] ? values[index].trim() : '';
                });
                data.push(row);
            }
        }

        return data;
    }

    /**
     * Display preview data
     */
    function msDisplayPreview(data) {
        const $preview = $('.ms-import-preview');
        $preview.html('');

        const previewCount = Math.min(data.length, 5);
        
        for (let i = 0; i < previewCount; i++) {
            const item = data[i];
            const status = msValidatePreviewItem(item);
            
            const html = `
                <div class="ms-import-preview-item">
                    <span class="ms-import-preview-status ${status.class}">${status.label}</span>
                    <div class="ms-import-preview-content">
                        ${Object.keys(item).map(key => `
                            <div><strong>${key}:</strong> ${item[key]}</div>
                        `).join('')}
                    </div>
                </div>
            `;
            $preview.append(html);
        }

        if (data.length > 5) {
            $preview.append(`<div class="ms-text-center">و ${data.length - 5} سجلات إضافية...</div>`);
        }
    }

    /**
     * Validate preview item
     */
    function msValidatePreviewItem(item) {
        const requiredFields = ['building_title', 'unit_number'];
        let missingFields = 0;

        requiredFields.forEach(field => {
            if (!item[field]) {
                missingFields++;
            }
        });

        if (missingFields === 0) {
            return { class: 'valid', label: '✅ صحيح' };
        } else if (missingFields < 2) {
            return { class: 'warning', label: '⚠️ تنبيه' };
        } else {
            return { class: 'invalid', label: '❌ غير صحيح' };
        }
    }

    /**
     * Auto map fields
     */
    function msAutoMapFields() {
        $('.ms-field-row').each(function() {
            const $destField = $(this).find('.ms-field-name').text();
            const $sourceSelect = $(this).find('.ms-source-field');
            
            // Try to find matching field
            $sourceSelect.find('option').each(function() {
                if ($(this).text().toLowerCase().includes($destField.toLowerCase()) ||
                    $(this).val().toLowerCase().includes($destField.toLowerCase())) {
                    $sourceSelect.val($(this).val());
                    return false;
                }
            });
        });

        msShowNotification('تم الربط التلقائي للحقول', 'success');
    }

    /**
     * Start import process
     */
    function msStartImport() {
        const $wizard = $('.ms-import-wizard');
        const $progress = $wizard.find('.ms-import-progress');
        const $progressBar = $progress.find('.ms-progress-bar-fill');
        const $status = $wizard.find('.ms-import-status');
        
        // Show progress
        $progress.show();
        $status.text('جاري الاستيراد...');

        let progress = 0;
        const interval = setInterval(function() {
            progress += Math.random() * 15;
            if (progress >= 100) {
                progress = 100;
                clearInterval(interval);
                
                $status.text('تم الاستيراد بنجاح!');
                msShowNotification('تم استيراد البيانات بنجاح', 'success');
                
                setTimeout(function() {
                    $wizard.find('.ms-import-complete').show();
                }, 500);
            }
            
            $progressBar.css('width', progress + '%');
        }, 300);
    }

    /**
     * Initialize Templates
     */
    function msInitTemplates() {
        // Handle template actions
        $('.ms-template-action').on('click', function(e) {
            e.preventDefault();
            const action = $(this).data('action');
            const templateId = $(this).data('template-id');

            switch (action) {
                case 'use':
                    msUseTemplate(templateId);
                    break;
                case 'edit':
                    msEditTemplate(templateId);
                    break;
                case 'delete':
                    msDeleteTemplate(templateId);
                    break;
            }
        });

        // Create new template
        $('.ms-create-template').on('click', function(e) {
            e.preventDefault();
            msShowTemplateModal();
        });
    }

    /**
     * Use template
     */
    function msUseTemplate(templateId) {
        window.location.href = msAddonData.adminUrl + '&template=' + templateId;
    }

    /**
     * Edit template
     */
    function msEditTemplate(templateId) {
        msShowTemplateModal(templateId);
    }

    /**
     * Delete template
     */
    function msDeleteTemplate(templateId) {
        if (confirm(msAddonData.strings.confirmDelete)) {
            $.ajax({
                url: msAddonData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ms_delete_template',
                    nonce: msAddonData.nonce,
                    template_id: templateId
                },
                success: function(response) {
                    if (response.success) {
                        msShowNotification('تم حذف القالب بنجاح', 'success');
                        location.reload();
                    } else {
                        msShowNotification(msAddonData.strings.importError, 'error');
                    }
                }
            });
        }
    }

    /**
     * Show template modal
     */
    function msShowTemplateModal(templateId = null) {
        const $modal = $('.ms-template-modal');
        
        if (templateId) {
            // Load template data
            $.ajax({
                url: msAddonData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ms_get_template',
                    nonce: msAddonData.nonce,
                    template_id: templateId
                },
                success: function(response) {
                    if (response.success) {
                        msFillTemplateForm(response.data);
                    }
                }
            });
        } else {
            msClearTemplateForm();
        }

        $modal.show();
    }

    /**
     * Fill template form
     */
    function msFillTemplateForm(data) {
        $('#ms_template_name').val(data.name);
        $('#ms_template_description').val(data.description);
        $('#ms_template_fields').val(JSON.stringify(data.fields));
    }

    /**
     * Clear template form
     */
    function msClearTemplateForm() {
        $('#ms_template_name').val('');
        $('#ms_template_description').val('');
        $('#ms_template_fields').val('');
    }

    /**
     * Initialize Notifications
     */
    function msInitNotifications() {
        // Auto-hide notifications after delay
        $(document).on('click', '.ms-notification', function() {
            $(this).removeClass('ms-show');
            setTimeout(function() {
                $(this).remove();
            }, 300);
        });
    }

    /**
     * Show notification
     */
    function msShowNotification(message, type) {
        const notification = $(`
            <div class="ms-notification ms-notification-${type}">
                <span class="ms-notification-icon">${type === 'success' ? '✅' : type === 'error' ? '❌' : '⚠️'}</span>
                <span class="ms-notification-message">${message}</span>
            </div>
        `);

        $('body').append(notification);

        setTimeout(function() {
            notification.addClass('ms-show');
        }, 100);

        setTimeout(function() {
            notification.removeClass('ms-show');
            setTimeout(function() {
                notification.remove();
            }, 300);
        }, 3000);
    }

    /**
     * Initialize Charts
     */
    function msInitCharts() {
        // Import statistics chart
        const importChart = document.getElementById('msImportChart');
        if (importChart && typeof Chart !== 'undefined') {
            new Chart(importChart, {
                type: 'line',
                data: {
                    labels: ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو'],
                    datasets: [{
                        label: 'العقارات المستوردة',
                        data: [12, 19, 3, 5, 2, 3],
                        borderColor: '#0073aa',
                        backgroundColor: 'rgba(0, 115, 170, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    }
                }
            });
        }

        // Property types chart
        const propertyTypesChart = document.getElementById('msPropertyTypesChart');
        if (propertyTypesChart && typeof Chart !== 'undefined') {
            new Chart(propertyTypesChart, {
                type: 'doughnut',
                data: {
                    labels: ['شقة', 'فيلا', 'محل', 'مكتب'],
                    datasets: [{
                        data: [45, 25, 20, 10],
                        backgroundColor: [
                            '#0073aa',
                            '#00a0d2',
                            '#46b450',
                            '#ffb900'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });
        }
    }

    // Make functions globally available
    window.msShowNotification = msShowNotification;
    window.msValidateStep = msValidateStep;

})(jQuery);
