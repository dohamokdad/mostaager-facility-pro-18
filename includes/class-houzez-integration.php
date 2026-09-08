<?php
/**
 * Houzez Advanced Integration - Mostaager Facility PRO Add-On
 */

if (!defined('ABSPATH')) {
    exit;
}

class MS_Houzez_Integration {
    
    private $property_post_type = 'property';
    
    public function __construct() {
        // Add custom fields to Houzez property posts
        add_action('add_meta_boxes', array($this, 'add_houzez_meta_boxes'));
        add_action('save_post', array($this, 'save_houzez_meta_data'));
        
        // Enhance property search with Mostaager data
        add_filter('posts_where', array($this, 'add_mostaager_to_search'));
        
        // Add Mostaager data to property listings
        add_filter('houzez_property_meta_output', array($this, 'add_mostaager_meta_to_output'));
        
        // Integration with Houzez user dashboard
        add_action('houzez_user_dashboard_widgets', array($this, 'add_mostaager_dashboard_widgets'));
    }
    
    /**
     * Add custom meta boxes to Houzez property posts
     */
    public function add_houzez_meta_boxes() {
        add_meta_box(
            'ms_property_info',
            '🏠 معلومات الوحدة (Mostaager)',
            array($this, 'render_property_info_metabox'),
            $this->property_post_type,
            'normal',
            'high'
        );
        
        add_meta_box(
            'ms_tenant_info',
            '👥 معلومات المستأجر',
            array($this, 'render_tenant_info_metabox'),
            $this->property_post_type,
            'side',
            'default'
        );
        
        add_meta_box(
            'ms_financial_info',
            '💰 المعلومات المالية',
            array($this, 'render_financial_info_metabox'),
            $this->property_post_type,
            'side',
            'default'
        );
    }
    
    /**
     * Render property info metabox
     */
    public function render_property_info_metabox($post) {
        wp_nonce_field('ms_property_info_nonce', 'ms_property_info_nonce');
        
        $unit_number = get_post_meta($post->ID, 'ms_unit_number', true);
        $floor = get_post_meta($post->ID, 'ms_floor', true);
        $unit_type = get_post_meta($post->ID, 'ms_unit_type', true);
        $unit_status = get_post_meta($post->ID, 'ms_unit_status', true);
        $building_id = get_post_meta($post->ID, 'ms_building_id', true);
        
        $unit_types = array(
            'apartment' => 'شقة',
            'shop' => 'محل',
            'office' => 'مكتب',
            'villa' => 'فيلا',
            'studio' => 'ستوديو'
        );
        
        $unit_statuses = array(
            'available' => 'متاحة',
            'rented' => 'مؤجرة',
            'sold' => 'مباعة',
            'maintenance' => 'تحت الصيانة'
        );
        
        // Get buildings list
        global $wpdb;
        $buildings = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}ms_buildings ORDER BY title ASC");
        
        ?>
        <div class="ms-houzez-metabox">
            <div class="ms-form-group">
                <label for="ms_unit_number">رقم الوحدة</label>
                <input type="text" id="ms_unit_number" name="ms_unit_number" value="<?php echo esc_attr($unit_number); ?>" class="ms-form-input">
            </div>
            
            <div class="ms-form-group">
                <label for="ms_floor">الطابق</label>
                <input type="text" id="ms_floor" name="ms_floor" value="<?php echo esc_attr($floor); ?>" class="ms-form-input">
            </div>
            
