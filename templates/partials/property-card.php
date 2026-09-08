<?php
if (!defined('ABSPATH')) {
    exit;
}

global $post, $delete_properties_nonce, $ms_dashboard_context;

$post_id = get_the_ID();
$thumbnail = get_the_post_thumbnail($post_id, [300, 200], array(
    'loading' => 'lazy',
    'decoding' => 'async',
    'class' => 'ms-property-card-thumbnail'
));
if (!$thumbnail) {
    // Try Houzez property image fields
    $gallery_ids = get_post_meta($post_id, 'fave_property_images', true);
    if (empty($gallery_ids)) {
        $gallery_ids = get_post_meta($post_id, 'property_images', true);
    }
    if (empty($gallery_ids)) {
        $gallery_ids = get_post_meta($post_id, 'fave_featured_image', true);
    }
    if (!empty($gallery_ids)) {
        if (is_string($gallery_ids)) {
            $gallery_ids = maybe_unserialize($gallery_ids);
        }
        if (is_array($gallery_ids) && !empty($gallery_ids)) {
            $first_id = intval($gallery_ids[0]);
            $first_url = wp_get_attachment_image_url($first_id, [300, 200]);
            if (!$first_url) {
                $first_url = wp_get_attachment_url($first_id);
            }
            if ($first_url) {
                $thumbnail = '<img src="' . esc_url($first_url) . '" alt="' . esc_attr(get_the_title()) . '" width="300" height="200" class="ms-property-card-thumbnail" style="object-fit:cover;border-radius:12px 12px 0 0;width:100%;height:200px;" loading="lazy" />';
            }
        }
    }
    // Try featured image ID directly
    if (!$thumbnail) {
        $featured_id = get_post_meta($post_id, '_thumbnail_id', true);
        if ($featured_id) {
            $featured_url = wp_get_attachment_image_url($featured_id, [300, 200]);
            if (!$featured_url) {
                $featured_url = wp_get_attachment_url($featured_id);
            }
            if ($featured_url) {
                $thumbnail = '<img src="' . esc_url($featured_url) . '" alt="' . esc_attr(get_the_title()) . '" width="300" height="200" class="ms-property-card-thumbnail" style="object-fit:cover;border-radius:12px 12px 0 0;width:100%;height:200px;" loading="lazy" />';
            }
        }
    }
    if (!$thumbnail) {
        $thumbnail = '<div style="width:100%;height:200px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border-radius:12px 12px 0 0;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.2rem;font-weight:600;">صورة العقار</div>';
    }
}

$status = get_post_status($post_id);
$status_labels = array(
    'publish' => 'منشور',
    'pending' => 'قيد المراجعة',
    'draft' => 'مسودة',
    'expired' => 'منتهي',
    'houzez_sold' => 'مباع',
    'disapproved' => 'مرفوض',
    'on_hold' => 'معلق',
    'private' => 'خاص',
);
$status_label = isset($status_labels[$status]) ? $status_labels[$status] : 'غير معروف';

$property_status_terms = taxonomy_exists('property_status') ? get_terms(array('taxonomy' => 'property_status', 'hide_empty' => false)) : array();
$current_property_status_terms = wp_get_post_terms($post_id, 'property_status', array('fields' => 'all'));
$current_property_status_slug = '';
$current_property_status_name = '';
if (is_array($current_property_status_terms) && !empty($current_property_status_terms)) {
    $current_property_status_slug = $current_property_status_terms[0]->slug;
    $current_property_status_name = $current_property_status_terms[0]->name;
}
$fave_property_status = get_post_meta($post_id, 'fave_property_status', true);
$translated_fave_property_status = function_exists('ms_translate_property_status_label') ? ms_translate_property_status_label($fave_property_status) : $fave_property_status;

$contract_url = get_post_meta($post_id, 'ms_property_contract_url', true);
$contract_type = get_post_meta($post_id, 'ms_property_contract_type', true);

$price = get_post_meta($post_id, 'fave_property_price', true);
$type = get_post_meta($post_id, 'fave_property_type', true);
$building_id = absint(get_post_meta($post_id, 'building_id', true));
if (!$building_id) { $building_id = absint(get_post_meta($post_id, 'ms_building_id', true)); }
$building_name = 'غير محدد';
if ($building_id) {
    global $wpdb;
    $building_table = $wpdb->prefix . 'ms_buildings';
    $building_row = $wpdb->get_row($wpdb->prepare("SELECT title, wp_post_id FROM {$building_table} WHERE id = %d OR wp_post_id = %d LIMIT 1", $building_id, $building_id));
    if ($building_row && !empty($building_row->title)) { $building_name = $building_row->title; }
    elseif ($building_row && !empty($building_row->wp_post_id)) { $building_name = get_the_title(absint($building_row->wp_post_id)) ?: ('مبنى #' . $building_id); }
    elseif (get_the_title($building_id)) { $building_name = get_the_title($building_id); }
}
$date = get_the_date();

