<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mostaager Pro Platform layer.
 * Adds normalized SaaS-grade modules while preserving the legacy ms_* and Houzez integrations.
 */

if (!defined('MOSTAAGER_PRO_PLATFORM_DB_VERSION')) {
    define('MOSTAAGER_PRO_PLATFORM_DB_VERSION', '14.1.1');
}

function msfp_install_pro_tables()
{
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    $sqls = array();

    $sqls[] = "CREATE TABLE {$wpdb->prefix}ms_building_agents (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        building_id BIGINT(20) UNSIGNED NOT NULL,
        agent_id BIGINT(20) UNSIGNED NOT NULL,
        assigned_by BIGINT(20) UNSIGNED DEFAULT 0,
        status VARCHAR(30) NOT NULL DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY building_agent (building_id, agent_id),
        KEY agent_id (agent_id),
        KEY building_id (building_id),
        KEY status (status)
    ) $charset_collate";

    $sqls[] = "CREATE TABLE {$wpdb->prefix}ms_expenses (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        building_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        unit_id BIGINT(20) UNSIGNED DEFAULT 0,
        vendor_id BIGINT(20) UNSIGNED DEFAULT 0,
        created_by BIGINT(20) UNSIGNED DEFAULT 0,
        expense_type VARCHAR(80) NOT NULL DEFAULT 'other',
        title VARCHAR(191) NOT NULL,
        description TEXT NULL,
        amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        currency VARCHAR(10) NOT NULL DEFAULT 'EGP',
        expense_date DATE NULL,
        status VARCHAR(40) NOT NULL DEFAULT 'approved',
        payment_status VARCHAR(40) NOT NULL DEFAULT 'paid',
        source VARCHAR(40) NOT NULL DEFAULT 'manual',
        reference_id BIGINT(20) UNSIGNED DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY building_id (building_id),
        KEY expense_type (expense_type),
        KEY status (status),
        KEY expense_date (expense_date)
    ) $charset_collate";

    $sqls[] = "CREATE TABLE {$wpdb->prefix}ms_expense_attachments (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        expense_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        file_id BIGINT(20) UNSIGNED DEFAULT 0,
        file_url TEXT NULL,
        file_type VARCHAR(100) DEFAULT '',
        uploaded_by BIGINT(20) UNSIGNED DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY expense_id (expense_id)
    ) $charset_collate";

    $sqls[] = "CREATE TABLE {$wpdb->prefix}ms_work_orders (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        building_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        unit_id BIGINT(20) UNSIGNED DEFAULT 0,
        request_id BIGINT(20) UNSIGNED DEFAULT 0,
        title VARCHAR(191) NOT NULL,
        description TEXT NULL,
        status VARCHAR(40) NOT NULL DEFAULT 'request',
        priority VARCHAR(40) NOT NULL DEFAULT 'medium',
        technician_id BIGINT(20) UNSIGNED DEFAULT 0,
        requested_by BIGINT(20) UNSIGNED DEFAULT 0,
        assigned_by BIGINT(20) UNSIGNED DEFAULT 0,
        estimated_cost DECIMAL(15,2) DEFAULT 0.00,
        actual_cost DECIMAL(15,2) DEFAULT 0.00,
        due_date DATE NULL,
        started_at DATETIME NULL,
        completed_at DATETIME NULL,
        closed_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY building_id (building_id),
        KEY unit_id (unit_id),
        KEY status (status),
        KEY technician_id (technician_id)
    ) $charset_collate";

    $sqls[] = "CREATE TABLE {$wpdb->prefix}ms_work_order_events (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        work_order_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        event_type VARCHAR(80) NOT NULL DEFAULT 'note',
        title VARCHAR(191) NOT NULL,
        description TEXT NULL,
        old_status VARCHAR(40) DEFAULT '',
        new_status VARCHAR(40) DEFAULT '',
        created_by BIGINT(20) UNSIGNED DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY work_order_id (work_order_id),
        KEY event_type (event_type)
    ) $charset_collate";

    $sqls[] = "CREATE TABLE {$wpdb->prefix}ms_work_order_attachments (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        work_order_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        file_id BIGINT(20) UNSIGNED DEFAULT 0,
        file_url TEXT NULL,
        file_type VARCHAR(100) DEFAULT '',
        uploaded_by BIGINT(20) UNSIGNED DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY work_order_id (work_order_id)
    ) $charset_collate";

    $sqls[] = "CREATE TABLE {$wpdb->prefix}ms_technician_ratings (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        work_order_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        technician_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        rated_by BIGINT(20) UNSIGNED DEFAULT 0,
        rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
        review TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY work_order_id (work_order_id),
        KEY technician_id (technician_id)
    ) $charset_collate";

    $sqls[] = "CREATE TABLE {$wpdb->prefix}ms_owner_reports (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        owner_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        building_id BIGINT(20) UNSIGNED DEFAULT 0,
        report_month VARCHAR(7) NOT NULL DEFAULT '',
        income DECIMAL(15,2) DEFAULT 0.00,
        expenses DECIMAL(15,2) DEFAULT 0.00,
        maintenance DECIMAL(15,2) DEFAULT 0.00,
        profit DECIMAL(15,2) DEFAULT 0.00,
        file_url TEXT NULL,
        emailed_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY owner_month (owner_id, report_month),
        KEY building_id (building_id)
    ) $charset_collate";

    $sqls[] = "CREATE TABLE {$wpdb->prefix}ms_documents (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        building_id BIGINT(20) UNSIGNED DEFAULT 0,
        unit_id BIGINT(20) UNSIGNED DEFAULT 0,
        document_type VARCHAR(80) NOT NULL DEFAULT 'other',
        title VARCHAR(191) NOT NULL,
        file_id BIGINT(20) UNSIGNED DEFAULT 0,
        file_url TEXT NULL,
        expiry_date DATE NULL,
        visibility VARCHAR(40) DEFAULT 'private',
        uploaded_by BIGINT(20) UNSIGNED DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY building_id (building_id),
        KEY unit_id (unit_id),
        KEY document_type (document_type)
    ) $charset_collate";

    $sqls[] = "CREATE TABLE {$wpdb->prefix}ms_meter_readings (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        building_id BIGINT(20) UNSIGNED DEFAULT 0,
        unit_id BIGINT(20) UNSIGNED DEFAULT 0,
        tenant_id BIGINT(20) UNSIGNED DEFAULT 0,
        meter_type VARCHAR(40) NOT NULL DEFAULT 'electricity',
        reading_value DECIMAL(15,3) NOT NULL DEFAULT 0.000,
        reading_date DATE NULL,
        image_id BIGINT(20) UNSIGNED DEFAULT 0,
        image_url TEXT NULL,
        notes TEXT NULL,
        created_by BIGINT(20) UNSIGNED DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY building_id (building_id),
        KEY unit_id (unit_id),
        KEY meter_type (meter_type),
        KEY reading_date (reading_date)
    ) $charset_collate";

    $sqls[] = "CREATE TABLE {$wpdb->prefix}ms_transfer_requests (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        work_order_id BIGINT(20) UNSIGNED NOT NULL,
        building_id BIGINT(20) UNSIGNED NOT NULL,
        requested_by BIGINT(20) UNSIGNED NOT NULL,
        funding_source VARCHAR(30) NOT NULL DEFAULT 'collection',
        amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        status VARCHAR(40) NOT NULL DEFAULT 'pending',
        processed_by BIGINT(20) UNSIGNED DEFAULT 0,
        processed_at DATETIME NULL,
        notes TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY work_order_id (work_order_id),
        KEY building_id (building_id),
        KEY requested_by (requested_by),
        KEY status (status)
    ) $charset_collate";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    foreach ($sqls as $sql) {
        dbDelta($sql);
    }

    // Preserve existing unit-level assignments when introducing explicit building assignments.
    $assignment_table = $wpdb->prefix . 'ms_building_agents';
    $units_table = $wpdb->prefix . 'ms_units';
    $wpdb->query("INSERT IGNORE INTO {$assignment_table} (building_id, agent_id, assigned_by, status) SELECT DISTINCT building_id, agent_id, 0, 'active' FROM {$units_table} WHERE agent_id > 0 AND building_id > 0");

    msfp_add_missing_column($wpdb->prefix . 'ms_buildings', 'building_type', "ALTER TABLE {$wpdb->prefix}ms_buildings ADD COLUMN building_type VARCHAR(80) DEFAULT 'residential'");
    msfp_add_missing_column($wpdb->prefix . 'ms_buildings', 'address', "ALTER TABLE {$wpdb->prefix}ms_buildings ADD COLUMN address TEXT NULL");
    msfp_add_missing_column($wpdb->prefix . 'ms_buildings', 'status', "ALTER TABLE {$wpdb->prefix}ms_buildings ADD COLUMN status VARCHAR(40) DEFAULT 'active'");
    msfp_add_missing_column($wpdb->prefix . 'ms_units', 'unit_number', "ALTER TABLE {$wpdb->prefix}ms_units ADD COLUMN unit_number VARCHAR(80) DEFAULT ''");
    msfp_add_missing_column($wpdb->prefix . 'ms_units', 'floor', "ALTER TABLE {$wpdb->prefix}ms_units ADD COLUMN floor VARCHAR(80) DEFAULT ''");
    msfp_add_missing_column($wpdb->prefix . 'ms_units', 'monthly_rent', "ALTER TABLE {$wpdb->prefix}ms_units ADD COLUMN monthly_rent DECIMAL(15,2) DEFAULT 0.00");
    msfp_add_missing_column($wpdb->prefix . 'ms_units', 'buyer_id', "ALTER TABLE {$wpdb->prefix}ms_units ADD COLUMN buyer_id BIGINT(20) UNSIGNED DEFAULT 0");
    msfp_add_missing_column($wpdb->prefix . 'ms_units', 'property_id', "ALTER TABLE {$wpdb->prefix}ms_units ADD COLUMN property_id BIGINT(20) UNSIGNED DEFAULT 0");
    msfp_add_missing_column($wpdb->prefix . 'ms_transfer_requests', 'owner_id', "ALTER TABLE {$wpdb->prefix}ms_transfer_requests ADD COLUMN owner_id BIGINT(20) UNSIGNED DEFAULT 0");
    msfp_add_missing_column($wpdb->prefix . 'ms_transfer_requests', 'bank_name', "ALTER TABLE {$wpdb->prefix}ms_transfer_requests ADD COLUMN bank_name VARCHAR(191) DEFAULT ''");
    msfp_add_missing_column($wpdb->prefix . 'ms_transfer_requests', 'bank_account_name', "ALTER TABLE {$wpdb->prefix}ms_transfer_requests ADD COLUMN bank_account_name VARCHAR(191) DEFAULT ''");
    msfp_add_missing_column($wpdb->prefix . 'ms_transfer_requests', 'bank_account_number', "ALTER TABLE {$wpdb->prefix}ms_transfer_requests ADD COLUMN bank_account_number VARCHAR(191) DEFAULT ''");
    msfp_add_missing_column($wpdb->prefix . 'ms_transfer_requests', 'bank_iban', "ALTER TABLE {$wpdb->prefix}ms_transfer_requests ADD COLUMN bank_iban VARCHAR(191) DEFAULT ''");
    msfp_add_missing_column($wpdb->prefix . 'ms_transfer_requests', 'funding_source', "ALTER TABLE {$wpdb->prefix}ms_transfer_requests ADD COLUMN funding_source VARCHAR(30) NOT NULL DEFAULT 'collection'");

    update_option('mostaager_pro_platform_db_version', MOSTAAGER_PRO_PLATFORM_DB_VERSION);
}

function msfp_agent_can_manage_building($agent_id, $building_id)
{
    $agent_id = absint($agent_id);
    $building_id = absint($building_id);
    if (!$agent_id || !$building_id) {
        return false;
    }
    if (user_can($agent_id, 'manage_options')) {
        return true;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'ms_building_agents';
    return (bool) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table} WHERE agent_id = %d AND building_id = %d AND status = 'active' LIMIT 1",
        $agent_id,
        $building_id
    ));
}