            <div class="ms-form-group">
                <label for="ms_unit_type">نوع الوحدة</label>
                <select id="ms_unit_type" name="ms_unit_type" class="ms-form-select">
                    <option value="">-- اختر --</option>
                    <?php foreach ($unit_types as $value => $label): ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($unit_type, $value); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="ms-form-group">
                <label for="ms_unit_status">حالة الوحدة</label>
                <select id="ms_unit_status" name="ms_unit_status" class="ms-form-select">
                    <option value="">-- اختر --</option>
                    <?php foreach ($unit_statuses as $value => $label): ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($unit_status, $value); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="ms-form-group">
                <label for="ms_building_id">المبنى</label>
                <select id="ms_building_id" name="ms_building_id" class="ms-form-select">
                    <option value="0">-- اختر المبنى --</option>
                    <?php foreach ($buildings as $building): ?>
                        <option value="<?php echo esc_attr($building->id); ?>" <?php selected($building_id, $building->id); ?>>
                            <?php echo esc_html($building->title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="ms-form-group">
                <label>
                    <input type="checkbox" name="ms_sync_with_units" value="1" <?php checked(get_post_meta($post->ID, 'ms_sync_with_units', true)); ?>>
                    مزامنة مع جدول الوحدات
                </label>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render tenant info metabox
     */
    public function render_tenant_info_metabox($post) {
        wp_nonce_field('ms_tenant_info_nonce', 'ms_tenant_info_nonce');
        
        $tenant_id = get_post_meta($post->ID, 'ms_current_tenant_id', true);
        $tenant_name = get_post_meta($post->ID, 'ms_current_tenant_name', true);
        $tenant_phone = get_post_meta($post->ID, 'ms_current_tenant_phone', true);
        $tenant_email = get_post_meta($post->ID, 'ms_current_tenant_email', true);
        
        $lease_start = get_post_meta($post->ID, 'ms_lease_start_date', true);
        $lease_end = get_post_meta($post->ID, 'ms_lease_end_date', true);
        $monthly_rent = get_post_meta($post->ID, 'ms_monthly_rent', true);
        
        ?>
        <div class="ms-houzez-metabox">
            <?php if ($tenant_id): ?>
                <div class="ms-tenant-card">
                    <div class="ms-tenant-avatar">
                        <?php echo get_avatar($tenant_id, 64); ?>
                    </div>
                    <div class="ms-tenant-details">
                        <h4><?php echo esc_html($tenant_name); ?></h4>
                        <p><?php echo esc_html($tenant_phone); ?></p>
                        <p><?php echo esc_html($tenant_email); ?></p>
                    </div>
                </div>
            <?php else: ?>
                <p class="ms-no-tenant">لا يوجد مستأجر حالي</p>
            <?php endif; ?>
            
            <hr>
            
            <div class="ms-form-group">
                <label for="ms_lease_start_date">تاريخ بداية العقد</label>
                <input type="date" id="ms_lease_start_date" name="ms_lease_start_date" value="<?php echo esc_attr($lease_start); ?>" class="ms-form-input">
            </div>
            
            <div class="ms-form-group">
                <label for="ms_lease_end_date">تاريخ نهاية العقد</label>
                <input type="date" id="ms_lease_end_date" name="ms_lease_end_date" value="<?php echo esc_attr($lease_end); ?>" class="ms-form-input">
            </div>
            
            <div class="ms-form-group">
                <label for="ms_monthly_rent">الإيجار الشهري</label>
                <input type="number" id="ms_monthly_rent" name="ms_monthly_rent" value="<?php echo esc_attr($monthly_rent); ?>" class="ms-form-input">
            </div>
        </div>
        <?php
    }
    
    /**
     * Render financial info metabox
     */
    public function render_financial_info_metabox($post) {
        wp_nonce_field('ms_financial_info_nonce', 'ms_financial_info_nonce');
        
        $invoices = $this->get_property_invoices($post->ID);
        $total_paid = $this->get_total_paid($post->ID);
        $total_due = $this->get_total_due($post->ID);
        
        ?>
        <div class="ms-houzez-metabox">
            <div class="ms-financial-summary">
                <div class="ms-financial-item">
                    <span class="ms-financial-label">المدفوع:</span>
                    <span class="ms-financial-value"><?php echo $this->format_currency($total_paid); ?></span>
                </div>
                <div class="ms-financial-item">
                    <span class="ms-financial-label">المستحق:</span>
                    <span class="ms-financial-value"><?php echo $this->format_currency($total_due); ?></span>
                </div>
                <div class="ms-financial-item">
                    <span class="ms-financial-label">الرصيد:</span>
                    <span class="ms-financial-value <?php echo ($total_due > $total_paid) ? 'ms-negative' : 'ms-positive'; ?>">
                        <?php echo $this->format_currency($total_paid - $total_due); ?>
                    </span>
                </div>
            </div>
            
            <hr>
            
            <h4>الفواتير الأخيرة</h4>
            <?php if (!empty($invoices)): ?>
                <ul class="ms-invoice-list">
                    <?php foreach (array_slice($invoices, 0, 5) as $invoice): ?>
                        <li class="ms-invoice-item">
                            <span class="ms-invoice-status <?php echo $invoice->status; ?>">
                                <?php echo $this->get_invoice_status_label($invoice->status); ?>
                            </span>
                            <span class="ms-invoice-amount"><?php echo $this->format_currency($invoice->amount); ?></span>
                            <span class="ms-invoice-date"><?php echo $invoice->due_date; ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="ms-no-invoices">لا توجد فواتير</p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Save Houzez meta data
     */
    public function save_houzez_meta_data($post_id) {
        // Save property info
        if (isset($_POST['ms_property_info_nonce']) && wp_verify_nonce($_POST['ms_property_info_nonce'], 'ms_property_info_nonce')) {
            update_post_meta($post_id, 'ms_unit_number', sanitize_text_field($_POST['ms_unit_number']));
            update_post_meta($post_id, 'ms_floor', sanitize_text_field($_POST['ms_floor']));
            update_post_meta($post_id, 'ms_unit_type', sanitize_text_field($_POST['ms_unit_type']));
            update_post_meta($post_id, 'ms_unit_status', sanitize_text_field($_POST['ms_unit_status']));
            update_post_meta($post_id, 'ms_building_id', intval($_POST['ms_building_id']));
            update_post_meta($id, 'ms_sync_with_units', isset($_POST['ms_sync_with_units']) ? 1 : 0);
        }
        
        // Save tenant info
        if (isset($_POST['ms_tenant_info_nonce']) && wp_verify_nonce($_POST['ms_tenant_info_nonce'], 'ms_tenant_info_nonce')) {
            update_post_meta($post_id, 'ms_lease_start_date', sanitize_text_field($_POST['ms_lease_start_date']));
            update_post_meta($post_id, 'ms_lease_end_date', sanitize_text_field($_POST['ms_lease_end_date']));
            update_post_meta($post_id, 'ms_monthly_rent', sanitize_text_field($_POST['ms_monthly_rent']));
        }
        
        // Save financial info
        if (isset($_POST['ms_financial_info_nonce']) && wp_verify_nonce($_POST['ms_financial_info_nonce'], 'ms_financial_info_nonce')) {
            // Financial info is mainly displayed, not edited directly
        }
    }
    
    /**
     * Add Mostaager data to property search
     */
    public function add_mostaager_to_search($where) {
        global $wpdb;
        
        // Add search by unit number
        if (isset($_GET['ms_unit_number']) && !empty($_GET['ms_unit_number'])) {
            $unit_number = sanitize_text_field($_GET['ms_unit_number']);
            $where .= $wpdb->prepare(
                " AND EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} 
                    WHERE post_id = {$wpdb->posts}.ID 
                    AND meta_key = 'ms_unit_number' 
                    AND meta_value = %s
                )",
                $unit_number
            );
        }
        
        // Add search by building
        if (isset($_GET['ms_building_id']) && !empty($_GET['ms_building_id'])) {
            $building_id = intval($_GET['ms_building_id']);
            $where .= $wpdb->prepare(
                " AND EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} 
                    WHERE post_id = {$wpdb->posts}.ID 
                    AND meta_key = 'ms_building_id' 
                    AND meta_value = %d
                )",
                $building_id
            );
        }
        
        // Add search by unit status
        if (isset($_GET['ms_unit_status']) && !empty($_GET['ms_unit_status'])) {
            $unit_status = sanitize_text_field($_GET['ms_unit_status']);
            $where .= $wpdb->prepare(
                " AND EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} 
                    WHERE post_id = {$wpdb->posts}.ID 
                    AND meta_key = 'ms_unit_status' 
                    AND meta_value = %s
                )",
                $unit_status
            );
        }
        
