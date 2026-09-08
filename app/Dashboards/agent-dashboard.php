<?php
if (!defined('ABSPATH')) exit;

add_shortcode('agent_dashboard_v4', function () {
    if (!is_user_logged_in()) {
        ob_start();
        ?>
        <div style="max-width:400px;margin:50px auto;padding:40px;background:#fff;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,0.1);">
            <h2 style="text-align:center;margin-bottom:30px;color:#0f172a;">تسجيل الدخول</h2>
            <?php
            $args = array(
                'echo' => true,
                'redirect' => (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
                'form_id' => 'ms-loginform',
                'label_username' => 'البريد الإلكتروني',
                'label_password' => 'كلمة المرور',
                'label_remember' => 'تذكرني',
                'label_log_in' => 'تسجيل الدخول',
                'id_username' => 'user_login',
                'id_password' => 'user_pass',
                'id_remember' => 'rememberme',
                'id_submit' => 'wp-submit',
                'remember' => true,
                'value_username' => '',
                'value_remember' => false,
            );
            wp_login_form($args);
            ?>
            <p style="text-align:center;margin-top:20px;">
                <a href="<?php echo wp_lostpassword_url(); ?>" style="color:#2563eb;text-decoration:none;">نسيت كلمة المرور؟</a>
            </p>
        </div>
        <?php
        return ob_get_clean();
    }

    $user = wp_get_current_user();
    
    // Check permissions: only site admin or agent can access
    if (!current_user_can('manage_options') && !function_exists('ms_user_has_role')) {
        return '<p>غير مصرح لك بالوصول إلى هذه الصفحة.</p>';
    }
    
    if (!current_user_can('manage_options') && !ms_user_has_role($user->ID, 'agent')) {
        return '<p>غير مصرح لك بالوصول إلى هذه الصفحة. هذه الصفحة مخصصة للوسطاء فقط.</p>';
    }

    ob_start();

    // Agent listings source chain: custom tables first, then Houzez property posts.
    $listings = function_exists('ms_get_properties_by_agent') ? ms_get_properties_by_agent($user->ID, 100) : [];
    if (empty($listings) && function_exists('ms_get_properties_by_owner')) {
        // Fallback: sometimes agent_id is not mapped, but owner_id is.
        $listings = ms_get_properties_by_owner($user->ID);
    }

    $houzez_submit_link = '';
    $houzez_messages_link = '';
    $houzez_properties_link = '';
    if (function_exists('houzez_get_template_link_2')) {
        $houzez_submit_link = houzez_get_template_link_2('template/user_dashboard_submit.php');
        $houzez_messages_link = houzez_get_template_link_2('template/user_dashboard_messages.php');
        $houzez_properties_link = houzez_get_template_link_2('template/user_dashboard_properties.php');
    } elseif (function_exists('houzez_get_template_link')) {
        $houzez_submit_link = houzez_get_template_link('template/user_dashboard_submit.php');
        $houzez_messages_link = houzez_get_template_link('template/user_dashboard_messages.php');
        $houzez_properties_link = houzez_get_template_link('template/user_dashboard_properties.php');
    }

    // Ensure the submit link never resolves to a broken '#': fallback chain
    $houzez_submit_final = $houzez_submit_link;
    if (empty($houzez_submit_final)) {
        $submit_page = get_page_by_path('submit-property');
        if ($submit_page && !empty($submit_page->ID)) {
            $houzez_submit_final = get_permalink($submit_page->ID);
        } else {
            $houzez_submit_final = home_url('/submit-property/');
        }
    }

    if (empty($listings)) {
        $author_listings = get_posts(array(
            'post_type' => 'property',
            'post_status' => array('publish', 'pending', 'draft', 'expired', 'houzez_sold', 'disapproved', 'on_hold', 'private', 'future'),
            'posts_per_page' => 100,
            'author' => $user->ID,
            'fields' => 'all',
        ));

        $agent_meta_listings = get_posts(array(
            'post_type' => 'property',
            'post_status' => array('publish', 'pending', 'draft', 'expired', 'houzez_sold', 'disapproved', 'on_hold', 'private', 'future'),
            'posts_per_page' => 100,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'fave_agents',
                    'value' => '"' . $user->ID . '"',
                    'compare' => 'LIKE',
                ),
                array(
                    'key' => 'fave_property_agency',
                    'value' => '"' . $user->ID . '"',
                    'compare' => 'LIKE',
                ),
            ),
            'fields' => 'all',
        ));

        if (!empty($author_listings) || !empty($agent_meta_listings)) {
            $listing_ids = array();
            $merged = array();
            foreach (array_merge($author_listings, $agent_meta_listings) as $post) {
                if (!in_array($post->ID, $listing_ids, true)) {
                    $listing_ids[] = $post->ID;
                    $merged[] = $post;
                }
            }
            $listings = $merged;
        }
    }

    $listings_count = is_array($listings) ? count($listings) : 0;
    $agent_maintenance_count = function_exists('ms_get_agent_open_maintenance_requests_count') ? ms_get_agent_open_maintenance_requests_count($user->ID) : 0;
    $agent_maintenance_requests = function_exists('ms_get_agent_maintenance_requests') ? ms_get_agent_maintenance_requests($user->ID, 20) : array();
    // Agents pay subscription/plan invoices only; property rent and building
    // maintenance invoices belong to the owner or building manager.
    $agent_invoices = function_exists('ms_get_agent_invoices') ? ms_get_agent_invoices($user->ID, 200, 'subscription') : array();
    
    // Group invoices by category
    $invoice_groups = [
        'property' => [], // property-rent, property-monthly, property-sale, agent-fees
        'building' => [], // building-maintenance, building-facilities, building-utilities
    ];
    foreach ($agent_invoices as $invoice) {
        $category = isset($invoice->invoice_category) ? $invoice->invoice_category : 'agent-fees';
        if (in_array($category, ['property-rent', 'property-monthly', 'property-sale', 'agent-fees'])) {
            $invoice_groups['property'][] = $invoice;
        } else {
            $invoice_groups['building'][] = $invoice;
        }
    }
    
    $agent_due_total = function_exists('ms_get_agent_invoice_total_due') ? ms_get_agent_invoice_total_due($user->ID, 'subscription') : 0;

    ?>

    <div class="ms-dashboard">

        <?php
        $unread_notifications_count = function_exists('ms_get_unread_notifications_count') ? ms_get_unread_notifications_count($user->ID) : 0;
        $notifications = function_exists('ms_get_notifications_by_user') ? ms_get_notifications_by_user($user->ID, 20) : array();

        $ms_dashboard_menu_items = array(
            array('label' => 'نظرة عامة', 'data_tab' => 'overview', 'icon' => '🏢'),
            array('label' => 'إضافة عقار', 'data_tab' => 'add-property', 'icon' => '✍️'),
            array('label' => 'العقارات', 'data_tab' => 'listings', 'icon' => '🏠', 'badge' => intval($listings_count)),
            array('label' => 'الاشتراكات', 'data_tab' => 'subscriptions', 'icon' => '📦'),
            array('label' => 'الصيانة', 'data_tab' => 'maintenance', 'icon' => '🛠️', 'badge' => intval($agent_maintenance_count)),
            array('label' => 'الفواتير', 'data_tab' => 'invoices', 'icon' => '📄', 'badge' => intval(count($agent_invoices))),
            array('label' => 'المناقشات', 'data_tab' => 'discussions', 'icon' => '💬'),
            array('label' => 'الإحصائيات', 'data_tab' => 'analytics', 'icon' => '📊'),
            array('label' => 'البروفايل', 'data_tab' => 'profile', 'icon' => '👤'),
            array('href' => wp_logout_url(), 'label' => 'تسجيل الخروج', 'external' => true, 'icon' => '🚪'),
        );
        ms_load_dashboard_sidebar($ms_dashboard_menu_items);
        ?>
                <main class="ms-content">

            <div class="ms-agent-topbar">
                <div>
                    <p class="ms-eyebrow">مركز أعمال الوسيط</p>
                    <h1>أدر قوائمك وتابع فرصك اليومية</h1>
                    <p class="ms-page-subtitle">ابدأ بإجراء واضح: أضف عقارًا، تابع طلب صيانة، أو راجع المستحقات.</p>
                </div>
                <div class="ms-dashboard-actions" role="group" aria-label="إجراءات سريعة">
                    <button type="button" class="ms-action-button ms-action-button-primary" data-tab-target="add-property">إضافة عقار</button>
                    <button type="button" class="ms-action-button" data-tab-target="maintenance">متابعة الصيانة<?php echo $agent_maintenance_count ? ' (' . intval($agent_maintenance_count) . ')' : ''; ?></button>
                    <button type="button" class="ms-action-button" data-tab-target="invoices">مراجعة الفواتير</button>
                </div>
            </div>

            <?php if (empty($listings)) : ?>
                <div class="ms-guided-empty-state">
                    <div class="ms-guided-empty-state__icon" aria-hidden="true">🏠</div>
                    <div>
                        <h2>انشر أول عقار لك</h2>
                        <p>أكمل بيانات عقارك وصوره ليظهر في القوائم ويبدأ استقبال العملاء المحتملين.</p>
                    </div>
                    <button type="button" class="ms-action-button ms-action-button-primary" data-tab-target="add-property">ابدأ الإضافة</button>
                </div>
            <?php endif; ?>

                        <div class="ms-tab-content active" id="overview">

                <?php echo do_shortcode('[ms_action_center_agent]'); ?>

                <div class="ms-grid">

                    <div class="ms-card"><h3>العقارات المدارة</h3><div id="ms-listings-count" class="ms-number"><?php echo intval($listings_count); ?></div></div>
                    <div class="ms-card"><h3>طلبات الصيانة</h3><div id="ms-maintenance-count" class="ms-number"><?php echo intval($agent_maintenance_count); ?></div></div>
                    <div class="ms-card"><h3>الفواتير</h3><div id="ms-invoices-count" class="ms-number"><?php echo intval(count($agent_invoices)); ?></div></div>
                    <div class="ms-card"><h3>المستحق</h3><div id="ms-due-total" class="ms-number">ج.م <?php echo number_format_i18n(floatval($agent_due_total), 2); ?></div></div>
                </div>

                <div class="ms-card" style="margin-top:20px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                        <h3 style="margin:0;">الإشعارات الأخيرة</h3>
                        <?php if ($unread_notifications_count > 0) : ?>
                            <span style="background:#ef4444;color:#fff;padding:3px 10px;border-radius:999px;font-size:0.78rem;font-weight:700;"><?php echo intval($unread_notifications_count); ?> غير مقروء</span>
                        <?php endif; ?>
                        <?php if (!empty($notifications)) : ?>
                            <button type="button" id="ms-mark-all-notifications-read" style="background:none;border:1px solid #d1d5db;padding:6px 14px;border-radius:8px;cursor:pointer;font-size:0.82rem;color:#4b5563;">تعليم الكل كمقروء</button>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($notifications)) : ?>
                        <div style="display:flex;flex-direction:column;gap:10px;">
                            <?php foreach ($notifications as $note) : ?>
                                <div style="padding:14px 16px;border-radius:12px;background:<?php echo !empty($note->is_read) ? '#f9fafb' : '#eff6ff'; ?>;border:1px solid <?php echo !empty($note->is_read) ? '#e5e7eb' : '#bfdbfe'; ?>;">
                                    <div class="ms-notification-message" style="font-size:14px;color:#111;line-height:1.6;"><?php echo esc_html(rawurldecode((string) ($note->message ?? ''))); ?></div>
                                    <div style="margin-top:6px;font-size:12px;color:#6b7280;"><?php echo esc_html(date_i18n('Y-m-d H:i', strtotime($note->created_at ?? $note->created_on ?? ''))); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <p style="padding:12px;margin:0;">لا توجد إشعارات جديدة.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ms-tab-content" id="add-property">
                <div class="ms-card">
                    <h3>إضافة عقار جديد</h3>
                    <p style="color:#64748b;margin-bottom:16px;">أكمل البيانات هنا دون مغادرة لوحة التحكم. تُحفظ المسودة تلقائيًا أثناء الكتابة.</p>
                    <?php echo do_shortcode('[ms_inline_add_property]'); ?>
                </div>
            </div>

            <div class="ms-tab-content" id="listings">
                <div class="ms-properties-header" style="display:flex;justify-content:space-between;align-items:center;gap:14px;margin:20px 0 18px;">
                    <div><h2 style="margin:0;color:#0f172a;">العقارات</h2><p style="margin:6px 0 0;color:#64748b;font-size:14px;">اعرض عقاراتك وغيّر الحالة وارفع عقود الإيجار أو البيع من البطاقة نفسها.</p></div>
                    <button type="button" class="ms-action-button ms-action-button-primary" data-tab-target="add-property">إضافة جديدة</button>
                </div>
                <div class="ms-card" style="margin-top:20px">
                    <?php
                    // Prepare Houzez-style properties query for agent
                    global $properties_query, $delete_properties_nonce;
                    $delete_properties_nonce = wp_create_nonce('delete_properties_nonce');

                    $paged = get_query_var('paged') ?: get_query_var('page') ?: 1;

                    $default_statuses = array('publish', 'pending', 'draft', 'expired', 'houzez_sold', 'disapproved', 'on_hold', 'private', 'future');

                    $search_keyword = isset($_GET['keyword']) ? sanitize_text_field($_GET['keyword']) : '';
                    $post_status_filter = isset($_GET['post_status']) ? sanitize_text_field($_GET['post_status']) : '';
                    $property_id_filter = isset($_GET['property_id']) ? intval($_GET['property_id']) : 0;
                    $min_price = isset($_GET['min-price']) ? floatval($_GET['min-price']) : 0;
                    $max_price = isset($_GET['max-price']) ? floatval($_GET['max-price']) : 0;
                    $type_filter = isset($_GET['type']) ? $_GET['type'] : '';
                    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
                    $featured_filter = isset($_GET['featured']) ? sanitize_text_field($_GET['featured']) : '';

                    $tax_query = array();
                    $meta_query = array('relation' => 'AND');

                    if (!empty($type_filter)) {
                        $type_terms = is_array($type_filter) ? array_map('sanitize_text_field', $type_filter) : array(sanitize_text_field($type_filter));
                        $tax_query[] = array(
                            'taxonomy' => 'property_type',
                            'field' => 'slug',
                            'terms' => $type_terms,
                            'operator' => 'IN',
                        );
                    }

                    if (!empty($status_filter)) {
                        $status_terms = is_array($status_filter) ? array_map('sanitize_text_field', $status_filter) : array(sanitize_text_field($status_filter));
                        $tax_query[] = array(
                            'taxonomy' => 'property_status',
                            'field' => 'slug',
                            'terms' => $status_terms,
                            'operator' => 'IN',
                        );
                    }

                    if ($featured_filter !== '') {
                        $meta_query[] = array(
                            'key' => 'fave_featured',
                            'value' => sanitize_text_field($featured_filter),
                            'compare' => '=',
                        );
                    }

                    if ($min_price || $max_price) {
                        $price_query = array(
                            'key' => 'fave_property_price',
                            'type' => 'NUMERIC',
                        );
                        if ($min_price && $max_price) {
                            $price_query['value'] = array($min_price, $max_price);
                            $price_query['compare'] = 'BETWEEN';
                        } elseif ($min_price) {
                            $price_query['value'] = $min_price;
                            $price_query['compare'] = '>=';
                        } else {
                            $price_query['value'] = $max_price;
                            $price_query['compare'] = '<=';
                        }
                        $meta_query[] = $price_query;
                    }

                    if ($property_id_filter) {
                        $args = array(
                            'post_type' => 'property',
                            'paged' => $paged,
                            'posts_per_page' => 10,
                            'suppress_filters' => false,
                            'p' => $property_id_filter,
                            'post_status' => $default_statuses,
                        );
                    } else {
                        if ($post_status_filter === 'all' || $post_status_filter === '' || $post_status_filter === 'mine') {
                            $resolved_statuses = $default_statuses;
                        } elseif ($post_status_filter === 'approved' || $post_status_filter === 'publish') {
                            $resolved_statuses = array('publish');
                        } elseif ($post_status_filter === 'sold') {
                            $resolved_statuses = array('houzez_sold');
                        } else {
                            $resolved_statuses = array($post_status_filter);
                        }

                        $resolved_property_ids = array();
                        if (!empty($listings)) {
                            $raw_ids = array();
                            foreach ($listings as $p_item) {
                                $item_id = is_object($p_item) && isset($p_item->ID) ? intval($p_item->ID) : (isset($p_item->id) ? intval($p_item->id) : 0);
                                if ($item_id) {
                                    $raw_ids[] = $item_id;
                                    $post = get_post($item_id);
                                    if ($post && $post->post_type === 'property') {
                                        $resolved_property_ids[] = $item_id;
                                        continue;
                                    }
                                }

                                $building_id = intval($p_item->building_id ?? 0);
                                if (!$building_id && isset($p_item->unit) && is_object($p_item->unit)) {
                                    $building_id = intval($p_item->unit->building_id ?? 0);
                                }
                                if ($building_id && function_exists('ms_get_properties_by_building')) {
                                    $props_by_building = ms_get_properties_by_building($building_id);
                                    foreach ((array)$props_by_building as $property_row) {
                                        $property_id = intval($property_row->ID ?? $property_row->id ?? 0);
                                        if ($property_id && !in_array($property_id, $resolved_property_ids, true)) {
                                            $resolved_property_ids[] = $property_id;
                                        }
                                    }
                                }

                                if (!$item_id && !empty($p_item->property_id)) {
                                    $property_id = intval($p_item->property_id);
                                    $property_post = get_post($property_id);
                                    if ($property_post && $property_post->post_type === 'property') {
                                        $resolved_property_ids[] = $property_id;
                                    }
                                }
                            }
                            $resolved_property_ids = array_values(array_unique($resolved_property_ids));

                            $args = array(
                                'post_type' => 'property',
                                'paged' => $paged,
                                'posts_per_page' => 10,
                                'suppress_filters' => false,
                                'post_status' => $resolved_statuses,
                            );
                            if (!empty($resolved_property_ids)) {
                                $args['post__in'] = $resolved_property_ids;
                            } else {
                                $args['post__in'] = array_values(array_unique($raw_ids));
                            }
                        } else {
                            $args = array(
                                'post_type' => 'property',
                                'paged' => $paged,
                                'posts_per_page' => 10,
                                'suppress_filters' => false,
                                'author' => $user->ID,
                                'post_status' => $resolved_statuses,
                            );
                        }
                    }

                    if (!empty($search_keyword)) {
                        $args['s'] = $search_keyword;
                    }
                    if (!empty($tax_query)) {
                        $args['tax_query'] = $tax_query;
                    }
                    if (count($meta_query) > 1) {
                        $args['meta_query'] = $meta_query;
                    }

                    $properties_query = new WP_Query($args);
                    ?>

                    <?php if ($properties_query->have_posts()): ?>
                        <?php
                        $current_page_url = get_permalink();
                        $current_status = isset($_GET['post_status']) ? sanitize_text_field($_GET['post_status']) : 'all';
                        $tabs = array(
                            'all' => 'الجميع',
                            'mine' => 'عقاراتي',
                            'publish' => 'الموافق عليها و المنشورة',
                            'draft' => 'المسودة',
                        );
                        $tab_counts = array(
                            'all' => function_exists('houzez_user_posts_count') ? houzez_user_posts_count('any') : count($listings),
                            'mine' => function_exists('houzez_user_posts_count') ? houzez_user_posts_count('any', true) : count($listings),
                            'publish' => function_exists('houzez_user_posts_count') ? houzez_user_posts_count('publish') : 0,
                            'draft' => function_exists('houzez_user_posts_count') ? houzez_user_posts_count('draft') : 0,
                        );
                        ?>

                        <div class="houzez-properties-tabs-js">
                            <div class="property-nav-tabs" style="position:relative; z-index:9999; touch-action:manipulation;">
                                <ul>
                                    <?php foreach ($tabs as $status => $label): ?>
                                        <?php
                                        $tab_params = array_merge($_GET, array('post_status' => $status, 'paged' => 1));
                                        $tab_url = add_query_arg($tab_params, $current_page_url);
                                        ?>
                                        <li>
                                            <button type="button" class="property-tab-button <?php echo $current_status === $status ? 'active' : ''; ?>" data-url="<?php echo esc_url($tab_url); ?>" aria-pressed="<?php echo $current_status === $status ? 'true' : 'false'; ?>" onclick="event.preventDefault(); event.stopImmediatePropagation(); window.location.assign(this.dataset.url); return false;" onpointerdown="event.preventDefault(); event.stopImmediatePropagation(); window.location.assign(this.dataset.url); return false;" onmousedown="event.preventDefault(); event.stopImmediatePropagation(); window.location.assign(this.dataset.url); return false;" ontouchstart="event.preventDefault(); event.stopImmediatePropagation(); window.location.assign(this.dataset.url); return false;">
                                                <?php echo esc_html($label); ?> <span>(<?php echo intval($tab_counts[$status]); ?>)</span>
                                            </button>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <script>
                                (function(){
                                    console.log('Mostaager inline tab loader active');
                                    var tabNav = document.querySelector('.property-nav-tabs');
                                    if (!tabNav) {
                                        console.log('Mostaager inline tab loader: no tabNav found');
                                        return;
                                    }
                                    tabNav.style.pointerEvents = 'auto';
                                    tabNav.style.zIndex = '999999';
                                    tabNav.style.position = 'relative';
                                    var buttons = tabNav.querySelectorAll('button.property-tab-button');
                                    console.log('Mostaager tab buttons found', buttons.length);
                                    buttons.forEach(function(button){
                                        button.addEventListener('click', function(e){
                                            console.log('Mostaager tab click', this.dataset.url, e.target);
                                            e.preventDefault();
                                            e.stopImmediatePropagation();
                                            var url = this.dataset.url;
                                            if (url) {
                                                window.location.assign(url);
                                            }
                                        }, true);
                                    });
                                    var links = tabNav.querySelectorAll('a[href]');
                                    console.log('Mostaager tab links found', links.length);
                                    links.forEach(function(link){
                                        link.addEventListener('click', function(e){
                                            console.log('Mostaager tab anchor click', this.href, e.target);
                                            if (this.getAttribute('href') === '#' || !this.href) return;
                                            e.preventDefault();
                                            e.stopImmediatePropagation();
                                            window.location.assign(this.href);
                                        }, true);
                                    });

                                    document.addEventListener('pointerdown', function(e){
                                        if (e.clientX == null || e.clientY == null) return;
                                        var tabRect = tabNav.getBoundingClientRect();
                                        if (e.clientX >= tabRect.left && e.clientX <= tabRect.right && e.clientY >= tabRect.top && e.clientY <= tabRect.bottom) {
                                            var el = document.elementFromPoint(e.clientX, e.clientY);
                                            console.log('Mostaager pointerdown in tab area', e.target, 'elementFromPoint', el, 'coords', e.clientX, e.clientY);
                                            if (el && !el.closest('button.property-tab-button') && !el.closest('a[href]')) {
                                                console.log('Mostaager covering element', el, 'closest button', el.closest('button.property-tab-button'), 'closest link', el.closest('a[href]'));
                                            }
                                        }
                                    }, true);
                                    document.addEventListener('mousedown', function(e){
                                        if (e.clientX == null || e.clientY == null) return;
                                        var tabRect = tabNav.getBoundingClientRect();
                                        if (e.clientX >= tabRect.left && e.clientX <= tabRect.right && e.clientY >= tabRect.top && e.clientY <= tabRect.bottom) {
                                            console.log('Mostaager mousedown in tab area', e.target, 'coords', e.clientX, e.clientY);
                                        }
                                    }, true);
                                    document.addEventListener('click', function(e){
                                        if (e.clientX == null || e.clientY == null) return;
                                        var tabRect = tabNav.getBoundingClientRect();
                                        if (e.clientX >= tabRect.left && e.clientX <= tabRect.right && e.clientY >= tabRect.top && e.clientY <= tabRect.bottom) {
                                            console.log('Mostaager click in tab area', e.target, 'coords', e.clientX, e.clientY);
                                        }
                                    }, true);
                                })();
                            </script>
                            <div class="houzez-tab-content">
                                <div class="houzez-data-content">
                                    <?php
                                    global $dashboard_properties;
                                    $dashboard_properties = $current_page_url;
                                    get_template_part('template-parts/dashboard/property/filters');
                                    ?>

                                    <div class="ms-properties-grid ms-agent-properties-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;margin-top:20px;">
                                        <?php global $ms_dashboard_context; $ms_dashboard_context = 'agent'; ?>
                                        <?php while ($properties_query->have_posts()): $properties_query->the_post(); ?>
                                            <?php include MOSTAAGER_ENTERPRISE_PATH . 'templates/partials/property-card.php'; ?>
                                        <?php endwhile; wp_reset_postdata(); unset($ms_dashboard_context); ?>
                                    </div>

                                    <?php get_template_part('template-parts/dashboard/property/pagination'); ?>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="ms-guided-empty-state" style="grid-template-columns:auto 1fr auto;">
                            <div class="ms-guided-empty-state__icon" aria-hidden="true">🏠</div>
                            <div><h2>لم يتم العثور على عقارات</h2><p>أضف أول عقار ليظهر هنا مع حالته وصورته وعقود الإيجار أو البيع.</p></div>
                            <button type="button" class="ms-action-button ms-action-button-primary" data-tab-target="add-property">إنشاء قائمة</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php
    $agent_subscription = function_exists('ms_get_agent_subscription') ? ms_get_agent_subscription($user->ID) : null;
    $subscription_status = function_exists('ms_get_agent_subscription_status') ? ms_get_agent_subscription_status($user->ID) : array('monthly_fee' => 0, 'status' => 'unknown', 'due' => 0, 'plan_name' => 'لا توجد باقة مفعلة');
    $active_package_name = !empty($subscription_status['plan_name']) ? $subscription_status['plan_name'] : 'لا توجد باقة مفعلة';
    $active_plan_key = !empty($subscription_status['plan_key']) ? sanitize_key($subscription_status['plan_key']) : '';
    $is_subscribed = !empty($agent_subscription);
    $status_labels = array('active' => 'نشط', 'paid' => 'مدفوع', 'due' => 'بانتظار السداد', 'expired' => 'منتهي', 'pending' => 'قيد المعالجة', 'unknown' => 'غير معروف');
    $status_key = sanitize_key($subscription_status['status'] ?? 'unknown');
    $status_label = $status_labels[$status_key] ?? ($subscription_status['status'] ?? 'غير معروف');
    $start_label = !empty($subscription_status['started_at']) ? date_i18n('Y-m-d', strtotime($subscription_status['started_at'])) : 'لم يبدأ بعد';
    $end_label = !empty($subscription_status['ends_at']) ? date_i18n('Y-m-d', strtotime($subscription_status['ends_at'])) : 'غير محدد';
    $plans = array(
        array('name' => 'الباقة الذهبية', 'amount' => 100.00, 'display_price' => '100 ج.م', 'capacity' => '50 شقة', 'description' => 'أفضل باقة لإضافة عدد كبير من العقارات مع دعم مخصص.', 'package_key' => 'gold', 'rank' => 3),
        array('name' => 'الباقة الفضية', 'amount' => 50.00, 'display_price' => '50 ج.م', 'capacity' => '25 شقة', 'description' => 'باقة متوازنة تناسب معظم الوسطاء الذين يريدون نموًا مستمرًا.', 'package_key' => 'silver', 'rank' => 2),
        array('name' => 'الباقة البرونزية', 'amount' => 25.00, 'display_price' => '25 ج.م', 'capacity' => '10 شقة', 'description' => 'باقة تمهيدية مناسبة للوسطاء الجدد أو الميزانية المحدودة.', 'package_key' => 'bronze', 'rank' => 1),
    );
    if (!$active_plan_key && $is_subscribed) {
        foreach ($plans as $legacy_plan) {
            if (stripos($active_package_name, $legacy_plan['name']) !== false) { $active_plan_key = $legacy_plan['package_key']; break; }
        }
    }
    ?>
            <div class="ms-tab-content" id="subscriptions">
                <div class="ms-card">
                    <h3>اشتراكي الحالي</h3>
                    <div class="ms-subscription-summary" style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:16px;">
                        <div><small>الباقة</small><strong><?php echo esc_html($active_package_name); ?></strong></div>
                        <div><small>تاريخ الاشتراك</small><strong><?php echo esc_html($start_label); ?></strong></div>
                        <div><small>نهاية الاشتراك</small><strong><?php echo esc_html($end_label); ?></strong></div>
                        <div><small>الحالة</small><strong class="ms-subscription-status-<?php echo esc_attr($status_key); ?>"><?php echo esc_html($status_label); ?></strong></div>
                    </div>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:18px;">
                        <?php if ($is_subscribed && $active_plan_key): ?><button type="button" class="ms-action-button ms-subscription-action-btn" data-mode="renew" data-plan-key="<?php echo esc_attr($active_plan_key); ?>">تجديد الاشتراك</button><?php endif; ?>
                        <span style="color:#64748b;font-size:13px;align-self:center;">سيتم إنشاء فاتورة ودفع آمن قبل تفعيل التغيير.</span>
                    </div>
                </div>
                <div class="ms-card" style="margin-top:18px;">
                    <h3>الترقية إلى باقة أفضل</h3>
                    <div class="ms-subscription-cards" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px;margin-top:16px;">
                        <?php foreach ($plans as $plan): $is_active = $is_subscribed && $active_plan_key === $plan['package_key']; $is_upgrade = !$active_plan_key || $plan['rank'] > (array_values(array_filter($plans, function($p) use ($active_plan_key){ return $p['package_key'] === $active_plan_key; }))[0]['rank'] ?? 0); ?>
                            <div class="ms-subscription-card" style="border:1px solid <?php echo $is_active ? '#2563eb' : '#e5e7eb'; ?>;border-radius:16px;padding:20px;background:#fff;">
                                <h4 style="margin:0 0 8px;font-size:1.1rem;"><?php echo esc_html($plan['name']); ?> <?php if ($is_active): ?><span style="color:#2563eb;font-size:.8rem;">(الحالية)</span><?php endif; ?></h4>
                                <p style="margin:0 0 14px;color:#475569;line-height:1.5;"><?php echo esc_html($plan['description']); ?></p>
                                <strong style="font-size:1.8rem;color:#111827;"><?php echo esc_html($plan['display_price']); ?></strong><span style="color:#475569;"> شهريًا</span>
                                <p style="color:#334155;font-weight:700;">حتى <?php echo esc_html($plan['capacity']); ?></p>
                                <?php if ($is_active): ?><span style="color:#16a34a;font-weight:700;">مشترك حاليًا</span><?php elseif ($is_upgrade): ?><button type="button" class="ms-action-button ms-action-button-primary ms-subscription-action-btn" data-mode="upgrade" data-plan-key="<?php echo esc_attr($plan['package_key']); ?>">ترقية إلى هذه الباقة</button><?php else: ?><span style="color:#64748b;font-size:13px;">باقة أقل من اشتراكك الحالي</span><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="ms-tab-content" id="maintenance">
                <div class="ms-card">
                    <h3>طلبات الصيانة</h3>
                    <p style="color:#666;margin-top:8px">عرض طلبات الصيانة المرتبطة بالمباني الخاصة بالعقارات التي يديرها الوسيط.</p>
                    <?php if (!empty($agent_maintenance_requests)): ?>
                        <div class="table-responsive" style="margin-top:14px;">
                            <table class="table table-hover align-middle m-0">
                                <thead>
                                    <tr>
                                        <th>رقم الطلب</th>
                                        <th>العنوان</th>
                                        <th>الحالة</th>
                                        <th>الأولوية</th>
                                        <th>التكلفة</th>
                                        <th>تاريخ الإنشاء</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($agent_maintenance_requests as $request): ?>
                                        <tr>
                                            <td><?php echo esc_html($request->id ?? 'N/A'); ?></td>
                                            <td><?php echo esc_html($request->title ?? 'بدون عنوان'); ?></td>
                                            <td><?php echo esc_html($request->status ?? 'غير معروف'); ?></td>
                                            <td><?php echo esc_html($request->priority ?? 'متوسط'); ?></td>
                                            <td><?php echo isset($request->cost) ? esc_html(number_format(floatval($request->cost), 2)) : '0.00'; ?></td>
                                            <td><?php echo esc_html($request->created_at ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="color:#666;margin-top:12px">لم يتم العثور على طلبات صيانة مرتبطة بهذه العقارات.</p>
                    <?php endif; ?>
                </div>
            </div>

                        <div class="ms-tab-content" id="invoices">
                <div class="ms-card">
                    <h3>فواتير الاشتراك والباقة</h3>
                    <?php $agent_renewal_cancelled = (bool) get_user_meta($user->ID, 'ms_agent_renewal_cancelled', true); $agent_renewal_nonce = wp_create_nonce('ms_agent_renewal_action'); ?>
                    <div class="ms-renewal-control" data-renewal-state="<?php echo $agent_renewal_cancelled ? 'cancelled' : 'active'; ?>" style="margin:16px 0;padding:14px 16px;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;"><div><strong>التجديد التلقائي</strong><p style="margin:5px 0 0;color:#64748b;font-size:13px;"><?php echo $agent_renewal_cancelled ? 'تم إلغاء التجديد. يبقى اشتراكك فعالًا حتى تاريخ انتهائه.' : 'التجديد مفعّل. يمكنك إلغاؤه مع بقاء اشتراكك فعالًا حتى تاريخ الانتهاء.'; ?></p><span class="ms-renewal-status" style="font-weight:700;color:<?php echo $agent_renewal_cancelled ? '#b91c1c' : '#15803d'; ?>;">الحالة: <?php echo $agent_renewal_cancelled ? 'ملغى' : 'مفعّل'; ?></span></div><button type="button" class="ms-renewal-toggle" data-renewal-action="<?php echo $agent_renewal_cancelled ? 'restore' : 'cancel'; ?>" data-renewal-nonce="<?php echo esc_attr($agent_renewal_nonce); ?>" style="border:1px solid <?php echo $agent_renewal_cancelled ? '#16a34a' : '#dc2626'; ?>;background:#fff;color:<?php echo $agent_renewal_cancelled ? '#15803d' : '#b91c1c'; ?>;padding:9px 14px;border-radius:8px;cursor:pointer;"><?php echo $agent_renewal_cancelled ? 'إعادة تفعيل التجديد' : 'إلغاء التجديد التلقائي'; ?></button></div>
                    <?php if(!empty($agent_invoices)): ?>

                        <div class="ms-invoice-subtabs" style="margin-top:18px;display:flex;gap:8px;flex-wrap:wrap;">
                            <button class="ms-invoice-subtab active" data-subtab="invoices-property" style="padding:10px 20px;border-radius:999px;border:1px solid #e5e7eb;background:#2563eb;color:#fff;cursor:pointer;font-weight:600;">فواتير العقار (رسوم)</button>
                            <button class="ms-invoice-subtab" data-subtab="invoices-building" style="display:none;padding:10px 20px;border-radius:999px;border:1px solid #e5e7eb;background:#fff;color:#0f172a;cursor:pointer;font-weight:600;">فواتير البناء والصيانة</button>
                        </div>
                        <div class="ms-invoice-subpanel active" id="invoices-property" style="margin-top:16px;">
                            <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;">
                                <div class="ms-card" style="flex:1;min-width:200px;">
                                    <h4>إجمالي المستحق</h4>
                                    <div class="ms-number">ج.م <?php echo esc_html(number_format(floatval($agent_due_total), 2)); ?></div>
                                </div>
                                <div class="ms-card" style="flex:1;min-width:200px;">
                                    <h4>عدد الفواتير</h4>
                                    <div class="ms-number"><?php echo intval(count($invoice_groups['property'])); ?></div>
                                </div>
                            </div>
                            <?php if (!empty($invoice_groups['property'])): ?>
                        <div class="table-responsive" style="margin-top:20px;">
                            <table class="widefat fixed striped" style="width:100%;border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th style="padding:12px;text-align:right">رقم الفاتورة</th>
                                        <th style="padding:12px;text-align:right">الوصف</th>
                                        <th style="padding:12px;text-align:right">الحالة</th>
                                        <th style="padding:12px;text-align:right">المبلغ</th>
                                        <th style="padding:12px;text-align:right">تاريخ الإنشاء</th>
                                        <th style="padding:12px;text-align:right">إجراء</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($invoice_groups['property'] as $invoice): ?>
                                        <?php
                                            $status = isset($invoice->status) ? strtolower(trim($invoice->status)) : 'unknown';
                                            $status_label = '';
                                            if ($status === 'paid') {
                                                $status_label = '<span style="color:#10b981;font-weight:600">مدفوع</span>';
                                            } elseif ($status === 'canceled') {
                                                $status_label = '<span style="color:#ef4444;font-weight:600">ملغي</span>';
                                            } elseif ($status === 'pending') {
                                                $status_label = 'معلقة';
                                            } elseif ($status === 'overdue') {
                                                $status_label = '<span style="color:#f59e0b;font-weight:600">متأخرة</span>';
                                            } else {
                                                $status_label = esc_html($status);
                                            }
                                        ?>
                                        <tr>
                                            <td style="padding:12px;text-align:center"><?php echo esc_html($invoice->id ?? ($invoice->invoice_number ?? 'N/A')); ?></td>
                                            <td style="padding:12px"><?php echo esc_html($invoice->description ?? $invoice->invoice_type ?? 'فاتورة'); ?></td>
                                            <td style="padding:12px"><?php echo $status_label; ?></td>
                                            <td style="padding:12px">ج.م <?php echo isset($invoice->amount) ? number_format_i18n(floatval($invoice->amount), 2) : '0.00'; ?></td>
                                            <td style="padding:12px"><?php echo esc_html($invoice->created_at ?? $invoice->due_date ?? '-'); ?></td>
                                            <td style="padding:12px">
                                                <?php if ($status === 'pending'): ?>
                                                    <button class="ms-pay-now-btn button button-secondary" data-invoice-id="<?php echo intval($invoice->id ?? 0); ?>" data-nonce="<?php echo wp_create_nonce('ms_pay_invoice_' . intval($invoice->id ?? 0)); ?>" style="padding:6px 12px;">ادفع الآن</button>
                                                <?php elseif ($status === 'paid'): ?>
                                                    <span style="color:#10b981;font-size:12px;">✓ تم الدفع</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p style="color:#666;margin-top:12px">لا توجد فواتير رسوم وسيط حالياً.</p>
                                                <?php endif; ?>
                    </div>
                    </div>
                    <div class="ms-invoice-subpanel" id="invoices-building" style="display:none;margin-top:16px;">
                        <?php if (!empty($invoice_groups['building'])): ?>
                            <div class="table-responsive" style="margin-top:20px;">
                                <table class="widefat fixed striped" style="width:100%;border-collapse:collapse;">
                                    <thead>
                                        <tr>
                                            <th style="padding:12px;text-align:right">رقم الفاتورة</th>
                                            <th style="padding:12px;text-align:right">الوصف</th>
                                            <th style="padding:12px;text-align:right">الحالة</th>
                                            <th style="padding:12px;text-align:right">المبلغ</th>
                                            <th style="padding:12px;text-align:right">تاريخ الإنشاء</th>
                                            <th style="padding:12px;text-align:right">إجراء</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($invoice_groups['building'] as $invoice): ?>
                                            <?php
                                                $status = isset($invoice->status) ? strtolower(trim($invoice->status)) : 'unknown';
                                                $status_label = '';
                                                if ($status === 'paid') {
                                                    $status_label = '<span style="color:#10b981;font-weight:600">مدفوع</span>';
                                                } elseif ($status === 'canceled') {
                                                    $status_label = '<span style="color:#ef4444;font-weight:600">ملغي</span>';
                                                } elseif ($status === 'pending') {
                                                    $status_label = 'معلقة';
                                                } elseif ($status === 'overdue') {
                                                    $status_label = '<span style="color:#f59e0b;font-weight:600">متأخرة</span>';
                                                } else {
                                                    $status_label = esc_html($status);
                                                }
                                            ?>
                                            <tr>
                                                <td style="padding:12px;text-align:center"><?php echo esc_html($invoice->id ?? ($invoice->invoice_number ?? 'N/A')); ?></td>
                                                <td style="padding:12px"><?php echo esc_html($invoice->description ?? $invoice->invoice_type ?? 'فاتورة'); ?></td>
                                                <td style="padding:12px"><?php echo $status_label; ?></td>
                                                <td style="padding:12px">ج.م <?php echo isset($invoice->amount) ? number_format_i18n(floatval($invoice->amount), 2) : '0.00'; ?></td>
                                                <td style="padding:12px"><?php echo esc_html($invoice->created_at ?? $invoice->due_date ?? '-'); ?></td>
                                                <td style="padding:12px">
                                                    <?php if ($status === 'pending'): ?>
                                                        <button class="ms-pay-now-btn button button-secondary" data-invoice-id="<?php echo intval($invoice->id ?? 0); ?>" data-nonce="<?php echo wp_create_nonce('ms_pay_invoice_' . intval($invoice->id ?? 0)); ?>" style="padding:6px 12px;">ادفع الآن</button>
                                                    <?php elseif ($status === 'paid'): ?>
                                                        <span style="color:#10b981;font-size:12px;">✓ تم الدفع</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p style="color:#666;margin-top:12px">لا توجد فواتير بناء وصيانة حالياً.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p style="color:#666;margin-top:12px">لا توجد فواتير حالياً.</p>
                <?php endif; ?>
                </div>
            </div>

            <div class="ms-tab-content" id="discussions">
                <div class="ms-card">
                    <h3>المناقشات</h3>
                    <?php
                    // Resolve a building_id from the agent's properties (first matching building_id meta)
                    $discussion_building_id = 0;
                    if (!empty($listings)) {
                        $building_ids = array();
                        foreach ($listings as $p_item) {
                            // If listing is an ms_units-like object with building_id property, use it directly
                            if (is_object($p_item) && isset($p_item->building_id) && intval($p_item->building_id)) {
                                $b = intval($p_item->building_id);
                                $building_ids[] = $b;
                                continue;
                            }
                            $prop_id = is_object($p_item) && isset($p_item->ID) ? intval($p_item->ID) : (isset($p_item->id) ? intval($p_item->id) : 0);
                            if ($prop_id) {
                                $b = get_post_meta($prop_id, 'building_id', true);
                                $b = intval($b);
                                if ($b) $building_ids[] = $b;
                            }
                        }
                        $building_ids = array_values(array_unique($building_ids));
                        if (!empty($building_ids)) {
                            $discussion_building_id = $building_ids[0];
                        }
                    }
                    ?>

                    <?php if (!$discussion_building_id): ?>
                        <p style="color:#666;margin-top:8px">لم يتم العثور على معرف مبنى صالح مرتبط بخصائص الوسيط. لا يمكن تحميل مواضيع المناقشات.</p>
                    <?php else: ?>
                        <div id="agent-discussions" data-building-id="<?php echo intval($discussion_building_id); ?>">
                            
                            <div class="ms-discussions-layout" style="display:flex;gap:12px;align-items:flex-start;">
                                <div class="ms-discussions-list" style="width:36%;min-width:220px;border-right:1px solid #eee;padding-right:12px;">
                                    <h4 style="margin-top:0">المواضيع</h4>
                                    <ul class="ms-discussions-list-ul" style="list-style:none;padding:0;margin:0;max-height:420px;overflow:auto;"></ul>
                                </div>
                                <div class="ms-discussion-detail" style="flex:1;min-width:320px;">
                                    <div class="ms-discussion-empty" style="color:#666">اختر موضوعاً لعرض التفاصيل</div>
                                    <div class="ms-discussion-messages" style="margin-top:12px;max-height:360px;overflow:auto;border:1px solid #f3f4f6;padding:12px;background:#fff;"></div>

                                    <form class="ms-discussion-reply-form" style="margin-top:12px;display:none;">
                                        <textarea name="reply" rows="4" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;"></textarea>
                                        <div style="margin-top:8px;text-align:left;">
                                            <button type="submit" class="ms-discussion-reply-submit" style="padding:8px 12px;background:#2563eb;color:#fff;border:none;border-radius:4px;">إرسال الرد</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ms-tab-content" id="analytics">
                <div class="ms-card"><h3>الإحصائيات</h3><p>عرض أداء العقارات والعمولات.</p></div>
            </div>

            <div class="ms-tab-content" id="profile">
                <div class="ms-card">
                                        <h3>البروفايل الشخصي</h3>
                    <?php echo do_shortcode('[ms_notification_preferences]'); ?>
                    <?php echo do_shortcode('[ms_unified_profile]'); ?>
                    <form id="ms-profile-form" method="post" enctype="multipart/form-data" style="display:none!important;margin-top:20px;">
                        <?php wp_nonce_field('ms_update_profile', 'ms_profile_nonce'); ?>
                        <input type="hidden" name="action" value="ms_update_profile">
                        
                        <div style="display:flex;align-items:center;gap:20px;margin-bottom:24px;">
                            <div style="width:100px;height:100px;border-radius:50%;overflow:hidden;background:#f3f4f6;display:flex;align-items:center;justify-content:center;">
                                <?php 
                                $avatar_url = get_avatar_url($user->ID, array('size' => 100));
                                if ($avatar_url) : ?>
                                    <img src="<?php echo esc_url($avatar_url); ?>" alt="Avatar" style="width:100%;height:100%;object-fit:cover;">
                                <?php else : ?>
                                    <span style="font-size:40px;">👤</span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div style="font-weight:700;font-size:18px;"><?php echo esc_html($user->display_name); ?></div>
                                <div style="color:#6b7280;"><?php echo esc_html($user->user_email); ?></div>
                                <button type="button" id="ms-change-avatar-btn" style="margin-top:8px;padding:6px 12px;background:#2563eb;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px;">تغيير الصورة</button>
                                <input type="file" name="avatar" id="ms-avatar-input" accept="image/*" style="display:none;">
                            </div>
                        </div>

                        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:20px;">
                            <div>
                                <label style="display:block;margin-bottom:8px;font-weight:600;">الاسم الأول</label>
                                <input type="text" name="first_name" value="<?php echo esc_attr($user->first_name); ?>" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                            </div>
                            <div>
                                <label style="display:block;margin-bottom:8px;font-weight:600;">اسم العائلة</label>
                                <input type="text" name="last_name" value="<?php echo esc_attr($user->last_name); ?>" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                            </div>
                            <div>
                                <label style="display:block;margin-bottom:8px;font-weight:600;">الاسم المعروض</label>
                                <input type="text" name="display_name" value="<?php echo esc_attr($user->display_name); ?>" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                            </div>
                            <div>
                                <label style="display:block;margin-bottom:8px;font-weight:600;">البريد الإلكتروني</label>
                                <input type="email" name="email" value="<?php echo esc_attr($user->user_email); ?>" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                            </div>
                            <div>
                                <label style="display:block;margin-bottom:8px;font-weight:600;">رقم الهاتف</label>
                                <input type="tel" name="phone" value="<?php echo esc_attr(get_user_meta($user->ID, 'billing_phone', true)); ?>" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                            </div>
                            <div>
                                <label style="display:block;margin-bottom:8px;font-weight:600;">العنوان</label>
                                <input type="text" name="address" value="<?php echo esc_attr(get_user_meta($user->ID, 'billing_address_1', true)); ?>" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
                            </div>
                        </div>

                        <div style="margin-top:20px;">
                            <label style="display:block;margin-bottom:8px;font-weight:600;">نبذة عني</label>
                            <textarea name="description" rows="4" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;"><?php echo esc_textarea($user->description); ?></textarea>
                        </div>

                        <div style="margin-top:24px;">
                            <button type="submit" style="padding:12px 24px;background:#2563eb;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:600;">حفظ التغييرات</button>
                        </div>
                    </form>
                </div>
            </div>

        </main>

    </div>

    <script>
    (function() {
        // Profile form handling
        var profileForm = document.getElementById('ms-profile-form');
        if (profileForm) {
            profileForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                var formData = new FormData(this);
                formData.append('action', 'ms_update_profile');
                
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    if (data.success) {
                        alert('تم تحديث البروفايل بنجاح');
                        location.reload();
                    } else {
                        alert('خطأ: ' + (data.data.message || 'حدث خطأ أثناء التحديث'));
                    }
                })
                .catch(function(error) {
                    console.error('Error:', error);
                    alert('حدث خطأ أثناء الاتصال بالخادم');
                });
            });
        }

        // Avatar upload button
        var avatarBtn = document.getElementById('ms-change-avatar-btn');
        var avatarInput = document.getElementById('ms-avatar-input');
        if (avatarBtn && avatarInput) {
            avatarBtn.addEventListener('click', function() {
                avatarInput.click();
            });
            avatarInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    // Auto submit form when avatar is selected
                    if (profileForm) {
                        profileForm.dispatchEvent(new Event('submit'));
                    }
                }
            });
        }
    })();
    </script>

    <?php
    return ob_get_clean();

});