function msfp_get_agent_building_ids($agent_id)
{
    $agent_id = absint($agent_id);
    if (!$agent_id) {
        return array();
    }
    if (user_can($agent_id, 'manage_options')) {
        global $wpdb;
        return array_map('absint', (array) $wpdb->get_col("SELECT id FROM {$wpdb->prefix}ms_buildings ORDER BY id ASC"));
    }
    global $wpdb;
    $table = $wpdb->prefix . 'ms_building_agents';
    return array_map('absint', (array) $wpdb->get_col($wpdb->prepare(
        "SELECT building_id FROM {$table} WHERE agent_id = %d AND status = 'active' ORDER BY building_id ASC",
        $agent_id
    )));
}

function msfp_get_agent_buildings($agent_id)
{
    global $wpdb;
    $ids = msfp_get_agent_building_ids($agent_id);
    if (empty($ids)) {
        return array();
    }
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ms_buildings WHERE id IN ({$placeholders}) ORDER BY title ASC",
        $ids
    ));
}

function msfp_get_agent_units($agent_id, $building_id = 0)
{
    global $wpdb;
    $agent_id = absint($agent_id);
    $building_id = absint($building_id);
    $ids = msfp_get_agent_building_ids($agent_id);
    if (empty($ids)) {
        return array();
    }
    if ($building_id && !in_array($building_id, $ids, true)) {
        return array();
    }
    $scope = $building_id ? array($building_id) : $ids;
    $placeholders = implode(',', array_fill(0, count($scope), '%d'));
    $params = $scope;
    $sql = "SELECT * FROM {$wpdb->prefix}ms_units WHERE building_id IN ({$placeholders})";
    if (!user_can($agent_id, 'manage_options')) {
        $sql .= " AND (agent_id = %d OR agent_id = 0)";
        $params[] = $agent_id;
    }
    $sql .= " ORDER BY building_id ASC, id DESC";
    return $wpdb->get_results($wpdb->prepare($sql, $params));
}

function msfp_add_missing_column($table, $column, $alter_sql)
{
    global $wpdb;

    $table_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s",
        $table
    ));
    if (empty($table_exists)) {
        return;
    }

    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s",
        $table,
        $column
    ));
    if (empty($exists)) {
        $wpdb->query($alter_sql);
    }
}

add_action('plugins_loaded', function () {
    if (get_option('mostaager_pro_platform_db_version') !== MOSTAAGER_PRO_PLATFORM_DB_VERSION) {
        msfp_install_pro_tables();
    }
}, 30);

function msfp_current_user_can_manage_building($building_id)
{
    $building_id = absint($building_id);
    if (!$building_id || !is_user_logged_in()) {
        return false;
    }
    if (current_user_can('manage_options')) {
        return true;
    }
    $user_id = get_current_user_id();
    if (function_exists('ms_current_user_manages_building') && ms_current_user_manages_building($user_id, $building_id)) {
        return true;
    }
    $buildings = function_exists('ms_get_buildings_by_manager') ? ms_get_buildings_by_manager($user_id) : array();
    foreach ((array) $buildings as $building) {
        if (intval($building->id ?? 0) === $building_id) {
            return true;
        }
    }
    return false;
}

function msfp_expense_type_labels()
{
    return array(
        'electricity' => 'كهرباء',
        'water' => 'مياه',
        'security' => 'حراسة',
        'cleaning' => 'تنظيف',
        'maintenance' => 'صيانة',
        'supplier' => 'موردين',
        'other' => 'أخرى',
    );
}

function msfp_work_order_status_labels()
{
    return array(
        'request' => 'طلب جديد',
        'assigned' => 'تم الإسناد',
        'in_progress' => 'قيد التنفيذ',
        'completed' => 'مكتمل',
        'closed' => 'مغلق',
        'cancelled' => 'ملغى',
        'canceled' => 'ملغى',
    );
}

function msfp_get_financial_summary($building_id, $month = '')
{
    global $wpdb;
    $building_id = absint($building_id);
    $month = $month ? sanitize_text_field($month) : current_time('Y-m');

    $start = $month . '-01';
    $end = date('Y-m-t', strtotime($start));

    $invoice_table = $wpdb->prefix . 'ms_invoices';
    $expense_table = $wpdb->prefix . 'ms_expenses';
    $wallet_table = $wpdb->prefix . 'ms_building_wallet';

    $monthly_income = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$invoice_table} WHERE building_id = %d AND status = 'paid' AND DATE(COALESCE(paid_date, created_at)) BETWEEN %s AND %s",
        $building_id,
        $start,
        $end
    ));

    $monthly_expenses = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$expense_table} WHERE building_id = %d AND status IN ('approved','paid') AND DATE(COALESCE(expense_date, created_at)) BETWEEN %s AND %s",
        $building_id,
        $start,
        $end
    ));

    $balance = $wpdb->get_var($wpdb->prepare("SELECT balance FROM {$wallet_table} WHERE building_id = %d", $building_id));
    $balance = $balance !== null ? (float) $balance : ($monthly_income - $monthly_expenses);

    return array(
        'current_balance' => $balance,
        'monthly_income' => $monthly_income,
        'monthly_expenses' => $monthly_expenses,
        'net_profit' => $monthly_income - $monthly_expenses,
        'month' => $month,
    );
}

function msfp_get_building_expenses($building_id, $limit = 50)
{
    global $wpdb;
    $table = $wpdb->prefix . 'ms_expenses';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE building_id = %d ORDER BY COALESCE(expense_date, DATE(created_at)) DESC, id DESC LIMIT %d",
        absint($building_id),
        absint($limit)
    ));
}

function msfp_record_wallet_transaction($building_id, $amount, $type, $description, $reference_id = 0)
{
    global $wpdb;
    $building_id = absint($building_id);
    $amount = (float) $amount;
    if (!$building_id || $amount == 0.0) {
        return false;
    }

    $wallet_table = $wpdb->prefix . 'ms_building_wallet';
    $tx_table = $wpdb->prefix . 'ms_building_wallet_transactions';

    $wallet_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wallet_table} WHERE building_id = %d", $building_id));
    if (!$wallet_id) {
        $wpdb->insert($wallet_table, array(
            'building_id' => $building_id,
            'balance' => 0,
            'target_amount' => 0,
            'status' => 'active',
            'created_at' => current_time('mysql'),
        ), array('%d', '%f', '%f', '%s', '%s'));
    }

    $wpdb->query($wpdb->prepare("UPDATE {$wallet_table} SET balance = balance + %f, updated_at = %s WHERE building_id = %d", $amount, current_time('mysql'), $building_id));
    return $wpdb->insert($tx_table, array(
        'building_id' => $building_id,
        'amount' => $amount,
        'type' => sanitize_key($type),
        'description' => sanitize_text_field($description),
        'reference_id' => absint($reference_id),
        'created_at' => current_time('mysql'),
    ), array('%d', '%f', '%s', '%s', '%d', '%s'));
}