$edit_url = get_edit_post_link($post_id);
$edit_url = $edit_url ? esc_url($edit_url) : '';
?>
<div class="ms-property-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.08);transition:transform 0.2s,box-shadow 0.2s;">
    <div class="ms-property-card-image" style="position:relative;">
        <?php echo $thumbnail; ?>
        <div class="ms-property-card-status-badge" style="position:absolute;top:12px;right:12px;">
            <?php if (!empty($current_property_status_name)): ?>
                <span style="background:rgba(37,99,235,0.95);color:#fff;padding:6px 12px;border-radius:999px;font-size:0.8rem;font-weight:600;box-shadow:0 2px 8px rgba(0,0,0,0.15);"><?php echo esc_html($current_property_status_name); ?></span>
            <?php elseif (!empty($fave_property_status)): ?>
                <span style="background:rgba(16,185,129,0.95);color:#fff;padding:6px 12px;border-radius:999px;font-size:0.8rem;font-weight:600;box-shadow:0 2px 8px rgba(0,0,0,0.15);"><?php echo esc_html($translated_fave_property_status); ?></span>
            <?php else: ?>
                <span style="background:rgba(107,114,128,0.95);color:#fff;padding:6px 12px;border-radius:999px;font-size:0.8rem;font-weight:600;box-shadow:0 2px 8px rgba(0,0,0,0.15);"><?php echo esc_html($status_label); ?></span>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="ms-property-card-content" style="padding:16px;">
        <h3 class="ms-property-card-title" style="margin:0 0 8px;font-size:1.1rem;font-weight:700;line-height:1.4;">
            <a href="<?php echo esc_url(get_permalink($post_id)); ?>" style="color:#111827;text-decoration:none;"><?php echo esc_html(get_the_title()); ?></a>
        </h3>
        
        <div class="ms-property-card-meta" style="display:flex;gap:12px;margin-bottom:12px;font-size:0.9rem;color:#6b7280;">
            <span><?php echo esc_html($type); ?></span>
            <span>•</span>
            <span>البناء: <?php echo esc_html($building_name); ?></span>
            <span>•</span>
            <span><?php echo esc_html($date); ?></span>
        </div>
        
        <div class="ms-property-card-price" style="font-size:1.3rem;font-weight:800;color:#2563eb;margin-bottom:12px;">
            <?php echo esc_html($price); ?>
        </div>
        
        <?php if (!empty($property_status_terms) && !is_wp_error($property_status_terms)): ?>
            <div class="ms-property-card-status-select" style="margin-bottom:12px;">
                <select class="agent-property-status" data-prop-id="<?php echo intval($post_id); ?>" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;background:#fff;font-size:0.9rem;cursor:pointer;">
                    <option value=""><?php echo esc_html__('تغيير الحالة', 'mostaager-facility-pro'); ?></option>
                    <?php foreach ($property_status_terms as $term): ?>
                        <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($current_property_status_slug, $term->slug); ?>>
                            <?php echo esc_html($term->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        
        <div class="ms-property-card-contract" style="margin-bottom:12px;">
            <?php if ($contract_url): ?>
                <div class="agent-contract-link" style="margin-bottom:8px;">
                    <a href="<?php echo esc_url($contract_url); ?>" target="_blank" style="display:inline-block;padding:8px 16px;background:#f3f4f6;color:#374151;text-decoration:none;border-radius:8px;font-size:0.85rem;font-weight:600;">
                        <?php echo esc_html($contract_type === 'sale' ? '📄 عرض عقد البيع' : ($contract_type === 'rent' ? '📄 عرض عقد الإيجار' : '📄 عرض العقد')); ?>
                    </a>
                </div>
                <?php $contract_signature_status = sanitize_key(get_post_meta($post_id, 'ms_property_contract_signature_status', true)); ?>
                <div style="font-size:12px;color:<?php echo $contract_signature_status === 'signed_by_both' ? '#166534' : '#92400e'; ?>;font-weight:600;">
                    <?php echo esc_html($contract_signature_status === 'signed_by_both' ? '✓ موقع من الطرفين' : '⏳ بانتظار توقيع الطرفين'); ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="ms-property-card-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
            <button type="button" class="button agent-upload-contract-button" data-prop-id="<?php echo intval($post_id); ?>" data-contract-type="rent" style="flex:1;padding:8px 12px;background:#2563eb;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:0.85rem;font-weight:600;transition:background 0.2s;">رفع عقد إيجار</button>
            <button type="button" class="button agent-upload-contract-button" data-prop-id="<?php echo intval($post_id); ?>" data-contract-type="sale" style="flex:1;padding:8px 12px;background:#10b981;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:0.85rem;font-weight:600;transition:background 0.2s;">رفع عقد بيع</button>
            <input type="file" name="contract_file" class="agent-contract-file-input" data-prop-id="<?php echo intval($post_id); ?>" data-contract-type="rent" style="display:none;" accept=".pdf,.png,.jpg,.jpeg,.doc,.docx">
            <input type="file" name="contract_file" class="agent-contract-file-input" data-prop-id="<?php echo intval($post_id); ?>" data-contract-type="sale" style="display:none;" accept=".pdf,.png,.jpg,.jpeg,.doc,.docx">
        </div>
        
        <div class="ms-property-card-footer" style="margin-top:12px;padding-top:12px;border-top:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:center;font-size:0.85rem;color:#6b7280;">
            <span>رقم العقار: <?php echo intval($post_id); ?></span>
            <div class="ms-property-card-edit-delete" style="display:flex;gap:12px;">
                <?php if ($edit_url): ?>
                    <a href="<?php echo $edit_url; ?>" style="color:#2563eb;text-decoration:none;font-weight:600;"><?php echo esc_html__('تعديل', 'mostaager-facility-pro'); ?></a>
                <?php endif; ?>
                <a href="#" class="delete-property" data-prop-id="<?php echo intval($post_id); ?>" data-security="<?php echo esc_attr($delete_properties_nonce); ?>" style="color:#ef4444;text-decoration:none;font-weight:600;"><?php echo esc_html__('حذف', 'mostaager-facility-pro'); ?></a>
            </div>
        </div>
    </div>
</div>
