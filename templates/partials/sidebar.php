<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('ms_dashboard_icon')) {
    require_once MOSTAAGER_ENTERPRISE_PATH . 'includes/ms-icons.php';
}

$menu_items = isset($ms_dashboard_menu_items) && is_array($ms_dashboard_menu_items) ? $ms_dashboard_menu_items : array();
$ms_brand_logo = apply_filters('ms_dashboard_logo_url', get_option('ms_dashboard_logo_url', ''));
?>
<?php $ms_in_shell = function_exists('ms_in_houzez_shell') && ms_in_houzez_shell(); ?>
<aside class="ms-sidebar<?php echo $ms_in_shell ? ' ms-sidebar--in-shell' : ''; ?>" aria-label="قائمة لوحة التحكم"<?php echo $ms_in_shell ? ' hidden' : ''; ?>>
    <div class="ms-sidebar-brand">
        <?php if ($ms_brand_logo) : ?>
            <img class="ms-sidebar-logo" src="<?php echo esc_url($ms_brand_logo); ?>" alt="مستأجر">
        <?php else : ?>
            <span class="ms-sidebar-mark" aria-hidden="true"><?php echo ms_dashboard_icon('building'); ?></span>
            <span class="ms-sidebar-wordmark">
                <strong>مستأجر</strong>
                <small>إيجار <i>•</i> بيع <i>•</i> ثقة</small>
            </span>
        <?php endif; ?>
    </div>
    <div class="ms-sidebar-title">القائمة</div>
    <ul class="ms-sidebar-menu">
        <?php foreach ($menu_items as $item) : ?>
            <?php
            $label = isset($item['label']) ? $item['label'] : '';
            $href = isset($item['href']) ? $item['href'] : '#';
            $tab = isset($item['data_tab']) ? $item['data_tab'] : '';
            $external = !empty($item['external']);
            $badge = isset($item['badge']) ? $item['badge'] : '';
            $active = !empty($item['active']);
            $attributes = '';
            if ($tab) {
                $attributes .= ' data-tab="' . esc_attr($tab) . '"';
                if (!$external) {
                    $href = '#' . esc_attr($tab);
                }
            }
            if ($external) {
                $attributes .= ' data-external="true"';
            }
            if ($active) {
                $attributes .= ' aria-current="page"';
            }
            $is_logout = $external && strpos((string) $href, 'logout') !== false;
            $icon_key = $tab ? $tab : ($is_logout ? 'logout' : 'dot');
            $link_classes = 'ms-tab-link' . ($active ? ' active' : '');
            $li_classes = trim(($active ? 'active' : '') . ($is_logout ? ' ms-menu-logout' : ''));
            ?>
            <li class="<?php echo esc_attr($li_classes); ?>">
                <a href="<?php echo esc_url($href); ?>" class="<?php echo esc_attr($link_classes); ?>"<?php echo $attributes; ?>>
                    <span class="ms-menu-item-main">
                        <span class="ms-menu-icon"><?php echo ms_dashboard_icon($icon_key); ?></span>
                        <span class="ms-menu-label"><?php echo esc_html($label); ?></span>
                    </span>
                    <?php if ($badge) : ?>
                        <span class="ms-menu-badge"><?php echo esc_html($badge); ?></span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <div class="ms-sidebar-skyline" aria-hidden="true"></div>
</aside>
