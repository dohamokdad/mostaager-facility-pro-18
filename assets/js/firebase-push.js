(function () {
    'use strict';
    if (!window.MostaagerFirebase || !window.MostaagerFirebase.enabled || !window.firebase || !firebase.messaging) return;

    try {
        firebase.initializeApp(window.MostaagerFirebase.config);
        const messaging = firebase.messaging();
        window.MostaagerEnablePush = function () { return register(true); };
        const register = function (requestPermission) {
            if (!('Notification' in window)) return Promise.resolve();
            const permissionPromise = requestPermission && Notification.permission === 'default'
                ? Notification.requestPermission()
                : Promise.resolve(Notification.permission);
            return permissionPromise.then(function (permission) {
                if (permission !== 'granted') return null;
                return messaging.getToken({ vapidKey: window.MostaagerFirebase.vapid_key });
            }).then(function (token) {
                if (!token) return;
                return fetch(window.MostaagerFirebase.ajax_url, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                    body: new URLSearchParams({
                        action: 'ms_register_push_token',
                        security: window.MostaagerFirebase.nonce,
                        token: token,
                        platform: 'web'
                    })
                });
            }).catch(function () {});
        };
        messaging.onMessage(function (payload) {
            const notification = payload.notification || {};
            const data = payload.data || {};
            if (window.NotificationDashboard && typeof window.NotificationDashboard.showRealtimeNotification === 'function') {
                window.NotificationDashboard.showRealtimeNotification({
                    message: notification.body || data.message || 'إشعار جديد',
                    type: data.type || 'push',
                    related_id: data.related_id || 0
                });
            }
        });
        register(false);
    } catch (e) {
        // Push is an optional enhancement; never break the dashboard when it is unavailable.
    }
})();
