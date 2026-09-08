<?php
/**
 * Mostaager Facility PRO - Houzez Building Page Integration
 * Display facilities on individual building pages
 */

if (!defined('ABSPATH')) {
    exit;
}

class MS_Houzez_Building_Integration {
    
    public function __construct() {
        // Only initialize if on the frontend
        if (!is_admin()) {
            // Check if Houzez hook exists, otherwise use a generic hook
            if (has_action('houzez_property_after_content')) {
                add_action('houzez_property_after_content', array($this, 'add_facilities_section'));
                add_action('houzez_property_after_content', array($this, 'add_maintenance_section'));
            } else {
                // Fallback to the_content filter
                add_filter('the_content', array($this, 'modify_content'), 20);
            }
            
            // Add custom CSS
            add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        }
    }
    
    /**
     * Modify content as fallback
     */
    public function modify_content($content) {
        if (is_singular('property')) {
            $property_id = get_the_ID();
            $facilities_html = $this->get_facilities_html($property_id);
            $maintenance_html = $this->get_maintenance_html($property_id);
            
            if ($facilities_html || $maintenance_html) {
                return $content . $facilities_html . $maintenance_html;
            }
        }
        return $content;
    }
    
    /**
     * Get facilities HTML
     */
    private function get_facilities_html($property_id) {
        $building_id = $this->get_building_id_from_property($property_id);
        if (!$building_id) return '';
        
        $facilities = $this->get_building_facilities($building_id);
        if (empty($facilities)) return '';
        
        ob_start();
        $this->add_facilities_section($property_id);
        return ob_get_clean();
    }
    
    /**
     * Get maintenance HTML
     */
    private function get_maintenance_html($property_id) {
        $building_id = $this->get_building_id_from_property($property_id);
        if (!$building_id) return '';
        
        $maintenance_requests = $this->get_building_maintenance($building_id);
        if (empty($maintenance_requests)) return '';
        
        ob_start();
        $this->add_maintenance_section($property_id);
        return ob_get_clean();
    }
    
    /**
     * Add facilities section to building page
     */
    public function add_facilities_section($property_id) {
        // Check if this is a building (property with building meta)
        $building_id = $this->get_building_id_from_property($property_id);
        
        if (!$building_id) {
            return '';
        }
        
        // Get facilities for this building
        $facilities = $this->get_building_facilities($building_id);
        
        if (empty($facilities)) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="ms-building-facilities-section" style="margin-top: 40px; padding: 32px; background: #f8fafc; border-radius: 16px;">
            <h3 style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
                <span>🏗️</span>
                <span>مرافق المبنى</span>
            </h3>
            
            <!-- Facilities Summary -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin-bottom: 24px;">
                <?php
                $working_count = 0;
                $maintenance_count = 0;
                
                foreach ($facilities as $facility) {
                    if ($facility->status === 'working') {
                        $working_count++;
                    } else {
                        $maintenance_count++;
                    }
                }
                ?>
                <div style="padding: 16px; background: white; border-radius: 12px; border: 1px solid #e2e8f0; text-align: center;">
                    <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 8px;">إجمالي المرافق</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: #0f172a;"><?php echo count($facilities); ?></div>
                </div>
                <div style="padding: 16px; background: white; border-radius: 12px; border: 1px solid #e2e8f0; text-align: center;">
                    <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 8px;">تعمل</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: #10b981;"><?php echo $working_count; ?></div>
                </div>
                <div style="padding: 16px; background: white; border-radius: 12px; border: 1px solid #e2e8f0; text-align: center;">
                    <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 8px;">تحت صيانة</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: #f59e0b;"><?php echo $maintenance_count; ?></div>
                </div>
            </div>
            
            <!-- Facilities List -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px;">
                <?php foreach ($facilities as $facility) : ?>
                    <div style="padding: 20px; background: white; border-radius: 12px; border: 1px solid #e2e8f0; transition: all 0.3s ease;">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                            <div style="flex: 1;">
                                <h4 style="font-size: 1.1rem; font-weight: 700; color: #0f172a; margin: 0 0 8px 0;">
                                    <?php echo esc_html($facility->name); ?>
                                </h4>
                                <div style="font-size: 0.9rem; color: #64748b;">
                                    <?php echo esc_html($facility->facility_type_name); ?>
                                </div>
                            </div>
                            <div style="padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; background: <?php echo $facility->status === 'working' ? '#dcfce7' : '#fef3c7'; ?>; color: <?php echo $facility->status === 'working' ? '#166534' : '#92400e'; ?>;">
                                <?php echo $facility->status === 'working' ? 'تعمل' : 'تحت صيانة'; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($facility->description)) : ?>
                            <p style="font-size: 0.9rem; color: #64748b; margin: 12px 0; line-height: 1.5;">
                                <?php echo esc_html($facility->description); ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if (!empty($facility->last_maintenance_date)) : ?>
                            <div style="font-size: 0.85rem; color: #64748b; margin-top: 12px;">
                                <span style="font-weight: 600;">آخر صيانة:</span>
                                <?php echo date('Y-m-d', strtotime($facility->last_maintenance_date)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Link to full dashboard -->
            <div style="margin-top: 24px; text-align: center;">
                <a href="<?php echo esc_url(home_url('/building-dashboard/?building_id=' . $building_id . '#facilities')); ?>" 
                   style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; background: #2563eb; color: white; border-radius: 10px; text-decoration: none; font-weight: 600; transition: all 0.3s ease;">
                    <span>⚙️</span>
                    <span>إدارة المرافق في لوحة التحكم</span>
                </a>
            </div>
        </div>
        <?php
        echo ob_get_clean();
    }
    
