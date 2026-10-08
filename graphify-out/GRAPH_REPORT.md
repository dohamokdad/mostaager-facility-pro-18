# Graph Report - mostaager-facility-pro-18  (2026-10-08)

## Corpus Check
- 199 files · ~388,740 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 25 file(s) not represented in the graph (top: .css 21, .z 2, (none) 1)

## Summary
- 2749 nodes · 5412 edges · 186 communities (74 shown, 112 thin omitted)
- Extraction: 86% EXTRACTED · 14% INFERRED · 0% AMBIGUOUS · INFERRED: 764 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `936c4020`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- TCPDF
- functions.php
- TCPDF_STATIC
- .writeHTML
- database.php
- MS_Advanced_Analytics
- RapidAddon
- MS_Advanced_Reports
- ajax.php
- admin.php
- QRcode
- elementor-widgets.php
- TCPDF2DBarcode
- ms_get_agent_subscription_status
- ms_current_user_manages_building
- MS_PDF
- pro-platform.php
- ms_user_can_view_dashboard
- MS_Dashboard_Advanced
- MS_Monitoring_Analytics
- TCPDF_FONTS
- GNU Lesser General Public License v3
- {closure#1}
- MS_Advanced_Interactions
- MS_Houzez_Building_Integration
- MS_Additional_Integrations
- ms-ux.js
- .Image
- MS_Advanced_Integrations
- MS_Advanced_Automation
- Mostager_Utility_Bills_API
- Mostager_WhatsApp_Integration
- dashboard.js
- MS_Performance_Optimizer
- wordpress-notifications-integration.php
- MS_Multi_Channel_Notifications
- MS_Advanced_User_Management
- admin.js
- advanced-financial.php
- ms_add_notification
- Mostager_Invoice_PDF
- Mostaager_DB
- RestApi
- MS_Houzez_Integration
- inline-property-form.php
- MS_Backup_System
- دليل API — منصة مستأجر العقاري
- MS_Addon_Dashboard
- Update Report 2026 (v18.0.0)
- MS_Advanced_Settings
- MS_Maintenance_API
- houzez-wallet-integration.php
- User Role Audit Report
- MS_Houzez_Dashboard_Bridge
- TCPDF_FILTERS
- MS_Firebase_FCM_Provider
- MS_Unified_Settings
- notification-preferences.php
- MS_Data_Sync
- Mostager_Facilities_API
- .file_exists
- .date
- MS_Custom_Fields_Manager
- houzez-rest-api-integration.php
- houzez-roles-integration.php
- ms_run_installer
- houzez-reports-integration.php
- houzez-ui-integration.php
- user-experience.php
- Mobile Developer API Guide
- building-manager-metabox.php
- houzez-invoice-integration.php
- wordpress-comments-integration.php
- ms_user_can_access_building
- MS_Houzez_CRM_Bridge
- init
- .get_users
- Mostaager Facility PRO Plugin (README)
- {closure#2}
- tenant-dashboard-enhancements.php
- rent-dashboard-enhancements.js
- MS_API
- agent-dashboard-tabs.js
- building-dashboard-enhancements.js
- Mostaager Facility PRO — 18.2.0 (UX P0)
- Mostaager Facility PRO — 18.1.0
- ms_get_building_id_values_for_query
- MS_Reports_Engine
- ms_get_tenant_unit
- MFP_Houzez_Map_Status
- MFP_Houzez_Notifications
- ms_get_company_clause
- Owner Role
- Mostaager Facility PRO — 18.3.0 (Brand identity + layout)
- migration-script.php
- monitoring.php
- Comprehensive Audit Report
- owner-agent-dashboard-enhancements.js
- Mostaager Facility PRO — 18.4.0 (Premium look + motion)
- CustomPostTypes
- analytics.php
- MFP_Houzez_Ratings
- Mostaager Facility PRO — 18.6.0 (Houzez integration, phases 2–4)
- agent-dashboard-tabs.php
- agent-properties-ui.js
- tenant-dashboard-enhancements.js
- msfp_get_agent_building_ids
- cli.php
- Mostaager Facility PRO — 18.15.0
- Mostaager Facility PRO — 18.19.0
- landing-desktop-hover-scroll.js
- Activator
- automation-advanced.php
- dashboard-advanced.php
- import-wizard-advanced.php
- {closure#1}
- .create_reply
- tcpdf_font_data.php
- MS_PDF_Document
- ms_render_action_center
- {closure#12}
- ms-houzez-shell.js
- Fixed: `admin-ajax.php 400 (Bad Request)` on every dashboard tab
- Mostaager Facility PRO — 18.17.0
- Mostaager Facility PRO — 18.8.0 (Phase 5: one property↔building/unit contract)
- .render_menu_section
- CHANGES-18.18.0.md
- CHANGES-18.7.0.md

## God Nodes (most connected - your core abstractions)
1. `TCPDF` - 427 edges
2. `TCPDF_STATIC` - 160 edges
3. `QRcode` - 100 edges
4. `MS_Advanced_Analytics` - 69 edges
5. `MS_Advanced_Reports` - 54 edges
6. `TCPDF_FONTS` - 52 edges
7. `RapidAddon` - 50 edges
8. `MS_PDF` - 44 edges
9. `RestApi` - 39 edges
10. `MS_Advanced_Automation` - 35 edges

## Surprising Connections (you probably didn't know these)
- `Facilities API (mostager/v1/facilities)` --implements--> `Mostager_Facilities_API`  [INFERRED]
  MOBILE_API_DOCUMENTATION.md → includes/class-facilities-api.php
- `Maintenance Tickets API (mostager/v1/maintenance)` --implements--> `MS_Maintenance_API`  [INFERRED]
  MOBILE_API_DOCUMENTATION.md → includes/class-maintenance-api.php
- `WhatsApp Integration` --references--> `Mostager_WhatsApp_Integration`  [INFERRED]
  COMPREHENSIVE_AUDIT_REPORT.md → includes/class-whatsapp-integration.php
- `Fixed` --references--> `ms_sync_unit_with_property()`  [INFERRED]
  CHANGES-18.8.0.md → includes/functions.php
- `Fixed: `admin-ajax.php 400 (Bad Request)` on every dashboard tab` --references--> `ms_get_notifications_since()`  [INFERRED]
  CHANGES-18.16.0.md → core/database.php

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Agent approval of owner status change requests** — comprehensive_audit_report_agent_approval_system, core_database_ms_create_status_change_request, core_database_ms_approve_status_change_request, core_database_ms_reject_status_change_request, includes_functions_ms_get_agent_status_change_requests, comprehensive_audit_report_ms_status_change_requests_table [EXTRACTED 1.00]
- **Security deposit reserve/freeze/release flow** — comprehensive_audit_report_security_deposit_system, includes_functions_ms_reserve_security_deposit, core_database_ms_create_security_deposit, core_database_ms_freeze_wallet_balance, core_database_ms_release_security_deposit, comprehensive_audit_report_ms_wallet_restricted_amounts_table [EXTRACTED 1.00]
- **Agent subscription expiry and restore lifecycle** — comprehensive_audit_report_agent_subscription_system, includes_functions_ms_check_agent_subscription_limits, includes_functions_ms_convert_agent_properties_to_draft, includes_functions_ms_restore_agent_properties_after_renewal, comprehensive_audit_report_cron_jobs [INFERRED 0.85]

## Communities (186 total, 112 thin omitted)

### Community 1 - "functions.php"
Cohesion: 0.04
Nodes (67): {closure#9}(), ms_get_active_maintenance_by_manager(), ms_get_paid_unpaid_units_by_manager(), ms_get_property_contacts(), ms_get_units_count_by_manager(), {closure#1}(), {closure#3}(), {closure#9}() (+59 more)

### Community 4 - "database.php"
Cohesion: 0.09
Nodes (33): ms_admin_tenants_page(), {closure#37}(), {closure#4}(), {closure#48}(), ms_log_ajax_error(), ms_add_notification(), ms_add_wallet_transaction(), ms_approve_status_change_request() (+25 more)

### Community 8 - "ajax.php"
Cohesion: 0.07
Nodes (30): {closure#10}(), {closure#16}(), {closure#2}(), {closure#24}(), {closure#25}(), {closure#26}(), {closure#29}(), {closure#33}() (+22 more)

### Community 11 - "admin.php"
Cohesion: 0.06
Nodes (21): ms_admin_canonical_discussions(), ms_admin_canonical_invoices(), ms_admin_canonical_maintenance(), ms_admin_canonical_records_page(), ms_admin_canonical_transfers(), ms_admin_ensure_ms_units_table(), ms_admin_get_building_label(), ms_admin_ms_invoices_page() (+13 more)

### Community 14 - "elementor-widgets.php"
Cohesion: 0.06
Nodes (5): Mostaager_Agent_Dashboard_Widget, Mostaager_Building_Dashboard_Widget, Mostaager_Owner_Dashboard_Widget, Mostaager_Tenant_Dashboard_Widget, ms_register_mostaager_elementor_widget()

### Community 16 - "ms_get_agent_subscription_status"
Cohesion: 0.10
Nodes (22): Scheduled Cron Jobs, ms_contract Post Type (Contracts), {closure#40}(), {closure#41}(), ms_process_agent_subscription_expiry(), ms_can_change_property_status(), ms_check_agent_subscription_limits(), ms_convert_agent_properties_to_draft() (+14 more)

### Community 17 - "ms_current_user_manages_building"
Cohesion: 0.07
Nodes (28): ms_admin_get_legacy_invoice_user_id(), {closure#15}(), {closure#20}(), {closure#21}(), {closure#23}(), {closure#30}(), ms_current_user_manages_building(), ms_add_maintenance_timeline_entry() (+20 more)

### Community 19 - "pro-platform.php"
Cohesion: 0.08
Nodes (20): {closure#1}(), {closure#2}(), {closure#3}(), {closure#5}(), {closure#8}(), msfp_add_missing_column(), msfp_ajax_add_expense(), msfp_expense_type_labels() (+12 more)

### Community 20 - "ms_user_can_view_dashboard"
Cohesion: 0.16
Nodes (19): {closure#1}(), {closure#1}(), {closure#2}(), ready(), {closure#7}(), {closure#8}(), ms_get_building_id_for_agent(), ms_get_properties_by_agent() (+11 more)

### Community 26 - "{closure#1}"
Cohesion: 0.08
Nodes (31): {closure#1}(), {closure#1}(), {closure#1}(), Fixes found while testing, Known, not fixed yet, Mostaager Facility PRO — 18.5.0 (Houzez integration, phase 1), New: Houzez dashboard bridge, {closure#11}() (+23 more)

### Community 28 - "MS_Houzez_Building_Integration"
Cohesion: 0.08
Nodes (6): MS_Automation_Importer, {closure#1}(), {closure#2}(), MS_Houzez_Building_Integration, MS_Templates_Manager, MS_Data_Validator

### Community 30 - "ms-ux.js"
Cohesion: 0.16
Nodes (17): bindDeepLinks(), countUp(), enhanceDashboards(), enhanceKpis(), enhancePills(), hasScrollParent(), holdTab(), MSUX (+9 more)

### Community 34 - "Mostager_Utility_Bills_API"
Cohesion: 0.12
Nodes (3): RestApiV2, {closure#1}(), Mostager_Utility_Bills_API

### Community 35 - "Mostager_WhatsApp_Integration"
Cohesion: 0.09
Nodes (19): mostager_ajax_test_whatsapp(), mostager_auto_whatsapp_invoice(), mostager_auto_whatsapp_invoice_paid(), mostager_auto_whatsapp_maintenance(), Mostager_WhatsApp_Integration, mostager_whatsapp_settings_page(), msfp_apply_late_payment_penalties(), msfp_calculate_penalty() (+11 more)

### Community 36 - "dashboard.js"
Cohesion: 0.13
Nodes (17): fetchMaintenanceRequests(), getAjax(), getCurrentBuildingId(), initMostaagerDashboardTabs(), activateTab(), activateTabByHash(), clearTabState(), getInitialTabFromUrl() (+9 more)

### Community 38 - "wordpress-notifications-integration.php"
Cohesion: 0.22
Nodes (4): ms_houzez_get_notifications(), ms_add_notification_badge(), ms_get_unread_notification_count(), ms_get_user_notifications()

### Community 41 - "admin.js"
Cohesion: 0.19
Nodes (19): msAutoMapFields(), msClearTemplateForm(), msDeleteTemplate(), msDisplayPreview(), msEditTemplate(), msFillTemplateForm(), msInitDashboard(), msInitTemplates() (+11 more)

### Community 42 - "advanced-financial.php"
Cohesion: 0.36
Nodes (7): {closure#1}(), {closure#2}(), msfp_calculate_property_roi(), msfp_generate_monthly_report(), msfp_get_owner_financial_summary(), msfp_render_financial_analytics(), msfp_render_roi_dashboard()

### Community 43 - "ms_add_notification"
Cohesion: 0.10
Nodes (25): Fixed, Known behaviour worth deciding on, Mostaager Facility PRO — 18.12.0 (payment path tested end-to-end), Result of the end-to-end test, {closure#4}(), {closure#6}(), {closure#27}(), {closure#28}() (+17 more)

### Community 44 - "Mostager_Invoice_PDF"
Cohesion: 0.23
Nodes (4): mostager_ajax_email_invoice(), Mostager_Invoice_PDF, ms_ajax_download_invoice_pdf(), ms_user_can_access_invoice_pdf()

### Community 45 - "Mostaager_DB"
Cohesion: 0.10
Nodes (3): ms_rest_get_facilities(), ms_rest_get_invoices(), Mostaager_DB

### Community 48 - "inline-property-form.php"
Cohesion: 0.31
Nodes (11): ms_extract_first_meta_id(), {closure#3}(), ms_agent_has_active_subscription(), {closure#2}(), ms_inline_property_building_options(), ms_inline_property_can_access(), ms_inline_property_people_options(), ms_inline_user_is_agent() (+3 more)

### Community 50 - "دليل API — منصة مستأجر العقاري"
Cohesion: 0.09
Nodes (22): 1. الأساسيات, 2. شكل الاستجابة (ثابت لكل المسارات), 3. تسجيل الدخول, 4. قاعدة الصلاحيات, 5.1 المباني والوحدات, 5.2 الفواتير, 5.3 الصيانة, 5.4 الإشعارات (+14 more)

### Community 52 - "Update Report 2026 (v18.0.0)"
Cohesion: 0.14
Nodes (7): ms_admin_import_data_page(), ms_delete_demo_data(), ms_import_demo_data_from_xml(), Data Import Guide (XML demo data), demo-data.xml, Update Report 2026 (v18.0.0), IMPORT_GUIDE.md

### Community 55 - "houzez-wallet-integration.php"
Cohesion: 0.05
Nodes (39): Fixed, Mostaager Facility PRO — 18.11.0 (WooCommerce-only payments), Removed, Unchanged (already gateway-agnostic), Fatal with WP-Optimize Minify (dashboard pages), Mostaager Facility PRO — 18.13.0, Wallet ⇄ WooCommerce (new includes/wallet-woo-sync.php), Audit of every money-in path (+31 more)

### Community 56 - "User Role Audit Report"
Cohesion: 0.17
Nodes (11): Agent Dashboard, AJAX Endpoints, Building Manager Dashboard, Endpoint Role Permission Matrix, Admin Role, Agent Role, Building Manager Role, User Role Audit Report (+3 more)

### Community 57 - "MS_Houzez_Dashboard_Bridge"
Cohesion: 0.10
Nodes (4): {closure#2}(), {closure#1}(), MS_Houzez_Dashboard_Bridge, ms_in_houzez_shell()

### Community 60 - "MS_Firebase_FCM_Provider"
Cohesion: 0.13
Nodes (3): {closure#12}(), {closure#13}(), MS_Firebase_FCM_Provider

### Community 62 - "notification-preferences.php"
Cohesion: 0.19
Nodes (8): {closure#14}(), {closure#1}(), {closure#2}(), {closure#3}(), ms_get_notification_preferences(), ms_notification_preference_phone(), ms_save_notification_preferences(), msfp_send_tenant_rent_due_reminders()

### Community 64 - "Mostager_Facilities_API"
Cohesion: 0.33
Nodes (5): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), Mostager_Facilities_API

### Community 66 - ".file_exists"
Cohesion: 0.19
Nodes (5): {closure#1}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#9}()

### Community 67 - ".date"
Cohesion: 0.37
Nodes (12): ms_admin_get_legacy_meta_bool(), ms_admin_get_legacy_meta_float(), ms_admin_get_legacy_meta_int(), ms_admin_get_legacy_meta_value(), ms_admin_migrate_legacy_all(), ms_admin_migrate_legacy_buildings(), ms_admin_migrate_legacy_expenses(), ms_admin_migrate_legacy_invoices() (+4 more)

### Community 69 - "houzez-rest-api-integration.php"
Cohesion: 0.18
Nodes (5): REST API hardening (mostager/v1), ms_houzez_api_permission(), ms_houzez_can_access_building(), ms_houzez_get_maintenance_requests(), ms_houzez_get_units()

### Community 70 - "houzez-roles-integration.php"
Cohesion: 0.27
Nodes (10): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), ms_facility_roles(), ms_houzez_map_role(), ms_houzez_reverse_map_role(), ms_sync_user_roles() (+2 more)

### Community 71 - "ms_run_installer"
Cohesion: 0.20
Nodes (8): {closure#2}(), ms_create_tables(), ms_plugin_install(), ms_register_facility_roles(), {closure#1}(), ms_create_notifications_table(), ms_create_user_activities_table(), ms_run_installer()

### Community 72 - "houzez-reports-integration.php"
Cohesion: 0.22
Nodes (9): ms_houzez_ajax_export_report(), ms_houzez_ajax_get_report(), ms_houzez_get_collection_report(), ms_houzez_get_expenses_report(), ms_houzez_get_maintenance_report(), ms_houzez_get_occupancy_report(), ms_houzez_get_revenue_report(), ms_houzez_html_to_rows() (+1 more)

### Community 73 - "houzez-ui-integration.php"
Cohesion: 0.21
Nodes (4): hex2rgb(), ms_houzez_add_badge_styles(), ms_houzez_add_color_variables(), ms_houzez_add_form_styles()

### Community 74 - "user-experience.php"
Cohesion: 0.18
Nodes (4): {closure#1}(), {closure#2}(), {closure#3}(), msfp_render_amenities_booking()

### Community 75 - "Mobile Developer API Guide"
Cohesion: 0.31
Nodes (9): Mobile Developer API Guide, Facilities API (mostager/v1/facilities), Invoice Pay Endpoint (/invoices/{id}/pay), Maintenance Tickets API (mostager/v1/maintenance), REST namespace mfp/v1, REST namespace mostager/v2 (enhanced invoices, utility bills), Push Token Registration, Utility Bills API (create, distribute) (+1 more)

### Community 76 - "building-manager-metabox.php"
Cohesion: 0.08
Nodes (18): New: includes/ms-property-link.php, ms_render_building_manager_metabox(), ms_render_property_building_metabox(), ms_save_building_manager(), ms_save_property_building(), ms_sync_building_manager_to_db(), ms_houzez_get_property_building_id(), ms_houzez_get_property_unit_data() (+10 more)

### Community 77 - "houzez-invoice-integration.php"
Cohesion: 0.20
Nodes (4): ms_houzez_create_invoice(), ms_houzez_sync_invoice_to_post(), ms_sync_old_invoices(), ms_houzez_rest_create_invoice()

### Community 80 - "wordpress-comments-integration.php"
Cohesion: 0.20
Nodes (4): ms_houzez_get_discussions(), ms_get_discussion_comments(), ms_notify_new_discussion_comment(), ms_sync_old_discussions()

### Community 81 - "ms_user_can_access_building"
Cohesion: 0.17
Nodes (13): Mostaager Facility PRO — 18.9.0 (Phase 6: API authorization + contract), Object-level authorization in mfp/v1 (previously missing), REST meta, Unified response contract, Validation, {closure#42}(), {closure#43}(), {closure#44}() (+5 more)

### Community 83 - "init"
Cohesion: 0.31
Nodes (6): init(), fields(), renderStep(), saveLocal(), saveServer(), showStatus()

### Community 84 - ".get_users"
Cohesion: 0.16
Nodes (7): {closure#8}(), ms_admin_buildings_page(), ms_building_manager_js(), ms_render_agents_cell(), ms_render_manager_cell(), ms_render_houzez_property_building_metabox(), ms_save_property_building_link()

### Community 85 - "Mostaager Facility PRO Plugin (README)"
Cohesion: 0.29
Nodes (7): Automatic Backup System, External Integrations (CRM, Accounting, Payment), Mostaager Facility PRO Plugin (README), Real-time Monitoring and Analytics, Full RTL Arabic Support / Arabic Dashboard, Smart Automation and Scheduled Tasks, WP All Import Add-On Integration

### Community 86 - "{closure#2}"
Cohesion: 0.22
Nodes (5): {closure#2}(), ms_create_agent_subscription_invoice(), ms_create_rent_invoice_if_needed(), ms_get_rent_invoice_payment_link(), Invoice Types (property-rent, property-sale, agent-fees, building-*)

### Community 88 - "tenant-dashboard-enhancements.php"
Cohesion: 0.44
Nodes (8): {closure#1}(), msfp_tenant_deposit(), msfp_tenant_documents(), msfp_tenant_maintenance(), msfp_tenant_meters(), msfp_tenant_overview(), msfp_tenant_tab_html(), msfp_tenant_unit_id()

### Community 89 - "rent-dashboard-enhancements.js"
Cohesion: 0.50
Nodes (7): addQuickActions(), bindMaintenanceFilters(), bindPayments(), decorateDocsMeters(), init(), qs(), warning()

### Community 93 - "agent-dashboard-tabs.js"
Cohesion: 0.38
Nodes (3): getPanel(), load(), showNonDestructiveNotice()

### Community 94 - "building-dashboard-enhancements.js"
Cohesion: 0.57
Nodes (6): addDataWarning(), addQuickActions(), bindFacilityFilters(), markActive(), qs(), refresh()

### Community 95 - "Mostaager Facility PRO — 18.2.0 (UX P0)"
Cohesion: 0.29
Nodes (6): Building manager — invoice actions, Currency, Financial actions: busy state + no double submit, Mostaager Facility PRO — 18.2.0 (UX P0), Readable error messages, Security deposit

### Community 96 - "Mostaager Facility PRO — 18.1.0"
Cohesion: 0.33
Nodes (5): Mostaager Facility PRO — 18.1.0, Optional settings (wp_options), PDF, Removed (dead code), Security

### Community 97 - "ms_get_building_id_values_for_query"
Cohesion: 0.40
Nodes (5): {closure#17}(), {closure#18}(), ms_get_building_id_values_for_query(), ms_get_maintenance_collection_progress(), ms_get_maintenance_requests()

### Community 101 - "ms_get_tenant_unit"
Cohesion: 0.33
Nodes (6): ms_get_tenant_unit(), {closure#16}(), {closure#18}(), msfp_handle_add_meter_reading(), msfp_render_documents_center(), msfp_render_meter_readings()

### Community 104 - "ms_get_company_clause"
Cohesion: 0.17
Nodes (10): {closure#3}(), ms_get_company_clause(), MFP_Houzez_Search_Filter, msfp_add_work_order_event(), msfp_ajax_add_work_order(), msfp_ajax_update_work_order_status(), msfp_check_maintenance_collection(), msfp_current_user_can_manage_building() (+2 more)

### Community 105 - "Owner Role"
Cohesion: 0.40
Nodes (3): Owner Dashboard, [owner_dashboard_v4] shortcode, Owner Role

### Community 106 - "Mostaager Facility PRO — 18.3.0 (Brand identity + layout)"
Cohesion: 0.40
Nodes (4): Colors → design tokens, Layout (all four dashboards), Mostaager Facility PRO — 18.3.0 (Brand identity + layout), New assets

### Community 107 - "migration-script.php"
Cohesion: 0.47
Nodes (4): ms_create_migration_backup(), ms_migrate_tenant_link(), ms_migrate_unit_to_property(), ms_perform_migration()

### Community 109 - "monitoring.php"
Cohesion: 0.60
Nodes (4): formatBytes(), loadAnalytics(), loadRealTimeStats(), updateAnalyticsCharts()

### Community 110 - "Comprehensive Audit Report"
Cohesion: 0.08
Nodes (25): Custom ms_* Database Tables, Comprehensive Audit Report, ms_status_change_requests table, ms_wallet_restricted_amounts table, Tenant Dashboard, WhatsApp Integration, WooCommerce Payment Integration, {closure#47}() (+17 more)

### Community 111 - "owner-agent-dashboard-enhancements.js"
Cohesion: 0.70
Nodes (4): bindQuickActions(), decodeNotifications(), init(), qs()

### Community 112 - "Mostaager Facility PRO — 18.4.0 (Premium look + motion)"
Cohesion: 0.40
Nodes (4): Files, Look, Mostaager Facility PRO — 18.4.0 (Premium look + motion), Motion

### Community 114 - "analytics.php"
Cohesion: 0.60
Nodes (3): {closure#1}(), ms_allowed_analytics_events(), ms_track_analytics_event()

### Community 116 - "Mostaager Facility PRO — 18.6.0 (Houzez integration, phases 2–4)"
Cohesion: 0.40
Nodes (4): Mostaager Facility PRO — 18.6.0 (Houzez integration, phases 2–4), Phase 2 — Roles, Phase 3 — CRM, Phase 4 — Cleanup

### Community 117 - "agent-dashboard-tabs.php"
Cohesion: 0.26
Nodes (9): {closure#1}(), msfp_agent_render_analytics(), msfp_agent_render_discussions(), msfp_agent_render_invoices(), msfp_agent_render_maintenance(), msfp_agent_tab_html(), msfp_agent_unit_ids(), ms_render_unified_profile() (+1 more)

### Community 118 - "agent-properties-ui.js"
Cohesion: 0.83
Nodes (3): bindFilters(), getTarget(), load()

### Community 119 - "tenant-dashboard-enhancements.js"
Cohesion: 0.83
Nodes (3): bindMaintenanceFilters(), bindPayments(), initRentEnhancements()

### Community 120 - "msfp_get_agent_building_ids"
Cohesion: 0.50
Nodes (4): {closure#4}(), msfp_get_agent_building_ids(), msfp_get_agent_buildings(), msfp_get_agent_units()

### Community 122 - "Mostaager Facility PRO — 18.15.0"
Cohesion: 0.50
Nodes (3): Fixed: deep links to a dashboard section did nothing, Fixed: two 404s on every dashboard page, Mostaager Facility PRO — 18.15.0

### Community 123 - "Mostaager Facility PRO — 18.19.0"
Cohesion: 0.50
Nodes (3): Debug mode from the console, Mostaager Facility PRO — 18.19.0, Self-defence: the section stays open

### Community 131 - "{closure#1}"
Cohesion: 0.50
Nodes (4): {closure#1}(), ms_migrate_add_frozen_balance_column(), ms_migrate_create_security_deposits_table(), ms_migrate_create_facility_tables()

### Community 140 - "ms_render_action_center"
Cohesion: 0.83
Nodes (3): {closure#1}(), {closure#2}(), ms_render_action_center()

### Community 161 - "{closure#12}"
Cohesion: 0.67
Nodes (4): {closure#11}(), {closure#12}(), msfp_compute_owner_report(), msfp_valid_report_month()

## Ambiguous Edges - Review These
- `ms_create_status_change_request()` → `functions.php`  [AMBIGUOUS]
  USER_ROLE_AUDIT_REPORT.md · relation: conceptually_related_to
- `ms_create_status_change_request()` → `Claim: no duplicate functions`  [AMBIGUOUS]
  USER_ROLE_AUDIT_REPORT.md · relation: conceptually_related_to
- `ms_approve_status_change_request()` → `functions.php`  [AMBIGUOUS]
  USER_ROLE_AUDIT_REPORT.md · relation: conceptually_related_to
- `ms_reject_status_change_request()` → `functions.php`  [AMBIGUOUS]
  USER_ROLE_AUDIT_REPORT.md · relation: conceptually_related_to

## Knowledge Gaps
- **70 isolated node(s):** `msAutomationConfig`, `ms_ajax`, `msImportConfig`, `style`, `TCPDF_FONT_DATA` (+65 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 828 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **112 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **What is the exact relationship between `ms_create_status_change_request()` and `functions.php`?**
  _Edge tagged AMBIGUOUS (relation: conceptually_related_to) - confidence is low._
- **Why does `TCPDF_STATIC` connect `TCPDF_STATIC` to `TCPDF`, `.file_exists`, `.date`, `.writeHTML`, `MS_Performance_Optimizer`, `.setFont`, `._out`, `.startSVGElementHandler`, `.openHTMLTagHandler`, `TCPDF_FONTS`, `.Image`?**
  _High betweenness centrality (0.217) - this node is a cross-community bridge._
- **Are the 100 inferred relationships involving `TCPDF_STATIC` (e.g. with `.addTTFfont()` and `.getFontFullPath()`) actually correct?**
  _`TCPDF_STATIC` has 100 INFERRED edges - model-reasoned connections that need verification._
- **What connects `msAutomationConfig`, `ms_ajax`, `msImportConfig` to the rest of the system?**
  _70 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `TCPDF` be split into smaller, more focused modules?**
  _Cohesion score 0.016428820048729552 - nodes in this community are weakly interconnected._
- **What is the exact relationship between `ms_create_status_change_request()` and `Claim: no duplicate functions`?**
  _Edge tagged AMBIGUOUS (relation: conceptually_related_to) - confidence is low._
- **Why does `TCPDF` connect `TCPDF` to `TCPDF_STATIC`, `.writeHTML`, `.setFont`, `._out`, `MS_PDF_Document`, `.startSVGElementHandler`, `.openHTMLTagHandler`, `TCPDF_FONTS`, `.Image`?**
  _High betweenness centrality (0.112) - this node is a cross-community bridge._