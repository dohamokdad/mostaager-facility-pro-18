<?php
/**
 * Settings View - Mostaager Facility PRO Add-On
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="ms-admin-container">
    <div class="ms-header">
        <h1>⚙️ الإعدادات</h1>
        <p class="ms-header-description">تكوين إعدادات الاستيراد والمزامنة</p>
    </div>

    <form method="post" action="" id="ms-settings-form">
        <?php wp_nonce_field('ms_settings_nonce', 'ms_settings_nonce'); ?>
        
        <div class="ms-card">
            <div class="ms-card-header">
                <h2 class="ms-card-title">إعدادات الاستيراد</h2>
            </div>
            <div class="ms-card-body">
                <div class="ms-form-group">
                    <label class="ms-form-label">التحقق التلقائي من البيانات</label>
                    <select name="auto_validation" class="ms-form-select">
                        <option value="1" <?php selected($settings['auto_validation'], '1'); ?>>مفعّل</option>
                        <option value="0" <?php selected($settings['auto_validation'], '0'); ?>>معطّل</option>
                    </select>
                    <p class="ms-form-help">التحقق التلقائي من صحة البيانات قبل الاستيراد</p>
                </div>

                <div class="ms-form-group">
                    <label class="ms-form-label">إنشاء المستخدمين تلقائياً</label>
                    <select name="create_users" class="ms-form-select">
                        <option value="1" <?php selected($settings['create_users'], '1'); ?>>مفعّل</option>
                        <option value="0" <?php selected($settings['create_users'], '0'); ?>>معطّل</option>
                    </select>
                    <p class="ms-form-help">إنشاء حسابات مستخدمين للمستأجرين تلقائياً</p>
                </div>

                <div class="ms-form-group">
                    <label class="ms-form-label">إرسال إشعارات</label>
                    <select name="send_notifications" class="ms-form-select">
                        <option value="1" <?php selected($settings['send_notifications'], '1'); ?>>مفعّل</option>
                        <option value="0" <?php selected($settings['send_notifications'], '0'); ?>>معطّل</option>
                    </select>
                    <p class="ms-form-help">إرسال إشعارات برسائل البريد الإلكتروني وواتساب</p>
                </div>

                <div class="ms-form-group">
                    <label class="ms-form-label">الحالة الافتراضية للوحدات</label>
                    <select name="default_status" class="ms-form-select">
                        <option value="available" <?php selected($settings['default_status'], 'available'); ?>>متاحة</option>
                        <option value="rented" <?php selected($settings['default_status'], 'rented'); ?>>مؤجرة</option>
                        <option value="sold" <?php selected($settings['default_status'], 'sold'); ?>>مباعة</option>
                        <option value="maintenance" <?php selected($settings['default_status'], 'maintenance'); ?>>تحت الصيانة</option>
                    </select>
                    <p class="ms-form-help">الحالة الافتراضية للوحدات المستوردة</p>
                </div>
            </div>
        </div>

        <div class="ms-card">
            <div class="ms-card-header">
                <h2 class="ms-card-title">إعدادات السجلات</h2>
            </div>
            <div class="ms-card-body">
                <div class="ms-form-group">
                    <label class="ms-form-label">تفعيل تسجيل السجلات</label>
                    <select name="logging_enabled" class="ms-form-select">
                        <option value="1" <?php selected($settings['logging_enabled'], '1'); ?>>مفعّل</option>
                        <option value="0" <?php selected($settings['logging_enabled'], '0'); ?>>معطّل</option>
                    </select>
                    <p class="ms-form-help">تسجيل جميع عمليات الاستيراد للمراجعة</p>
                </div>

                <div class="ms-form-group">
                    <label class="ms-form-label">فترة الاحتفاظ بالسجلات</label>
                    <input type="number" name="log_retention_days" class="ms-form-input" value="<?php echo esc_attr($settings['log_retention_days'] ?? 30); ?>">
                    <p class="ms-form-help">عدد الأيام للاحتفاظ بسجلات الاستيراد</p>
                </div>
            </div>
        </div>

        <div class="ms-card">
            <div class="ms-card-header">
                <h2 class="ms-card-title">إعدادات الأمان</h2>
            </div>
            <div class="ms-card-body">
                <div class="ms-form-group">
                    <label class="ms-form-label">الحد الأقصى للسجلات لكل استيراد</label>
                    <input type="number" name="max_records_per_import" class="ms-form-input" value="<?php echo esc_attr($settings['max_records_per_import'] ?? 1000); ?>">
                    <p class="ms-form-help">لمنع استيراد كميات كبيرة جداً مرة واحدة</p>
                </div>

                <div class="ms-form-group">
                    <label class="ms-form-label">الحد الأقصى لحجم الملف (MB)</label>
                    <input type="number" name="max_file_size_mb" class="ms-form-input" value="<?php echo esc_attr($settings['max_file_size_mb'] ?? 10); ?>">
                    <p class="ms-form-help">الحد الأقصى لحجم ملف الاستيراد بالميجابايت</p>
                </div>
            </div>
        </div>

        <div class="ms-card">
            <div class="ms-card-header">
                <h2 class="ms-card-title">إعدادات المزامنة</h2>
            </div>
            <div class="ms-card-body">
                <div class="ms-form-group">
                    <label class="ms-form-label">مزامنة تلقائية مع البيانات الموجودة</label>
                    <select name="auto_sync_existing" class="ms-form-select">
                        <option value="1" <?php selected($settings['auto_sync_existing'] ?? '0', '1'); ?>>مفعّل</option>
                        <option value="0" <?php selected($settings['auto_sync_existing'] ?? '0', '0'); ?>>معطّل</option>
                    </select>
                    <p class="ms-form-help">تحديث البيانات الموجودة بدلاً من إنشاء سجلات جديدة</p>
                </div>

                <div class="ms-form-group">
                    <label class="ms-form-label">استراتيجية التعامل مع التكرارات</label>
                    <select name="duplicate_strategy" class="ms-form-select">
                        <option value="skip" <?php selected($settings['duplicate_strategy'] ?? 'skip', 'skip'); ?>>تخطي التكرارات</option>
                        <option value="update" <?php selected($settings['duplicate_strategy'] ?? 'skip', 'update'); ?>>تحديث السجلات الموجودة</option>
                        <option value="create" <?php selected($settings['duplicate_strategy'] ?? 'skip', 'create'); ?>>إنشاء سجلات جديدة</option>
                    </select>
                    <p class="ms-form-help">كيفية التعامل مع السجلات المكررة</p>
                </div>
            </div>
        </div>

        <div class="ms-form-actions">
            <button type="submit" name="save_settings" value="1" class="ms-button ms-button-primary">
                حفظ الإعدادات
            </button>
            <button type="button" class="ms-button ms-button-outline" id="ms-reset-settings">
                استعادة الافتراضيات
            </button>
        </div>
    </form>
</div>
