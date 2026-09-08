<?php
/**
 * Advanced Reports Admin View
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class="wrap ms-reports-page">
    <h1>التقارير المتقدمة</h1>
    <p>إنشاء وإدارة التقارير التلقائية والتحليلات المتقدمة</p>
    
    <div class="ms-reports-container">
        <!-- Report Generation Section -->
        <div class="ms-report-section">
            <h2>إنشاء تقرير جديد</h2>
            
            <form id="ms-generate-report-form">
                <?php wp_nonce_field('ms_generate_report', 'nonce'); ?>
                
                <div class="ms-form-group">
                    <label>نوع التقرير</label>
                    <select name="report_type" id="ms-report-type" required>
                        <?php foreach ($report_types as $key => $type): ?>
                            <option value="<?php echo $key; ?>"><?php echo $type['icon'] . ' ' . $type['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="ms-form-group">
                    <label>تاريخ البداية</label>
                    <input type="date" name="start_date" id="ms-start-date">
                </div>
                
                <div class="ms-form-group">
                    <label>تاريخ النهاية</label>
                    <input type="date" name="end_date" id="ms-end-date">
                </div>
                
                <div class="ms-form-group">
                    <label>الفلاتر</label>
                    <div id="ms-filters-container">
                        <!-- Dynamic filters will be loaded here -->
                    </div>
                </div>
                
                <button type="submit" class="ms-btn ms-btn-primary">إنشاء التقرير</button>
            </form>
        </div>
        
        <!-- Report Display Section -->
        <div class="ms-report-section" id="ms-report-display" style="display:none;">
            <h2>نتائج التقرير</h2>
            <div id="ms-report-content"></div>
            
            <div class="ms-export-buttons">
                <button class="ms-btn ms-btn-secondary" data-format="csv">تصدير CSV</button>
                <button class="ms-btn ms-btn-secondary" data-format="excel">تصدير Excel</button>
                <button class="ms-btn ms-btn-secondary" data-format="pdf">تصدير PDF</button>
            </div>
        </div>
        
        <!-- Scheduled Reports Section -->
        <div class="ms-report-section">
            <h2>التقارير المجدولة</h2>
            
            <button class="ms-btn ms-btn-primary" id="ms-add-scheduled-report">إضافة تقرير مجدول</button>
            
            <div id="ms-scheduled-reports-list">
                <?php if (!empty($scheduled_reports)): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>نوع التقرير</th>
                                <th>الجدولة</th>
                                <th>المستلمين</th>
                                <th>التشغيل القادم</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($scheduled_reports as $report): ?>
                                <tr>
                                    <td><?php echo $report_types[$report['report_type']]['name'] ?? $report['report_type']; ?></td>
                                    <td><?php echo $report['schedule']; ?></td>
                                    <td><?php echo implode(', ', $report['recipients']); ?></td>
                                    <td><?php echo $report['next_run']; ?></td>
                                    <td>
                                        <button class="ms-btn ms-btn-danger ms-delete-scheduled-report" data-report-id="<?php echo $report['id']; ?>">حذف</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>لا توجد تقارير مجدولة</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.ms-reports-page {
    max-width: 1200px;
    margin: 20px 0;
}

.ms-reports-container {
    display: grid;
    gap: 30px;
}

.ms-report-section {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.ms-report-section h2 {
    color: #1e293b;
    font-size: 20px;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e2e8f0;
}

.ms-form-group {
    margin-bottom: 20px;
}

.ms-form-group label {
    display: block;
    color: #475569;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 8px;
}

.ms-form-group select,
.ms-form-group input {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
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

.ms-btn-danger {
    background: #ef4444;
    color: white;
}

.ms-btn-danger:hover {
    background: #dc2626;
}

.ms-export-buttons {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

#ms-report-content {
    background: #f8fafc;
    padding: 20px;
    border-radius: 8px;
    margin-top: 20px;
}
</style>

<script>
(function($) {
    'use strict';
    
    const ReportsDashboard = {
        init: function() {
            this.initGenerateForm();
            this.initExportButtons();
            this.initScheduledReports();
        },
        
        initGenerateForm: function() {
            $('#ms-generate-report-form').on('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const filters = {};
                
                $('#ms-filters-container input, #ms-filters-container select').each(function() {
                    filters[$(this).attr('name')] = $(this).val();
                });
                
                const data = {
                    action: 'ms_generate_report',
                    nonce: formData.get('nonce'),
                    report_type: formData.get('report_type'),
                    start_date: formData.get('start_date'),
                    end_date: formData.get('end_date'),
                    filters: JSON.stringify(filters)
                };
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: data,
                    beforeSend: function() {
                        $(this).find('button').prop('disabled', true).text('جاري إنشاء التقرير...');
                    },
                    success: function(response) {
                        if (response.success) {
                            ReportsDashboard.displayReport(response.data);
                        } else {
                            alert('حدث خطأ أثناء إنشاء التقرير');
                        }
                    },
                    error: function() {
                        alert('حدث خطأ في الاتصال');
                    },
                    complete: function() {
                        $(this).find('button').prop('disabled', false).text('إنشاء التقرير');
                    }
                });
            });
        },
        
        displayReport: function(data) {
            $('#ms-report-display').show();
            $('#ms-report-content').html('<pre>' + JSON.stringify(data, null, 2) + '</pre>');
        },
        
        initExportButtons: function() {
            $('.ms-export-buttons button').on('click', function() {
                const format = $(this).data('format');
                const reportData = $('#ms-report-content pre').text();
                
                const data = {
                    action: 'ms_export_report',
                    nonce: $('#ms-generate-report-form input[name="nonce"]').val(),
                    report_type: $('#ms-report-type').val(),
                    format: format,
                    data: reportData
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
        },
        
        initScheduledReports: function() {
            $('#ms-add-scheduled-report').on('click', function() {
                const reportType = prompt('نوع التقرير:');
                const schedule = prompt('الجدولة (daily/weekly/monthly):');
                const recipients = prompt('المستلمين (comma separated emails):');
                
                if (reportType && schedule && recipients) {
                    const data = {
                        action: 'ms_schedule_report',
                        nonce: $('#ms-generate-report-form input[name="nonce"]').val(),
                        report_type: reportType,
                        schedule: schedule,
                        recipients: JSON.stringify(recipients.split(',').map(e => e.trim())),
                        filters: JSON.stringify({})
                    };
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: data,
                        success: function(response) {
                            if (response.success) {
                                alert('تم إضافة التقرير المجدول بنجاح!');
                                location.reload();
                            } else {
                                alert('حدث خطأ أثناء إضافة التقرير');
                            }
                        },
                        error: function() {
                            alert('حدث خطأ في الاتصال');
                        }
                    });
                }
            });
            
            $('.ms-delete-scheduled-report').on('click', function() {
                const reportId = $(this).data('report-id');
                
                if (confirm('هل أنت متأكد من حذف هذا التقرير المجدول؟')) {
                    const data = {
                        action: 'ms_delete_scheduled_report',
                        nonce: $('#ms-generate-report-form input[name="nonce"]').val(),
                        report_id: reportId
                    };
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: data,
                        success: function(response) {
                            if (response.success) {
                                alert('تم حذف التقرير المجدول بنجاح!');
                                location.reload();
                            } else {
                                alert('حدث خطأ أثناء الحذف');
                            }
                        },
                        error: function() {
                            alert('حدث خطأ في الاتصال');
                        }
                    });
                }
            });
        }
    };
    
    $(document).ready(function() {
        ReportsDashboard.init();
    });
    
})(jQuery);
</script>