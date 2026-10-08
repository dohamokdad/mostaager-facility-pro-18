# Graph Report - mostaager-facility-pro-18  (2026-10-08)

## Corpus Check
- 250 files · ~427,527 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 134 file(s) not represented in the graph (top: .z 84, (none) 29, .css 20)

## Summary
- 2735 nodes · 5278 edges · 237 communities (64 shown, 173 thin omitted)
- Extraction: 88% EXTRACTED · 12% INFERRED · 0% AMBIGUOUS · INFERRED: 654 edges (avg confidence: 0.85)
- Token cost: 173,203 input · 0 output

## Community Hubs (Navigation)
- TCPDF Core Class
- Manager Query Helpers
- TCPDF Static Utilities
- Status Change Approvals DB
- Advanced Analytics Engine
- Migrations and Rapid Addon
- Advanced Reports Module
- Core AJAX Handlers
- Admin Panel Pages
- QR Code Encoder
- TCPDF Image Handling
- Elementor Widgets
- 2D Barcodes (Datamatrix/PDF417)
- Documented Subsystems Overview
- Building Access Control
- 1D Barcodes
- Pro Platform Features
- Agent Dashboard Tabs
- Advanced Dashboard UI (JS)
- Monitoring and Alerts
- TCPDF Font Management
- Readme Group 25
- Database Group 26
- Interactions Advanced Group 27
- Templates Group 28
- Integrations Group 29
- Composer Group 30
- Tcpdf Group 31
- Advanced Integrations Group 32
- Automation Group 33
- Utility Bills Api Group 34
- Whatsapp Integration Group 35
- Dashboard Group 36
- Performance Group 37
- Houzez User Dashboard Integration Group 38
- Notifications Group 39
- User Management Group 40
- Admin Group 41
- Dashboard Charts Group 42
- Functions Group 43
- Invoice Pdf Group 44
- Api Group 45
- Restapi Group 46
- Houzez Integration Group 47
- Inline Property Form Group 48
- Backup Group 49
- Smart Automation Group 50
- Dashboard Group 51
- Admin Canonical Records Group 52
- Advanced Settings Group 53
- Maintenance Api Group 54
- Houzez Wallet Integration Group 55
- User Role Audit Report Group 56
- Houzez Building Integration Group 57
- Tcpdf Filters Group 59
- Firebase Fcm Group 60
- Unified Settings Group 61
- Notification Preferences Group 62
- Data Sync Group 63
- Facilities Api Group 64
- Bootstrap Group 66
- Admin Group 67
- Custom Fields Group 68
- Houzez Rest Api Integration Group 69
- Houzez Roles Integration Group 70
- Mostaager Facility Pro Group 71
- Houzez Reports Integration Group 72
- Houzez Ui Integration Group 73
- User Experience Group 74
- Mobile Api Documentation Group 75
- Building Manager Metabox Group 76
- Houzez Invoice Integration Group 77
- Wordpress Comments Integration Group 80
- Houzez Property Building Metabox Group 81
- Houzez Mostaager Adapter Group 82
- Inline Property Group 83
- Admin Group 84
- Readme Group 85
- Agent Subscription Group 86
- Houzez Maintenance Cpt Group 87
- Tenant Dashboard Enhancements Group 88
- Rent Dashboard Enhancements Group 89
- Telr Integration Group 90
- Building Dashboard Group 92
- Agent Dashboard Tabs Group 93
- Building Dashboard Enhancements Group 94
- Admin Group 95
- Property Sync Group 96
- Houzez Integration Fields Group 97
- Houzez Notifications Group 100
- Pro Platform Group 101
- Houzez Map Status Group 102
- Houzez Notifications Group 103
- Houzez Search Filter Group 104
- Lead Converter Group 105
- Houzez Sync Group 106
- Migration Script Group 107
- Monitoring Group 109
- Functions Group 110
- Owner Agent Dashboard Enhancements Group 111
- Ajax Group 112
- Customposttypes Group 113
- Analytics Group 114
- Houzez Ratings Group 115
- Houzez Wallet Integration Group 116
- Unified Profile Group 117
- Agent Properties Ui Group 118
- Tenant Dashboard Enhancements Group 119
- Pro Platform Group 120
- Cli Group 121
- Houzez Lead Convert Group 122
- Houzez Lead To Tenant Group 123
- Landing Desktop Hover Scroll Group 125
- Activator Group 127
- Automation Advanced Group 128
- Dashboard Advanced Group 129
- Import Wizard Advanced Group 130
- Reports Advanced Group 131
- Restapi Group 136
- Tcpdf Font Data Group 138