    /**
     * Add maintenance section to building page
     */
    public function add_maintenance_section($property_id) {
        // Check if this is a building (property with building meta)
        $building_id = $this->get_building_id_from_property($property_id);
        
        if (!$building_id) {
            return '';
        }
        
        // Get maintenance requests for this building
        $maintenance_requests = $this->get_building_maintenance($building_id);
        
        if (empty($maintenance_requests)) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="ms-building-maintenance-section" style="margin-top: 40px; padding: 32px; background: #f8fafc; border-radius: 16px;">
            <h3 style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
                <span>🛠️</span>
                <span>طلبات الصيانة</span>
            </h3>
            
            <!-- Maintenance Summary -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin-bottom: 24px;">
                <?php
                $pending_count = 0;
                $in_progress_count = 0;
                $completed_count = 0;
                
                foreach ($maintenance_requests as $request) {
                    switch ($request->status) {
                        case 'pending':
                            $pending_count++;
                            break;
                        case 'in_progress':
                            $in_progress_count++;
                            break;
                        case 'completed':
                            $completed_count++;
                            break;
                    }
                }
                ?>
                <div style="padding: 16px; background: white; border-radius: 12px; border: 1px solid #e2e8f0; text-align: center;">
                    <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 8px;">إجمالي الطلبات</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: #0f172a;"><?php echo count($maintenance_requests); ?></div>
                </div>
                <div style="padding: 16px; background: white; border-radius: 12px; border: 1px solid #e2e8f0; text-align: center;">
                    <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 8px;">معلقة</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: #f59e0b;"><?php echo $pending_count; ?></div>
                </div>
                <div style="padding: 16px; background: white; border-radius: 12px; border: 1px solid #e2e8f0; text-align: center;">
                    <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 8px;">قيد التنفيذ</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: #2563eb;"><?php echo $in_progress_count; ?></div>
                </div>
                <div style="padding: 16px; background: white; border-radius: 12px; border: 1px solid #e2e8f0; text-align: center;">
                    <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 8px;">مكتملة</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: #10b981;"><?php echo $completed_count; ?></div>
                </div>
            </div>
            
            <!-- Maintenance List -->
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php foreach ($maintenance_requests as $request) : ?>
                    <div style="padding: 20px; background: white; border-radius: 12px; border: 1px solid #e2e8f0; transition: all 0.3s ease;">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                            <div style="flex: 1;">
                                <h4 style="font-size: 1.1rem; font-weight: 700; color: #0f172a; margin: 0 0 8px 0;">
                                    <?php echo esc_html($request->title); ?>
                                </h4>
                                <div style="font-size: 0.9rem; color: #64748b;">
                                    وحدة: <?php echo esc_html($request->unit_number); ?>
                                </div>
                            </div>
                            <div style="padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; background: <?php echo $this->get_status_color($request->status); ?>; color: <?php echo $this->get_status_text_color($request->status); ?>;">
                                <?php echo $this->get_status_label($request->status); ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($request->description)) : ?>
                            <p style="font-size: 0.9rem; color: #64748b; margin: 12px 0; line-height: 1.5;">
                                <?php echo esc_html($request->description); ?>
                            </p>
                        <?php endif; ?>
                        
