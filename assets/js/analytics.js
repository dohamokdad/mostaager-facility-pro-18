(function($){
    'use strict';
    const cfg = window.MostaagerAnalytics || {};
    if (!cfg.ajax_url || !cfg.nonce) return;
    function track(eventName, context, metadata) {
        const body = new URLSearchParams({
            action: 'ms_track_event', security: cfg.nonce,
            event_name: eventName, context: context || '',
            metadata: JSON.stringify(metadata || {})
        });
        if (navigator.sendBeacon) {
            try { navigator.sendBeacon(cfg.ajax_url, body); return; } catch(e) {}
        }
        $.post(cfg.ajax_url, body.toString());
    }
    $(document).on('click', '[data-tab-target], [data-tab]', function(){
        track('dashboard_tab_open', $(this).data('tab-target') || $(this).data('tab') || 'unknown', {});
    });
    $(document).on('click', '[data-notification-id]', function(){
        track('notification_open', 'notification', {id: parseInt($(this).data('notification-id'), 10) || 0});
    });
    $(document).on('click', '[data-payment-start], .ms-pay-invoice, .pay-invoice', function(){
        track('invoice_payment_start', 'invoice', {id: parseInt($(this).data('invoice-id'), 10) || 0});
    });
    $(document).on('submit', '[data-property-form], #ms-property-form, form[action*="property"]', function(){
        track('property_create_complete', 'property_form', {});
    });
    $(document).on('input change', '[data-agent-step], .agent-form-step input, .agent-form-step select', function(){
        const step = $(this).closest('[data-agent-step], .agent-form-step').data('agent-step') || '';
        track('agent_form_step', String(step), {});
    });
    $(document).on('change', '[data-maintenance-filter], .maintenance-filter select', function(){
        track('maintenance_filter_apply', 'maintenance', {filter: $(this).attr('name') || ''});
    });
})(jQuery);
