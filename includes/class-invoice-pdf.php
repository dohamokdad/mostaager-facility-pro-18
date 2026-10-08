<?php
/**
 * Mostaager Facility PRO — Invoice PDF
 *
 * فاتورة عربية RTL بهوية المنصة. تعتمد على محرك MS_PDF (includes/class-ms-pdf.php).
 *
 * نقاط الدخول:
 *  - GET  admin-ajax.php?action=ms_download_invoice&id=ID&_wpnonce=...   (رابط تحميل مباشر)
 *  - POST admin-ajax.php  action=mostager_email_invoice                  (إرسال بالبريد)
 *  - ms_invoice_pdf_url($id)  ← استخدمها في أي قالب لتوليد رابط التحميل
 *
 * إعدادات اختيارية (wp_options):
 *  - ms_invoice_vat_rate       نسبة الضريبة (0 افتراضياً). المبلغ المخزَّن يُعامَل كإجمالي شامل.
 *  - ms_invoice_bank_details   نص بيانات التحويل البنكي. إن كان فارغاً لا يظهر الصندوق.
 *  - ms_invoice_payment_terms  نص شروط السداد.
 *
 * @package Mostaager_Facility_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mostager_Invoice_PDF {

    /**
     * @param int    $invoice_id
     * @param string $output 'D' تحميل | 'I' عرض | 'S' نص | 'F' حفظ في $path
     * @param string $path   مسار الحفظ عند 'F'
     * @return string|bool|WP_Error
     */
    public function generate_invoice($invoice_id, $output = 'D', $path = '') {
        $invoice = self::get_invoice($invoice_id);
        if (!$invoice) {
            return new WP_Error('not_found', 'الفاتورة غير موجودة');
        }

        $number = self::number($invoice);
        $pdf    = MS_PDF::create('فاتورة ' . $number);
        if (is_wp_error($pdf)) {
            return $pdf;
        }

        $totals = self::totals($invoice);

        try {
            $pdf->SetSubject(self::type_label($invoice) . ' - ' . ($invoice->building_name ?: ''));
            $pdf->AddPage();

            MS_PDF::render_header($pdf, self::type_label($invoice), 'رقم: ' . MS_PDF::ltr($number));
            $this->render_info($pdf, $invoice);
            $this->render_items($pdf, $invoice, $totals);
            $this->render_totals($pdf, $totals);
            $this->render_notes($pdf);
            $this->render_qr($pdf, $invoice, $number, $totals['total']);

            $content = $pdf->Output('', 'S');
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[MS Invoice PDF] ' . $e->getMessage());
            }
            return new WP_Error('pdf_failed', 'تعذّر إنشاء ملف الفاتورة.');
        }

        $filename = 'فاتورة-' . $number . '.pdf';

        switch ($output) {
            case 'S':
                return $content;
            case 'F':
                return $path && false !== file_put_contents($path, $content);
            case 'I':
                MS_PDF::send_file($content, $filename, 'application/pdf', true);
                return true;
            case 'D':
            default:
                MS_PDF::send_file($content, $filename, 'application/pdf');
                return true;
        }
    }

    /* ------------------------------------------------------------------ */

    public static function get_invoice($invoice_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT i.*, b.title AS building_name, b.address AS building_address,
                    u.unit_number, u.floor, usr.display_name AS customer_name
             FROM {$wpdb->prefix}ms_invoices i
             LEFT JOIN {$wpdb->prefix}ms_buildings b ON i.building_id = b.id
             LEFT JOIN {$wpdb->prefix}ms_units u ON i.unit_id = u.id
             LEFT JOIN {$wpdb->users} usr ON i.user_id = usr.ID
             WHERE i.id = %d",
            absint($invoice_id)
        ));
    }

    private static function number($invoice) {
        return !empty($invoice->invoice_number) ? $invoice->invoice_number : (string) absint($invoice->id);
    }

    /**
     * كان الكود القديم يضيف 14% فوق المبلغ ← إجمالي PDF لا يطابق المبلغ المطلوب دفعه.
     * الآن: المبلغ المخزَّن هو الإجمالي دائماً، والضريبة (إن وُجدت) تُعرض كجزء منه.
     */
    private static function totals($invoice) {
        $total = round((float) ($invoice->amount ?? 0), 2);
        $rate  = (float) get_option('ms_invoice_vat_rate', 0);
        $rate  = $rate > 1 ? $rate / 100 : $rate; // يقبل 14 أو 0.14

        if ($rate > 0) {
            $subtotal = round($total / (1 + $rate), 2);
            $tax      = round($total - $subtotal, 2);
        } else {
            $subtotal = $total;
            $tax      = 0.0;
        }

        return compact('subtotal', 'tax', 'total', 'rate');
    }

    private static function type_label($invoice) {
        $type = strtolower((string) ($invoice->invoice_type ?? '') . ' ' . (string) ($invoice->invoice_category ?? ''));
        if (strpos($type, 'subscription') !== false || strpos($type, 'agent-fees') !== false) return 'فاتورة اشتراك';
        if (strpos($type, 'maintenance') !== false) return 'فاتورة صيانة';
        if (strpos($type, 'sale') !== false)        return 'فاتورة بيع';
        if (strpos($type, 'rent') !== false)        return 'فاتورة إيجار';
        return 'فاتورة مصاريف مرافق';
    }

    private static function date($value) {
        if (empty($value) || strpos((string) $value, '0000') === 0) {
            return '-';
        }
        $ts = strtotime($value);
        if (!$ts) {
            return '-';
        }
        $out = date_i18n('j F Y', $ts);
        return preg_match('/\p{Arabic}/u', $out) ? $out : MS_PDF::ltr($out);
    }

    /* ------------------------------------------------------------------ */

    private function render_info($pdf, $invoice) {
        $pdf->SetTextColor(13, 27, 42);
        $pdf->SetFont(MS_PDF::FONT, 'B', 12);
        $pdf->Cell(0, 8, 'معلومات الفاتورة', 0, 1, 'R');

        $w  = 93;
        $lh = 7;
        $pdf->SetFont(MS_PDF::FONT, '', 10);
        $pdf->SetTextColor(70, 70, 70);

        $unit = $invoice->unit_number ? MS_PDF::ltr($invoice->unit_number) : '-';
        if (!empty($invoice->floor)) {
            $unit .= ' (الدور ' . $invoice->floor . ')';
        }

        $rows = array(
            array('العميل: ' . ($invoice->customer_name ?: '-'), 'تاريخ الإصدار: ' . self::date($invoice->created_at ?? '')),
            array('المبنى: ' . ($invoice->building_name ?: '-'), 'تاريخ الاستحقاق: ' . self::date($invoice->due_date ?? '')),
            array('العنوان: ' . ($invoice->building_address ?: '-'), 'حالة الدفع: ' . MS_PDF::status_label($invoice->status ?? '')),
            array('الوحدة: ' . $unit, !empty($invoice->paid_date) ? 'تاريخ الدفع: ' . self::date($invoice->paid_date) : ''),
        );

        foreach ($rows as $row) {
            $pdf->Cell($w, $lh, $row[0], 0, 0, 'R');
            $pdf->Cell($w, $lh, $row[1], 0, 1, 'R');
        }

        $pdf->Ln(4);
        $pdf->SetDrawColor(212, 175, 55);
        $pdf->Line(12, $pdf->GetY(), 198, $pdf->GetY());
        $pdf->Ln(5);
    }

    private function render_items($pdf, $invoice, $totals) {
        $desc = !empty($invoice->description) ? wp_strip_all_tags($invoice->description) : self::type_label($invoice);

        $pdf->SetTextColor(13, 27, 42);
        $pdf->SetFont(MS_PDF::FONT, 'B', 11);
        $pdf->Cell(0, 8, 'التفاصيل', 0, 1, 'R');

        $html  = '<table cellpadding="6" border="0.3" style="border-color:#d1d5db;">';
        $html .= '<tr style="background-color:#0D1B2A;color:#ffffff;font-weight:bold;">'
               . '<th width="55%" align="center">البيان</th>'
               . '<th width="15%" align="center">الكمية</th>'
               . '<th width="30%" align="center">المبلغ</th></tr>';
        $html .= '<tr><td>' . esc_html($desc) . '</td>'
               . '<td align="center">1</td>'
               . '<td align="center">' . esc_html(MS_PDF::money($totals['subtotal'])) . '</td></tr>';
        $html .= '</table>';

        $pdf->SetFont(MS_PDF::FONT, '', 10);
        $pdf->SetTextColor(50, 50, 50);
        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Ln(2);
    }

    private function render_totals($pdf, $totals) {
        $label = 55;
        $value = 40;

        if ($totals['tax'] > 0) {
            $pct = rtrim(rtrim(number_format($totals['rate'] * 100, 2), '0'), '.');
            $lines = array(
                array('المبلغ قبل الضريبة:', $totals['subtotal']),
                array('ضريبة القيمة المضافة (' . $pct . '%):', $totals['tax']),
            );
            foreach ($lines as $line) {
                $pdf->SetX(103);
                $pdf->SetFont(MS_PDF::FONT, '', 10);
                $pdf->SetTextColor(80, 80, 80);
                $pdf->Cell($label, 8, $line[0], 0, 0, 'R');
                $pdf->SetFont(MS_PDF::FONT, 'B', 10);
                $pdf->SetTextColor(13, 27, 42);
                $pdf->Cell($value, 8, MS_PDF::money($line[1]), 0, 1, 'L');
            }
        }

        $pdf->SetX(103);
        $pdf->SetFillColor(212, 175, 55);
        $pdf->SetTextColor(13, 27, 42);
        $pdf->SetFont(MS_PDF::FONT, 'B', 13);
        $pdf->Cell($label, 12, 'الإجمالي المستحق:', 0, 0, 'R', true);
        $pdf->Cell($value, 12, MS_PDF::money($totals['total']), 0, 1, 'L', true);
        $pdf->Ln(6);
    }

    private function render_notes($pdf) {
        $default_terms = "شروط السداد:\n"
            . "- يرجى سداد الفاتورة قبل تاريخ الاستحقاق.\n"
            . "- للاستفسارات يرجى التواصل مع إدارة المبنى أو خدمة العملاء.";
        $terms = trim((string) get_option('ms_invoice_payment_terms', $default_terms));

        if ($terms !== '') {
            $pdf->SetTextColor(100, 100, 100);
            $pdf->SetFont(MS_PDF::FONT, '', 9);
            $pdf->MultiCell(0, 6, MS_PDF::strip_emoji($terms), 0, 'R');
        }

        $bank = trim((string) get_option('ms_invoice_bank_details', ''));
        if ($bank !== '') {
            $pdf->Ln(3);
            $pdf->SetFillColor(248, 249, 250);
            $pdf->SetDrawColor(212, 175, 55);
            $pdf->SetTextColor(13, 27, 42);
            $pdf->SetFont(MS_PDF::FONT, 'B', 9);
            $pdf->Cell(0, 8, 'بيانات التحويل البنكي', 1, 1, 'C', true);
            $pdf->SetFont(MS_PDF::FONT, '', 9);
            $pdf->SetTextColor(80, 80, 80);
            $pdf->MultiCell(0, 6, MS_PDF::strip_emoji($bank), 1, 'C', true);
        }
    }

    private function render_qr($pdf, $invoice, $number, $total) {
        if (!method_exists($pdf, 'write2DBarcode')) {
            return;
        }
        $payload = wp_json_encode(array(
            'invoice' => $number,
            'amount'  => number_format($total, 2, '.', ''),
            'date'    => substr((string) ($invoice->created_at ?? ''), 0, 10),
        ));

        // ضع الرمز في أسفل يسار الصفحة بدون تداخل مع المحتوى
        $y = max($pdf->GetY() + 4, 240);
        if ($y > 252) {
            $pdf->AddPage();
            $y = 20;
        }
        $pdf->write2DBarcode($payload, 'QRCODE,M', 168, $y, 28, 28, array(), 'N');
    }

    /* ------------------------------------------------------------------ */

    public function email_invoice($invoice_id, $email) {
        $content = $this->generate_invoice($invoice_id, 'S');
        if (is_wp_error($content) || !$content) {
            return false;
        }

        $invoice = self::get_invoice($invoice_id);
        $number  = self::number($invoice);

        // الكود القديم كان يكتب الملف باسم عربي في مجلد العمل ويرسل ملفاً مؤقتاً فارغاً
        $tmp = trailingslashit(get_temp_dir()) . 'invoice-' . absint($invoice_id) . '-' . wp_generate_password(8, false) . '.pdf';
        if (false === file_put_contents($tmp, $content)) {
            return false;
        }

        $subject = self::type_label($invoice) . ' #' . $number;
        $message = "مرحباً،\n\n"
            . "مرفق نسخة من الفاتورة رقم {$number}.\n"
            . ($invoice->building_name ? "المبنى: {$invoice->building_name}\n" : '')
            . ($invoice->unit_number ? "الوحدة: {$invoice->unit_number}\n" : '')
            . 'المبلغ: ' . number_format((float) $invoice->amount, 2) . " ج.م\n\n"
            . MS_PDF::brand('name') . "\n" . MS_PDF::brand('contact');

        $sent = wp_mail($email, $subject, $message, array('Content-Type: text/plain; charset=UTF-8'), array($tmp));
        @unlink($tmp);

        return $sent;
    }
}