function msfp_render_financial_center($building_id = 0)
{
    $building_id = absint($building_id ?: ($_GET['building_id'] ?? 0));
    if (!$building_id) {
        return '<div class="ms-card"><h3>المركز المالي</h3><p>يرجى اختيار مبنى لعرض المركز المالي.</p></div>';
    }
    if (!msfp_current_user_can_manage_building($building_id)) {
        return '<div class="ms-card"><h3>المركز المالي</h3><p>ليس لديك صلاحية لعرض بيانات هذا المبنى.</p></div>';
    }

    $summary = msfp_get_financial_summary($building_id);
    ob_start();
    ?>
    <div class="ms-card msfp-financial-center">
        <h3>Financial Center - المركز المالي</h3>
        <p style="color:#64748b;margin-top:4px;">ملخص مالي شهري للمبنى المختار، يشمل الرصيد الحالي والدخل والمصروفات وصافي الربح.</p>
        <div class="ms-grid" style="margin-top:16px;">
            <div class="ms-card"><h4>Current Balance</h4><div class="ms-number">ج.م <?php echo number_format_i18n($summary['current_balance'], 2); ?></div></div>
            <div class="ms-card"><h4>Monthly Income</h4><div class="ms-number">ج.م <?php echo number_format_i18n($summary['monthly_income'], 2); ?></div></div>
            <div class="ms-card"><h4>Monthly Expenses</h4><div class="ms-number">ج.م <?php echo number_format_i18n($summary['monthly_expenses'], 2); ?></div></div>
            <div class="ms-card"><h4>Net Profit</h4><div class="ms-number">ج.م <?php echo number_format_i18n($summary['net_profit'], 2); ?></div></div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function msfp_render_expenses_center($building_id = 0)
{
    $building_id = absint($building_id ?: ($_GET['building_id'] ?? 0));
    if (!$building_id) {
        return '<div class="ms-card"><h3>المصروفات</h3><p>يرجى اختيار مبنى لإدارة المصروفات.</p></div>';
    }
    if (!msfp_current_user_can_manage_building($building_id)) {
        return '<div class="ms-card"><h3>المصروفات</h3><p>ليس لديك صلاحية لإدارة مصروفات هذا المبنى.</p></div>';
    }

    $types = msfp_expense_type_labels();
    $expenses = msfp_get_building_expenses($building_id, 100);
    ob_start();
    ?>
    <div class="ms-card msfp-expenses-center">
        <h3>Expenses - إدارة المصروفات</h3>
        <form id="msfp-expense-form" enctype="multipart/form-data" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-top:16px;align-items:end;">
            <input type="hidden" name="action" value="msfp_add_expense">
            <input type="hidden" name="building_id" value="<?php echo esc_attr($building_id); ?>">
            <input type="hidden" name="security" value="<?php echo esc_attr(wp_create_nonce('msfp_expense_nonce')); ?>">
            <label>العنوان<input type="text" name="title" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"></label>
            <label>النوع<select name="expense_type" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;">
                <?php foreach ($types as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?>
            </select></label>
            <label>المبلغ<input type="number" name="amount" min="0" step="0.01" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"></label>
            <label>تاريخ المصروف<input type="date" name="expense_date" value="<?php echo esc_attr(current_time('Y-m-d')); ?>" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"></label>
            <label>مرفق PDF/صورة<input type="file" name="attachment" accept="image/*,.pdf" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"></label>
            <label style="grid-column:1/-1;">الوصف<textarea name="description" rows="3" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"></textarea></label>
            <button type="submit" style="padding:11px 18px;background:#2563eb;color:#fff;border:0;border-radius:8px;cursor:pointer;">حفظ المصروف</button>
            <div id="msfp-expense-message" style="display:none;color:#059669;font-weight:600;"></div>
        </form>

        <h4 style="margin-top:24px;">آخر المصروفات</h4>
        <?php if (empty($expenses)) : ?>
            <p>لا توجد مصروفات مسجلة لهذا المبنى بعد.</p>
        <?php else : ?>
            <table style="width:100%;border-collapse:collapse;margin-top:12px;">
                <thead><tr style="background:#f8fafc;text-align:right;"><th style="padding:12px;">التاريخ</th><th style="padding:12px;">العنوان</th><th style="padding:12px;">النوع</th><th style="padding:12px;">المبلغ</th><th style="padding:12px;">الحالة</th></tr></thead>
                <tbody>
                <?php foreach ($expenses as $expense) : ?>
                    <tr style="border-top:1px solid #e5e7eb;">
                        <td style="padding:12px;"><?php echo esc_html($expense->expense_date ?: mysql2date('Y-m-d', $expense->created_at)); ?></td>
                        <td style="padding:12px;"><?php echo esc_html($expense->title); ?></td>
                        <td style="padding:12px;"><?php echo esc_html($types[$expense->expense_type] ?? $expense->expense_type); ?></td>
                        <td style="padding:12px;">ج.م <?php echo number_format_i18n((float) $expense->amount, 2); ?></td>
                        <td style="padding:12px;"><?php echo esc_html($expense->status); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <script>
    (function(){
        var form = document.getElementById('msfp-expense-form');
        if (!form || form.dataset.bound === '1') return;
        form.dataset.bound = '1';
        form.addEventListener('submit', function(e){
            e.preventDefault();
            var msg = document.getElementById('msfp-expense-message');
            var data = new FormData(form);
            fetch('<?php echo esc_js(admin_url('admin-ajax.php')); ?>', { method: 'POST', credentials: 'same-origin', body: data })
                .then(function(r){ return r.json(); })
                .then(function(res){
                    msg.style.display = 'block';
                    msg.style.color = res.success ? '#059669' : '#dc2626';
                    msg.textContent = res.data && res.data.message ? res.data.message : (res.success ? 'تم الحفظ بنجاح.' : 'تعذر حفظ المصروف.');
                    if (res.success) { setTimeout(function(){ window.location.reload(); }, 900); }
                })
                .catch(function(){ msg.style.display = 'block'; msg.style.color = '#dc2626'; msg.textContent = 'حدث خطأ في الاتصال.'; });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

add_shortcode('mostaager_financial_center', function ($atts) {
    $atts = shortcode_atts(array('building_id' => 0), $atts, 'mostaager_financial_center');
    return msfp_render_financial_center(absint($atts['building_id']));
});

add_shortcode('mostaager_expenses_center', function ($atts) {
    $atts = shortcode_atts(array('building_id' => 0), $atts, 'mostaager_expenses_center');
    return msfp_render_expenses_center(absint($atts['building_id']));
});

add_action('wp_ajax_msfp_add_expense', 'msfp_ajax_add_expense');
function msfp_ajax_add_expense()
{
    check_ajax_referer('msfp_expense_nonce', 'security');

    $building_id = absint($_POST['building_id'] ?? 0);
    if (!msfp_current_user_can_manage_building($building_id)) {
        wp_send_json_error(array('message' => 'ليست لديك صلاحية لإضافة مصروف لهذا المبنى.'), 403);
    }

    $title = sanitize_text_field($_POST['title'] ?? '');
    $amount = (float) ($_POST['amount'] ?? 0);
    if ($title === '' || $amount <= 0) {
        wp_send_json_error(array('message' => 'يرجى إدخال عنوان ومبلغ صحيحين.'), 422);
    }

    global $wpdb;
    $expense_table = $wpdb->prefix . 'ms_expenses';
    $inserted = $wpdb->insert($expense_table, array(
        'building_id' => $building_id,
        'created_by' => get_current_user_id(),
        'expense_type' => sanitize_key($_POST['expense_type'] ?? 'other'),
        'title' => $title,
        'description' => sanitize_textarea_field($_POST['description'] ?? ''),
        'amount' => $amount,
        'currency' => 'EGP',
        'expense_date' => sanitize_text_field($_POST['expense_date'] ?? current_time('Y-m-d')),
        'status' => 'approved',
        'payment_status' => 'paid',
        'source' => 'manual',
        'created_at' => current_time('mysql'),
    ), array('%d','%d','%s','%s','%s','%f','%s','%s','%s','%s','%s','%s'));

    if (!$inserted) {
        wp_send_json_error(array('message' => 'تعذر حفظ المصروف في قاعدة البيانات.'), 500);
    }

    $expense_id = (int) $wpdb->insert_id;

    if (!empty($_FILES['attachment']['name'])) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachment_id = media_handle_upload('attachment', 0);
        if (!is_wp_error($attachment_id)) {
            $wpdb->insert($wpdb->prefix . 'ms_expense_attachments', array(
                'expense_id' => $expense_id,
                'file_id' => $attachment_id,
                'file_url' => wp_get_attachment_url($attachment_id),
                'file_type' => get_post_mime_type($attachment_id),
                'uploaded_by' => get_current_user_id(),
                'created_at' => current_time('mysql'),
            ), array('%d','%d','%s','%s','%d','%s'));
        }
    }

    msfp_record_wallet_transaction($building_id, -1 * $amount, 'expense', 'مصروف: ' . $title, $expense_id);
    wp_send_json_success(array('message' => 'تم حفظ المصروف وتحديث محفظة المبنى بنجاح.', 'expense_id' => $expense_id));
}

function msfp_get_building_work_orders($building_id, $limit = 50)
{
    global $wpdb;
    $building_id = absint($building_id);
    $limit = max(1, absint($limit));
    if (!$building_id) {
        return array();
    }

    $building_values = function_exists('ms_get_building_id_values_for_query')
        ? ms_get_building_id_values_for_query($building_id)
        : array($building_id);
    $building_values = array_values(array_unique(array_filter(array_map('absint', $building_values))));
    if (empty($building_values)) {
        $building_values = array($building_id);
    }
    $placeholders = implode(',', array_fill(0, count($building_values), '%d'));

    $work_order_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ms_work_orders WHERE building_id IN ({$placeholders}) ORDER BY id DESC LIMIT %d",
        array_merge($building_values, array($limit))
    ));

    if (!empty($work_order_rows)) {
        return $work_order_rows;
    }

    $legacy_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, building_id, title, description, status, priority, assigned_to AS technician_id, cost AS estimated_cost, cost AS actual_cost, start_date, due_date, created_at, updated_at
         FROM {$wpdb->prefix}ms_maintenance_requests
         WHERE building_id IN ({$placeholders})
         ORDER BY id DESC LIMIT %d",
        array_merge($building_values, array($limit))
    ));

    foreach ((array) $legacy_rows as $legacy_row) {
        $legacy_row->source = 'maintenance_request';
        $work_order_rows[] = $legacy_row;
    }

    usort($work_order_rows, function ($left, $right) {
        return absint($right->id ?? 0) <=> absint($left->id ?? 0);
    });

    return array_slice($work_order_rows, 0, $limit);
}

function msfp_add_work_order_event($work_order_id, $event_type, $title, $description = '', $old_status = '', $new_status = '')
{
    global $wpdb;
    return $wpdb->insert($wpdb->prefix . 'ms_work_order_events', array(
        'work_order_id' => absint($work_order_id),
        'event_type' => sanitize_key($event_type),
        'title' => sanitize_text_field($title),
        'description' => sanitize_textarea_field($description),
        'old_status' => sanitize_key($old_status),
        'new_status' => sanitize_key($new_status),
        'created_by' => get_current_user_id(),
        'created_at' => current_time('mysql'),
    ), array('%d','%s','%s','%s','%s','%s','%d','%s'));
}

function msfp_get_work_order_collection_progress($work_order_id)
{
    global $wpdb;
    $work_order_id = absint($work_order_id);
    if (!$work_order_id) return array('total' => 0, 'paid' => 0, 'percent' => 0, 'total_amount' => 0, 'paid_amount' => 0, 'remaining_amount' => 0);
    $company = ms_get_company_clause('i');
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT COUNT(*) AS total, SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid, SUM(amount) AS total_amount, SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) AS paid_amount FROM {$wpdb->prefix}ms_invoices i WHERE work_order_id = %d AND {$company['clause']}",
        $work_order_id, $company['value']
    ));
    $total = intval($row->total ?? 0); $paid = intval($row->paid ?? 0); $total_amount = round(floatval($row->total_amount ?? 0), 2); $paid_amount = round(floatval($row->paid_amount ?? 0), 2);
    return array('total' => $total, 'paid' => $paid, 'percent' => $total > 0 ? round(($paid / $total) * 100, 2) : 0, 'total_amount' => $total_amount, 'paid_amount' => $paid_amount, 'remaining_amount' => round(max(0, $total_amount - $paid_amount), 2));
}

