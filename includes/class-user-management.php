<?php
/**
 * Advanced User Management - Mostaager Facility PRO Add-On
 * User roles, permissions, activity tracking, and bulk operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class MS_Advanced_User_Management {
    
    private $user_roles;
    private $user_activities;
    
    public function __construct() {
        add_action('wp_ajax_ms_get_users', array($this, 'get_users'));
        add_action('wp_ajax_ms_create_user', array($this, 'create_user'));
        add_action('wp_ajax_ms_update_user', array($this, 'update_user'));
        add_action('wp_ajax_ms_delete_user', array($this, 'delete_user'));
        add_action('wp_ajax_ms_bulk_user_action', array($this, 'bulk_user_action'));
        add_action('wp_ajax_ms_get_user_activity', array($this, 'get_user_activity'));
        add_action('wp_ajax_ms_update_user_permissions', array($this, 'update_user_permissions'));
        
        // Initialize user roles (in memory)
        $this->init_user_roles();
        
        // Register custom roles in WordPress if not already registered
        add_action('init', array($this, 'register_wordpress_roles'));
        
        // Track user activity
        add_action('wp_login', array($this, 'track_user_login'), 10, 2);
        add_action('profile_update', array($this, 'track_user_update'), 10, 2);
    }
    
    /**
     * Register custom roles in WordPress
     * Called on 'init' hook so roles are available system-wide
     */
    public function register_wordpress_roles() {
        $roles_to_register = array(
            'building_manager' => array(
                'display_name' => 'مدير المبنى',
                'capabilities' => array(
                    'read'              => true,
                    'manage_buildings'  => true,
                    'manage_maintenance'=> true,
                    'manage_invoices'   => true,
                    'manage_units'      => true,
                    'view_reports'      => true,
                )
            ),
            'property_owner' => array(
                'display_name' => 'مالك العقار',
                'capabilities' => array(
                    'read'                   => true,
                    'view_own_properties'    => true,
                    'view_own_maintenance'   => true,
                    'view_own_invoices'      => true,
                )
            ),
            'ms_tenant' => array(
                'display_name' => 'المستأجر',
                'capabilities' => array(
                    'read'                   => true,
                    'view_own_unit'          => true,
                    'submit_maintenance'     => true,
                    'view_own_invoices'      => true,
                )
            ),
            'maintenance_staff' => array(
                'display_name' => 'فريق الصيانة',
                'capabilities' => array(
                    'read'                       => true,
                    'view_assigned_maintenance'  => true,
                    'update_maintenance_status'  => true,
                )
            ),
            'ms_accountant' => array(
                'display_name' => 'المحاسب',
                'capabilities' => array(
                    'read'                    => true,
                    'manage_invoices'         => true,
                    'view_financial_reports'  => true,
                    'process_payments'        => true,
                )
            ),
        );
        
        foreach ($roles_to_register as $role_slug => $role_data) {
            // Only add if not already registered
            if (!get_role($role_slug)) {
                add_role($role_slug, $role_data['display_name'], $role_data['capabilities']);
            }
        }
    }
    
    /**
     * Remove custom roles on plugin deactivation (call from main plugin file if needed)
     */
    public static function remove_wordpress_roles() {
        $custom_roles = array(
            'building_manager', 'property_owner', 'ms_tenant',
            'maintenance_staff', 'ms_accountant'
        );
        foreach ($custom_roles as $role) {
            remove_role($role);
        }
    }
    
    /**
     * Initialize user roles
     */
    private function init_user_roles() {
        $this->user_roles = array(
            'building_manager' => array(
                'name' => 'مدير المبنى',
                'capabilities' => array(
                    'read' => true,
                    'manage_buildings' => true,
                    'manage_maintenance' => true,
                    'manage_invoices' => true,
                    'manage_units' => true,
                    'view_reports' => true
                )
            ),
            'property_owner' => array(
                'name' => 'مالك العقار',
                'capabilities' => array(
                    'read' => true,
                    'view_own_properties' => true,
                    'view_own_maintenance' => true,
                    'view_own_invoices' => true
                )
            ),
            'tenant' => array(
                'name' => 'المستأجر',
                'capabilities' => array(
                    'read' => true,
                    'view_own_unit' => true,
                    'submit_maintenance' => true,
                    'view_own_invoices' => true
                )
            ),
            'maintenance_staff' => array(
                'name' => 'فريق الصيانة',
                'capabilities' => array(
                    'read' => true,
                    'view_assigned_maintenance' => true,
                    'update_maintenance_status' => true
                )
            ),
            'accountant' => array(
                'name' => 'المحاسب',
                'capabilities' => array(
                    'read' => true,
                    'manage_invoices' => true,
                    'view_financial_reports' => true,
                    'process_payments' => true
                )
            )
        );
    }
    
    /**
     * Track user login
     */
    public function track_user_login($user_login, $user) {
        $this->log_user_activity($user->ID, 'login', array('ip' => $this->get_user_ip()));
    }
    
    /**
     * Track user update
     */
    public function track_user_update($user_id, $old_user_data) {
        $this->log_user_activity($user_id, 'profile_update', array(
            'previous_data' => $old_user_data
        ));
    }
    
    /**
     * Log user activity
     */
    private function log_user_activity($user_id, $action, $details = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ms_user_activities';
        
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'action' => $action,
                'details' => json_encode($details),
                'ip_address' => $this->get_user_ip(),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Get user IP
     */
    private function get_user_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']);
        } else {
            return sanitize_text_field($_SERVER['REMOTE_ADDR']);
        }
    }

    private function require_user_management_capability() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'ليس لديك الصلاحية'), 403);
        }
    }
    
    /**
     * Get users via AJAX
     */
    public function get_users() {
        check_ajax_referer('ms_get_users', 'nonce');
        $this->require_user_management_capability();
        
        $role = isset($_POST['role']) ? sanitize_text_field($_POST['role']) : '';
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 20;
        
        $args = array(
            'number' => $per_page,
            'paged' => $page,
            'orderby' => 'display_name',
            'order' => 'ASC'
        );
        
        if ($role) {
            $args['role__in'] = array($role);
        }
        
        if ($search) {
            $args['search'] = '*' . $search . '*';
        }
        
        $users = get_users($args);
        $total_users = count_users();
        $total_filtered = $role ? (isset($total_users['avail_roles'][$role]) ? $total_users['avail_roles'][$role] : 0) : $total_users['total_users'];
        
        $user_data = array();
        foreach ($users as $user) {
            $user_data[] = array(
                'ID' => $user->ID,
                'display_name' => $user->display_name,
                'user_email' => $user->user_email,
                'roles' => $user->roles,
                'building_access' => $this->get_user_building_access($user->ID),
                'last_active' => $this->get_user_last_active($user->ID),
                'status' => $this->get_user_status($user->ID)
            );
        }
        
        wp_send_json_success(array(
            'users' => $user_data,
            'total' => $total_filtered,
            'page' => $page,
            'per_page' => $per_page
        ));
    }
    
    /**
     * Get user building access
     */
    private function get_user_building_access($user_id) {
        $building_ids = get_user_meta($user_id, 'building_access', true);
        return is_array($building_ids) ? $building_ids : array();
    }
    
    /**
     * Get user last active
     */
    private function get_user_last_active($user_id) {
        return get_user_meta($user_id, 'last_activity', true);
    }
    
    /**
     * Get user status
     */
    private function get_user_status($user_id) {
        $user = get_userdata($user_id);
        return $user && $user->user_status === 0 ? 'active' : 'inactive';
    }
    
    /**
     * Create user via AJAX
     */
    public function create_user() {
        check_ajax_referer('ms_create_user', 'nonce');
        $this->require_user_management_capability();
        
        $username = sanitize_text_field($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        $role = sanitize_text_field($_POST['role']);
        $building_access = isset($_POST['building_access']) ? json_decode(stripslashes($_POST['building_access']), true) : array();
        
        // Validate input
        if (empty($username) || empty($email) || empty($password)) {
            wp_send_json_error(array('message' => 'جميع الحقول مطلوبة'));
        }
        
        if (username_exists($username)) {
            wp_send_json_error(array('message' => 'اسم المستخدم موجود بالفعل'));
        }
        
        if (email_exists($email)) {
            wp_send_json_error(array('message' => 'البريد الإلكتروني موجود بالفعل'));
        }
        
        // Create user
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error(array('message' => $user_id->get_error_message()));
        }
        
        // Set role
        $user = new WP_User($user_id);
        $user->set_role($role);
        
        // Set building access
        update_user_meta($user_id, 'building_access', $building_access);
        
        // Log activity
        $this->log_user_activity($user_id, 'user_created', array(
            'created_by' => get_current_user_id(),
            'role' => $role
        ));
        
        wp_send_json_success(array(
            'user_id' => $user_id,
            'message' => 'تم إنشاء المستخدم بنجاح'
        ));
    }
    
    /**
     * Update user via AJAX
     */
    public function update_user() {
        check_ajax_referer('ms_update_user', 'nonce');
        $this->require_user_management_capability();
        
        $user_id = intval($_POST['user_id']);
        $display_name = sanitize_text_field($_POST['display_name']);
        $email = sanitize_email($_POST['email']);
        $role = sanitize_text_field($_POST['role']);
        $building_access = isset($_POST['building_access']) ? json_decode(stripslashes($_POST['building_access']), true) : array();
        
        // Validate input
        if (empty($display_name) || empty($email)) {
            wp_send_json_error(array('message' => 'جميع الحقول مطلوبة'));
        }
        
        // Update user
        $user_data = array(
            'ID' => $user_id,
            'display_name' => $display_name,
            'user_email' => $email
        );
        
        $result = wp_update_user($user_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        // Update role
        $user = new WP_User($user_id);
        $user->set_role($role);
        
        // Update building access
        update_user_meta($user_id, 'building_access', $building_access);
        
        // Log activity
        $this->log_user_activity($user_id, 'user_updated', array(
            'updated_by' => get_current_user_id(),
            'role' => $role
        ));
        
        wp_send_json_success(array(
            'user_id' => $user_id,
            'message' => 'تم تحديث المستخدم بنجاح'
        ));
    }
    
    /**
     * Delete user via AJAX
     */
    public function delete_user() {
        check_ajax_referer('ms_delete_user', 'nonce');
        $this->require_user_management_capability();
        
        $user_id = intval($_POST['user_id']);
        $reassign_to = isset($_POST['reassign_to']) ? intval($_POST['reassign_to']) : 0;
        
        if ($user_id === get_current_user_id()) {
            wp_send_json_error(array('message' => 'لا يمكنك حذف حسابك الحالي'));
        }
        
        // Log activity before deletion
        $this->log_user_activity($user_id, 'user_deleted', array(
            'deleted_by' => get_current_user_id()
        ));
        
        // Delete user
        $result = wp_delete_user($user_id, $reassign_to);
        
        if (!$result) {
            wp_send_json_error(array('message' => 'فشل حذف المستخدم'));
        }
        
        wp_send_json_success(array(
            'user_id' => $user_id,
            'message' => 'تم حذف المستخدم بنجاح'
        ));
    }
    
    /**
     * Bulk user action
     */
    public function bulk_user_action() {
        check_ajax_referer('ms_bulk_user_action', 'nonce');
        $this->require_user_management_capability();
        
        $action = sanitize_text_field($_POST['bulk_action']);
        $user_ids = array_map('intval', $_POST['user_ids']);
        
        $results = array();
        
        foreach ($user_ids as $user_id) {
            switch ($action) {
                case 'activate':
                    $result = $this->activate_user($user_id);
                    break;
                case 'deactivate':
                    $result = $this->deactivate_user($user_id);
                    break;
                case 'delete':
                    $result = $this->delete_user_single($user_id);
                    break;
                case 'change_role':
                    $new_role = sanitize_text_field($_POST['new_role']);
                    $result = $this->change_user_role($user_id, $new_role);
                    break;
                default:
                    $result = array('success' => false, 'message' => 'إجراء غير صالح');
            }
            
            $results[] = array(
                'user_id' => $user_id,
                'result' => $result
            );
        }
        
        wp_send_json_success(array(
            'results' => $results,
            'message' => 'تم تنفيذ الإجراءات الجماعية'
        ));
    }
    
    /**
     * Activate user
     */
    private function activate_user($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return array('success' => false, 'message' => 'المستخدم غير موجود');
        }
        
        // WordPress doesn't have a native deactivate/activate, so we use a meta field
        update_user_meta($user_id, 'account_status', 'active');
        
        return array('success' => true, 'message' => 'تم تفعيل المستخدم');
    }
    
    /**
     * Deactivate user
     */
    private function deactivate_user($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return array('success' => false, 'message' => 'المستخدم غير موجود');
        }
        
        update_user_meta($user_id, 'account_status', 'inactive');
        
        return array('success' => true, 'message' => 'تم تعطيل المستخدم');
    }
    
    /**
     * Delete user single
     */
    private function delete_user_single($user_id) {
        if ($user_id === get_current_user_id()) {
            return array('success' => false, 'message' => 'لا يمكنك حذف حسابك الحالي');
        }
        
        $result = wp_delete_user($user_id);
        
        if (!$result) {
            return array('success' => false, 'message' => 'فشل حذف المستخدم');
        }
        
        return array('success' => true, 'message' => 'تم حذف المستخدم');
    }
    
    /**
     * Change user role
     */
    private function change_user_role($user_id, $new_role) {
        $user = new WP_User($user_id);
        $user->set_role($new_role);
        
        return array('success' => true, 'message' => 'تم تغيير الدور بنجاح');
    }
    
    /**
     * Get user activity
     */
    public function get_user_activity() {
        check_ajax_referer('ms_get_user_activity', 'nonce');
        $this->require_user_management_capability();
        
        $user_id = intval($_POST['user_id']);
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 50;
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ms_user_activities';
        
        $activities = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
            $user_id,
            $limit
        ));
        
        wp_send_json_success(array(
            'activities' => $activities,
            'user_id' => $user_id
        ));
    }
    
    /**
     * Update user permissions
     */
    public function update_user_permissions() {
        check_ajax_referer('ms_update_user_permissions', 'nonce');
        $this->require_user_management_capability();
        
        $user_id = intval($_POST['user_id']);
        $permissions = json_decode(stripslashes($_POST['permissions']), true);
        
        // Store custom permissions
        update_user_meta($user_id, 'custom_permissions', $permissions);
        
        // Log activity
        $this->log_user_activity($user_id, 'permissions_updated', array(
            'updated_by' => get_current_user_id(),
            'permissions' => $permissions
        ));
        
        wp_send_json_success(array(
            'user_id' => $user_id,
            'message' => 'تم تحديث الصلاحيات بنجاح'
        ));
    }
    
    /**
     * Render user management dashboard
     */
    public function render_user_management_dashboard() {
        ob_start();
        ?>
        <div class="ms-user-management-dashboard">
            <div class="ms-dashboard-header">
                <h2>إدارة المستخدمين المتقدمة</h2>
                <p>إدارة المستخدمين والصلاحيات وتتبع النشاط</p>
            </div>
            
            <!-- User Statistics -->
            <div class="ms-user-stats">
                <div class="ms-stat-card">
                    <div class="ms-stat-icon">👥</div>
                    <div class="ms-stat-info">
                        <h3><?php echo count_users()['total_users']; ?></h3>
                        <p>إجمالي المستخدمين</p>
                    </div>
                </div>
                
                <div class="ms-stat-card">
                    <div class="ms-stat-icon">🏢</div>
                    <div class="ms-stat-info">
                        <h3><?php echo isset(count_users()['avail_roles']['building_manager']) ? count_users()['avail_roles']['building_manager'] : 0; ?></h3>
                        <p>مديري الأبنية</p>
                    </div>
                </div>
                
                <div class="ms-stat-card">
                    <div class="ms-stat-icon">👤</div>
                    <div class="ms-stat-info">
                        <h3><?php echo isset(count_users()['avail_roles']['tenant']) ? count_users()['avail_roles']['tenant'] : 0; ?></h3>
                        <p>المستأجرين</p>
                    </div>
                </div>
                
                <div class="ms-stat-card">
                    <div class="ms-stat-icon">🟢</div>
                    <div class="ms-stat-info">
                        <h3><?php echo $this->get_active_users_count(); ?></h3>
                        <p>المستخدمين النشطين</p>
                    </div>
                </div>
            </div>
            
            <!-- User Controls -->
            <div class="ms-user-controls">
                <div class="ms-control-group">
                    <button class="ms-btn ms-btn-primary" id="ms-add-user">إضافة مستخدم جديد</button>
                    <button class="ms-btn ms-btn-secondary" id="ms-bulk-actions">إجراءات جماعية</button>
                </div>
                
                <div class="ms-control-group">
                    <input type="text" id="ms-user-search" placeholder="بحث عن مستخدم...">
                    <select id="ms-role-filter">
                        <option value="">جميع الأدوار</option>
                        <?php foreach ($this->user_roles as $role_key => $role): ?>
                            <option value="<?php echo $role_key; ?>"><?php echo $role['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <!-- Users Table -->
            <div class="ms-users-table-container">
                <table class="ms-users-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="ms-select-all-users"></th>
                            <th>الاسم</th>
                            <th>البريد الإلكتروني</th>
                            <th>الدور</th>
                            <th>المباني</th>
                            <th>آخر نشاط</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="ms-users-table-body">
                        <!-- Users will be loaded here -->
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="ms-pagination" id="ms-users-pagination">
                <!-- Pagination will be loaded here -->
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get active users count
     */
    private function get_active_users_count() {
        global $wpdb;
        
        $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}ms_user_activities WHERE created_at >= %s",
            $thirty_days_ago
        ));
        
        return $count ? intval($count) : 0;
    }
}