/* ====================================================================== *
 * صلاحيات + نقاط الدخول
 * ====================================================================== */

/**
 * من يحق له تحميل فاتورة؟ الأدمن، صاحب الفاتورة، مالك الوحدة، مدير المبنى.
 * ملاحظة: $wpdb يرجّع الأرقام كنصوص ← نقارن بعد absint (مقارنة === مباشرة كانت تفشل دائماً).
 */
function ms_user_can_access_invoice_pdf($user_id, $invoice) {
    $user_id = absint($user_id);
    if (!$user_id || !$invoice) {
        return false;
    }
    if (user_can($user_id, 'manage_options')) {
        return true;
    }
    if (absint($invoice->user_id ?? 0) === $user_id) {
        return true;
    }
    if (function_exists('ms_invoice_belongs_to_owner') && ms_invoice_belongs_to_owner(absint($invoice->id), $user_id)) {
        return true;
    }
    $building_id = absint($invoice->building_id ?? 0);
    if ($building_id && function_exists('ms_get_buildings_by_manager')
        && function_exists('ms_user_has_role') && ms_user_has_role($user_id, 'building_manager')) {
        foreach ((array) ms_get_buildings_by_manager($user_id) as $b) {
            if (absint($b->id ?? $b->ID ?? 0) === $building_id) {
                return true;
            }
        }
    }
    return false;
}