function msfp_render_maintenance_pro($building_id = 0)
{
    $building_id = absint($building_id ?: ($_GET['building_id'] ?? 0));
    if (!$building_id) {
        return '<div class="ms-card"><h3>Maintenance Center PRO</h3><p>يرجى اختيار مبنى لعرض مركز الصيانة.</p></div>';
    }
    if (!msfp_current_user_can_manage_building($building_id)) {
        return '<div class="ms-card"><h3>Maintenance Center PRO</h3><p>ليس لديك صلاحية لإدارة صيانة هذا المبنى.</p></div>';
    }

    $orders = msfp_get_building_work_orders($building_id, 100);
    $statuses = msfp_work_order_status_labels();
    $building_wallet = function_exists('ms_get_building_wallet') ? ms_get_building_wallet($building_id) : null;
    $building_wallet_balance = $building_wallet ? round((float) ($building_wallet->balance ?? 0), 2) : 0;
    ob_start();
    ?>
    <div class="ms-card msfp-maintenance-pro">
        <h3>Maintenance Center PRO - مركز الصيانة المتقدم</h3>
        <form id="msfp-work-order-form" enctype="multipart/form-data" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-top:16px;align-items:end;">
            <input type="hidden" name="action" value="msfp_add_work_order">
            <input type="hidden" name="building_id" value="<?php echo esc_attr($building_id); ?>">
            <input type="hidden" name="security" value="<?php echo esc_attr(wp_create_nonce('msfp_work_order_nonce')); ?>">
            <label>العنوان<input type="text" name="title" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"></label>
            <label>الأولوية<select name="priority" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"><option value="low">منخفضة</option><option value="medium" selected>متوسطة</option><option value="high">عالية</option><option value="emergency">طارئة</option></select></label>
            <label>الفني / المستخدم<input type="number" name="technician_id" min="0" placeholder="User ID" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"></label>
            <label>التكلفة المتوقعة<input type="number" name="estimated_cost" min="0.01" max="<?php echo esc_attr(get_option('ms_max_maintenance_cost', 10000000)); ?>" step="0.01" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"></label>
            <label>تاريخ بدء الصيانة<input type="date" name="start_date" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"></label>
            <label>تاريخ انتهاء الصيانة<input type="date" name="end_date" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"></label>
            <label>آخر موعد للتحصيل<input type="date" name="collection_deadline" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"></label>
            <label>تاريخ الإنجاز المتوقع<input type="date" name="due_date" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"></label>
            <label style="grid-column:1/-1;">الوصف<textarea name="description" rows="3" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"></textarea></label>
            <label style="grid-column:1/-1;">
                <input type="checkbox" name="distribute_invoices" id="distribute_invoices" value="1" style="margin-left:8px;">
                <label for="distribute_invoices" style="display:inline;">توزيع الفواتير بالتساوي على الملاك والمستأجرين بعد إنشاء الطلب</label>
            </label>
            <button type="submit" style="padding:11px 18px;background:#2563eb;color:#fff;border:0;border-radius:8px;cursor:pointer;">إنشاء أمر عمل</button>
            <div id="msfp-work-order-message" style="display:none;font-weight:600;"></div>
        </form>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:24px;"><h4 style="margin:0;">الصيانات</h4><div class="ms-maintenance-filters" style="display:flex;gap:6px;flex-wrap:wrap;"><button type="button" class="ms-maintenance-filter is-active" data-maintenance-filter="active" style="padding:7px 12px;border:1px solid #2563eb;background:#2563eb;color:#fff;border-radius:6px;cursor:pointer;">النشطة</button><button type="button" class="ms-maintenance-filter" data-maintenance-filter="completed" style="padding:7px 12px;border:1px solid #cbd5e1;background:#fff;color:#334155;border-radius:6px;cursor:pointer;">المنتهية</button><button type="button" class="ms-maintenance-filter" data-maintenance-filter="cancelled" style="padding:7px 12px;border:1px solid #cbd5e1;background:#fff;color:#334155;border-radius:6px;cursor:pointer;">الملغاة</button><button type="button" class="ms-maintenance-filter" data-maintenance-filter="all" style="padding:7px 12px;border:1px solid #cbd5e1;background:#fff;color:#334155;border-radius:6px;cursor:pointer;">الكل</button></div></div>
        <?php if (empty($orders)) : ?>
            <p>لا توجد أوامر صيانة متقدمة بعد.</p>
        <?php else : ?>
            <table style="width:100%;border-collapse:collapse;margin-top:12px;">
                <thead><tr style="background:#f8fafc;text-align:right;"><th style="padding:12px;">#</th><th style="padding:12px;">العنوان</th><th style="padding:12px;">الحالة</th><th style="padding:12px;">الفني</th><th style="padding:12px;">التكلفة</th><th style="padding:12px;">إجراء</th><th style="padding:12px;">التحصيل</th></tr></thead>
                <tbody>
                <?php foreach ($orders as $order) : ?>
                    <?php
                    $order_status = sanitize_key($order->status ?? 'request');
                    $row_filter = in_array($order_status, array('cancelled', 'canceled', 'rejected', 'deleted'), true)
                        ? 'cancelled'
                        : (in_array($order_status, array('completed', 'closed', 'done'), true) ? 'completed' : 'active');
                    ?>
                    <tr data-maintenance-status="<?php echo esc_attr($row_filter); ?>" style="border-top:1px solid #e5e7eb;">
                        <td style="padding:12px;">#<?php echo esc_html($order->id); ?></td>
                        <td style="padding:12px;"><?php echo esc_html($order->title); ?></td>
                        <td style="padding:12px;"><?php echo esc_html($statuses[$order->status] ?? $order->status); ?></td>
                        <td style="padding:12px;"><?php echo esc_html($order->technician_id ? (get_userdata($order->technician_id)->display_name ?? '#' . $order->technician_id) : 'غير مسند'); ?></td>
                        <td style="padding:12px;">ج.م <?php echo number_format_i18n((float) $order->actual_cost ?: (float) $order->estimated_cost, 2); ?></td>
                        <td style="padding:12px;">
                            <select class="msfp-wo-status" data-id="<?php echo esc_attr($order->id); ?>" data-security="<?php echo esc_attr(wp_create_nonce('msfp_work_order_nonce')); ?>">
                                <?php foreach ($statuses as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($order->status, $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
                            </select>
                        </td>
                        <?php $collection = msfp_get_work_order_collection_progress($order->id); global $wpdb; $existing_transfer_status = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}ms_transfer_requests WHERE work_order_id = %d AND funding_source = 'collection' ORDER BY id DESC LIMIT 1", absint($order->id))); $wallet_transfer_status = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}ms_transfer_requests WHERE work_order_id = %d AND funding_source = 'building_wallet' ORDER BY id DESC LIMIT 1", absint($order->id))); ?>
                        <td style="padding:12px;min-width:240px;">
                            <div style="font-size:12px;color:#475569;margin-bottom:6px;"><?php echo intval($collection['paid']); ?> / <?php echo intval($collection['total']); ?> فواتير — <?php echo esc_html($collection['percent']); ?>%</div>
                            <div style="height:7px;background:#e2e8f0;border-radius:99px;overflow:hidden;"><span style="display:block;height:100%;width:<?php echo esc_attr(min(100, (float) $collection['percent'])); ?>%;background:<?php echo $collection['percent'] >= 100 ? '#059669' : '#f59e0b'; ?>;"></span></div>
                            <div style="font-size:12px;color:#64748b;margin-top:5px;">مدفوع: ج.م <?php echo number_format_i18n((float) $collection['paid_amount'], 2); ?> — متبقٍ: ج.م <?php echo number_format_i18n((float) $collection['remaining_amount'], 2); ?></div>
                            <button type="button" class="msfp-check-collection" data-work-order-id="<?php echo esc_attr($order->id); ?>" data-security="<?php echo esc_attr(wp_create_nonce('msfp_work_order_nonce')); ?>" style="padding:6px 12px;background:#059669;color:#fff;border:0;border-radius:6px;cursor:pointer;font-size:12px;margin-top:7px;">تحديث التحصيل</button>
                            <?php if ($collection['total'] > 0 && $collection['paid'] >= $collection['total'] && $collection['paid_amount'] > 0 && !in_array($existing_transfer_status, array('pending', 'approved'), true)) : ?><button type="button" class="msfp-request-transfer" data-work-order-id="<?php echo esc_attr($order->id); ?>" data-security="<?php echo esc_attr(wp_create_nonce('msfp_work_order_nonce')); ?>" style="padding:6px 12px;background:#2563eb;color:#fff;border:0;border-radius:6px;cursor:pointer;font-size:12px;margin-right:4px;margin-top:7px;">طلب سحب المبلغ</button><?php elseif (in_array($existing_transfer_status, array('pending', 'approved'), true)) : ?><span style="display:inline-block;margin-top:7px;padding:6px 9px;background:#ecfdf5;color:#047857;border-radius:6px;font-size:12px;">طلب السحب: <?php echo $existing_transfer_status === 'approved' ? 'تمت الموافقة' : 'قيد المراجعة'; ?></span><?php else : ?><span style="display:inline-block;margin-top:7px;color:#94a3b8;font-size:12px;">يظهر زر السحب بعد اكتمال التحصيل</span><?php endif; ?>
                            <?php if ($building_wallet_balance > 0 && !in_array($wallet_transfer_status, array('pending', 'approved'), true)) : ?><div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-top:8px;"><input type="number" class="msfp-wallet-transfer-amount" min="0.01" max="<?php echo esc_attr($building_wallet_balance); ?>" step="0.01" value="<?php echo esc_attr(min((float) ($order->estimated_cost ?? 0), $building_wallet_balance)); ?>" data-work-order-id="<?php echo esc_attr($order->id); ?>" style="width:120px;padding:6px;border:1px solid #cbd5e1;border-radius:6px;"><button type="button" class="msfp-request-wallet-transfer" data-work-order-id="<?php echo esc_attr($order->id); ?>" data-security="<?php echo esc_attr(wp_create_nonce('msfp_work_order_nonce')); ?>" style="padding:6px 10px;background:#0f766e;color:#fff;border:0;border-radius:6px;cursor:pointer;font-size:12px;">تحويل من المحفظة</button></div><small style="display:block;margin-top:4px;color:#64748b;">رصيد المحفظة المتاح: ج.م <?php echo number_format_i18n($building_wallet_balance, 2); ?></small><?php elseif (in_array($wallet_transfer_status, array('pending', 'approved'), true)) : ?><span style="display:block;margin-top:8px;padding:6px 9px;background:#ecfdf5;color:#047857;border-radius:6px;font-size:12px;">طلب المحفظة: <?php echo $wallet_transfer_status === 'approved' ? 'تمت الموافقة والخصم' : 'قيد المراجعة'; ?></span><?php endif; ?>
                            <span class="msfp-collection-status" data-work-order-id="<?php echo esc_attr($order->id); ?>" style="display:none;margin-right:8px;font-size:12px;"></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <script>
    (function(){
        var form = document.getElementById('msfp-work-order-form');
        var ajax = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
        if (form && form.dataset.bound !== '1') {
            form.dataset.bound = '1';
            form.addEventListener('submit', function(e){
                e.preventDefault();
                var msg = document.getElementById('msfp-work-order-message');
                fetch(ajax, {method:'POST', credentials:'same-origin', body:new FormData(form)}).then(function(r){return r.json();}).then(function(res){
                    msg.style.display='block'; msg.style.color=res.success?'#059669':'#dc2626'; msg.textContent=(res.data&&res.data.message)?res.data.message:'تمت العملية.'; if(res.success){setTimeout(function(){location.reload();},900);}
                }).catch(function(){msg.style.display='block';msg.style.color='#dc2626';msg.textContent='حدث خطأ في الاتصال.';});
            });
        }
        document.querySelectorAll('.ms-maintenance-filter').forEach(function(filterBtn){
            if (filterBtn.dataset.bound === '1') return; filterBtn.dataset.bound = '1';
            filterBtn.addEventListener('click', function(){
                var filter = this.getAttribute('data-maintenance-filter') || 'active';
                document.querySelectorAll('.ms-maintenance-filter').forEach(function(btn){ btn.style.background = '#fff'; btn.style.color = '#334155'; btn.style.borderColor = '#cbd5e1'; });
                this.style.background = '#2563eb'; this.style.color = '#fff'; this.style.borderColor = '#2563eb';
                document.querySelectorAll('.msfp-maintenance-pro tbody tr[data-maintenance-status]').forEach(function(row){ row.style.display = (filter === 'all' || row.getAttribute('data-maintenance-status') === filter) ? '' : 'none'; });
            });
        });
        document.querySelectorAll('.msfp-wo-status').forEach(function(sel){
            if (sel.dataset.bound === '1') return; sel.dataset.bound='1';
            sel.addEventListener('change', function(){
                var fd = new FormData(); fd.append('action','msfp_update_work_order_status'); fd.append('work_order_id', sel.dataset.id); fd.append('status', sel.value); fd.append('security', sel.dataset.security);
                fetch(ajax, {method:'POST', credentials:'same-origin', body:fd}).then(function(){ setTimeout(function(){location.reload();},400); });
            });
        });
        document.querySelectorAll('.msfp-check-collection').forEach(function(btn){
            if (btn.dataset.bound === '1') return; btn.dataset.bound='1';
            btn.addEventListener('click', function(){
                var workOrderId = this.getAttribute('data-work-order-id');
                var security = this.getAttribute('data-security');
                var statusSpan = document.querySelector('.msfp-collection-status[data-work-order-id="' + workOrderId + '"]');
                if (statusSpan) {
                    statusSpan.style.display = 'inline';
                    statusSpan.textContent = 'جاري الفحص...';
                    statusSpan.style.color = '#64748b';
                }
                var fd = new FormData();
                fd.append('action', 'msfp_check_maintenance_collection');
                fd.append('work_order_id', workOrderId);
                fd.append('security', security);
                fetch(ajax, {method:'POST', credentials:'same-origin', body:fd}).then(function(r){return r.json();}).then(function(res){
                    if (statusSpan) {
                        if (res.success) {
                            if (res.data.collection_complete) {
                                statusSpan.textContent = 'مكتمل - ' + res.data.total_collected + ' ج.م';
                                statusSpan.style.color = '#059669';
                                statusSpan.style.fontWeight = '600';
                            } else {
                                statusSpan.textContent = 'غير مكتمل (' + res.data.paid + '/' + res.data.total + ')';
                                statusSpan.style.color = '#f59e0b';
                            }
                        } else {
                            statusSpan.textContent = 'خطأ';
                            statusSpan.style.color = '#dc2626';
                        }
                    }
                }).catch(function(){
                    if (statusSpan) {
                        statusSpan.textContent = 'خطأ في الاتصال';
                        statusSpan.style.color = '#dc2626';
                    }
                });
            });
        });
        document.querySelectorAll('.msfp-request-transfer').forEach(function(btn){
            if (btn.dataset.bound === '1') return; btn.dataset.bound='1';
            btn.addEventListener('click', function(){
                var workOrderId = this.getAttribute('data-work-order-id');
                var security = this.getAttribute('data-security');
                if (!confirm('هل أنت متأكد من طلب تحويل مبلغ الصيانة إلى مدير الموقع؟')) return;
                
                var fd = new FormData();
                fd.append('action', 'msfp_request_transfer');
                fd.append('work_order_id', workOrderId);
                fd.append('security', security);
                fetch(ajax, {method:'POST', credentials:'same-origin', body:fd}).then(function(r){return r.json();}).then(function(res){
                    if (res.success) {
                        alert(res.data.message || 'تم إرسال طلب التحويل بنجاح');
                        setTimeout(function(){ location.reload(); }, 1000);
                    } else {
                        alert(res.data.message || 'حدث خطأ');
                    }
                }).catch(function(){
                    alert('حدث خطأ في الاتصال');
                });
            });
        });
        document.querySelectorAll('.msfp-request-wallet-transfer').forEach(function(btn){
            if (btn.dataset.bound === '1') return; btn.dataset.bound='1';
            btn.addEventListener('click', function(){
                var workOrderId = this.getAttribute('data-work-order-id');
                var security = this.getAttribute('data-security');
                var amountInput = document.querySelector('.msfp-wallet-transfer-amount[data-work-order-id="' + workOrderId + '"]');
                var amount = amountInput ? parseFloat(amountInput.value || '0') : 0;
                if (!(amount > 0)) { alert('يرجى إدخال مبلغ تحويل صحيح.'); return; }
                if (!confirm('هل تريد طلب تحويل ' + amount.toFixed(2) + ' ج.م من محفظة البناء لتنفيذ الصيانة؟')) return;
                var fd = new FormData();
                fd.append('action', 'msfp_request_wallet_transfer');
                fd.append('work_order_id', workOrderId);
                fd.append('amount', amount.toFixed(2));
                fd.append('security', security);
                this.disabled = true;
                fetch(ajax, {method:'POST', credentials:'same-origin', body:fd}).then(function(r){return r.json();}).then(function(res){
                    if (res.success) { alert(res.data.message || 'تم إرسال طلب التحويل من المحفظة.'); setTimeout(function(){ location.reload(); }, 700); }
                    else { alert(res.data.message || 'حدث خطأ'); }
                }).catch(function(){ alert('حدث خطأ في الاتصال'); }).finally(function(){ btn.disabled = false; });
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

add_shortcode('mostaager_maintenance_pro', function ($atts) {
    $atts = shortcode_atts(array('building_id' => 0), $atts, 'mostaager_maintenance_pro');
    return msfp_render_maintenance_pro(absint($atts['building_id']));
});

add_action('wp_ajax_msfp_add_work_order', 'msfp_ajax_add_work_order');
function msfp_ajax_add_work_order()
{
    check_ajax_referer('msfp_work_order_nonce', 'security');
    $building_id = absint($_POST['building_id'] ?? 0);
    if (!msfp_current_user_can_manage_building($building_id)) {
        wp_send_json_error(array('message' => 'ليست لديك صلاحية لإدارة هذا المبنى.'), 403);
    }
    $title = sanitize_text_field($_POST['title'] ?? '');
    if ($title === '') {
        wp_send_json_error(array('message' => 'يرجى إدخال عنوان أمر العمل.'), 422);
    }
    $estimated_cost = round((float) ($_POST['estimated_cost'] ?? 0), 2);
    if ($estimated_cost <= 0) {
        wp_send_json_error(array('message' => 'يرجى إدخال التكلفة المتوقعة.'), 422);
    }
    $max_cost = (float) get_option('ms_max_maintenance_cost', 10000000);
    if ($max_cost > 0 && $estimated_cost > $max_cost) {
        wp_send_json_error(array('message' => 'قيمة الصيانة تتجاوز الحد المسموح (' . number_format_i18n($max_cost, 2) . ' ج.م).'), 422);
    }
    
    global $wpdb;
    $wpdb->insert($wpdb->prefix . 'ms_work_orders', array(
        'building_id' => $building_id,
        'title' => $title,
        'description' => sanitize_textarea_field($_POST['description'] ?? ''),
        'status' => !empty($_POST['technician_id']) ? 'assigned' : 'request',
        'priority' => sanitize_key($_POST['priority'] ?? 'medium'),
        'technician_id' => absint($_POST['technician_id'] ?? 0),
        'requested_by' => get_current_user_id(),
        'assigned_by' => !empty($_POST['technician_id']) ? get_current_user_id() : 0,
        'estimated_cost' => $estimated_cost,
        'start_date' => sanitize_text_field($_POST['start_date'] ?? ''),
        'end_date' => sanitize_text_field($_POST['end_date'] ?? ''),
        'collection_deadline' => sanitize_text_field($_POST['collection_deadline'] ?? ''),
        'due_date' => sanitize_text_field($_POST['due_date'] ?? ''),
        'created_at' => current_time('mysql'),
    ), array('%d','%s','%s','%s','%s','%d','%d','%d','%f','%s','%s','%s','%s','%s'));
    $id = (int) $wpdb->insert_id;
    if (!$id) {
        wp_send_json_error(array('message' => 'تعذر حفظ أمر العمل.'), 500);
    }
    msfp_add_work_order_event($id, 'created', 'Request Created', 'تم إنشاء أمر العمل.', '', !empty($_POST['technician_id']) ? 'assigned' : 'request');
    
    // Also create entry in ms_maintenance_requests for visibility in owner/tenant dashboards
    $wpdb->insert($wpdb->prefix . 'ms_maintenance_requests', array(
        'building_id' => $building_id,
        'title' => $title,
        'description' => sanitize_textarea_field($_POST['description'] ?? ''),
        'status' => !empty($_POST['technician_id']) ? 'in_progress' : 'pending',
        'priority' => sanitize_key($_POST['priority'] ?? 'medium'),
        'maintenance_type' => 'building',
        'cost' => $estimated_cost,
        'assigned_to' => absint($_POST['technician_id'] ?? 0),
        'payer_type' => 'owner',
        'start_date' => sanitize_text_field($_POST['start_date'] ?? ''),
        'due_date' => sanitize_text_field($_POST['due_date'] ?? ''),
        'created_at' => current_time('mysql'),
    ), array('%d','%s','%s','%s','%s','%s','%f','%d','%s','%s','%s','%s'));
    
    // Distribute invoices if checkbox is checked
    $distribute_invoices = isset($_POST['distribute_invoices']) && intval($_POST['distribute_invoices']) === 1;
    if ($distribute_invoices && $estimated_cost > 0) {
        msfp_distribute_maintenance_invoices($building_id, $id, $estimated_cost, sanitize_text_field($_POST['collection_deadline'] ?? ''));
    }
    
    wp_send_json_success(array('message' => 'تم إنشاء أمر العمل بنجاح.', 'work_order_id' => $id));
}

function msfp_distribute_maintenance_invoices($building_id, $work_order_id, $total_cost, $deadline)
{
    global $wpdb;
    $company = ms_get_company_clause('u');
    
    // Get all units in the building
    $units = $wpdb->get_results($wpdb->prepare(
        "SELECT u.id, u.owner_id, COALESCE(NULLIF(u.tenant_id, 0), (
            SELECT ut.tenant_id FROM {$wpdb->prefix}ms_unit_tenants ut
            WHERE ut.unit_id = u.id AND ut.status = 'active'
                AND (ut.end_date IS NULL OR ut.end_date = '' OR ut.end_date >= CURDATE())
            ORDER BY ut.start_date DESC, ut.id DESC LIMIT 1
        ), 0) AS tenant_id
        FROM {$wpdb->prefix}ms_units u
        WHERE u.building_id = %d AND {$company['clause']}",
        $building_id, $company['value']
    ));
    
    if (empty($units)) {
        return false;
    }
    
    $unit_count = count($units);
    $cost_per_unit = $total_cost / $unit_count;
    
    foreach ($units as $unit) {
        // Occupied units are billed to the tenant; vacant units are billed to the owner.
        $payer_id = !empty($unit->tenant_id) ? absint($unit->tenant_id) : absint($unit->owner_id);
        $payer_type = !empty($unit->tenant_id) ? 'tenant' : 'owner';
        if (!$payer_id) {
            continue;
        }

        $wpdb->insert($wpdb->prefix . 'ms_invoices', array(
            'user_id' => $payer_id,
            'building_id' => $building_id,
            'unit_id' => $unit->id,
            'work_order_id' => $work_order_id,
            'payer_type' => $payer_type,
            'amount' => $cost_per_unit,
            'invoice_type' => 'maintenance',
            'invoice_category' => 'building-maintenance',
            'description' => 'حصة صيانة المبنى - أمر عمل #' . $work_order_id,
            'due_date' => $deadline,
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            $company['column'] => $company['value'],
        ), array('%d','%d','%d','%d','%s','%f','%s','%s','%s','%s','%s','%s','%s'));
    }
    
    return true;
}

// Check maintenance collection completion and send notification
add_action('wp_ajax_msfp_check_maintenance_collection', 'msfp_check_maintenance_collection');
function msfp_check_maintenance_collection()
{
    check_ajax_referer('msfp_work_order_nonce', 'security');
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'يرجى تسجيل الدخول.'), 403);
    }
    
    $work_order_id = isset($_POST['work_order_id']) ? absint($_POST['work_order_id']) : 0;
    if (!$work_order_id) {
        wp_send_json_error(array('message' => 'معرف أمر العمل غير صالح'), 400);
    }
    
    global $wpdb;
    $company = ms_get_company_clause('i');
    
    // Get work order details
    $work_order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ms_work_orders WHERE id = %d",
        $work_order_id
    ));
    
    if (!$work_order) {
        wp_send_json_error(array('message' => 'أمر العمل غير موجود'), 404);
    }
    
    // Check if user can manage this building
    if (!msfp_current_user_can_manage_building($work_order->building_id)) {
        wp_send_json_error(array('message' => 'غير مصرح'), 403);
    }
    
    // Get all invoices for this work order
    $invoices = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ms_invoices WHERE work_order_id = %d AND {$company['clause']}",
        $work_order_id, $company['value']
    ));
    
    if (empty($invoices)) {
        wp_send_json_error(array('message' => 'لا توجد فواتير لهذا أمر العمل'), 404);
    }
    
    // Check if all invoices are paid
    $total_invoices = count($invoices);
    $paid_invoices = count(array_filter($invoices, function($inv) {
        return strtolower(trim($inv->status ?? '')) === 'paid';
    }));
    $paid_amount = 0.0;
    foreach ($invoices as $invoice) { if (strtolower(trim($invoice->status ?? '')) === 'paid') $paid_amount += (float) ($invoice->amount ?? 0); }
    
    if ($paid_invoices === $total_invoices && $total_invoices > 0) {
        // Send notification to building manager
        $building_manager_id = get_current_user_id();
        $message = 'تم اكتمال تحصيل مبلغ صيانة أمر العمل #' . $work_order_id . ' بنجاح. يمكنك الآن طلب تحويل المبلغ إلى مدير الموقع.';
        
        if (function_exists('ms_add_notification')) {
            ms_add_notification($building_manager_id, 'maintenance_collection_complete', $message, absint($work_order->building_id ?? 0), $work_order_id);
        }
        
        wp_send_json_success(array(
            'message' => 'تم إرسال الإشعار',
            'collection_complete' => true,
            'total_collected' => round($paid_amount, 2),
            'paid_amount' => round($paid_amount, 2)
        ));
    } else {
        wp_send_json_success(array(
            'message' => 'لم يكتمل التحصيل بعد',
            'collection_complete' => false,
            'paid' => $paid_invoices,
            'total' => $total_invoices
        ));
    }
}

