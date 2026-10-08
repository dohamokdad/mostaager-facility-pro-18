<?php
/**
 * Mostaager Facility PRO — PDF Engine
 *
 * نقطة واحدة لكل ما يخص PDF في البلجن:
 *  - تحميل TCPDF عند الحاجة فقط (lazy) بدل autoload الذي لم يكن موجوداً أصلاً.
 *  - مستند عربي RTL موحّد الهوية (ترويسة + تذييل بترقيم الصفحات).
 *  - تحويل أي بيانات تقرير (مصفوفات / صفوف / HTML) إلى PDF.
 *  - تخزين آمن للملفات المصدّرة خارج الوصول العام + رابط تحميل لمرة واحدة.
 *
 * @package Mostaager_Facility_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class MS_PDF {

    const FONT        = 'aealarabiya';
    const EXPORT_DIR  = 'ms-exports';
    const TOKEN_TTL   = 900; // 15 دقيقة
    const FILE_MAXAGE = 3600; // تُحذف الملفات بعد ساعة

    /** @var bool */
    private static $loaded = false;

    /* ------------------------------------------------------------------ *
     * Bootstrap
     * ------------------------------------------------------------------ */

    public static function init() {
        add_action('wp_ajax_ms_download_export', array(__CLASS__, 'ajax_download_export'));
        add_action('ms_pdf_cleanup_exports', array(__CLASS__, 'cleanup_exports'));

        if (!wp_next_scheduled('ms_pdf_cleanup_exports')) {
            wp_schedule_event(time() + 300, 'hourly', 'ms_pdf_cleanup_exports');
        }
    }

    /**
     * تحميل TCPDF عند الطلب فقط — لا يُحمَّل في أي صفحة لا تحتاجه.
     */
    public static function load() {
        if (self::$loaded || class_exists('TCPDF', false)) {
            self::$loaded = true;
            self::load_document_class();
            return true;
        }

        $base = MOSTAAGER_ENTERPRISE_PATH . 'vendor/tecnickcom/tcpdf/';
        if (!file_exists($base . 'tcpdf.php')) {
            return false;
        }

        // TCPDF 6.11+ يستخدم ثوابت cURL داخل تعريف الكلاس نفسه ← بدون الإضافة يحدث Fatal Error
        // يوقف الصفحة بالكامل. نتحقق مسبقاً ونرجع رسالة واضحة بدلاً من ذلك.
        if (!extension_loaded('curl') || !extension_loaded('mbstring')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[MS_PDF] TCPDF requires the PHP curl and mbstring extensions.');
            }
            return false;
        }

        if (!defined('K_TCPDF_EXTERNAL_CONFIG')) {
            define('K_TCPDF_EXTERNAL_CONFIG', true);
        }
        if (!defined('K_PATH_MAIN'))   define('K_PATH_MAIN', $base);
        if (!defined('K_PATH_URL'))    define('K_PATH_URL', '');
        if (!defined('K_PATH_FONTS'))  define('K_PATH_FONTS', $base . 'fonts/');
        if (!defined('K_PATH_CACHE'))  define('K_PATH_CACHE', trailingslashit(get_temp_dir()));
        if (!defined('K_PATH_IMAGES')) define('K_PATH_IMAGES', '');
        if (!defined('K_BLANK_IMAGE')) define('K_BLANK_IMAGE', '_blank.png');
        if (!defined('PDF_PAGE_FORMAT'))      define('PDF_PAGE_FORMAT', 'A4');
        if (!defined('PDF_PAGE_ORIENTATION')) define('PDF_PAGE_ORIENTATION', 'P');
        if (!defined('PDF_CREATOR'))          define('PDF_CREATOR', 'Mostaager Facility PRO');
        if (!defined('PDF_AUTHOR'))           define('PDF_AUTHOR', 'Mostaager');
        if (!defined('PDF_UNIT'))             define('PDF_UNIT', 'mm');
        if (!defined('PDF_MARGIN_HEADER'))    define('PDF_MARGIN_HEADER', 5);
        if (!defined('PDF_MARGIN_FOOTER'))    define('PDF_MARGIN_FOOTER', 10);
        if (!defined('PDF_MARGIN_TOP'))       define('PDF_MARGIN_TOP', 27);
        if (!defined('PDF_MARGIN_BOTTOM'))    define('PDF_MARGIN_BOTTOM', 25);
        if (!defined('PDF_MARGIN_LEFT'))      define('PDF_MARGIN_LEFT', 15);
        if (!defined('PDF_MARGIN_RIGHT'))     define('PDF_MARGIN_RIGHT', 15);
        if (!defined('PDF_FONT_NAME_MAIN'))   define('PDF_FONT_NAME_MAIN', self::FONT);
        if (!defined('PDF_FONT_SIZE_MAIN'))   define('PDF_FONT_SIZE_MAIN', 10);
        if (!defined('PDF_FONT_NAME_DATA'))   define('PDF_FONT_NAME_DATA', self::FONT);
        if (!defined('PDF_FONT_SIZE_DATA'))   define('PDF_FONT_SIZE_DATA', 8);
        if (!defined('PDF_FONT_MONOSPACED'))  define('PDF_FONT_MONOSPACED', 'courier');
        if (!defined('PDF_IMAGE_SCALE_RATIO')) define('PDF_IMAGE_SCALE_RATIO', 1.25);
        if (!defined('HEAD_MAGNIFICATION'))   define('HEAD_MAGNIFICATION', 1.1);
        if (!defined('K_CELL_HEIGHT_RATIO'))  define('K_CELL_HEIGHT_RATIO', 1.25);
        if (!defined('K_TITLE_MAGNIFICATION')) define('K_TITLE_MAGNIFICATION', 1.3);
        if (!defined('K_SMALL_RATIO'))        define('K_SMALL_RATIO', 2 / 3);
        if (!defined('K_THAI_TOPCHARS'))      define('K_THAI_TOPCHARS', false);
        if (!defined('K_TCPDF_CALLS_IN_HTML')) define('K_TCPDF_CALLS_IN_HTML', false);
        if (!defined('K_TCPDF_THROW_EXCEPTION_ERROR')) define('K_TCPDF_THROW_EXCEPTION_ERROR', true);
        if (!defined('K_TIMEZONE'))           define('K_TIMEZONE', wp_timezone_string() ?: 'UTC');

        require_once $base . 'tcpdf.php';
        self::$loaded = class_exists('TCPDF', false);

        if (self::$loaded) {
            self::load_document_class();
        }

        return self::$loaded;
    }

    private static function load_document_class() {
        if (!class_exists('MS_PDF_Document', false)) {
            require_once __DIR__ . '/pdf/class-ms-pdf-document.php';
        }
    }

    /**
     * مستند جديد بالإعدادات الموحّدة. يرجع WP_Error إذا تعذّر تحميل المكتبة.
     */
    public static function create($title = '') {
        if (!self::load()) {
            return new WP_Error('ms_pdf_unavailable', 'تعذّر تحميل محرك PDF. تأكد من وجود مجلد vendor/tecnickcom/tcpdf وتفعيل إضافتي PHP: curl و mbstring.');
        }

        $pdf = new MS_PDF_Document('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Mostaager Facility PRO');
        $pdf->SetAuthor(self::brand('name'));
        if ($title) {
            $pdf->SetTitle($title);
        }
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->setFooterMargin(10);
        $pdf->setRTL(true);
        $pdf->SetMargins(12, 12, 12);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->SetFont(self::FONT, '', 10);

        return $pdf;
    }

    /* ------------------------------------------------------------------ *
     * Branding (قابلة للتعديل من wp_options بدون لمس الكود)
     * ------------------------------------------------------------------ */

    public static function brand($key) {
        $defaults = array(
            'name'    => 'منصة مستأجر العقاري',
            'tagline' => 'نظام إدارة المرافق والعقارات',
            'contact' => 'info@ejar-egy.com | 01010756695 | www.ejar-egy.com',
        );
        $saved = get_option('ms_pdf_branding', array());
        $value = is_array($saved) && !empty($saved[$key]) ? $saved[$key] : ($defaults[$key] ?? '');

        return apply_filters('ms_pdf_brand_' . $key, $value);
    }

    /**
     * الترويسة الزرقاء الموحّدة لكل المستندات.
     */
    public static function render_header($pdf, $badge, $sub_badge = '') {
        $pdf->SetFillColor(13, 27, 42);
        $pdf->Rect(0, 0, 210, 38, 'F');

        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont(self::FONT, 'B', 18);
        $pdf->SetXY(15, 8);
        $pdf->Cell(100, 10, self::brand('name'), 0, 0, 'R');

        $pdf->SetFont(self::FONT, '', 10);
        $pdf->SetXY(15, 19);
        $pdf->Cell(100, 6, self::brand('tagline'), 0, 0, 'R');

        $pdf->SetFont(self::FONT, '', 8);
        $pdf->SetXY(15, 26);
        $pdf->Cell(100, 5, self::ltr(self::brand('contact')), 0, 0, 'R');

        $pdf->SetFillColor(212, 175, 55);
        $pdf->SetTextColor(13, 27, 42);
        $pdf->SetFont(self::FONT, 'B', 13);
        $pdf->SetXY(135, 10);
        $pdf->Cell(60, 10, $badge, 0, 1, 'C', true);

        if ($sub_badge !== '') {
            $pdf->SetFont(self::FONT, '', 10);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetXY(135, 21);
            $pdf->Cell(60, 7, $sub_badge, 0, 1, 'C');
        }

        $pdf->SetFillColor(212, 175, 55);
        $pdf->Rect(0, 38, 210, 1.5, 'F');

        $pdf->SetTextColor(40, 40, 40);
        $pdf->SetFont(self::FONT, '', 10);
        $pdf->SetY(46);
    }

    /* ------------------------------------------------------------------ *
     * Reports
     * ------------------------------------------------------------------ */

    /**
     * يبني PDF تقرير من بيانات عامة (مصفوفة) ويحفظه بشكل آمن.
     *
     * @return array { success, download_url, filename } | { success:false, error }
     */
    public static function export_report($data, $title, $meta = array()) {
        $pdf = self::create($title);
        if (is_wp_error($pdf)) {
            return array('success' => false, 'error' => $pdf->get_error_message());
        }

        $html = self::data_to_html($data);

        return self::build_and_store($pdf, $title, $html, $meta);
    }

    /**
     * يبني PDF تقرير من HTML جاهز (مثلاً جداول تقارير Houzez).
     */
    public static function export_html($html, $title, $meta = array()) {
        $pdf = self::create($title);
        if (is_wp_error($pdf)) {
            return array('success' => false, 'error' => $pdf->get_error_message());
        }

        $html = preg_replace('#<h2\b[^>]*>.*?</h2>#is', '', (string) $html, 1);

        return self::build_and_store($pdf, $title, self::clean_html($html), $meta);
    }

    private static function build_and_store($pdf, $title, $html, $meta) {
        try {
            $pdf->AddPage();
            self::render_header($pdf, 'تقرير', date_i18n('j F Y'));

            $pdf->SetFont(self::FONT, 'B', 15);
            $pdf->SetTextColor(13, 27, 42);
            $pdf->Cell(0, 10, self::strip_emoji($title), 0, 1, 'R');

            if (!empty($meta)) {
                $pdf->SetFont(self::FONT, '', 9);
                $pdf->SetTextColor(100, 100, 100);
                foreach ($meta as $label => $value) {
                    $pdf->Cell(0, 6, $label . ': ' . (preg_match('/\p{Arabic}/u', (string) $value) ? $value : self::ltr($value)), 0, 1, 'R');
                }
            }

            $pdf->Ln(3);
            $pdf->SetFont(self::FONT, '', 10);
            $pdf->SetTextColor(40, 40, 40);

            if (trim(wp_strip_all_tags($html)) === '') {
                $html = '<p>لا توجد بيانات لعرضها في هذا التقرير.</p>';
            }

            $pdf->writeHTML($html, true, false, true, false, '');

            $content = $pdf->Output('', 'S');
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[MS_PDF] ' . $e->getMessage());
            }
            return array('success' => false, 'error' => 'تعذّر إنشاء ملف PDF.');
        }

        $filename = sanitize_file_name(self::slug($title) . '-' . wp_date('Y-m-d') . '.pdf');

        return self::store($content, $filename, 'application/pdf');
    }

    /**
     * يحوّل بيانات التقرير (أي شكل) إلى HTML جدولي نظيف مناسب لـ TCPDF.
     */
    public static function data_to_html($data, $depth = 0) {
        if (is_object($data)) {
            $data = json_decode(wp_json_encode($data), true);
        }
        if (!is_array($data) || empty($data)) {
            return '';
        }

        // قائمة صفوف ← جدول
        if (self::is_list_of_rows($data)) {
            return self::rows_to_table(array_values($data));
        }

        $html    = '';
        $scalars = array();

        foreach ($data as $key => $value) {
            if (is_object($value)) {
                $value = json_decode(wp_json_encode($value), true);
            }

            if (is_array($value)) {
                if ($depth >= 3 || empty($value)) {
                    continue;
                }
                $html .= '<h3 style="color:#0D1B2A;font-size:12pt;">' . esc_html(self::label($key)) . '</h3>';
                $html .= self::data_to_html($value, $depth + 1);
                $html .= '<br/>';
            } else {
                $scalars[$key] = $value;
            }
        }

        if (!empty($scalars)) {
            $html = self::key_value_table($scalars) . '<br/>' . $html;
        }

        return $html;
    }

    private static function is_list_of_rows($data) {
        if (array_keys($data) !== range(0, count($data) - 1)) {
            return false;
        }
        foreach ($data as $row) {
            if (!is_array($row) && !is_object($row)) {
                return false;
            }
        }
        return true;
    }

    private static function rows_to_table($rows) {
        $columns = array();
        foreach ($rows as $row) {
            foreach ((array) $row as $k => $v) {
                if (is_scalar($v) || $v === null) {
                    $columns[$k] = true;
                }
            }
        }
        // حدّ أقصى 7 أعمدة حتى يبقى الجدول مقروءاً على A4
        $columns = array_slice(array_keys($columns), 0, 7);
        if (empty($columns)) {
            return '';
        }

        $html  = '<table cellpadding="5" border="0.3" style="border-color:#d1d5db;">';
        $html .= '<tr style="background-color:#0D1B2A;color:#ffffff;font-weight:bold;">';
        foreach ($columns as $col) {
            $html .= '<th align="center">' . esc_html(self::label($col)) . '</th>';
        }
        $html .= '</tr>';

        $i = 0;
        foreach ($rows as $row) {
            $row = (array) $row;
            $bg  = ($i++ % 2) ? '#f8fafc' : '#ffffff';
            $html .= '<tr style="background-color:' . $bg . ';">';
            foreach ($columns as $col) {
                $html .= '<td align="center">' . self::format_value($col, $row[$col] ?? '') . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table>';

        return $html;
    }

    private static function key_value_table($pairs) {
        $html = '<table cellpadding="5" border="0.3" style="border-color:#d1d5db;">';
        $i = 0;
        foreach ($pairs as $k => $v) {
            $bg = ($i++ % 2) ? '#f8fafc' : '#ffffff';
            $html .= '<tr style="background-color:' . $bg . ';">';
            $html .= '<td width="45%" style="color:#0D1B2A;font-weight:bold;">' . esc_html(self::label($k)) . '</td>';
            $html .= '<td width="55%">' . self::format_value($k, $v) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';
        return $html;
    }

    /**
     * أسماء أعمدة عربية للمفاتيح الشائعة في قاعدة البيانات.
     */
    public static function label($key) {
        static $map = null;
        if ($map === null) {
            $map = array(
                'id' => '#', 'invoice_number' => 'رقم الفاتورة', 'title' => 'العنوان',
                'description' => 'الوصف', 'amount' => 'المبلغ', 'total' => 'الإجمالي',
                'status' => 'الحالة', 'priority' => 'الأولوية', 'due_date' => 'تاريخ الاستحقاق',
                'paid_date' => 'تاريخ الدفع', 'created_at' => 'تاريخ الإنشاء', 'updated_at' => 'آخر تحديث',
                'building_id' => 'المبنى', 'building_name' => 'المبنى', 'building_title' => 'المبنى',
                'unit_id' => 'الوحدة', 'unit_number' => 'رقم الوحدة', 'user_id' => 'المستخدم',
                'tenant_id' => 'المستأجر', 'owner_id' => 'المالك', 'month' => 'الشهر',
                'paid' => 'المدفوع', 'pending' => 'المعلّق', 'overdue' => 'المتأخر',
                'count' => 'العدد', 'cost' => 'التكلفة', 'invoice_type' => 'نوع الفاتورة',
                'expense_type' => 'نوع المصروف', 'income' => 'الإيرادات', 'expenses' => 'المصروفات',
                'profit' => 'صافي الربح', 'summary' => 'الملخص', 'requests' => 'الطلبات',
                'invoices' => 'الفواتير', 'metrics' => 'المؤشرات', 'total_revenue' => 'إجمالي الإيرادات',
                'total_invoices' => 'عدد الفواتير', 'total_maintenance' => 'إجمالي الصيانة',
                'active_buildings' => 'المباني النشطة', 'active_tenants' => 'المستأجرون النشطون',
                'occupancy_rate' => 'نسبة الإشغال', 'collection_rate' => 'نسبة التحصيل',
            );
        }
        $key = (string) $key;
        if (isset($map[$key])) {
            return $map[$key];
        }
        return is_numeric($key) ? '#' . ((int) $key + 1) : ucwords(str_replace(array('_', '-'), ' ', $key));
    }

    /**
     * قيمة جاهزة للعرض داخل HTML (writeHTML) — الأرقام تُعزل بـ span dir=ltr.
     */
    public static function format_value($key, $value) {
        if ($value === null || $value === '') {
            return '-';
        }
        if (is_bool($value)) {
            return $value ? 'نعم' : 'لا';
        }
        if ($key === 'status' || $key === 'priority') {
            return esc_html(self::status_label($value));
        }
        if (is_numeric($value) && self::is_money_key($key)) {
            return self::ltr_html(number_format((float) $value, 2)) . ' ج.م';
        }
        $value = self::strip_emoji((string) $value);
        if ($value !== '' && !preg_match('/\p{Arabic}/u', $value)) {
            return self::ltr_html($value);
        }
        return esc_html($value);
    }

    private static function is_money_key($key) {
        $key = strtolower((string) $key);
        if (preg_match('/count|number|_invoices$|^invoices|rate|percent|_id$|^id$/', $key)) {
            return false;
        }
        return (bool) preg_match('/amount|cost|income|expense|profit|revenue|balance|^paid$|^pending$|^overdue$|^total$|collected|due$/', $key);
    }

    public static function ltr_html($text) {
        return '<span dir="ltr">' . esc_html($text) . '</span>';
    }

    public static function status_label($status) {
        $labels = array(
            'paid' => 'مسددة', 'pending' => 'معلّقة', 'overdue' => 'متأخرة',
            'cancelled' => 'ملغاة', 'canceled' => 'ملغاة', 'partial' => 'مسددة جزئياً',
            'new' => 'جديد', 'in_progress' => 'قيد التنفيذ', 'completed' => 'مكتمل',
            'closed' => 'مغلق', 'high' => 'عالية', 'medium' => 'متوسطة', 'low' => 'منخفضة',
            'occupied' => 'مشغولة', 'vacant' => 'شاغرة', 'maintenance' => 'صيانة', 'reserved' => 'محجوزة',
            'active' => 'نشط', 'inactive' => 'غير نشط', 'approved' => 'معتمد', 'rejected' => 'مرفوض',
        );
        $key = strtolower((string) $status);
        return $labels[$key] ?? (string) $status;
    }

    /**
     * ينظّف HTML قادم من صفحات الإدارة: يحذف الأزرار والسكربتات والأنماط التي لا يفهمها TCPDF.
     */
    public static function clean_html($html) {
        $html = preg_replace('#<(script|style|button|form|input|select)\b[^>]*>.*?</\1>#is', '', $html);
        $html = preg_replace('#<(button|input|select)\b[^>]*/?>#is', '', $html);
        $html = preg_replace('/\sclass="[^"]*"/i', '', $html);
        $html = preg_replace('/\sonclick="[^"]*"/i', '', $html);
        // الجداول: ترويسة موحّدة الهوية
        $html = preg_replace('/<table\b[^>]*>/i', '<table cellpadding="5" border="0.3" style="border-color:#d1d5db;">', $html);
        $html = preg_replace('/<th\b[^>]*>/i', '<th align="center" style="background-color:#0D1B2A;color:#ffffff;font-weight:bold;">', $html);
        $html = preg_replace('/<td\b[^>]*>/i', '<td align="center">', $html);
        $html = preg_replace('/<h2\b[^>]*>/i', '<h2 style="color:#0D1B2A;font-size:13pt;">', $html);
        // إزالة thead/tbody (TCPDF يتعامل مع tr مباشرة بشكل أدق)
        $html = preg_replace('#</?(thead|tbody|tfoot)\b[^>]*>#i', '', $html);

        // الأرقام داخل النصوص (مثل 3,000.00) تنقلب في RTL ← نعزلها
        $html = preg_replace_callback('/>([^<]+)</u', function ($m) {
            $text = preg_replace('/(\d[\d.,:\/\-]*\d%?|\d%?)/u', '<span dir="ltr">$1</span>', $m[1]);
            return '>' . $text . '<';
        }, $html);

        return self::strip_emoji($html);
    }

    /**
     * يعزل نصاً لاتينياً/رقمياً داخل سطر عربي حتى لا تنقلب أجزاؤه
     * (مثلاً 8,500.00 كانت تظهر 500.00,8 و INV-2026-0042 تظهر 0042-2026-INV).
     */
    public static function ltr($text) {
        return "\u{202A}" . $text . "\u{202C}";
    }

    public static function money($amount) {
        return self::ltr(number_format((float) $amount, 2)) . ' ج.م';
    }

    /**
     * خط aealarabiya لا يحتوي رموز Emoji — تظهر كمربعات فارغة إن بقيت.
     */
    public static function strip_emoji($text) {
        $text = str_replace(array('—', '–', '…'), array('-', '-', '...'), (string) $text);
        return trim(preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{200D}]/u', '', $text));
    }

    private static function slug($title) {
        $slug = sanitize_title(self::strip_emoji($title));
        return $slug ?: 'report';
    }

    /* ------------------------------------------------------------------ *
     * Secure export storage
     * ------------------------------------------------------------------ *
     * المشكلة القديمة: التقارير كانت تُكتب في wp-content/uploads/YYYY/MM/
     * بأسماء متوقعة (financial_report_2026-09-22.csv) — أي شخص يخمّن الاسم يحمّلها.
     * الحل: مجلد محمي + اسم عشوائي + رابط مؤقت مربوط بالمستخدم نفسه.
     */

    public static function export_dir() {
        $upload = wp_upload_dir();
        $dir    = trailingslashit($upload['basedir']) . self::EXPORT_DIR;

        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        if (!file_exists($dir . '/.htaccess')) {
            @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
        }
        if (!file_exists($dir . '/index.php')) {
            @file_put_contents($dir . '/index.php', "<?php // Silence is golden.\n");
        }

        return $dir;
    }

    /**
     * يحفظ محتوى ملف ويرجع رابط تحميل مؤقت خاص بالمستخدم الحالي.
     */
    public static function store($content, $filename, $mime) {
        $dir  = self::export_dir();
        $file = $dir . '/' . wp_generate_password(24, false, false) . '.bin';

        if (false === @file_put_contents($file, $content)) {
            return array('success' => false, 'error' => 'تعذّر حفظ الملف المصدَّر.');
        }

        $token = wp_generate_password(32, false, false);
        set_transient('ms_export_' . $token, array(
            'user_id'  => get_current_user_id(),
            'file'     => $file,
            'filename' => $filename,
            'mime'     => $mime,
        ), self::TOKEN_TTL);

        return array(
            'success'      => true,
            'download_url' => add_query_arg(array(
                'action' => 'ms_download_export',
                'token'  => $token,
            ), admin_url('admin-ajax.php')),
            'filename'     => $filename,
        );
    }

    public static function ajax_download_export() {
        if (!is_user_logged_in()) {
            wp_die('يجب تسجيل الدخول.', '', array('response' => 401));
        }

        $token = isset($_GET['token']) ? preg_replace('/[^A-Za-z0-9]/', '', wp_unslash($_GET['token'])) : '';
        $entry = $token ? get_transient('ms_export_' . $token) : false;

        if (!$entry || (int) $entry['user_id'] !== get_current_user_id()) {
            wp_die('انتهت صلاحية رابط التحميل. أعد التصدير.', '', array('response' => 403));
        }

        $real_dir  = realpath(self::export_dir());
        $real_file = realpath($entry['file']);
        if (!$real_file || !$real_dir || strpos($real_file, $real_dir) !== 0 || !is_readable($real_file)) {
            wp_die('الملف غير موجود.', '', array('response' => 404));
        }

        delete_transient('ms_export_' . $token);
        self::send_file(file_get_contents($real_file), $entry['filename'], $entry['mime']);
        @unlink($real_file);
        exit;
    }

    /**
     * يرسل ملفاً للمتصفح مع ترويسات صحيحة لأسماء الملفات العربية.
     */
    public static function send_file($content, $filename, $mime, $inline = false) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        nocache_headers();
        $ascii = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . strlen($content));
        header('X-Content-Type-Options: nosniff');
        header(sprintf(
            'Content-Disposition: %s; filename="%s"; filename*=UTF-8\'\'%s',
            $inline ? 'inline' : 'attachment',
            $ascii,
            rawurlencode($filename)
        ));
        echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- binary file
    }

    public static function cleanup_exports() {
        $dir = self::export_dir();
        foreach ((array) glob($dir . '/*.bin') as $file) {
            if (is_file($file) && (time() - filemtime($file)) > self::FILE_MAXAGE) {
                @unlink($file);
            }
        }
    }
}

MS_PDF::init();
