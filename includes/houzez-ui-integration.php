<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Houzez UI Integration
 * Unifies Mostaager UI with Houzez theme design
 */

// Enqueue Houzez styles for Mostaager dashboards
add_action('wp_enqueue_scripts', 'ms_houzey_enqueue_dashboard_styles');

function ms_houzey_enqueue_dashboard_styles() {
    if (!is_page_template('templates/dashboard.php') && !is_singular('ms_building')) {
        return;
    }
    
    // Enqueue Houzez theme styles if available
    if (function_exists('houzez_enqueue_styles')) {
        houzez_enqueue_styles();
    }
    
    // Enqueue Mostaager dashboard styles
    wp_enqueue_style(
        'ms-houzez-dashboard',
        MOSTAAGER_ENTERPRISE_URL . 'assets/css/houzez-integration.css',
        [],
        MOSTAAGER_ENTERPRISE_VERSION
    );
}

// Enqueue admin styles
add_action('admin_enqueue_scripts', 'ms_houzey_enqueue_admin_styles');

function ms_houzey_enqueue_admin_styles() {
    $screen = get_current_screen();
    
    if ($screen && ($screen->id === 'toplevel_page_ms-houzez-reports' || strpos($screen->id, 'ms_') !== false)) {
        wp_enqueue_style(
            'ms-houzez-admin',
            MOSTAAGER_ENTERPRISE_URL . 'assets/css/houzez-integration.css',
            [],
            MOSTAAGER_ENTERPRISE_VERSION
        );
    }
}

// Add Houzez color variables to Mostaager dashboard
add_action('wp_head', 'ms_houzez_add_color_variables');

function ms_houzez_add_color_variables() {
    if (!is_page_template('templates/dashboard.php') && !is_singular('ms_building')) {
        return;
    }
    
    // Get Houzez theme colors if available
    $primary_color = get_option('houzez_primary_color', '#f5af02');
    $secondary_color = get_option('houzez_secondary_color', '#0f172a');
    $accent_color = get_option('houzez_accent_color', '#2563eb');
    
    ?>
    <style>
        :root {
            --ms-primary-color: <?php echo esc_attr($primary_color); ?>;
            --ms-secondary-color: <?php echo esc_attr($secondary_color); ?>;
            --ms-accent-color: <?php echo esc_attr($accent_color); ?>;
            --ms-text-color: #0f172a;
            --ms-bg-color: #f4f7f9;
            --ms-border-color: #e5e7eb;
            --ms-success-color: #10b981;
            --ms-warning-color: #f59e0b;
            --ms-danger-color: #ef4444;
        }
        
        /* Override Mostaager colors with Houzez theme colors */
        .ms-sidebar {
            background: var(--ms-secondary-color);
            box-shadow: inset 4px 0 0 rgba(<?php echo hex2rgb($primary_color); ?>, 0.12);
        }
        
        .ms-sidebar-title {
            color: var(--ms-primary-color);
        }
        
        .ms-menu-icon {
            background: rgba(<?php echo hex2rgb($primary_color); ?>, 0.12);
            color: var(--ms-primary-color);
        }
        
        .ms-tab-link.active {
            background: rgba(<?php echo hex2rgb($primary_color); ?>, 0.24);
            color: var(--ms-primary-color);
        }
        
        .ms-sidebar-menu li.active > a,
        .ms-sidebar-menu li > a:hover {
            background: rgba(<?php echo hex2rgb($primary_color); ?>, 0.16);
            color: var(--ms-primary-color);
        }
    </style>
    <?php
}

// Helper function to convert hex to rgb
function hex2rgb($hex) {
    $hex = str_replace('#', '', $hex);
    
    if (strlen($hex) == 3) {
        $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
        $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
        $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
    } else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }
    
    return "$r, $g, $b";
}

// Use Houzez fonts for Mostaager
add_action('wp_enqueue_scripts', 'ms_houzey_enqueue_fonts');

function ms_houzey_enqueue_fonts() {
    if (!is_page_template('templates/dashboard.php') && !is_singular('ms_building')) {
        return;
    }
    
    // Use Cairo font (Arabic) and Poppins (English) like Houzez
    wp_enqueue_style('ms-google-fonts', 'https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap', [], null);
}

// Add Houzez-style buttons to Mostaager
add_action('admin_head', 'ms_houzez_add_button_styles');

