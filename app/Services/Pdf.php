<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Response;

/**
 * PDF output. When the optional mPDF package is installed (composer require mpdf/mpdf, upload /vendor),
 * real PDF files are produced on the server (also used for WhatsApp/email sharing).
 * Without it, the print-ready page opens the browser's print dialog ("Save as PDF").
 */
final class Pdf
{
    public static function available(): bool
    {
        return class_exists(\Mpdf\Mpdf::class);
    }

    public static function render(string $html): ?string
    {
        if (!self::available()) {
            return null;
        }
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8', 'format' => 'A4', 'tempDir' => \App\Core\Config::storagePath('cache'),
            'autoScriptToLang' => true, 'autoLangToFont' => true, 'margin_top' => 10, 'margin_bottom' => 14,
        ]);
        $mpdf->WriteHTML($html);
        return $mpdf->Output('', 'S');
    }

    public static function outputOrPrint(string $html, string $filename): never
    {
        if (($_GET['format'] ?? '') !== 'html' && ($pdf = self::render($html)) !== null) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . rawurlencode($filename) . '"');
            header('Cache-Control: private, no-store');
            echo $pdf;
            exit;
        }
        Response::html($html);
    }
}