/**
 * رابط تحميل الفاتورة — nonce مربوط برقم الفاتورة نفسها.
 */
function ms_invoice_pdf_url($invoice_id, $inline = false) {
    $invoice_id = absint($invoice_id);
    $args = array('action' => 'ms_download_invoice', 'id' => $invoice_id);
    if ($inline) {
        $args['inline'] = 1; // عرض داخل المتصفح بدل التحميل
    }
    return wp_nonce_url(add_query_arg($args, admin_url('admin-ajax.php')), 'ms_invoice_pdf_' . $invoice_id);
}

add_action('wp_ajax_ms_download_invoice', 'ms_ajax_download_invoice_pdf');
function ms_ajax_download_invoice_pdf() {
    $invoice_id = isset($_GET['id']) ? absint($_GET['id']) : 0;

    if (!$invoice_id || !check_ajax_referer('ms_invoice_pdf_' . $invoice_id, '_wpnonce', false)) {
        wp_die('رابط غير صالح أو منتهي الصلاحية. حدّث الصفحة وحاول مجدداً.', '', array('response' => 403));
    }

    $invoice = Mostager_Invoice_PDF::get_invoice($invoice_id);
    if (!$invoice) {
        wp_die('الفاتورة غير موجودة.', '', array('response' => 404));
    }
    if (!ms_user_can_access_invoice_pdf(get_current_user_id(), $invoice)) {
        wp_die('غير مصرح لك بتحميل هذه الفاتورة.', '', array('response' => 403));
    }

    $mode   = !empty($_GET['inline']) ? 'I' : 'D';
    $result = (new Mostager_Invoice_PDF())->generate_invoice($invoice_id, $mode);
    if (is_wp_error($result)) {
        wp_die(esc_html($result->get_error_message()), '', array('response' => 500));
    }
    exit;
}

add_action('wp_ajax_mostager_email_invoice', 'mostager_ajax_email_invoice');
function mostager_ajax_email_invoice() {
    $invoice_id = isset($_POST['invoice_id']) ? absint($_POST['invoice_id']) : 0;

    if (!$invoice_id || !check_ajax_referer('ms_invoice_pdf_' . $invoice_id, 'security', false)) {
        wp_send_json_error('فشل التحقق الأمني', 403);
    }

    $invoice = Mostager_Invoice_PDF::get_invoice($invoice_id);
    if (!$invoice || !ms_user_can_access_invoice_pdf(get_current_user_id(), $invoice)) {
        wp_send_json_error('غير مصرح', 403);
    }

    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    if (!is_email($email)) {
        wp_send_json_error('البريد الإلكتروني غير صالح', 400);
    }

    if ((new Mostager_Invoice_PDF())->email_invoice($invoice_id, $email)) {
        wp_send_json_success('تم إرسال الفاتورة بنجاح');
    }
    wp_send_json_error('فشل إرسال البريد الإلكتروني', 500);
}
