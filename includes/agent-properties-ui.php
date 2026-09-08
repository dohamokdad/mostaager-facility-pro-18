<?php
if (!defined('ABSPATH')) exit;

/**
 * Houzez-style agent property cards. The renderer is intentionally independent
 * from the theme's internal dashboard markup and can be injected into #listings.
 */
function msfp_agent_property_ids($user_id)
{
    $user_id = absint($user_id);
    $properties = function_exists('ms_get_properties_by_agent') ? ms_get_properties_by_agent($user_id) : array();
    if (empty($properties) && function_exists('ms_get_properties_by_owner')) {
        $properties = ms_get_properties_by_owner($user_id);
    }
    $ids = array();
    foreach ((array) $properties as $property) {
        $id = is_object($property) ? absint($property->ID ?? $property->id ?? $property->post_id ?? 0) : absint(is_array($property) ? ($property['ID'] ?? $property['id'] ?? $property['post_id'] ?? 0) : $property);
        if ($id) $ids[] = $id;
    }
    if (!$ids) {
        $posts = get_posts(array(
            'post_type' => 'property',
            'post_status' => array('publish','pending','draft','expired','houzez_sold','disapproved','on_hold','private','future'),
            'posts_per_page' => -1,
            'author' => $user_id,
            'fields' => 'ids',
        ));
        $ids = array_map('absint', (array) $posts);
    }
    return array_values(array_unique(array_filter($ids)));
}

function msfp_agent_property_value($post_id, $keys, $fallback = '')
{
    foreach ((array) $keys as $key) {
        $value = get_post_meta($post_id, $key, true);
        if ($value !== '' && $value !== null) return is_array($value) ? reset($value) : $value;
    }
    return $fallback;
}