add_action('wp_ajax_msfp_request_wallet_transfer', 'msfp_request_wallet_transfer');
function msfp_request_wallet_transfer()
{
    check_ajax_referer('msfp_work_order_nonce', 'security');
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'يرجى تسجيل الدخول.'), 403);
    }

    $work_order_id = absint($_POST['work_order_id'] ?? 0);
    $amount = round((float) ($_POST['amount'] ?? 0), 2);
    if (!$work_order_id || $amount <= 0) {
        wp_send_json_error(array('message' => 'بيانات طلب التحويل غير صالحة.'), 422);
    }

    global $wpdb;
    $work_order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ms_work_orders WHERE id = %d", $work_order_id));
    if (!$work_order || !msfp_current_user_can_manage_building(absint($work_order->building_id))) {
        wp_send_json_error(array('message' => 'غير مصرح بهذا الطلب.'), 403);
    }

    $wallet = function_exists('ms_get_building_wallet') ? ms_get_building_wallet(absint($work_order->building_id)) : null;
    $balance = $wallet ? round((float) ($wallet->balance ?? 0), 2) : 0;
    if ($amount > $balance) {
        wp_send_json_error(array('message' => 'المبلغ المطلوب أكبر من رصيد محفظة البناء.'), 422);
    }

    $transfer_table = $wpdb->prefix . 'ms_transfer_requests';
    $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$transfer_table} WHERE work_order_id = %d AND funding_source = 'building_wallet' AND status IN ('pending','approved') LIMIT 1", $work_order_id));
    if ($existing) {
        wp_send_json_error(array('message' => 'يوجد طلب تحويل من المحفظة لهذا الأمر قيد المراجعة أو معتمد.'), 409);
    }

    $company = ms_get_company_clause('t');
    $inserted = $wpdb->insert($transfer_table, array(
        'work_order_id' => $work_order_id,
        'building_id' => absint($work_order->building_id),
        'requested_by' => get_current_user_id(),
        'funding_source' => 'building_wallet',
        'amount' => $amount,
        'status' => 'pending',
        'notes' => 'طلب تمويل تنفيذ الصيانة من محفظة البناء.',
        'created_at' => current_time('mysql'),
        $company['column'] => $company['value'],
    ));
    if ($inserted === false) {
        wp_send_json_error(array('message' => 'تعذر حفظ طلب التحويل.'), 500);
    }

    $message = 'طلب تحويل من محفظة البناء بمبلغ ' . number_format_i18n($amount, 2) . ' ج.م لأمر العمل #' . $work_order_id;
    foreach (get_users(array('capability' => 'manage_options')) as $site_manager) {
        if (function_exists('ms_add_notification')) {
            ms_add_notification($site_manager->ID, 'wallet_transfer_request', $message, absint($work_order->building_id), $wpdb->insert_id);
        }
    }

    wp_send_json_success(array('message' => 'تم إرسال طلب التحويل من محفظة البناء إلى الإدارة للمراجعة.'));
}