function ms_houzez_add_button_styles() {
    ?>
    <style>
        .ms-houzez-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            text-decoration: none;
        }
        
        .ms-houzez-btn-primary {
            background: var(--ms-primary-color);
            color: #fff;
        }
        
        .ms-houzez-btn-primary:hover {
            background: #d49a00;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 175, 2, 0.4);
        }
        
        .ms-houzez-btn-secondary {
            background: var(--ms-secondary-color);
            color: #fff;
        }
        
        .ms-houzez-btn-secondary:hover {
            background: #1e293b;
            transform: translateY(-2px);
        }
        
        .ms-houzez-btn-accent {
            background: var(--ms-accent-color);
            color: #fff;
        }
        
        .ms-houzez-btn-accent:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
        }
        
        .ms-houzez-btn-outline {
            background: transparent;
            border: 2px solid var(--ms-primary-color);
            color: var(--ms-primary-color);
        }
        
        .ms-houzez-btn-outline:hover {
            background: var(--ms-primary-color);
            color: #fff;
        }
    </style>
    <?php
}

// Use Houzez card styles
add_action('admin_head', 'ms_houzez_add_card_styles');

function ms_houzez_add_card_styles() {
    ?>
    <style>
        .ms-houzez-card {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }
        
        .ms-houzez-card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            transform: translateY(-2px);
        }
        
        .ms-houzez-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .ms-houzez-card-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--ms-text-color);
            margin: 0;
        }
        
        .ms-houzez-card-body {
            color: #64748b;
            line-height: 1.6;
        }
        
        .ms-houzez-card-footer {
            margin-top: 16px;
            padding-top: 12px;
            border-top: 1px solid #e5e7eb;
        }
    </style>
    <?php
}

// Use Houzez table styles
add_action('admin_head', 'ms_houzez_add_table_styles');

function ms_houzez_add_table_styles() {
    ?>
    <style>
        .ms-houzez-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .ms-houzez-table thead {
            background: var(--ms-secondary-color);
            color: #fff;
        }
        
        .ms-houzez-table th {
            padding: 16px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }
        
        .ms-houzez-table td {
            padding: 16px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .ms-houzez-table tbody tr:hover {
            background: #f8fafc;
        }
        
        .ms-houzez-table tbody tr:last-child td {
            border-bottom: none;
        }
    </style>
    <?php
}

// Use Houzez form styles
add_action('admin_head', 'ms_houzez_add_form_styles');

function ms_houzez_add_form_styles() {
    ?>
    <style>
        .ms-houzez-form-group {
            margin-bottom: 20px;
        }
        
        .ms-houzez-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--ms-text-color);
            font-size: 14px;
        }
        
        .ms-houzez-input,
        .ms-houzez-select,
        .ms-houzez-textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #fff;
        }
        
        .ms-houzez-input:focus,
        .ms-houzez-select:focus,
        .ms-houzez-textarea:focus {
            outline: none;
            border-color: var(--ms-primary-color);
            box-shadow: 0 0 0 3px rgba(<?php echo hex2rgb(get_option('houzez_primary_color', '#f5af02')); ?>, 0.1);
        }
        
        .ms-houzez-input::placeholder,
        .ms-houzez-select::placeholder,
        .ms-houzez-textarea::placeholder {
            color: #94a3b8;
        }
    </style>
    <?php
}

// Add Houzez-style status badges
add_action('admin_head', 'ms_houzez_add_badge_styles');

function ms_houzez_add_badge_styles() {
    ?>
    <style>
        .ms-houzez-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .ms-houzez-badge-success {
            background: #dcfce7;
            color: #166534;
        }
        
        .ms-houzez-badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .ms-houzez-badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .ms-houzez-badge-info {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .ms-houzez-badge-primary {
            background: rgba(<?php echo hex2rgb(get_option('houzez_primary_color', '#f5af02')); ?>, 0.1);
            color: var(--ms-primary-color);
        }
    </style>
    <?php
}

// Responsive design improvements
add_action('admin_head', 'ms_houzez_add_responsive_styles');

function ms_houzez_add_responsive_styles() {
    ?>
    <style>
        @media (max-width: 768px) {
            .ms-dashboard {
                flex-direction: column;
            }
            
            .ms-sidebar {
                width: 100%;
                border-radius: 0 0 16px 16px;
                box-shadow: none;
            }
            
            .ms-content {
                padding: 20px 16px;
            }
            
            .ms-grid {
                grid-template-columns: 1fr;
            }
            
            .ms-houzez-table {
                font-size: 12px;
            }
            
            .ms-houzez-table th,
            .ms-houzez-table td {
                padding: 8px;
            }
        }
        
        @media (max-width: 480px) {
            .ms-houzez-btn {
                width: 100%;
                margin-bottom: 8px;
            }
            
            .ms-houzey-card {
                padding: 16px;
            }
        }
    </style>
    <?php
}
