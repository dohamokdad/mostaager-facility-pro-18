<?php
/**
 * مستند PDF موحّد — يُحمَّل فقط بعد تحميل TCPDF عبر MS_PDF::load().
 */

if (!defined('ABSPATH')) {
    exit;
}

class MS_PDF_Document extends TCPDF {

    public function Footer() {
        $this->SetY(-12);
        $this->SetFont(MS_PDF::FONT, '', 8);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(
            0,
            5,
            'تم إنشاء هذا المستند إلكترونياً بواسطة ' . MS_PDF::brand('name')
                . '  |  صفحة ' . $this->getAliasNumPage() . ' من ' . $this->getAliasNbPages(),
            0,
            0,
            'C'
        );
    }
}