## God Nodes (most connected - your core abstractions)
1. `TCPDF` - 427 edges
2. `TCPDF_STATIC` - 160 edges
3. `QRcode` - 100 edges
4. `MS_Advanced_Analytics` - 69 edges
5. `MS_Advanced_Reports` - 54 edges
6. `TCPDF_FONTS` - 52 edges
7. `RapidAddon` - 50 edges
8. `Mostager_WhatsApp_Integration` - 37 edges
9. `TCPDFBarcode` - 37 edges
10. `MS_Advanced_Automation` - 35 edges

## Surprising Connections (you probably didn't know these)
- `Facilities API (mostager/v1/facilities)` --implements--> `Mostager_Facilities_API`  [INFERRED]
  MOBILE_API_DOCUMENTATION.md → includes/class-facilities-api.php
- `Maintenance Tickets API (mostager/v1/maintenance)` --implements--> `MS_Maintenance_API`  [INFERRED]
  MOBILE_API_DOCUMENTATION.md → includes/class-maintenance-api.php
- `WhatsApp Integration` --references--> `Mostager_WhatsApp_Integration`  [INFERRED]
  COMPREHENSIVE_AUDIT_REPORT.md → includes/class-whatsapp-integration.php
- `Claim: no duplicate functions` --conceptually_related_to--> `ms_create_status_change_request()`  [AMBIGUOUS]
  USER_ROLE_AUDIT_REPORT.md → core/database.php
- `Facility Statuses (working, under_maintenance, critical)` --references--> `Mostager_Facilities_API`  [EXTRACTED]
  USER_ROLE_AUDIT_REPORT.md → includes/class-facilities-api.php

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Security deposit reserve/freeze/release flow** — comprehensive_audit_report_security_deposit_system, includes_functions_ms_reserve_security_deposit, core_database_ms_create_security_deposit, core_database_ms_freeze_wallet_balance, core_database_ms_release_security_deposit, comprehensive_audit_report_ms_wallet_restricted_amounts_table [EXTRACTED 1.00]
- **Agent approval of owner status change requests** — comprehensive_audit_report_agent_approval_system, core_database_ms_create_status_change_request, core_database_ms_approve_status_change_request, core_database_ms_reject_status_change_request, includes_functions_ms_get_agent_status_change_requests, comprehensive_audit_report_ms_status_change_requests_table [EXTRACTED 1.00]
- **Agent subscription expiry and restore lifecycle** — comprehensive_audit_report_agent_subscription_system, includes_functions_ms_check_agent_subscription_limits, includes_functions_ms_convert_agent_properties_to_draft, includes_functions_ms_restore_agent_properties_after_renewal, comprehensive_audit_report_cron_jobs [INFERRED 0.85]
- **DejaVu font coverage reports** — vendor_tecnickcom_tcpdf_fonts_dejavu_fonts_ttf_2_33_langcover_dejavu_language_coverage, vendor_tecnickcom_tcpdf_fonts_dejavu_fonts_ttf_2_33_unicover_dejavu_unicode_coverage, vendor_tecnickcom_tcpdf_fonts_dejavu_fonts_ttf_2_34_langcover_dejavu_language_coverage, vendor_tecnickcom_tcpdf_fonts_dejavu_fonts_ttf_2_34_unicover_dejavu_unicode_coverage [INFERRED 0.95]
- **Fonts converted via tcpdf_addfont** — vendor_tecnickcom_tcpdf_tools_convert_fonts_examples_dejavu_fonts, vendor_tecnickcom_tcpdf_tools_convert_fonts_examples_freefont, vendor_tecnickcom_tcpdf_tools_convert_fonts_examples_arabic_fonts, vendor_tecnickcom_tcpdf_tools_convert_fonts_examples_cid0_cjk_fonts, vendor_tecnickcom_tcpdf_tools_convert_fonts_examples_type1_pdfa_core_fonts [EXTRACTED 1.00]