                        <div style="font-size: 0.85rem; color: #64748b; margin-top: 12px;">
                            <span style="font-weight: 600;">تاريخ الطلب:</span>
                            <?php echo date('Y-m-d H:i', strtotime($request->created_at)); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Link to full dashboard -->
            <div style="margin-top: 24px; text-align: center;">
                <a href="<?php echo esc_url(home_url('/building-dashboard/?building_id=' . $building_id . '#maintenance')); ?>" 
                   style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; background: #2563eb; color: white; border-radius: 10px; text-decoration: none; font-weight: 600; transition: all 0.3s ease;">
                    <span>🛠️</span>
                    <span>إدارة الصيانة في لوحة التحكم</span>
                </a>
            </div>
        </div>
        <?php
        echo ob_get_clean();
    }
    
    /**
     * Get building ID from property
     */
    private function get_building_id_from_property($property_id) {
        // Check multiple meta keys for building ID
        $building_id = get_post_meta($property_id, '_ms_building_id', true);
        
        if (!$building_id) {
            $building_id = get_post_meta($property_id, 'ms_building_id', true);
        }
        
        if (!$building_id) {
            $building_id = get_post_meta($property_id, 'building_id', true);
        }
        
        return intval($building_id);
    }
    
    /**
     * Get facilities for building
     */
    private function get_building_facilities($building_id) {
        global $wpdb;
        
        $facilities_table = $wpdb->prefix . 'ms_facilities';
        $types_table = $wpdb->prefix . 'ms_facility_types';
        
        $sql = "SELECT f.*, ft.name AS facility_type_name
                FROM {$facilities_table} f
                LEFT JOIN {$types_table} ft ON f.facility_type_id = ft.id
                WHERE f.building_id = %d
                ORDER BY f.status ASC, f.created_at DESC";
        
        return $wpdb->get_results($wpdb->prepare($sql, $building_id));
    }
    
    /**
     * Get maintenance requests for building
     */
    private function get_building_maintenance($building_id) {
        global $wpdb;
        
        $maintenance_table = $wpdb->prefix . 'ms_maintenance_requests';
        
        $sql = "SELECT mr.*, u.unit_number
                FROM {$maintenance_table} mr
                LEFT JOIN {$wpdb->prefix}ms_units u ON mr.unit_id = u.id
                WHERE mr.building_id = %d
                ORDER BY mr.created_at DESC
                LIMIT 10";
        
        return $wpdb->get_results($wpdb->prepare($sql, $building_id));
    }
    
    /**
     * Get status color
     */
    private function get_status_color($status) {
        $colors = array(
            'pending' => '#fef3c7',
            'in_progress' => '#dbeafe',
            'completed' => '#dcfce7',
            'cancelled' => '#fee2e2'
        );
        
        return isset($colors[$status]) ? $colors[$status] : '#f3f4f6';
    }
    
    /**
     * Get status text color
     */
    private function get_status_text_color($status) {
        $colors = array(
            'pending' => '#92400e',
            'in_progress' => '#065f46',
            'completed' => '#166534',
            'cancelled' => '#991b1b'
        );
        
        return isset($colors[$status]) ? $colors[$status] : '#475569';
    }
    
    /**
     * Get status label
     */
    private function get_status_label($status) {
        $labels = array(
            'pending' => 'معلقة',
            'in_progress' => 'قيد التنفيذ',
            'completed' => 'مكتملة',
            'cancelled' => 'ملغاة'
        );
        
        return isset($labels[$status]) ? $labels[$status] : $status;
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets() {
        if (is_singular('property')) {
            wp_enqueue_style(
                'ms-houzez-building-integration',
                plugin_dir_url(__FILE__) . 'assets/css/houzez-building-integration.css',
                array(),
                MOSTAAGER_ENTERPRISE_VERSION
            );
        }
    }
}

// Initialize the integration only when WordPress is fully loaded
add_action('plugins_loaded', function() {
    new MS_Houzez_Building_Integration();
});