        return $where;
    }
    
    /**
     * Add Mostaager meta to property output
     */
    public function add_mostaager_meta_to_output($meta, $post_id) {
        $unit_number = get_post_meta($post_id, 'ms_unit_number', true);
        $floor = get_post_meta($post_id, 'ms_floor', true);
        $unit_type = get_post_type($post_id) === 'property' ? get_post_meta($post_id, 'ms_unit_type', true) : '';
        $unit_status = get_post_meta($post_id, 'ms_unit_status', true);
        
        if ($unit_number) {
            $meta['unit_number'] = array(
                'label' => 'رقم الوحدة',
                'value' => $unit_number,
                'icon' => 'fas fa-door-open'
            );
        }
        
        if ($floor) {
            $meta['floor'] = array(
                'label' => 'الطابق',
                'value' => $floor,
                'icon' => 'fas fa-layer-group'
            );
        }
        
        if ($unit_type) {
            $unit_types = array(
                'apartment' => 'شقة',
                'shop' => 'محل',
                'office' => 'مكتب',
                'villa' => 'فيلا',
                'studio' => 'ستوديو'
            );
            $meta['unit_type'] = array(
                'label' => 'نوع الوحدة',
                'value' => isset($unit_types[$unit_type]) ? $unit_types[$unit_type] : $unit_type,
                'icon' => 'fas fa-home'
            );
        }
        
        if ($unit_status) {
            $status_labels = array(
                'available' => 'متاحة',
                'rented' => 'مؤجرة',
                'sold' => 'مباعة',
                'maintenance' => 'تحت الصيانة'
            );
            $meta['unit_status'] = array(
                'label' => 'حالة الوحدة',
                'value' => isset($status_labels[$unit_status]) ? $status_labels[$unit_status] : $unit_status,
                'icon' => 'fas fa-info-circle'
            );
        }
        
        return $meta;
    }
    
    /**
     * Add Mostaager widgets to user dashboard
     */
    public function add_mostaager_dashboard_widgets() {
        // Add tenant properties widget
        houzez_add_dashboard_widget('ms_tenant_properties', array(
            'title' => 'عقاراتي',
            'icon' => 'houzez-icon-home-2',
            'callback' => array($this, 'render_tenant_properties_widget')
        ));
        
        // Add tenant invoices widget
        houzez_add_dashboard_widget('ms_tenant_invoices', array(
            'title' => 'فواتيري',
            'icon' => 'houzez-icon-file-text',
            'callback' => array($this, 'render_tenant_invoices_widget')
        ));
    }
    
    /**
     * Render tenant properties widget
     */
    public function render_tenant_properties_widget() {
        $user_id = get_current_user_id();
        $properties = $this->get_user_properties($user_id);
        
        if (!empty($properties)) {
            echo '<div class="ms-dashboard-widget">';
            echo '<ul class="ms-property-list">';
            
            foreach ($properties as $property) {
                echo '<li class="ms-property-item">';
                echo '<a href="' . get_permalink($property->ID) . '">';
                echo get_the_post_thumbnail($property->ID, 'thumbnail');
                echo '<div class="ms-property-info">';
                echo '<h4>' . get_the_title($property->ID) . '</h4>';
                echo '<span class="ms-property-status">' . get_post_meta($property->ID, 'ms_unit_status', true) . '</span>';
                echo '</div>';
                echo '</a>';
                echo '</li>';
            }
            
            echo '</ul>';
            echo '</div>';
        } else {
            echo '<p>لا توجد عقارات مرتبطة بحسابك</p>';
        }
    }
    
    /**
     * Render tenant invoices widget
     */
    public function render_tenant_invoices_widget() {
        $user_id = get_current_user_id();
        $invoices = $this->get_user_invoices($user_id);
        
        if (!empty($invoices)) {
            echo '<div class="ms-dashboard-widget">';
            echo '<ul class="ms-invoice-list">';
            
            foreach (array_slice($invoices, 0, 5) as $invoice) {
                echo '<li class="ms-invoice-item">';
                echo '<span class="ms-invoice-amount">' . $this->format_currency($invoice->amount) . '</span>';
                echo '<span class="ms-invoice-status ' . $invoice->status . '">' . $this->get_invoice_status_label($invoice->status) . '</span>';
                echo '<span class="ms-invoice-date">' . $invoice->due_date . '</span>';
                echo '</li>';
            }
            
            echo '</ul>';
            echo '</div>';
        } else {
            echo '<p>لا توجد فواتير</p>';
        }
    }
    
    /**
     * Get property invoices
     */
    private function get_property_invoices($property_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ms_invoices 
            WHERE unit_id = %d 
            ORDER BY created_at DESC 
            LIMIT 10",
            $property_id
        ));
    }
    
    /**
     * Get total paid
     */
    private function get_total_paid($property_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM {$wpdb->prefix}ms_invoices 
            WHERE unit_id = %d AND status = 'paid'",
            $property_id
        )) ?: 0;
    }
    
    /**
     * Get total due
     */
    private function get_total_due($property_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM {$wpdb->prefix}ms_invoices 
            WHERE unit_id = %d AND status != 'paid'",
            $property_id
        )) ?: 0;
    }
    
    /**
     * Format currency
     */
    private function format_currency($amount) {
        return number_format($amount, 2) . ' EGP';
    }
    
    /**
     * Get invoice status label
     */
    private function get_invoice_status_label($status) {
        $labels = array(
            'paid' => 'مدفوع',
            'pending' => 'معلق',
            'overdue' => 'متأخر',
            'cancelled' => 'ملغي'
        );
        
        return isset($labels[$status]) ? $labels[$status] : $status;
    }
    
    /**
     * Get user properties
     */
    private function get_user_properties($user_id) {
        $properties = array();
        
        // Get properties where user is tenant
        global $wpdb;
        $property_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT unit_id FROM {$wpdb->prefix}ms_unit_tenants 
            WHERE tenant_id = %d AND (end_date IS NULL OR end_date >= CURDATE())",
            $user_id
        ));
        
        if (!empty($property_ids)) {
            $properties = get_posts(array(
                'post_type' => 'property',
                'post__in' => $property_ids,
                'posts_per_page' => 5
            ));
        }
        
        return $properties;
    }
    
    /**
     * Get user invoices
     */
    private function get_user_invoices($user_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ms_invoices 
            WHERE user_id = %d 
            ORDER BY created_at DESC 
            LIMIT 5",
            $user_id
        ));
    }
}

// Initialize Houzez integration - skip during installation
if (!defined('WP_INSTALLING') || !WP_INSTALLING) {
    new MS_Houzez_Integration();
}
