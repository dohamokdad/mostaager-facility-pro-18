<?php
if (!defined('ABSPATH')) { exit; }

function ms_unified_profile_value($user, $key, $fallback = '') {
    $value = get_user_meta($user->ID, $key, true);
    return is_scalar($value) ? (string) $value : $fallback;
}

function ms_render_unified_profile() {
    if (!is_user_logged_in()) { return '<p class="ms-profile-message">يرجى تسجيل الدخول لعرض الملف الشخصي.</p>'; }
    $user = wp_get_current_user();
    $custom_avatar = esc_url_raw((string) get_user_meta($user->ID, 'ms_custom_avatar', true));
    $avatar = $custom_avatar ?: get_avatar_url($user->ID, array('size' => 160));
    $role_names = array(
        'administrator' => 'مسؤول الموقع', 'owner' => 'مالك', 'agent' => 'وسيط عقاري',
        'houzez_agent' => 'وسيط عقاري', 'building_manager' => 'مدير مبنى', 'rent' => 'مستأجر', 'subscriber' => 'مستخدم'
    );
    $roles = array_map(function ($role) use ($role_names) { return $role_names[$role] ?? $role; }, (array) $user->roles);
    $display_options = array_unique(array_filter(array($user->display_name, $user->user_login, trim($user->first_name . ' ' . $user->last_name))));
    $primary_role = !empty($user->roles) ? (string) reset($user->roles) : 'subscriber';
    $primary_role_label = $role_names[$primary_role] ?? $primary_role;
    ob_start(); ?>
    <section class="ms-unified-profile" dir="rtl">
        <div class="ms-profile-head">
            <div class="ms-profile-avatar"><img src="<?php echo esc_url($avatar); ?>" alt="<?php echo esc_attr($user->display_name); ?>"></div>
            <div><h3><?php echo esc_html($user->display_name); ?></h3><p><?php echo esc_html($user->user_email); ?></p><small>الحد الأدنى للصورة 300 × 300 بكسل</small><label class="ms-profile-avatar-upload">تحديث صورة الملف الشخصي<input type="file" name="avatar" form="ms-unified-profile-form" accept="image/jpeg,image/png,image/gif,image/webp"></label></div>
        </div>
        <form id="ms-unified-profile-form" class="ms-profile-form" enctype="multipart/form-data">
            <?php wp_nonce_field('ms_update_profile', 'ms_profile_nonce'); ?>
            <input type="hidden" name="action" value="ms_update_profile">
            <div class="ms-profile-section-title">معلومات الاتصال</div>
            <div class="ms-profile-contact-summary"><strong><?php echo esc_html($user->user_email); ?></strong><span>وسائل الإعلام الاجتماعية وبيانات الاتصال</span></div>
            <div class="ms-profile-section-title">المعلومات</div>
            <div class="ms-profile-grid">
                <label>اسم المستخدم<input name="username" value="<?php echo esc_attr($user->user_login); ?>" readonly></label>
                <label>البريد الإلكتروني<input type="email" name="email" value="<?php echo esc_attr($user->user_email); ?>" required></label>
                <label>الاسم الأول<input name="first_name" value="<?php echo esc_attr($user->first_name); ?>"></label>
                <label>الاسم الأخير<input name="last_name" value="<?php echo esc_attr($user->last_name); ?>"></label>
                <label>الاسم المعروض<select name="display_name"><?php foreach ($display_options as $display_option) : ?><option value="<?php echo esc_attr($display_option); ?>" <?php selected($user->display_name, $display_option); ?>><?php echo esc_html($display_option); ?></option><?php endforeach; ?></select></label>
                <label>الوظيفة<input name="job_title" value="<?php echo esc_attr(ms_unified_profile_value($user, 'fave_author_title', ms_unified_profile_value($user, 'job_title'))); ?>"></label>
                <label>الترخيص<input name="license" value="<?php echo esc_attr(ms_unified_profile_value($user, 'fave_author_license')); ?>"></label>
                <label>رقم الجوال<input type="tel" name="phone" value="<?php echo esc_attr(ms_unified_profile_value($user, 'billing_phone')); ?>"></label>
                <label>رقم WhatsApp<input name="whatsapp" value="<?php echo esc_attr(ms_unified_profile_value($user, 'fave_author_whatsapp')); ?>"></label>
                <label>الرقم الضريبي<input name="tax_number" value="<?php echo esc_attr(ms_unified_profile_value($user, 'tax_number')); ?>"></label>
                <label>اللغات<input name="languages" value="<?php echo esc_attr(ms_unified_profile_value($user, 'fave_author_language')); ?>" placeholder="العربية، الإنجليزية"></label>
                <label>اسم الشركة<input name="company" value="<?php echo esc_attr(ms_unified_profile_value($user, 'fave_author_company')); ?>"></label>
                <label class="ms-profile-full">العنوان<textarea name="address" rows="3"><?php echo esc_textarea(ms_unified_profile_value($user, 'billing_address_1')); ?></textarea></label>
                <label class="ms-profile-full">مناطق الخدمة<textarea name="service_areas" rows="3"><?php echo esc_textarea(ms_unified_profile_value($user, 'fave_author_service_area')); ?></textarea></label>
                <label class="ms-profile-full">التخصصات<textarea name="specialties" rows="3"><?php echo esc_textarea(ms_unified_profile_value($user, 'fave_author_specialties')); ?></textarea></label>
                <label class="ms-profile-full">نبذة<textarea name="description" rows="4"><?php echo esc_textarea($user->description); ?></textarea></label>
            </div>
            <div class="ms-profile-actions"><button type="submit" class="ms-action-button ms-action-button-primary">حفظ التغييرات</button><span data-profile-status aria-live="polite"></span></div>
        </form>
        <section class="ms-profile-account-role">
            <div class="ms-profile-section-title">حساب الدور</div>
            <div class="ms-profile-grid"><label>الدور الحالي<select disabled><option selected><?php echo esc_html($primary_role_label); ?></option></select></label><label>نوع الحساب<input value="<?php echo esc_attr(implode('، ', $roles)); ?>" readonly></label></div>
            <p class="ms-profile-help">يتم تحديد الدور والصلاحيات من إدارة WordPress ولا يمكن تغييرهما من بروفايل المستخدم.</p>
        </section>
        <div class="ms-profile-password"><div class="ms-profile-section-title">تغيير كلمة المرور</div><form id="ms-unified-password-form"><input type="hidden" name="action" value="ms_update_profile_password"><input type="hidden" name="security" value="<?php echo esc_attr(wp_create_nonce('ms_update_profile_password')); ?>"><div class="ms-profile-grid"><label>كلمة المرور الجديدة<input type="password" name="new_password" minlength="8" required></label><label>تأكيد كلمة المرور<input type="password" name="confirm_password" minlength="8" required></label></div><button class="ms-action-button" type="submit">تحديث كلمة المرور</button><span data-password-status aria-live="polite"></span></form></div>
    </section>
    <?php return ob_get_clean();
}
add_shortcode('ms_unified_profile', 'ms_render_unified_profile');

add_action('wp_ajax_ms_update_profile_password', function () {
    if (!is_user_logged_in() || !check_ajax_referer('ms_update_profile_password', 'security', false)) { wp_send_json_error(array('message' => 'طلب غير صالح.'), 403); }
    $new = isset($_POST['new_password']) ? (string) $_POST['new_password'] : '';
    $confirm = isset($_POST['confirm_password']) ? (string) $_POST['confirm_password'] : '';
    if (strlen($new) < 8 || $new !== $confirm) { wp_send_json_error(array('message' => 'تأكد من تطابق كلمة المرور وأن لا تقل عن 8 أحرف.'), 422); }
    wp_set_password($new, get_current_user_id());
    wp_send_json_success(array('message' => 'تم تحديث كلمة المرور. سجّل الدخول مرة أخرى.'));
});
