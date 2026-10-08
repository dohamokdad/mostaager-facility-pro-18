<?php
/**
 * Mostaager dashboard rendered inside the Houzez 4.x dashboard frame.
 * نفس بنية قوالب Houzez (template/user_dashboard_*.php): هيدر اللوحة ← القائمة ← الشريط العلوي ← المحتوى.
 * المحتوى هو محتوى الصفحة نفسها (الـ shortcode الخاص بلوحة مستأجر).
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!is_user_logged_in()) {
    // نفس سلوك Houzez: صفحات اللوحة للمستخدمين المسجلين فقط
    wp_safe_redirect(wp_login_url(get_permalink()));
    exit;
}

get_header('dashboard');
?>

<?php get_template_part('template-parts/dashboard/sidebar'); ?>

<div class="dashboard-right">
    <?php get_template_part('template-parts/dashboard/topbar'); ?>

    <div class="dashboard-content ms-hz-content">
        <?php
        while (have_posts()) :
            the_post();
            the_content();
        endwhile;
        ?>
    </div>
</div>

<?php get_footer('dashboard'); ?>
