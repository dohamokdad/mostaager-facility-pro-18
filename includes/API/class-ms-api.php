<?php
/**
 * Mostaager — Shared REST layer (Phase 7)
 *
 * طبقة واحدة للاستجابة والترقيم والتفويض تخدم المسارين:
 *   mfp/v1      (JWT — التطبيقات الخارجية والموبايل)
 *   mostager/v1 (كوكي ووردبريس + X-WP-Nonce — واجهات داخل الموقع، deprecated)
 *
 * الهدف: منطق صلاحيات وشكل استجابة واحد بدل نسختين تتباعدان مع الوقت.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class MS_API {

    /** المسار القديم: يبقى يعمل، مع ترويسة تنبيه وتاريخ إيقاف مقترح */
    const DEPRECATED_NAMESPACE = 'mostager/v1';
    const SUNSET_DATE          = '2027-01-01';

    public static function init() {
        add_filter('rest_post_dispatch', array(__CLASS__, 'normalize_legacy_response'), 10, 3);
    }

    /* ------------------------------------------------------------------ *
     * الاستجابة الموحّدة
     * ------------------------------------------------------------------ */

    public static function success($data, $meta = array(), $status = 200) {
        $body = array('success' => true, 'data' => $data);
        if (!empty($meta)) {
            $body['meta'] = $meta;
        }
        return new WP_REST_Response($body, $status);
    }

    public static function error($code, $message, $status = 400, $details = array()) {
        return new WP_REST_Response(array(
            'success' => false,
            'code'    => sanitize_key($code),
            'message' => $message,
            'details' => (object) $details,
        ), $status);
    }

    /* ------------------------------------------------------------------ *
     * الترقيم
     * ------------------------------------------------------------------ */

    public static function paging($request, $default_per_page = 20) {
        $page     = max(1, absint($request->get_param('page')));
        $per_page = absint($request->get_param('per_page')) ?: $default_per_page;
        $per_page = min(100, max(1, $per_page));

        return array($page, $per_page, ($page - 1) * $per_page);
    }

    public static function paginate($items, $page, $per_page) {
        $items = array_values((array) $items);
        $total = count($items);

        return array(
            array_slice($items, ($page - 1) * $per_page, $per_page),
            array(
                'page'        => $page,
                'per_page'    => $per_page,
                'total'       => $total,
                'total_pages' => (int) ceil($total / max(1, $per_page)),
            ),
        );
    }

    /* ------------------------------------------------------------------ *
     * التفويض
     * ------------------------------------------------------------------ */

    /** المستخدم الحالي: من توكن JWT إن وُجد، وإلا من جلسة ووردبريس */
    public static function current_user_id($request = null) {
        if ($request instanceof WP_REST_Request) {
            $jwt_user = absint($request->get_param('jwt_user_id'));
            if ($jwt_user) {
                return $jwt_user;
            }
        }
        return get_current_user_id();
    }

    public static function can_access_building($user_id, $building_id) {
        $user_id     = absint($user_id);
        $building_id = absint($building_id);
        if (!$user_id || !$building_id) {
            return false;
        }
        if (user_can($user_id, 'manage_options')) {
            return true;
        }
        if (function_exists('ms_user_can_access_building')) {
            return ms_user_can_access_building($user_id, $building_id);
        }
        return function_exists('ms_current_user_manages_building')
            && ms_current_user_manages_building($user_id, $building_id);
    }

    public static function can_access_invoice($user_id, $invoice_id) {
        return function_exists('ms_user_can_access_invoice')
            ? ms_user_can_access_invoice($user_id, $invoice_id)
            : user_can($user_id, 'manage_options');
    }

    /* ------------------------------------------------------------------ *
     * توحيد المسار القديم بدون إعادة كتابته
     * ------------------------------------------------------------------ */

    /**
     * يلفّ ردود mostager/v1 بنفس الغلاف ({success,data} أو {success,code,message})
     * ويضيف ترويسات إيقاف تدريجي، فيتعامل تطبيق الموبايل مع شكل واحد فقط.
     */
    public static function normalize_legacy_response($response, $server, $request) {
        if (!($response instanceof WP_REST_Response) || !($request instanceof WP_REST_Request)) {
            return $response;
        }
        if (strpos(ltrim($request->get_route(), '/'), self::DEPRECATED_NAMESPACE) !== 0) {
            return $response;
        }

        $response->header('X-MS-API-Deprecated', 'true');
        $response->header('X-MS-API-Successor', 'mfp/v1');
        $response->header('Sunset', self::SUNSET_DATE);

        $data   = $response->get_data();
        $status = $response->get_status();

        if (is_array($data) && isset($data['success'])) {
            return $response; // ملفوف مسبقاً
        }

        if ($status >= 400) {
            $response->set_data(array(
                'success' => false,
                'code'    => isset($data['code']) ? sanitize_key($data['code']) : 'error',
                'message' => isset($data['message']) ? $data['message'] : 'حدث خطأ',
                'details' => (object) array(),
            ));
            return $response;
        }

        $response->set_data(array('success' => true, 'data' => $data));

        return $response;
    }
}

MS_API::init();
