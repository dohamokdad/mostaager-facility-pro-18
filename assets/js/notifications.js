// Notification Dashboard JavaScript
(function($) {
    'use strict';

    const notificationAjaxUrl = (window.MostaagerAjax || {}).ajax_url || window.ajaxurl || '/wp-admin/admin-ajax.php';
    
    const NotificationDashboard = {
        init: function() {
            this.initSendForm();
            this.initScheduleForm();
            this.initTemplateSelector();
            this.initPreferencesForm();
            this.loadStats();
            this.initRealtimeNotifications();
        },
        
        initSendForm: function() {
            $('#ms-send-notification-form').on('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const channels = [];
                $(this).find('input[name="channels[]"]:checked').each(function() {
                    channels.push($(this).val());
                });
                
                const data = {
                    action: 'ms_send_notification',
                    nonce: formData.get('nonce'),
                    recipient: formData.get('recipient'),
                    message: formData.get('message'),
                    channels: JSON.stringify(channels),
                    type: formData.get('type')
                };
                
                $.ajax({
                    url: notificationAjaxUrl,
                    type: 'POST',
                    data: data,
                    beforeSend: function() {
                        $(this).find('button').prop('disabled', true).text('جاري الإرسال...');
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('تم إرسال الإشعار بنجاح!');
                            $('#ms-send-notification-form')[0].reset();
                            NotificationDashboard.loadStats();
                        } else {
                            alert('حدث خطأ أثناء الإرسال');
                        }
                    },
                    error: function() {
                        alert('حدث خطأ في الاتصال');
                    },
                    complete: function() {
                        $(this).find('button').prop('disabled', false).text('إرسال الإشعار');
                    }
                });
            });
        },
        
        initScheduleForm: function() {
            $('#ms-schedule-notification-form').on('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const channels = [];
                $(this).find('input[name="channels[]"]:checked').each(function() {
                    channels.push($(this).val());
                });
                
                const data = {
                    action: 'ms_schedule_notification',
                    nonce: formData.get('nonce'),
                    recipient: formData.get('recipient'),
                    message: formData.get('message'),
                    channels: JSON.stringify(channels),
                    schedule_time: formData.get('schedule_time'),
                    type: 'custom'
                };
                
                $.ajax({
                    url: notificationAjaxUrl,
                    type: 'POST',
                    data: data,
                    beforeSend: function() {
                        $(this).find('button').prop('disabled', true).text('جاري الجدولة...');
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('تم جدولة الإشعار بنجاح!');
                            $('#ms-schedule-notification-form')[0].reset();
                        } else {
                            alert('حدث خطأ أثناء الجدولة');
                        }
                    },
                    error: function() {
                        alert('حدث خطأ في الاتصال');
                    },
                    complete: function() {
                        $(this).find('button').prop('disabled', false).text('جدولة الإشعار');
                    }
                });
            });
        },
        
        initTemplateSelector: function() {
            $('#ms-notification-type').on('change', function() {
                const templateType = $(this).val();
                
                if (templateType !== 'custom') {
                    const data = {
                        action: 'ms_get_notification_templates',
                        nonce: $('#ms-send-notification-form input[name="nonce"]').val()
                    };
                    
                    $.ajax({
                        url: notificationAjaxUrl,
                        type: 'POST',
                        data: data,
                        success: function(response) {
                            if (response.success && response.data[templateType]) {
                                const template = response.data[templateType];
                                $('#ms-send-notification-form textarea[name="message"]').val(template.message);
                            }
                        }
                    });
                }
            });
        },
        
        loadStats: function() {
            const data = {
                action: 'ms_get_notification_stats',
                nonce: $('#ms-send-notification-form input[name="nonce"]').val()
            };
            
            $.ajax({
                url: notificationAjaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        NotificationDashboard.updateStatsUI(response.data);
                    }
                }
            });
        },
        
        initPreferencesForm: function() {
            $(document).on('submit', '[data-ms-notification-preferences-form]', function(event) {
                event.preventDefault();
                const form = $(this);
                const status = form.closest('[data-ms-notification-preferences]').find('[data-preferences-status]');
                const data = {
                    action: 'ms_save_notification_preferences',
                    security: (window.MostaagerAjax || {}).nonce || '',
                    in_app: form.find('[name="in_app"]').is(':checked') ? 1 : 0,
                    email: form.find('[name="email"]').is(':checked') ? 1 : 0,
                    whatsapp: form.find('[name="whatsapp"]').is(':checked') ? 1 : 0,
                    push: form.find('[name="push"]').is(':checked') ? 1 : 0,
                    rent_reminder: form.find('[name="rent_reminder"]').is(':checked') ? 1 : 0
                };
                status.text('جاري الحفظ...').removeClass('is-error');
                $.post(notificationAjaxUrl, data)
                    .done(function(response) {
                        if (response && response.success) {
                            status.text('تم حفظ التفضيلات بنجاح.');
                            if (data.push && typeof window.MostaagerEnablePush === 'function') {
                                window.MostaagerEnablePush().then(function () {
                                    status.text('تم حفظ التفضيلات وتفعيل إشعارات المتصفح.');
                                }).catch(function () {
                                    status.text('تم حفظ التفضيلات، لكن تعذر تفعيل إشعارات المتصفح.');
                                });
                            }
                        } else {
                            status.text('تعذر حفظ التفضيلات.').addClass('is-error');
                        }
                    })
                    .fail(function() {
                        status.text('تعذر الاتصال بالخادم.').addClass('is-error');
                    });
            });
        },

        initRealtimeNotifications: function() {
            if (typeof window.MostaagerNotifications === 'undefined' || !window.MostaagerNotifications.ajax_url) {
                return;
            }
            if (!document.querySelector('.ms-dashboard, .ms-owner-dashboard')) {
                return;
            }

            this.notificationCursor = parseInt(window.localStorage.getItem('ms_notification_cursor') || '0', 10) || 0;
            this.notificationInitialLoad = true;
            this.notificationPollInFlight = false;
            this.ensureRealtimeUI();
            this.pollNotifications();

            const interval = parseInt(window.MostaagerNotifications.poll_interval, 10) || 20000;
            window.setInterval(() => this.pollNotifications(), interval);
        },

        ensureRealtimeUI: function() {
            if (!document.getElementById('ms-realtime-notification-region')) {
                $('body').append('<div id="ms-realtime-notification-region" class="ms-realtime-notification-region" aria-live="polite" aria-atomic="true"></div>');
            }

        },

        pollNotifications: function() {
            if (this.notificationPollInFlight) {
                return;
            }
            this.notificationPollInFlight = true;

            $.ajax({
                url: window.MostaagerNotifications.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'ms_get_notifications_since',
                    security: window.MostaagerNotifications.nonce,
                    after_id: this.notificationCursor
                }
            }).done((response) => {
                if (!response || !response.success || !response.data) {
                    return;
                }

                const notifications = Array.isArray(response.data.notifications) ? response.data.notifications : [];
                if (!this.notificationInitialLoad) {
                    notifications.forEach((notification) => this.showRealtimeNotification(notification));
                }
                this.notificationInitialLoad = false;

                const nextCursor = parseInt(response.data.last_id, 10) || this.notificationCursor;
                if (nextCursor > this.notificationCursor) {
                    this.notificationCursor = nextCursor;
                    window.localStorage.setItem('ms_notification_cursor', String(nextCursor));
                }
                this.updateUnreadBadge(parseInt(response.data.unread_count, 10) || 0);
            }).always(() => {
                this.notificationPollInFlight = false;
            });
        },

        updateUnreadBadge: function(count) {
            return count;
        },

        showRealtimeNotification: function(notification) {
            if (!notification || !notification.message) {
                return;
            }

            const title = (window.MostaagerNotifications.i18n || {}).new_notification || 'إشعار جديد';
            const toast = $('<div class="ms-realtime-notification" role="status">' +
                '<div class="ms-realtime-notification__title"></div>' +
                '<div class="ms-realtime-notification__message"></div>' +
                '<a class="ms-realtime-notification__link" target="_self" rel="noopener">فتح التفاصيل</a>' +
                '<button type="button" class="ms-realtime-notification__close" aria-label="إغلاق">×</button>' +
                '</div>');
            toast.find('.ms-realtime-notification__title').text(title);
            toast.find('.ms-realtime-notification__message').text(notification.message);
            const relatedId = parseInt(notification.related_id || notification.reference_id || 0, 10) || 0;
            if (relatedId && String(notification.type || '').indexOf('maintenance') !== -1) {
                const safeUrl = new URL('/rent-dashboard/', window.location.origin);
                safeUrl.hash = 'maintenance-' + relatedId;
                toast.find('.ms-realtime-notification__link').attr('href', safeUrl.toString());
            } else {
                toast.find('.ms-realtime-notification__link').remove();
            }
            toast.find('.ms-realtime-notification__close').on('click', () => toast.remove());
            $('#ms-realtime-notification-region').append(toast);

            const duration = parseInt(window.MostaagerNotifications.toast_duration, 10) || 6500;
            window.setTimeout(() => toast.fadeOut(250, () => toast.remove()), duration);

            if (document.visibilityState === 'hidden' && 'Notification' in window && Notification.permission === 'granted') {
                new Notification(title, { body: notification.message });
            }
        },

        updateStatsUI: function(stats) {
            $('.ms-stat-card').each(function() {
                const icon = $(this).find('.ms-stat-icon').text();
                const value = $(this).find('h3');
                
                if (icon === '📧') {
                    value.text(stats.email_sent);
                } else if (icon === '📱') {
                    value.text(stats.sms_sent);
                } else if (icon === '💬') {
                    value.text(stats.whatsapp_sent);
                } else if (icon === '🔔') {
                    value.text(stats.push_sent);
                } else if (icon === '❌') {
                    value.text(stats.failed);
                }
            });
        }
    };
    
    window.NotificationDashboard = NotificationDashboard;
    $(document).ready(function() {
        NotificationDashboard.init();
    });
    
})(jQuery);