function msfp_render_agent_property_cards($user_id = 0)
{
    if (!$user_id) $user_id = get_current_user_id();
    $ids = msfp_agent_property_ids($user_id);
    $status_terms = taxonomy_exists('property_status') ? get_terms(array('taxonomy' => 'property_status', 'hide_empty' => false)) : array();
    if (is_wp_error($status_terms)) $status_terms = array();
    $statuses = array('publish' => 'متاح', 'pending' => 'قيد المراجعة', 'draft' => 'مسودة', 'expired' => 'منتهي', 'houzez_sold' => 'مباع', 'on_hold' => 'معلق');
    ob_start(); ?>
    <section class="msfp-agent-properties" dir="rtl" data-msfp-agent-properties>
        <header class="msfp-agent-properties__header">
            <div><span class="msfp-eyebrow">إدارة العقارات</span><h2>عقاراتي</h2><p>إدارة عقاراتك وتحديث حالتها ورفع عقود الإيجار أو البيع من نفس الصفحة.</p></div>
            <div class="msfp-agent-properties__tools"><span class="msfp-count-badge">إجمالي العقارات <strong><?php echo count($ids); ?></strong></span><a class="msfp-add-property" href="#add-property">+ إضافة عقار</a></div>
        </header>
        <div class="msfp-agent-properties__filters" role="tablist" aria-label="تصفية العقارات">
            <button type="button" class="is-active" data-msfp-property-filter="all">الكل <b><?php echo count($ids); ?></b></button>
            <button type="button" data-msfp-property-filter="publish">متاح</button>
            <button type="button" data-msfp-property-filter="pending">قيد المراجعة</button>
            <button type="button" data-msfp-property-filter="houzez_sold">مباع</button>
        </div>
        <?php if (!$ids) : ?><div class="msfp-agent-properties__empty"><strong>لا توجد عقارات حتى الآن</strong><p>ابدأ بإضافة عقارك ليظهر هنا بنفس نمط صفحة عقاراتي.</p><a class="msfp-add-property" href="#add-property">إضافة عقار جديد</a></div><?php else : ?>
            <div class="msfp-agent-properties__grid">
            <?php foreach ($ids as $property_id) :
                $post = get_post($property_id); if (!$post) continue;
                $terms = get_the_terms($property_id, 'property_status'); $term_slug = !empty($terms) && !is_wp_error($terms) ? sanitize_title($terms[0]->slug) : sanitize_title($post->post_status);
                $term_label = !empty($terms) && !is_wp_error($terms) ? $terms[0]->name : ($statuses[$post->post_status] ?? 'غير محدد');
                $image = get_the_post_thumbnail_url($property_id, 'medium_large');
                if (!$image) $image = msfp_agent_property_value($property_id, array('fave_property_images','property_image'), '');
                if (is_numeric($image)) $image = wp_get_attachment_image_url(absint($image), 'medium_large');
                $price = msfp_agent_property_value($property_id, array('fave_property_price','property_price','price'), '');
                $address = msfp_agent_property_value($property_id, array('fave_property_map_address','property_address','address'), '');
                $type = msfp_agent_property_value($property_id, array('property_type','fave_property_type'), '');
                $generic_contract = msfp_agent_property_value($property_id, array('ms_property_contract_url'), '');
                $generic_contract_type = sanitize_key(msfp_agent_property_value($property_id, array('ms_property_contract_type'), ''));
                $rent_contract = msfp_agent_property_value($property_id, array('ms_rent_contract_url'), $generic_contract_type === 'rent' ? $generic_contract : '');
                $sale_contract = msfp_agent_property_value($property_id, array('ms_sale_contract_url'), $generic_contract_type === 'sale' ? $generic_contract : '');
                ?>
                <article class="msfp-property-card" data-msfp-property-card data-property-status="<?php echo esc_attr($term_slug); ?>">
                    <a class="msfp-property-card__media" href="<?php echo esc_url(get_permalink($property_id)); ?>" target="_blank" rel="noopener">
                        <?php if ($image) : ?><img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr(get_the_title($property_id)); ?>" loading="lazy"><?php else : ?><span class="msfp-property-card__placeholder">صورة العقار</span><?php endif; ?>
                        <span class="msfp-property-card__status"><?php echo esc_html($term_label); ?></span>
                    </a>
                    <div class="msfp-property-card__body">
                        <div class="msfp-property-card__top"><span class="msfp-property-card__type"><?php echo esc_html($type ?: 'عقار'); ?></span><small>#<?php echo intval($property_id); ?></small></div>
                        <h3><a href="<?php echo esc_url(get_permalink($property_id)); ?>" target="_blank" rel="noopener"><?php echo esc_html(get_the_title($property_id)); ?></a></h3>
                        <?php if ($address) : ?><p class="msfp-property-card__address">⌖ <?php echo esc_html($address); ?></p><?php endif; ?>
                        <div class="msfp-property-card__meta"><strong><?php echo $price !== '' ? 'ج.م ' . esc_html(number_format_i18n((float) $price, 0)) : 'السعر عند الطلب'; ?></strong><span><?php echo esc_html(get_the_date('', $property_id)); ?></span></div>
                        <div class="msfp-property-card__actions">
                            <label class="msfp-property-card__status-control">الحالة<select class="agent-property-status" data-prop-id="<?php echo intval($property_id); ?>"><option value="">اختر الحالة</option><?php if ($status_terms) : foreach ($status_terms as $status_term) : ?><option value="<?php echo esc_attr($status_term->slug); ?>" <?php selected($term_slug, $status_term->slug); ?>><?php echo esc_html($status_term->name); ?></option><?php endforeach; else : foreach ($statuses as $status_key => $status_name) : ?><option value="<?php echo esc_attr($status_key); ?>" <?php selected($term_slug, $status_key); ?>><?php echo esc_html($status_name); ?></option><?php endforeach; endif; ?></select></label>
                            <div class="msfp-property-card__contracts">
                                <?php if ($rent_contract) : ?><a class="agent-contract-link" href="<?php echo esc_url($rent_contract); ?>" target="_blank" rel="noopener">عرض عقد الإيجار</a><?php endif; ?>
                                <input type="file" hidden class="agent-contract-file-input" data-prop-id="<?php echo intval($property_id); ?>" data-contract-type="rent" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                <button type="button" class="agent-upload-contract-button" data-prop-id="<?php echo intval($property_id); ?>" data-contract-type="rent">رفع عقد إيجار</button>
                                <?php if ($sale_contract) : ?><a class="agent-contract-link" href="<?php echo esc_url($sale_contract); ?>" target="_blank" rel="noopener">عرض عقد البيع</a><?php endif; ?>
                                <input type="file" hidden class="agent-contract-file-input" data-prop-id="<?php echo intval($property_id); ?>" data-contract-type="sale" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                <button type="button" class="agent-upload-contract-button" data-prop-id="<?php echo intval($property_id); ?>" data-contract-type="sale">رفع عقد بيع</button>
                            </div>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?></div>
        <?php endif; ?>
    </section>
    <?php return ob_get_clean();
}

add_shortcode('ms_agent_properties_cards', function () { return is_user_logged_in() ? msfp_render_agent_property_cards() : '<p>يرجى تسجيل الدخول.</p>'; });

add_action('wp_ajax_ms_get_agent_properties_cards', function () {
    if (!is_user_logged_in() || !check_ajax_referer('mostaager-ajax-nonce', 'security', false)) wp_send_json_error('forbidden', 403);
    $user = wp_get_current_user();
    if (!function_exists('ms_user_can_view_dashboard') || !ms_user_can_view_dashboard($user->ID, 'agent')) wp_send_json_error('forbidden', 403);
    wp_send_json_success(array('html' => msfp_render_agent_property_cards($user->ID)));
});