// Request transfer to site manager
add_action('wp_ajax_msfp_request_transfer', 'msfp_request_transfer');
function msfp_request_transfer()
{
    check_ajax_referer('msfp_work_order_nonce', 'security');
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'يرجى تسجيل الدخول.'), 403);
    }
    
    $work_order_id = isset($_POST['work_order_id']) ? absint($_POST['work_order_id']) : 0;
    if (!$work_order_id) {
        wp_send_json_error(array('message' => 'معرف أمر العمل غير صالح'), 400);
    }
    
    global $wpdb;
    $company = ms_get_company_clause('w');
    
    // Get work order details
    $work_order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ms_work_orders WHERE id = %d",
        $work_order_id
    ));
    
    if (!$work_order) {
        wp_send_json_error(array('message' => 'أمر العمل غير موجود'), 404);
    }
    
    // Check if user can manage this building
    if (!msfp_current_user_can_manage_building($work_order->building_id)) {
        wp_send_json_error(array('message' => 'غير مصرح'), 403);
    }
    
    // Check if collection is complete
    $company_i = ms_get_company_clause('i');
    $invoices = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ms_invoices WHERE work_order_id = %d AND {$company_i['clause']}",
        $work_order_id, $company_i['value']
    ));
    
    if (empty($invoices)) {
        wp_send_json_error(array('message' => 'لا توجد فواتير لهذا أمر العمل'), 400);
    }
    
    $total_invoices = count($invoices);
    $paid_invoices = count(array_filter($invoices, function($inv) {
        return strtolower(trim($inv->status ?? '')) === 'paid';
    }));
    $paid_amount = 0.0;
    foreach ($invoices as $invoice) { if (strtolower(trim($invoice->status ?? '')) === 'paid') $paid_amount += (float) ($invoice->amount ?? 0); }
    
    if ($paid_invoices !== $total_invoices || $total_invoices < 1 || $paid_amount <= 0) {
        wp_send_json_error(array('message' => 'لم يكتمل تحصيل جميع الفواتير بعد'), 400);
    }
    
    // Get site managers (users with manage_options capability)
    $site_managers = get_users(array('capability' => 'manage_options'));
    
    if (empty($site_managers)) {
        wp_send_json_error(array('message' => 'لا يوجد مدير موقع لإرسال الطلب إليه'), 404);
    }
    
    $building_manager_id = get_current_user_id();
    $building_manager = get_userdata($building_manager_id);
    $owner_id = absint($wpdb->get_var($wpdb->prepare("SELECT owner_id FROM {$wpdb->prefix}ms_units WHERE building_id = %d AND owner_id > 0 ORDER BY id ASC LIMIT 1", absint($work_order->building_id))));
    if (!$owner_id) {
        wp_send_json_error(array('message' => 'لا يمكن إرسال طلب التحويل قبل ربط مالك بالوحدة أو المبنى.'), 422);
    }
    $bank_name = sanitize_text_field(get_user_meta($owner_id, 'ms_bank_name', true) ?: get_user_meta($owner_id, 'bank_name', true));
    $bank_account_name = sanitize_text_field(get_user_meta($owner_id, 'ms_bank_account_name', true) ?: get_user_meta($owner_id, 'bank_account_name', true));
    $bank_account_number = sanitize_text_field(get_user_meta($owner_id, 'ms_bank_account_number', true) ?: get_user_meta($owner_id, 'bank_account_number', true));
    $bank_iban = sanitize_text_field(get_user_meta($owner_id, 'ms_bank_iban', true) ?: get_user_meta($owner_id, 'bank_iban', true));
    if (!$bank_name || (!$bank_account_number && !$bank_iban)) {
        wp_send_json_error(array('message' => 'لا يمكن إرسال طلب التحويل قبل إكمال بيانات الحساب البنكي للمالك.'), 422);
    }
    $amount = round($paid_amount, 2);
    $existing_request = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}ms_transfer_requests WHERE work_order_id = %d AND status IN ('pending','approved') LIMIT 1", $work_order_id));
    if ($existing_request) {
        wp_send_json_error(array('message' => 'تم تقديم طلب سحب لهذا الأمر مسبقًا وهو قيد المراجعة أو معتمد.'), 409);
    }
    
    // Create transfer request record
    $wpdb->insert($wpdb->prefix . 'ms_transfer_requests', array(
        'work_order_id' => $work_order_id,
        'building_id' => $work_order->building_id,
        'requested_by' => $building_manager_id,
        'owner_id' => $owner_id,
        'bank_name' => $bank_name,
        'bank_account_name' => $bank_account_name,
        'bank_account_number' => $bank_account_number,
        'bank_iban' => $bank_iban,
        'amount' => $amount,
        'status' => 'pending',
        'created_at' => current_time('mysql'),
        $company['column'] => $company['value'],
    ));
    
    $transfer_request_id = $wpdb->insert_id;
    
    // Send notification to all site managers
    $message = 'طلب تحويل صيانة جديد من ' . ($building_manager ? $building_manager->display_name : 'مدير المبنى') . ' لمبلغ ' . number_format_i18n($amount, 2) . ' ج.م لأمر العمل #' . $work_order_id;
    
    foreach ($site_managers as $site_manager) {
        if (function_exists('ms_add_notification')) {
            ms_add_notification($site_manager->ID, 'transfer_request', $message, absint($work_order->building_id), $transfer_request_id);
        }
    }
    
    wp_send_json_success(array(
        'message' => 'تم إرسال طلب السحب إلى الإدارة للمراجعة بنجاح',
        'transfer_request_id' => $transfer_request_id,
        'amount' => $amount
    ));
}

add_action('wp_ajax_msfp_update_work_order_status', 'msfp_ajax_update_work_order_status');
function msfp_ajax_update_work_order_status()
{
    check_ajax_referer('msfp_work_order_nonce', 'security');
    global $wpdb;
    $id = absint($_POST['work_order_id'] ?? 0);
    $status = sanitize_key($_POST['status'] ?? 'request');
    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ms_work_orders WHERE id = %d", $id));
    if (!$order || !msfp_current_user_can_manage_building($order->building_id)) {
        wp_send_json_error(array('message' => 'غير مسموح.'), 403);
    }
    $extra = array('status' => $status, 'updated_at' => current_time('mysql'));
    if ($status === 'in_progress') { $extra['started_at'] = current_time('mysql'); }
    if ($status === 'completed') { $extra['completed_at'] = current_time('mysql'); }
    if ($status === 'closed') { $extra['closed_at'] = current_time('mysql'); }
    $wpdb->update($wpdb->prefix . 'ms_work_orders', $extra, array('id' => $id));
    msfp_add_work_order_event($id, 'status_change', 'Status Updated', 'تم تحديث حالة أمر العمل.', $order->status, $status);
    wp_send_json_success(array('message' => 'تم تحديث الحالة.'));
}

function msfp_render_owner_reports($owner_id = 0)
{
    $owner_id = absint($owner_id ?: get_current_user_id());
    if (!$owner_id || !is_user_logged_in()) {
        return '<div class="ms-card"><p>يرجى تسجيل الدخول لعرض تقارير المالك.</p></div>';
    }
    global $wpdb;
    $report_rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ms_owner_reports WHERE owner_id = %d ORDER BY report_month DESC, id DESC LIMIT 100", $owner_id));
    $reports = array();
    foreach ((array) $report_rows as $report_row) {
        if (!isset($reports[$report_row->report_month])) {
            $reports[$report_row->report_month] = $report_row;
        }
    }
    $reports = array_slice(array_values($reports), 0, 24);
    ob_start();
    ?>
    <div class="ms-card msfp-owner-reports"><h3>Owner Reports PDF - تقارير المالك</h3>
        <form method="post" style="margin:12px 0;display:flex;gap:10px;flex-wrap:wrap;align-items:end;">
            <?php wp_nonce_field('msfp_generate_owner_report', 'msfp_owner_report_nonce'); ?>
            <input type="hidden" name="msfp_owner_report_action" value="generate">
            <label>الشهر<input type="month" name="report_month" value="<?php echo esc_attr(current_time('Y-m')); ?>" style="padding:10px;border:1px solid #ddd;border-radius:8px;"></label>
            <button type="submit" style="padding:10px 16px;background:#2563eb;color:#fff;border:0;border-radius:8px;">إنشاء تقرير شهري</button>
        </form>
        <?php if (empty($reports)) : ?><p>لا توجد تقارير محفوظة بعد.</p><?php else : ?>
        <table style="width:100%;border-collapse:collapse;"><thead><tr style="background:#f8fafc;text-align:right;"><th style="padding:12px;">الشهر</th><th style="padding:12px;">الإيرادات</th><th style="padding:12px;">المصروفات</th><th style="padding:12px;">الأرباح</th><th style="padding:12px;">الملف</th></tr></thead><tbody>
        <?php foreach ($reports as $report) : ?><tr style="border-top:1px solid #e5e7eb;"><td style="padding:12px;"><?php echo esc_html($report->report_month); ?></td><td style="padding:12px;">ج.م <?php echo number_format_i18n((float)$report->income,2); ?></td><td style="padding:12px;">ج.م <?php echo number_format_i18n((float)$report->expenses,2); ?></td><td style="padding:12px;">ج.م <?php echo number_format_i18n((float)$report->profit,2); ?></td><td style="padding:12px;"><?php echo $report->file_url ? '<a href="'.esc_url($report->file_url).'" target="_blank">تحميل</a>' : '—'; ?></td></tr><?php endforeach; ?>
        </tbody></table><?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode('mostaager_owner_reports', function ($atts) {
    $atts = shortcode_atts(array('owner_id' => 0), $atts, 'mostaager_owner_reports');
    return msfp_render_owner_reports(absint($atts['owner_id']));
});

add_action('init', function () {
    if (empty($_POST['msfp_owner_report_action']) || $_POST['msfp_owner_report_action'] !== 'generate') {
        return;
    }
    if (!is_user_logged_in() || empty($_POST['msfp_owner_report_nonce']) || !wp_verify_nonce($_POST['msfp_owner_report_nonce'], 'msfp_generate_owner_report')) {
        return;
    }
    global $wpdb;
    $owner_id = get_current_user_id();
    $month = sanitize_text_field($_POST['report_month'] ?? current_time('Y-m'));
    $income = (float) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM {$wpdb->prefix}ms_invoices WHERE status='paid' AND DATE_FORMAT(COALESCE(paid_date, created_at), '%%Y-%%m') = %s", $month));
    $expenses = (float) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM {$wpdb->prefix}ms_expenses WHERE DATE_FORMAT(COALESCE(expense_date, created_at), '%%Y-%%m') = %s", $month));
    $profit = $income - $expenses;
    $reports_table = $wpdb->prefix . 'ms_owner_reports';
    $existing_report_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$reports_table} WHERE owner_id = %d AND report_month = %s ORDER BY id ASC LIMIT 1", $owner_id, $month));
    $report_data = array('income'=>$income,'expenses'=>$expenses,'maintenance'=>0,'profit'=>$profit,'created_at'=>current_time('mysql'));
    if ($existing_report_id) {
        $wpdb->update($reports_table, $report_data, array('id' => absint($existing_report_id)), array('%f','%f','%f','%f','%s'), array('%d'));
    } else {
        $wpdb->insert($reports_table, array_merge(array('owner_id'=>$owner_id,'report_month'=>$month), $report_data), array('%d','%s','%f','%f','%f','%f','%s'));
    }
});

