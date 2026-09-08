<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Firebase Cloud Messaging HTTP v1 bridge.
 *
 * Credentials must be supplied through wp-config.php or environment variables:
 * define('MS_FIREBASE_PROJECT_ID', 'your-project-id');
 * define('MS_FIREBASE_SERVICE_ACCOUNT_JSON', '/absolute/path/service-account.json');
 * define('MS_FIREBASE_WEB_CONFIG', json_encode(array(...)));
 */
class MS_Firebase_FCM_Provider
{
    const TOKEN_META_KEY = 'ms_fcm_registration_tokens';
    const SERVICE_ACCOUNT_OPTION = 'ms_firebase_service_account_json';

    public static function register_hooks()
    {
        add_action('ms_notification_created', array(__CLASS__, 'handle_notification_created'), 10, 3);
    }

    public static function handle_notification_created($notification_id, $user_id, $payload)
    {
        $preferences = function_exists('ms_get_notification_preferences')
            ? ms_get_notification_preferences($user_id)
            : array('push' => 0);

        if (empty($preferences['push'])) {
            return;
        }

        $tokens = self::get_user_tokens($user_id);
        if (empty($tokens)) {
            return;
        }

        $title = !empty($payload['type']) && $payload['type'] === 'maintenance_status_changed'
            ? 'تحديث طلب الصيانة'
            : 'إشعار جديد';

        self::send_to_tokens($tokens, $title, $payload['message'] ?? '', array(
            'notification_id' => absint($notification_id),
            'type' => sanitize_key($payload['type'] ?? 'system'),
            'related_id' => absint($payload['related_id'] ?? 0),
            'building_id' => absint($payload['building_id'] ?? 0),
            'link' => self::safe_notification_link($payload),
        ));
    }

    public static function register_token($user_id, $token, $platform = 'web')
    {
        $user_id = absint($user_id);
        $token = sanitize_text_field($token);
        $platform = sanitize_key($platform);
        if (!$user_id || empty($token) || strlen($token) > 4096) {
            return false;
        }

        $tokens = self::get_user_tokens($user_id);
        $tokens[$token] = array(
            'token' => $token,
            'platform' => $platform ?: 'web',
            'updated_at' => current_time('mysql'),
        );
        update_user_meta($user_id, self::TOKEN_META_KEY, array_slice($tokens, -10, 10, true));
        return true;
    }

    public static function unregister_token($user_id, $token)
    {
        $user_id = absint($user_id);
        $token = sanitize_text_field($token);
        $tokens = self::get_user_tokens($user_id);
        if (isset($tokens[$token])) {
            unset($tokens[$token]);
            update_user_meta($user_id, self::TOKEN_META_KEY, $tokens);
        }
        return true;
    }

    private static function get_user_tokens($user_id)
    {
        $raw = get_user_meta(absint($user_id), self::TOKEN_META_KEY, true);
        if (!is_array($raw)) {
            return array();
        }
        $tokens = array();
        foreach ($raw as $key => $value) {
            $token = is_array($value) ? ($value['token'] ?? $key) : $value;
            $token = sanitize_text_field($token);
            if ($token) {
                $tokens[$token] = is_array($value) ? $value : array('token' => $token, 'platform' => 'web');
            }
        }
        return $tokens;
    }

    private static function get_service_account()
    {
        $source = defined('MS_FIREBASE_SERVICE_ACCOUNT_JSON') ? MS_FIREBASE_SERVICE_ACCOUNT_JSON : '';
        if (!$source) {
            $source = getenv('MS_FIREBASE_SERVICE_ACCOUNT_JSON') ?: '';
        }
        if (!$source) {
            $source = get_option(self::SERVICE_ACCOUNT_OPTION, '');
        }
        if (!$source) {
            return array();
        }

        $trimmed = ltrim((string) $source);
        $json = (strpos($trimmed, '{') === 0) ? $source : (is_file($source) ? file_get_contents($source) : '');
        $data = json_decode($json, true);
        return is_array($data) ? $data : array();
    }

    private static function get_access_token($service_account)
    {
        $cached = get_transient('ms_firebase_access_token');
        if ($cached) {
            return $cached;
        }
        if (empty($service_account['client_email']) || empty($service_account['private_key'])) {
            return new WP_Error('firebase_credentials_missing', 'Firebase service account is not configured.');
        }

        $now = time();
        $header = self::base64url_encode(wp_json_encode(array('alg' => 'RS256', 'typ' => 'JWT')));
        $claims = self::base64url_encode(wp_json_encode(array(
            'iss' => $service_account['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        )));
        $unsigned = $header . '.' . $claims;
        $signature = '';
        if (!openssl_sign($unsigned, $signature, $service_account['private_key'], OPENSSL_ALGO_SHA256)) {
            return new WP_Error('firebase_signing_failed', 'Unable to sign Firebase OAuth assertion.');
        }

        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'timeout' => 15,
            'body' => array(
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $unsigned . '.' . self::base64url_encode($signature),
            ),
        ));
        if (is_wp_error($response)) {
            return $response;
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['access_token'])) {
            return new WP_Error('firebase_token_failed', 'Firebase OAuth token request failed.');
        }
        set_transient('ms_firebase_access_token', sanitize_text_field($body['access_token']), 3300);
        return $body['access_token'];
    }

    public static function send_to_tokens($tokens, $title, $body, $data = array())
    {
        $account = self::get_service_account();
        $project_id = defined('MS_FIREBASE_PROJECT_ID') ? MS_FIREBASE_PROJECT_ID : (getenv('MS_FIREBASE_PROJECT_ID') ?: ($account['project_id'] ?? ''));
        if (!$project_id || empty($account)) {
            return new WP_Error('firebase_not_configured', 'Firebase is not configured.');
        }

        $access_token = self::get_access_token($account);
        if (is_wp_error($access_token)) {
            return $access_token;
        }

        $results = array();
        foreach (array_unique(array_filter(array_map('sanitize_text_field', (array) $tokens))) as $token) {
            $response = wp_remote_post('https://fcm.googleapis.com/v1/projects/' . rawurlencode($project_id) . '/messages:send', array(
                'timeout' => 15,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json; UTF-8',
                ),
                'body' => wp_json_encode(array('message' => array(
                    'token' => $token,
                    'notification' => array('title' => sanitize_text_field($title), 'body' => wp_strip_all_tags($body)),
                    'data' => array_map('strval', (array) $data),
                    'webpush' => array('fcm_options' => array('link' => esc_url_raw($data['link'] ?? home_url('/')))),
                ))),
            ));
            $results[] = is_wp_error($response) ? $response : wp_remote_retrieve_response_code($response);
        }
        return $results;
    }

    private static function safe_notification_link($payload)
    {
        $request_id = absint($payload['related_id'] ?? 0);
        return $request_id ? home_url('/rent-dashboard/#maintenance-' . $request_id) : home_url('/rent-dashboard/#overview');
    }

    private static function base64url_encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

MS_Firebase_FCM_Provider::register_hooks();