## Communities (237 total, 173 thin omitted)

### Community 1 - "Manager Query Helpers"
Cohesion: 0.04
Nodes (69): {closure#9}(), ms_get_active_maintenance_by_manager(), ms_get_paid_unpaid_units_by_manager(), ms_get_property_contacts(), {closure#1}(), {closure#2}(), {closure#8}(), ms_build_telr_payload() (+61 more)

### Community 4 - "Status Change Approvals DB"
Cohesion: 0.06
Nodes (56): {closure#1}(), {closure#1}(), Custom ms_* Database Tables, ms_status_change_requests table, ms_wallet_restricted_amounts table, {closure#11}(), {closure#37}(), {closure#38}() (+48 more)

### Community 8 - "Core AJAX Handlers"
Cohesion: 0.06
Nodes (34): {closure#16}(), {closure#24}(), {closure#25}(), {closure#26}(), {closure#29}(), {closure#32}(), {closure#33}(), {closure#34}() (+26 more)

### Community 11 - "Admin Panel Pages"
Cohesion: 0.06
Nodes (15): ms_admin_canonical_discussions(), ms_admin_canonical_invoices(), ms_admin_canonical_maintenance(), ms_admin_canonical_records_page(), ms_admin_canonical_transfers(), ms_admin_get_building_label(), ms_admin_ms_invoices_page(), ms_admin_ms_transfers_page() (+7 more)

### Community 14 - "Elementor Widgets"
Cohesion: 0.06
Nodes (5): Mostaager_Agent_Dashboard_Widget, Mostaager_Building_Dashboard_Widget, Mostaager_Owner_Dashboard_Widget, Mostaager_Tenant_Dashboard_Widget, ms_register_mostaager_elementor_widget()

### Community 15 - "2D Barcodes (Datamatrix/PDF417)"
Cohesion: 0.09
Nodes (3): Datamatrix, PDF417, TCPDF2DBarcode

### Community 16 - "Documented Subsystems Overview"
Cohesion: 0.09
Nodes (24): AJAX Endpoints, Scheduled Cron Jobs, Comprehensive Audit Report, ms_contract Post Type (Contracts), WhatsApp Integration, {closure#40}(), ms_process_agent_subscription_expiry(), Comprehensive Documentation (DOCUMENTATION.md) (+16 more)

### Community 17 - "Building Access Control"
Cohesion: 0.07
Nodes (25): {closure#17}(), {closure#18}(), {closure#20}(), {closure#22}(), {closure#30}(), ms_current_user_manages_building(), ms_add_maintenance_timeline_entry(), ms_get_building_id_values_for_query() (+17 more)

### Community 19 - "Pro Platform Features"
Cohesion: 0.09
Nodes (26): {closure#1}(), {closure#2}(), {closure#3}(), {closure#5}(), {closure#8}(), msfp_add_missing_column(), msfp_add_work_order_event(), msfp_ajax_add_expense() (+18 more)

### Community 20 - "Agent Dashboard Tabs"
Cohesion: 0.10
Nodes (32): {closure#1}(), {closure#2}(), {closure#21}(), {closure#3}(), {closure#7}(), {closure#8}(), ms_get_building_id_for_agent(), ms_get_properties_by_agent() (+24 more)

### Community 25 - "Readme Group 25"
Cohesion: 0.09
Nodes (29): Embedded files support (Factur-X / ZUGFeRD), Font subsetting head checksum fix, PHP 8.5 deprecation fixes, TCPDF Changelog, TCPDF 6.11.3 (2026-04-21) deprecation notice release, DejaVu 2.33 font language coverage, DejaVu 2.33 Unicode block coverage, Arabic coverage (Sans and Sans Mono only, not Serif) (+21 more)

### Community 26 - "Database Group 26"
Cohesion: 0.10
Nodes (24): {closure#1}(), ms_admin_get_legacy_invoice_user_id(), ms_admin_tenants_page(), {closure#15}(), {closure#5}(), ms_get_dashboard_template_path(), ms_load_dashboard_sidebar(), ms_get_building_wallet_transactions() (+16 more)

### Community 28 - "Templates Group 28"
Cohesion: 0.11
Nodes (3): MS_Automation_Importer, MS_Templates_Manager, MS_Data_Validator

### Community 30 - "Composer Group 30"
Cohesion: 0.07
Nodes (27): archive, exclude, authors, autoload, classmap, config, sort-packages, description (+19 more)

### Community 34 - "Utility Bills Api Group 34"
Cohesion: 0.13
Nodes (3): RestApiV2, {closure#1}(), Mostager_Utility_Bills_API

### Community 35 - "Whatsapp Integration Group 35"
Cohesion: 0.17
Nodes (6): mostager_ajax_test_whatsapp(), mostager_auto_whatsapp_invoice(), mostager_auto_whatsapp_invoice_paid(), mostager_auto_whatsapp_maintenance(), Mostager_WhatsApp_Integration, mostager_whatsapp_settings_page()

### Community 36 - "Dashboard Group 36"
Cohesion: 0.13
Nodes (17): fetchMaintenanceRequests(), getAjax(), getCurrentBuildingId(), initMostaagerDashboardTabs(), activateTab(), activateTabByHash(), clearTabState(), getInitialTabFromUrl() (+9 more)

### Community 38 - "Houzez User Dashboard Integration Group 38"
Cohesion: 0.10
Nodes (6): ms_houzez_get_notifications(), ms_houzez_add_user_dashboard_menu(), ms_houzez_user_notifications_tab(), ms_add_notification_badge(), ms_get_unread_notification_count(), ms_get_user_notifications()

### Community 41 - "Admin Group 41"
Cohesion: 0.19
Nodes (19): msAutoMapFields(), msClearTemplateForm(), msDeleteTemplate(), msDisplayPreview(), msEditTemplate(), msFillTemplateForm(), msInitDashboard(), msInitTemplates() (+11 more)

### Community 42 - "Dashboard Charts Group 42"
Cohesion: 0.18
Nodes (8): {closure#1}(), {closure#2}(), msfp_calculate_property_roi(), msfp_generate_monthly_report(), msfp_get_owner_financial_summary(), msfp_render_financial_analytics(), msfp_render_roi_dashboard(), Mostager_Dashboard_Charts

### Community 43 - "Functions Group 43"
Cohesion: 0.15
Nodes (15): WooCommerce Payment Integration, {closure#4}(), {closure#6}(), {closure#27}(), {closure#28}(), {closure#1}(), ms_activate_agent_subscription_from_invoice(), ms_process_mostaager_order_status() (+7 more)

### Community 44 - "Invoice Pdf Group 44"
Cohesion: 0.15
Nodes (4): mostager_ajax_email_invoice(), mostager_ajax_generate_pdf(), Mostager_Invoice_PDF, MS_Reports_Engine

### Community 45 - "Api Group 45"
Cohesion: 0.11
Nodes (3): ms_rest_get_facilities(), ms_rest_get_invoices(), Mostaager_DB

### Community 48 - "Inline Property Form Group 48"
Cohesion: 0.18
Nodes (14): ms_extract_first_meta_id(), {closure#1}(), {closure#2}(), {closure#3}(), ready(), ms_agent_has_active_subscription(), {closure#2}(), ms_inline_property_building_options() (+6 more)

### Community 50 - "Smart Automation Group 50"
Cohesion: 0.18
Nodes (13): msfp_apply_late_payment_penalties(), msfp_calculate_penalty(), msfp_log_automation_event(), msfp_render_automation_logs(), msfp_render_automation_settings(), msfp_send_maintenance_followup_email(), msfp_send_maintenance_followup_whatsapp(), msfp_send_maintenance_followups() (+5 more)

### Community 52 - "Admin Canonical Records Group 52"
Cohesion: 0.14
Nodes (7): ms_admin_import_data_page(), ms_delete_demo_data(), ms_import_demo_data_from_xml(), Data Import Guide (XML demo data), demo-data.xml, Update Report 2026 (v18.0.0), IMPORT_GUIDE.md

### Community 55 - "Houzez Wallet Integration Group 55"
Cohesion: 0.23
Nodes (13): ms_houzez_get_wallet(), ms_houzez_user_wallet_tab(), ms_add_to_wallet(), ms_deduct_from_wallet(), ms_get_wallet_balance(), ms_get_wallet_transactions(), ms_process_wallet_topup_after_payment(), ms_record_wallet_transaction() (+5 more)

### Community 56 - "User Role Audit Report Group 56"
Cohesion: 0.17
Nodes (11): Agent Dashboard, Owner Dashboard, Endpoint Role Permission Matrix, Admin Role, Agent Role, Building Manager Role, User Role Audit Report, Facility Statuses (working, under_maintenance, critical) (+3 more)

### Community 60 - "Firebase Fcm Group 60"
Cohesion: 0.13
Nodes (3): {closure#12}(), {closure#13}(), MS_Firebase_FCM_Provider

### Community 62 - "Notification Preferences Group 62"
Cohesion: 0.19
Nodes (8): {closure#14}(), {closure#1}(), {closure#2}(), {closure#3}(), ms_get_notification_preferences(), ms_notification_preference_phone(), ms_save_notification_preferences(), msfp_send_tenant_rent_due_reminders()

### Community 64 - "Facilities Api Group 64"
Cohesion: 0.32
Nodes (6): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), Mostager_Facilities_API, ms_get_company_clause()

### Community 66 - "Bootstrap Group 66"
Cohesion: 0.18
Nodes (5): {closure#1}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}()

### Community 67 - "Admin Group 67"
Cohesion: 0.38
Nodes (13): ms_admin_get_legacy_meta_bool(), ms_admin_get_legacy_meta_float(), ms_admin_get_legacy_meta_int(), ms_admin_get_legacy_meta_value(), ms_admin_migrate_legacy_all(), ms_admin_migrate_legacy_buildings(), ms_admin_migrate_legacy_expenses(), ms_admin_migrate_legacy_invoices() (+5 more)

### Community 69 - "Houzez Rest Api Integration Group 69"
Cohesion: 0.19
Nodes (4): ms_houzez_can_access_building(), ms_houzez_get_discussions(), ms_houzez_get_maintenance_requests(), ms_houzez_get_units()

### Community 70 - "Houzez Roles Integration Group 70"
Cohesion: 0.22
Nodes (9): ms_houzez_filter_agent_properties(), ms_houzez_filter_manager_buildings(), ms_houzez_filter_owner_properties(), ms_houzez_get_agent_properties(), ms_houzez_get_manager_buildings(), ms_houzez_get_owner_properties(), ms_houzez_map_role(), ms_user_has_houzez_role() (+1 more)

### Community 71 - "Mostaager Facility Pro Group 71"
Cohesion: 0.20
Nodes (8): {closure#2}(), ms_create_tables(), ms_plugin_install(), ms_register_facility_roles(), {closure#1}(), ms_create_notifications_table(), ms_create_user_activities_table(), ms_run_installer()

### Community 72 - "Houzez Reports Integration Group 72"
Cohesion: 0.24
Nodes (7): ms_houzez_ajax_export_report(), ms_houzez_ajax_get_report(), ms_houzez_get_collection_report(), ms_houzez_get_expenses_report(), ms_houzez_get_maintenance_report(), ms_houzez_get_occupancy_report(), ms_houzez_get_revenue_report()

### Community 73 - "Houzez Ui Integration Group 73"
Cohesion: 0.21
Nodes (4): hex2rgb(), ms_houzez_add_badge_styles(), ms_houzez_add_color_variables(), ms_houzez_add_form_styles()

### Community 75 - "Mobile Api Documentation Group 75"
Cohesion: 0.27
Nodes (9): Mobile Developer API Guide, Facilities API (mostager/v1/facilities), Invoice Pay Endpoint (/invoices/{id}/pay), Maintenance Tickets API (mostager/v1/maintenance), REST namespace mfp/v1, REST namespace mostager/v2 (enhanced invoices, utility bills), Push Token Registration, Utility Bills API (create, distribute) (+1 more)

### Community 76 - "Building Manager Metabox Group 76"
Cohesion: 0.20
Nodes (3): ms_render_building_manager_metabox(), ms_save_building_manager(), ms_sync_building_manager_to_db()

### Community 77 - "Houzez Invoice Integration Group 77"
Cohesion: 0.20
Nodes (3): ms_houzez_create_invoice(), ms_sync_old_invoices(), ms_houzez_rest_create_invoice()

### Community 80 - "Wordpress Comments Integration Group 80"
Cohesion: 0.22
Nodes (3): ms_get_discussion_comments(), ms_notify_new_discussion_comment(), ms_sync_old_discussions()

### Community 81 - "Houzez Property Building Metabox Group 81"
Cohesion: 0.20
Nodes (7): ms_approve_status_change_request(), ms_create_status_change_request(), ms_reject_status_change_request(), ms_render_houzez_property_building_metabox(), ms_save_property_building_link(), msfp_admin_maintenance_withdrawals_page(), ms_add_notification()

### Community 83 - "Inline Property Group 83"
Cohesion: 0.31
Nodes (6): init(), fields(), renderStep(), saveLocal(), saveServer(), showStatus()

### Community 84 - "Admin Group 84"
Cohesion: 0.28
Nodes (5): {closure#8}(), ms_admin_buildings_page(), ms_building_manager_js(), ms_render_agents_cell(), ms_render_manager_cell()

### Community 85 - "Readme Group 85"
Cohesion: 0.22
Nodes (9): Houzez Property to Unit Sync, Automatic Backup System, External Integrations (CRM, Accounting, Payment), Houzez Theme, Mostaager Facility PRO Plugin (README), Real-time Monitoring and Analytics, Full RTL Arabic Support / Arabic Dashboard, Smart Automation and Scheduled Tasks (+1 more)

### Community 86 - "Agent Subscription Group 86"
Cohesion: 0.22
Nodes (5): {closure#2}(), ms_create_agent_subscription_invoice(), ms_create_rent_invoice_if_needed(), ms_get_rent_invoice_payment_link(), Invoice Types (property-rent, property-sale, agent-fees, building-*)

### Community 88 - "Tenant Dashboard Enhancements Group 88"
Cohesion: 0.44
Nodes (8): {closure#1}(), msfp_tenant_deposit(), msfp_tenant_documents(), msfp_tenant_maintenance(), msfp_tenant_meters(), msfp_tenant_overview(), msfp_tenant_tab_html(), msfp_tenant_unit_id()

### Community 89 - "Rent Dashboard Enhancements Group 89"
Cohesion: 0.50
Nodes (7): addQuickActions(), bindMaintenanceFilters(), bindPayments(), decorateDocsMeters(), init(), qs(), warning()

### Community 93 - "Agent Dashboard Tabs Group 93"
Cohesion: 0.38
Nodes (3): getPanel(), load(), showNonDestructiveNotice()

### Community 94 - "Building Dashboard Enhancements Group 94"
Cohesion: 0.57
Nodes (6): addDataWarning(), addQuickActions(), bindFacilityFilters(), markActive(), qs(), refresh()

### Community 95 - "Admin Group 95"
Cohesion: 0.33
Nodes (7): ms_admin_ensure_ms_units_table(), ms_generate_units_from_properties(), ms_get_property_posts_with_building_id(), ms_get_property_sync_candidates(), ms_get_total_units_count(), ms_sync_units_to_houzez_properties(), ms_units_table_exists()

### Community 97 - "Houzez Integration Fields Group 97"
Cohesion: 0.38
Nodes (3): ms_houzez_get_property_building_id(), ms_houzez_get_property_unit_data(), ms_houzez_sync_property_to_unit()

### Community 100 - "Houzez Notifications Group 100"
Cohesion: 0.33
Nodes (5): {closure#10}(), ms_mark_notifications_read(), ms_ajax_get_houzez_notifications(), ms_ajax_mark_houzez_notifications_read(), ms_enqueue_houzez_bell_assets()

### Community 101 - "Pro Platform Group 101"
Cohesion: 0.33
Nodes (6): ms_get_tenant_unit(), {closure#10}(), {closure#12}(), msfp_handle_add_meter_reading(), msfp_render_documents_center(), msfp_render_meter_readings()

### Community 107 - "Migration Script Group 107"
Cohesion: 0.47
Nodes (4): ms_create_migration_backup(), ms_migrate_tenant_link(), ms_migrate_unit_to_property(), ms_perform_migration()

### Community 109 - "Monitoring Group 109"
Cohesion: 0.60
Nodes (4): formatBytes(), loadAnalytics(), loadRealTimeStats(), updateAnalyticsCharts()

### Community 110 - "Functions Group 110"
Cohesion: 0.60
Nodes (3): Tenant Dashboard, ms_get_tenant_building_info(), ms_get_tenant_contracts()

### Community 111 - "Owner Agent Dashboard Enhancements Group 111"
Cohesion: 0.70
Nodes (4): bindQuickActions(), decodeNotifications(), init(), qs()

### Community 112 - "Ajax Group 112"
Cohesion: 0.40
Nodes (5): {closure#23}(), ms_get_building_wallet(), mostaager_get_wallet(), msfp_request_wallet_transfer(), {closure#4}()

### Community 114 - "Analytics Group 114"
Cohesion: 0.60
Nodes (3): {closure#1}(), ms_allowed_analytics_events(), ms_track_analytics_event()

### Community 118 - "Agent Properties Ui Group 118"
Cohesion: 0.83
Nodes (3): bindFilters(), getTarget(), load()

### Community 119 - "Tenant Dashboard Enhancements Group 119"
Cohesion: 0.83
Nodes (3): bindMaintenanceFilters(), bindPayments(), initRentEnhancements()

### Community 120 - "Pro Platform Group 120"
Cohesion: 0.50
Nodes (4): {closure#4}(), msfp_get_agent_building_ids(), msfp_get_agent_buildings(), msfp_get_agent_units()

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
- **46 isolated node(s):** `msAutomationConfig`, `ms_ajax`, `msImportConfig`, `reportData`, `style` (+41 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 825 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **173 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **What is the exact relationship between `ms_create_status_change_request()` and `functions.php`?**
  _Edge tagged AMBIGUOUS (relation: conceptually_related_to) - confidence is low._
- **Why does `TCPDF_STATIC` connect `TCPDF Static Utilities` to `TCPDF Core Class`, `Bootstrap Group 66`, `TCPDF Links and TOC`, `Performance Group 37`, `TCPDF Page Output`, `TCPDF Document Serialization`, `TCPDF Image Handling`, `TCPDF Forms and Annotations`, `TCPDF Font Management`, `Tcpdf Group 31`?**
  _High betweenness centrality (0.235) - this node is a cross-community bridge._
- **Are the 100 inferred relationships involving `TCPDF_STATIC` (e.g. with `.addTTFfont()` and `.getFontFullPath()`) actually correct?**
  _`TCPDF_STATIC` has 100 INFERRED edges - model-reasoned connections that need verification._
- **What connects `msAutomationConfig`, `ms_ajax`, `msImportConfig` to the rest of the system?**
  _46 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `TCPDF Core Class` be split into smaller, more focused modules?**
  _Cohesion score 0.01751452909800175 - nodes in this community are weakly interconnected._
- **What is the exact relationship between `ms_create_status_change_request()` and `Claim: no duplicate functions`?**
  _Edge tagged AMBIGUOUS (relation: conceptually_related_to) - confidence is low._
- **Why does `{closure#1}()` connect `Database Group 26` to `Facilities Api Group 64`, `Manager Query Helpers`, `Bootstrap Group 66`, `Status Change Approvals DB`, `User Management Group 40`, `Admin Panel Pages`, `Ajax Group 112`, `Building Access Control`, `Pro Platform Features`, `Building Dashboard Group 92`?**
  _High betweenness centrality (0.142) - this node is a cross-community bridge._