function msfp_render_documents_center($user_id = 0)
{
    $user_id = absint($user_id ?: get_current_user_id());
    if (!$user_id || !is_user_logged_in()) { return '<div class="ms-card"><p>يرجى تسجيل الدخول لعرض مركز المستندات.</p></div>'; }
    global $wpdb;
    $docs = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ms_documents WHERE user_id = %d ORDER BY id DESC LIMIT 100", $user_id));
    // Include the signed rental contract stored on the tenant's Houzez property.
    $tenant_unit_id = 0;
    if (function_exists('ms_get_tenant_unit')) {
        $tenant_unit_row = ms_get_tenant_unit($user_id);
        $tenant_unit_id = absint($tenant_unit_row->unit_id ?? 0);
    }
    $unit_table = $wpdb->prefix . 'ms_units';
    if (!$tenant_unit_id) {
        $tenant_unit_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$unit_table} WHERE tenant_id = %d AND status IN ('rented','occupied','active') ORDER BY id DESC LIMIT 1", $user_id));
        $tenant_unit_id = absint($tenant_unit_row->id ?? $tenant_unit_row->unit_id ?? 0);
    }
    if ($tenant_unit_id) {
        $tenant_unit_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$unit_table} WHERE id = %d LIMIT 1", $tenant_unit_id));
        $property_id = absint($tenant_unit_row->wp_post_id ?? $tenant_unit_row->property_id ?? 0);
        if ($property_id) {
            $signed_contract_url = esc_url_raw((string) get_post_meta($property_id, 'ms_rent_contract_url', true));
            if (!$signed_contract_url) {
                $contract_type = sanitize_key((string) get_post_meta($property_id, 'ms_property_contract_type', true));
                if ($contract_type === 'rent') { $signed_contract_url = esc_url_raw((string) get_post_meta($property_id, 'ms_property_contract_url', true)); }
            }
            if ($signed_contract_url) {
                $already_listed = false;
                foreach ((array) $docs as $existing_doc) { if (!empty($existing_doc->file_url) && $existing_doc->file_url === $signed_contract_url) { $already_listed = true; break; } }
                if (!$already_listed) {
                    array_unshift($docs, (object) array('title' => 'عقد الإيجار الموقّع من الطرفين', 'document_type' => 'lease_contract', 'created_at' => get_post_field('post_date', $property_id), 'file_url' => $signed_contract_url, 'file_id' => 0));
                }
            }
        }
    }
    ob_start(); ?>
    <div class="ms-card msfp-documents"><h3>Documents Center - مركز المستندات</h3>
        <form id="msfp-document-form" enctype="multipart/form-data" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;align-items:end;">
            <input type="hidden" name="action" value="msfp_upload_document"><input type="hidden" name="security" value="<?php echo esc_attr(wp_create_nonce('msfp_document_nonce')); ?>">
            <label>العنوان<input name="title" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"></label>
            <label>النوع<select name="document_type" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"><option value="lease_contract">عقد الإيجار</option><option value="identity">الهوية</option><option value="insurance">التأمين</option><option value="other">أخرى</option></select></label>
            <label>الملف<input type="file" name="document_file" required accept="image/*,.pdf,.doc,.docx" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"></label>
            <button type="submit" style="padding:10px 16px;background:#2563eb;color:#fff;border:0;border-radius:8px;">رفع المستند</button><div id="msfp-document-message" style="display:none;font-weight:600;"></div>
        </form>
        <?php if (empty($docs)) : ?><p style="margin-top:16px;">لا توجد مستندات بعد.</p><?php else : ?><table style="width:100%;border-collapse:collapse;margin-top:16px;"><thead><tr style="background:#f8fafc;text-align:right;"><th style="padding:12px;">العنوان</th><th style="padding:12px;">النوع</th><th style="padding:12px;">تاريخ الرفع</th><th style="padding:12px;">تحميل</th></tr></thead><tbody><?php foreach ($docs as $doc) : ?><tr style="border-top:1px solid #e5e7eb;"><td style="padding:12px;"><?php echo esc_html($doc->title); ?></td><td style="padding:12px;"><?php echo esc_html($doc->document_type); ?></td><td style="padding:12px;"><?php echo esc_html($doc->created_at); ?></td><td style="padding:12px;"><a href="<?php echo esc_url($doc->file_url); ?>" target="_blank">تحميل</a></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
    </div><script>(function(){var f=document.getElementById('msfp-document-form');if(!f||f.dataset.bound==='1')return;f.dataset.bound='1';f.addEventListener('submit',function(e){e.preventDefault();var m=document.getElementById('msfp-document-message');m.style.display='block';m.style.color='#64748b';m.textContent='جاري الرفع...';fetch('<?php echo esc_js(admin_url('admin-ajax.php')); ?>',{method:'POST',credentials:'same-origin',body:new FormData(f)}).then(function(r){return r.json();}).then(function(res){m.style.display='block';m.style.color=res.success?'#059669':'#dc2626';m.textContent=res.data&&res.data.message?res.data.message:(res.success?'تم الرفع بنجاح.':(res.data&&res.data.message?res.data.message:'حدث خطأ.'));if(res.success)setTimeout(function(){location.reload();},800);}).catch(function(err){m.style.display='block';m.style.color='#dc2626';m.textContent='حدث خطأ في الاتصال.';console.error(err);});});})();</script>
    <?php return ob_get_clean();
}
add_shortcode('mostaager_tenant_documents', function ($atts) { $atts = shortcode_atts(array('user_id'=>0), $atts, 'mostaager_tenant_documents'); return msfp_render_documents_center(absint($atts['user_id'])); });

function msfp_handle_upload_document()
{
    check_ajax_referer('msfp_document_nonce', 'security');
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'يرجى تسجيل الدخول.'), 403);
    }

    // Only allow common user roles to upload documents (admins always allowed)
    $user_id = get_current_user_id();
    if (!current_user_can('manage_options')) {
        $allowed_roles = array('owner', 'tenant', 'agent', 'building_manager');
        $ok = false;
        if (function_exists('ms_user_has_role')) {
            foreach ($allowed_roles as $r) {
                if (ms_user_has_role($user_id, $r)) {
                    $ok = true;
                    break;
                }
            }
        }
        if (!$ok) {
            wp_send_json_error(array('message' => 'غير مصرح'), 403);
        }
    }

    if (empty($_FILES['document_file']['name'])) {
        wp_send_json_error(array('message' => 'يرجى اختيار ملف.'), 422);
    }

    // Basic file validations
    $file = $_FILES['document_file'];
    $max_size = 10 * 1024 * 1024; // 10 MB
    if ($file['size'] > $max_size) {
        wp_send_json_error(array('message' => 'حجم الملف أكبر من الحد المسموح (10MB).'), 413);
    }

    $allowed_exts = array('pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif');
    $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ((!empty($check['ext']) && !in_array($check['ext'], $allowed_exts, true)) || !in_array($ext, $allowed_exts, true)) {
        wp_send_json_error(array('message' => 'امتداد الملف غير مدعوم.'), 415);
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $file_id = media_handle_upload('document_file', 0);
    if (is_wp_error($file_id)) {
        wp_send_json_error(array('message' => $file_id->get_error_message()), 500);
    }

    global $wpdb;
    $doc_type = sanitize_key($_POST['document_type'] ?? 'other');
    $allowed_types = array('lease_contract', 'identity', 'insurance', 'other');
    if (!in_array($doc_type, $allowed_types, true)) {
        $doc_type = 'other';
    }
    $title = sanitize_text_field($_POST['title'] ?? 'Document');
    $file_url = wp_get_attachment_url($file_id);

    $inserted = $wpdb->insert($wpdb->prefix . 'ms_documents', array(
        'user_id' => $user_id,
        'document_type' => $doc_type,
        'title' => $title,
        'file_id' => $file_id,
        'file_url' => $file_url,
        'uploaded_by' => $user_id,
        'created_at' => current_time('mysql'),
    ), array('%d', '%s', '%s', '%d', '%s', '%d', '%s'));

    if (!$inserted) {
        wp_send_json_error(array('message' => 'فشل حفظ بيانات المستند.'), 500);
    }

    $doc_id = $wpdb->insert_id;
    wp_send_json_success(array('message' => 'تم رفع المستند بنجاح.', 'document' => array('id' => $doc_id, 'file_url' => $file_url)));
}
add_action('wp_ajax_msfp_upload_document', 'msfp_handle_upload_document');
add_action('wp_ajax_nopriv_msfp_upload_document', function () {
    wp_send_json_error(array('message'=>'يرجى تسجيل الدخول للرفع.'),403);
});

function msfp_render_meter_readings($unit_id = 0)
{
    $unit_id = absint($unit_id ?: ($_GET['unit_id'] ?? 0));
    if (!is_user_logged_in()) {
        return '<div class="ms-card"><p>يرجى تسجيل الدخول لرفع قراءات العدادات.</p></div>';
    }

    $user_id = get_current_user_id();
    $is_admin = current_user_can('manage_options');

    if (!$unit_id && function_exists('ms_get_tenant_unit')) {
        $tenant_unit = ms_get_tenant_unit($user_id);
        if ($tenant_unit) {
            $unit_id = intval($tenant_unit->unit_id ?? 0);
        }
    }

    if ($unit_id && !$is_admin && function_exists('ms_get_tenant_unit')) {
        $tenant_unit = ms_get_tenant_unit($user_id);
        if (!$tenant_unit || intval($tenant_unit->unit_id ?? 0) !== $unit_id) {
            $unit_id = intval($tenant_unit->unit_id ?? 0);
        }
    }

    global $wpdb;
    if ($unit_id) {
        $where = $wpdb->prepare('unit_id=%d', $unit_id);
    } else {
        $where = $wpdb->prepare('tenant_id=%d OR created_by=%d', $user_id, $user_id);
    }

    $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ms_meter_readings WHERE {$where} ORDER BY reading_date DESC, id DESC LIMIT 100");
    ob_start(); ?>
    <div class="ms-card msfp-meters"><h3>Meter Readings - قراءات العدادات</h3>
        <?php if (!$unit_id && !$is_admin) : ?>
            <p style="margin-top:16px;">لا يمكن تحديد وحدتك الحالية. يرجى التأكد من ربط حسابك بوحدة سكنية قبل رفع قراءة عداد.</p>
        <?php else : ?>
            <form id="msfp-meter-form" enctype="multipart/form-data" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;align-items:end;">
                <input type="hidden" name="action" value="msfp_add_meter_reading">
                <input type="hidden" name="security" value="<?php echo esc_attr(wp_create_nonce('msfp_meter_nonce')); ?>">
                <input type="hidden" name="unit_id" value="<?php echo esc_attr($unit_id); ?>">
                <label>نوع العداد<select name="meter_type" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"><option value="electricity">عداد كهرباء</option><option value="water">عداد مياه</option><option value="gas">عداد غاز</option></select></label>
                <label>القراءة<input type="number" name="reading_value" step="0.001" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"></label>
                <label>التاريخ<input type="date" name="reading_date" value="<?php echo esc_attr(current_time('Y-m-d')); ?>" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"></label>
                <label>صورة العداد<input type="file" name="meter_image" accept="image/*" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;"></label>
                <button type="submit" style="padding:10px 16px;background:#2563eb;color:#fff;border:0;border-radius:8px;">حفظ القراءة</button>
                <div id="msfp-meter-message" style="display:none;font-weight:600;"></div>
            </form>
        <?php endif; ?>
        <?php if (empty($rows)) : ?>
            <p style="margin-top:16px;">لا توجد قراءات مسجلة بعد.</p>
        <?php else : ?>
            <table style="width:100%;border-collapse:collapse;margin-top:16px;"><thead><tr style="background:#f8fafc;text-align:right;"><th style="padding:12px;">النوع</th><th style="padding:12px;">القراءة</th><th style="padding:12px;">التاريخ</th><th style="padding:12px;">الصورة</th></tr></thead><tbody><?php foreach ($rows as $row) : ?><tr style="border-top:1px solid #e5e7eb;"><td style="padding:12px;"><?php $meter_type = $row->meter_type ?? ''; $type_labels = array('electricity' => 'عداد كهرباء', 'water' => 'عداد مياه', 'gas' => 'عداد غاز'); echo esc_html($type_labels[$meter_type] ?? $meter_type); ?></td><td style="padding:12px;"><?php echo esc_html($row->reading_value); ?></td><td style="padding:12px;"><?php echo esc_html($row->reading_date); ?></td><td style="padding:12px;"><?php echo $row->image_url ? '<a href="'.esc_url($row->image_url).'" target="_blank">عرض</a>' : '—'; ?></td></tr><?php endforeach; ?></tbody></table>
        <?php endif; ?>
    </div>
    <script>(function(){var f=document.getElementById('msfp-meter-form');if(!f||f.dataset.bound==='1')return;f.dataset.bound='1';f.addEventListener('submit',function(e){e.preventDefault();var m=document.getElementById('msfp-meter-message');m.style.display='block';m.style.color='#64748b';m.textContent='جاري الحفظ...';fetch('<?php echo esc_js(admin_url('admin-ajax.php')); ?>',{method:'POST',credentials:'same-origin',body:new FormData(f)}).then(function(r){return r.json();}).then(function(res){m.style.display='block';m.style.color=res.success?'#059669':'#dc2626';m.textContent=res.data&&res.data.message?res.data.message:(res.success?'تم الحفظ بنجاح.':(res.data&&res.data.message?res.data.message:'حدث خطأ.'));if(res.success)setTimeout(function(){location.reload();},800);}).catch(function(err){m.style.display='block';m.style.color='#dc2626';m.textContent='حدث خطأ في الاتصال.';console.error(err);});});})();</script>
    <?php return ob_get_clean();
}
add_shortcode('mostaager_meter_readings', function ($atts) { $atts = shortcode_atts(array('unit_id'=>0), $atts, 'mostaager_meter_readings'); return msfp_render_meter_readings(absint($atts['unit_id'])); });

