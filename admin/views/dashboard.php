<?php
/**
 * Dashboard View - Mostaager Facility PRO Add-On
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="ms-admin-container">
    <div class="ms-header">
        <h1>📊 لوحة تحكم استيراد Mostaager</h1>
        <p class="ms-header-description">نظرة عامة على نشاط الاستيراد والإحصائيات</p>
    </div>

    <!-- Statistics Grid -->
    <div class="ms-dashboard-grid">
        <div class="ms-stat-card">
            <div class="ms-stat-icon">🏠</div>
            <div class="ms-stat-value"><?php echo number_format($stats['properties']); ?></div>
            <div class="ms-stat-label">العقارات المستوردة</div>
            <div class="ms-stat-trend positive">↑ 12% هذا الشهر</div>
        </div>
        
        <div class="ms-stat-card">
            <div class="ms-stat-icon">🏗️</div>
            <div class="ms-stat-value"><?php echo number_format($stats['buildings']); ?></div>
            <div class="ms-stat-label">المباني المنشأة</div>
            <div class="ms-stat-trend positive">↑ 5% هذا الشهر</div>
        </div>
        
        <div class="ms-stat-card">
            <div class="ms-stat-icon">👥</div>
            <div class="ms-stat-value"><?php echo number_format($stats['tenants']); ?></div>
            <div class="ms-stat-label">المستأجرين</div>
            <div class="ms-stat-trend positive">↑ 8% هذا الشهر</div>
        </div>
        
        <div class="ms-stat-card">
            <div class="ms-stat-icon">✅</div>
            <div class="ms-stat-value"><?php echo $stats['success_rate']; ?>%</div>
            <div class="ms-stat-label">نسبة النجاح</div>
            <div class="ms-stat-trend positive">↑ 2% هذا الشهر</div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="ms-card">
        <div class="ms-card-header">
            <h2 class="ms-card-title">🚀 إجراءات سريعة</h2>
        </div>
        <div class="ms-card-body">
            <div class="ms-quick-actions">
                <a href="<?php echo admin_url('admin.php?page=ms-import-new'); ?>" class="ms-button ms-button-primary">
                    📥 استيراد جديد
                </a>
                <a href="<?php echo admin_url('admin.php?page=ms-import-templates'); ?>" class="ms-button ms-button-secondary">
                    📋 القوالب الجاهزة
                </a>
                <a href="<?php echo admin_url('admin.php?page=ms-import-logs'); ?>" class="ms-button ms-button-outline">
                    📊 سجلات الاستيراد
                </a>
                <a href="<?php echo admin_url('admin.php?page=ms-import-settings'); ?>" class="ms-button ms-button-outline">
                    ⚙️ الإعدادات
                </a>
            </div>
        </div>
    </div>

    <!-- Recent Imports -->
    <div class="ms-card">
        <div class="ms-card-header">
            <h2 class="ms-card-title">🔄 آخر عمليات الاستيراد</h2>
            <button class="ms-button ms-button-outline ms-refresh-stats">تحديث</button>
        </div>
        <div class="ms-card-body">
            <?php if (!empty($recent_imports)): ?>
                <table class="ms-table">
                    <thead>
                        <tr>
                            <th>العملية</th>
                            <th>الملف</th>
                            <th>الحالة</th>
                            <th>المدة</th>
                            <th>التاريخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_imports as $import): ?>
                            <tr>
                                <td>
                                    <?php if ($import->status === 'success'): ?>
                                        <span class="ms-badge ms-badge-success">نجح</span>
                                    <?php elseif ($import->status === 'failed'): ?>
                                        <span class="ms-badge ms-badge-error">فشل</span>
                                    <?php else: ?>
                                        <span class="ms-badge ms-badge-warning">قيد المعالجة</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($import->file_name); ?></td>
                                <td>
                                    <?php echo esc_html($import->records_processed); ?> سجل
                                    <?php if ($import->errors_count > 0): ?>
                                        <span class="ms-text-error">(<?php echo $import->errors_count; ?> أخطاء)</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($import->duration); ?> ثانية</td>
                                <td><?php echo esc_html($import->created_at); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="ms-empty-state">
                    <div class="ms-empty-state-icon">📭</div>
                    <h3 class="ms-empty-state-title">لا توجد عمليات استيراد</h3>
                    <p class="ms-empty-state-description">ابدأ باستيراد البيانات الأولى</p>
                    <a href="<?php echo admin_url('admin.php?page=ms-import-new'); ?>" class="ms-button ms-button-primary">
                        استيراد جديد
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="ms-dashboard-grid" style="margin-top: 20px;">
        <div class="ms-card">
            <div class="ms-card-header">
                <h2 class="ms-card-title">📈 إحصائيات الاستيراد</h2>
            </div>
            <div class="ms-card-body">
                <canvas id="msImportChart" height="200"></canvas>
            </div>
        </div>
        
        <div class="ms-card">
            <div class="ms-card-header">
                <h2 class="ms-card-title">🏠 أنواع العقارات</h2>
            </div>
            <div class="ms-card-body">
                <canvas id="msPropertyTypesChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Load Chart.js if not already loaded -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