function msfp_handle_add_meter_reading()
{
    check_ajax_referer('msfp_meter_nonce', 'security');
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'يرجى تسجيل الدخول.'), 403);
    }

    $user_id = get_current_user_id();
    $unit_id = absint($_POST['unit_id'] ?? 0);
    $meter_type = sanitize_key($_POST['meter_type'] ?? 'electricity');
    $reading_value = isset($_POST['reading_value']) ? floatval($_POST['reading_value']) : 0;
    $reading_date = sanitize_text_field($_POST['reading_date'] ?? current_time('Y-m-d'));
    $is_admin = current_user_can('manage_options');

    if (!$unit_id && function_exists('ms_get_tenant_unit')) {
        $tenant_unit = ms_get_tenant_unit($user_id);
        if ($tenant_unit) {
            $unit_id = intval($tenant_unit->unit_id ?? 0);
        }
    }

    if (!$unit_id && !$is_admin) {
        wp_send_json_error(array('message' => 'لا يمكن تحديد وحدة العداد. يرجى التأكد من ربطك بوحدة سكنية.'), 403);
    }

    if (!$is_admin && $unit_id) {
        $tenant_unit = function_exists('ms_get_tenant_unit') ? ms_get_tenant_unit($user_id) : null;
        if (!$tenant_unit || intval($tenant_unit->unit_id ?? 0) !== $unit_id) {
            wp_send_json_error(array('message' => 'لا تملك صلاحية إضافة قراءة لهذه الوحدة.'), 403);
        }
    }

    $allowed_types = array('electricity', 'water', 'gas');
    if (!in_array($meter_type, $allowed_types, true)) {
        $meter_type = 'electricity';
    }

    if ($reading_value <= 0) {
        wp_send_json_error(array('message' => 'يرجى إدخال قيمة قراءة صحيحة أكبر من صفر.'), 422);
    }

    $date_obj = date_create_from_format('Y-m-d', $reading_date);
    if (!$date_obj || $date_obj->format('Y-m-d') !== $reading_date) {
        wp_send_json_error(array('message' => 'يرجى إدخال تاريخ قراءة صالح.'), 422);
    }

    $image_id = 0;
    $image_url = '';
    if (!empty($_FILES['meter_image']['name'])) {
        $file = $_FILES['meter_image'];
        $max_size = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $max_size) {
            wp_send_json_error(array('message' => 'حجم صورة العداد أكبر من الحد المسموح (10MB).'), 413);
        }

        $allowed_image_exts = array('jpg', 'jpeg', 'png', 'gif');
        $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ((!empty($check['ext']) && !in_array($check['ext'], $allowed_image_exts, true)) || !in_array($ext, $allowed_image_exts, true)) {
            wp_send_json_error(array('message' => 'امتداد الصورة غير مدعوم. الرجاء رفع JPG أو PNG أو GIF.'), 415);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $image_id = media_handle_upload('meter_image', 0);
        if (is_wp_error($image_id)) {
            wp_send_json_error(array('message' => $image_id->get_error_message()), 500);
        }
        $image_url = wp_get_attachment_url($image_id);
    }

    global $wpdb;
    $inserted = $wpdb->insert(
        $wpdb->prefix . 'ms_meter_readings',
        array(
            'building_id' => 0,
            'unit_id' => $unit_id,
            'tenant_id' => $user_id,
            'meter_type' => $meter_type,
            'reading_value' => $reading_value,
            'reading_date' => $reading_date,
            'image_id' => $image_id,
            'image_url' => $image_url,
            'created_by' => $user_id,
            'created_at' => current_time('mysql'),
        ),
        array('%d', '%d', '%d', '%s', '%f', '%s', '%d', '%s', '%d', '%s')
    );

    if (!$inserted) {
        wp_send_json_error(array('message' => 'فشل حفظ قراءة العداد. حاول مرة أخرى.'), 500);
    }

    wp_send_json_success(array(
        'message' => 'تم حفظ قراءة العداد بنجاح.',
        'reading_id' => $wpdb->insert_id,
        'image_url' => $image_url,
    ));
}
add_action('wp_ajax_msfp_add_meter_reading', 'msfp_handle_add_meter_reading');
add_action('wp_ajax_nopriv_msfp_add_meter_reading', function () {
    wp_send_json_error(array('message' => 'يرجى تسجيل الدخول لحفظ القراءة.'), 403);
});

add_action('wp_ajax_ms_wallet_recharge', function () {
    check_ajax_referer('ms_wallet_recharge_nonce', 'security');
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message'=>'يرجى تسجيل الدخول.'),403);
    }

    $amount = floatval($_POST['amount'] ?? 0);
    if ($amount <= 0) {
        wp_send_json_error(array('message'=>'يرجى إدخال مبلغ صحيح.'),422);
    }

    if (function_exists('ms_create_woo_order_for_wallet_recharge')) {
        $redirect_url = ms_create_woo_order_for_wallet_recharge(get_current_user_id(), $amount);
        if ($redirect_url) {
            wp_send_json_success(array('redirect_url' => $redirect_url));
        } else {
            wp_send_json_error(array('message'=>'تعذر إنشاء طلب الشحن. يرجى التأكد من تفعيل WooCommerce.'),500);
        }
    } else {
        wp_send_json_error(array('message'=>'وظيفة شحن المحفظة غير متاحة.'),500);
    }
});

add_action('wp_ajax_nopriv_ms_wallet_recharge', function () {
    wp_send_json_error(array('message'=>'يرجى تسجيل الدخول لشحن المحفظة.'),403);
});


/**
 * Admin queue for maintenance withdrawal requests.
 * Approval records authorization only; the actual bank/cash transfer remains manual.
 */
add_action('admin_menu', function () {
    if (current_user_can('manage_options')) {
        add_submenu_page('options-general.php', 'طلبات سحب الصيانة', 'طلبات سحب الصيانة', 'manage_options', 'mostaager-maintenance-withdrawals', 'msfp_admin_maintenance_withdrawals_page');
    }
});

function msfp_admin_maintenance_withdrawals_page()
{
    if (!current_user_can('manage_options')) wp_die('غير مصرح لك بالوصول إلى هذه الصفحة.');
    global $wpdb;
    $table = $wpdb->prefix . 'ms_transfer_requests';
    if (isset($_POST['msfp_withdrawal_action'], $_POST['request_id']) && check_admin_referer('msfp_withdrawal_action', 'msfp_withdrawal_nonce')) {
        $request_id = absint($_POST['request_id']);
        $action = sanitize_key($_POST['msfp_withdrawal_action']);
        $new_status = $action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : '');
        if ($new_status) {
            $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $request_id));
            $wallet_deducted = false;
            if ($request && $new_status === 'approved' && sanitize_key($request->funding_source ?? 'collection') === 'building_wallet') {
                $wpdb->query('START TRANSACTION');
                $wallet_table = $wpdb->prefix . 'ms_building_wallet';
                $deducted = $wpdb->query($wpdb->prepare(
                    "UPDATE {$wallet_table} SET balance = balance - %f, updated_at = %s WHERE building_id = %d AND balance >= %f",
                    round((float) $request->amount, 2),
                    current_time('mysql'),
                    absint($request->building_id),
                    round((float) $request->amount, 2)
                ));
                if ($deducted === 1) {
                    $wallet_deducted = (bool) $wpdb->insert($wpdb->prefix . 'ms_building_wallet_transactions', array(
                        'building_id' => absint($request->building_id),
                        'amount' => -round((float) $request->amount, 2),
                        'type' => 'maintenance_transfer',
                        'description' => 'تمويل تنفيذ أمر الصيانة #' . absint($request->work_order_id),
                        'reference_id' => $request_id,
                        'created_at' => current_time('mysql'),
                    ), array('%d','%f','%s','%s','%d','%s'));
                }
                if (!$wallet_deducted) {
                    $wpdb->query('ROLLBACK');
                    echo '<div class="notice notice-error is-dismissible"><p>تعذر اعتماد الطلب: رصيد محفظة البناء غير كافٍ أو تعذر تسجيل الحركة.</p></div>';
                    $new_status = '';
                } else {
                    $wpdb->query('COMMIT');
                }
            }
            if ($new_status) {
                $wpdb->update($table, array('status' => $new_status, 'processed_by' => get_current_user_id(), 'processed_at' => current_time('mysql'), 'notes' => sanitize_textarea_field($_POST['notes'] ?? '')), array('id' => $request_id), array('%s','%d','%s','%s'), array('%d'));
                if ($request && function_exists('ms_add_notification')) {
                    $message = $new_status === 'approved'
                        ? (sanitize_key($request->funding_source ?? 'collection') === 'building_wallet' ? 'تمت الموافقة وخصم المبلغ من محفظة البناء لتنفيذ الصيانة.' : 'وافقت الإدارة على طلب سحب مبلغ الصيانة. يمكن تنفيذ التحويل يدويًا.')
                        : 'رفضت الإدارة طلب سحب مبلغ الصيانة.';
                    ms_add_notification(absint($request->requested_by), 'maintenance_withdrawal_' . $new_status, $message, absint($request->building_id), $request_id);
                }
                echo '<div class="notice notice-success is-dismissible"><p>تم تحديث حالة طلب السحب.</p></div>';
            }
        }
    }
    $status_filter = isset($_GET['status']) ? sanitize_key($_GET['status']) : 'pending';
    $where = $status_filter && in_array($status_filter, array('pending','approved','rejected'), true) ? $wpdb->prepare(' WHERE status = %s ', $status_filter) : '';
    $rows = $wpdb->get_results("SELECT * FROM {$table}{$where} ORDER BY id DESC LIMIT 200");
    echo '<div class="wrap" dir="rtl"><h1>طلبات سحب الصيانة</h1><p>تظهر هنا طلبات السحب بعد اكتمال تحصيل فواتير أمر العمل. الموافقة تسجل التفويض، والتحويل المالي الفعلي يتم يدويًا حسب سياسة الإدارة.</p><form method="get"><input type="hidden" name="page" value="mostaager-maintenance-withdrawals"><select name="status"><option value="pending"'.selected($status_filter,'pending',false).'>معلقة</option><option value="approved"'.selected($status_filter,'approved',false).'>معتمدة</option><option value="rejected"'.selected($status_filter,'rejected',false).'>مرفوضة</option></select> <button class="button">تصفية</button></form><table class="widefat fixed striped" style="margin-top:16px"><thead><tr><th>#</th><th>أمر العمل</th><th>المبنى</th><th>المبلغ</th><th>طالب السحب</th><th>الحالة</th><th>التاريخ</th><th>الإجراء</th></tr></thead><tbody>';
    if (!$rows) echo '<tr><td colspan="8">لا توجد طلبات.</td></tr>';
    foreach ((array) $rows as $row) {
        $requester = get_userdata(absint($row->requested_by));
        echo '<tr><td>'.intval($row->id).'</td><td>#'.intval($row->work_order_id).'</td><td>#'.intval($row->building_id).'</td><td>'.number_format_i18n((float)$row->amount,2).' ج.م</td><td>'.esc_html($requester ? $requester->display_name : '#'.intval($row->requested_by)).'</td><td>'.esc_html($row->status).'</td><td>'.esc_html($row->created_at).'</td><td>';
        if ($row->status === 'pending') {
            echo '<form method="post" style="display:inline-flex;gap:5px;align-items:center">'; wp_nonce_field('msfp_withdrawal_action','msfp_withdrawal_nonce'); echo '<input type="hidden" name="request_id" value="'.intval($row->id).'">'; echo '<input type="text" name="notes" placeholder="ملاحظة" style="width:130px"><button class="button button-primary" name="msfp_withdrawal_action" value="approve">موافقة</button><button class="button" name="msfp_withdrawal_action" value="reject">رفض</button></form>';
        } else { echo '—'; }
        echo '</td></tr>';
    }
    echo '</tbody></table></div>';
}
