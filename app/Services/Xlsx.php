<?php
declare(strict_types=1);

namespace App\Services;

/** Minimal Excel (.xlsx) writer: one sheet, header row, inline strings and numbers, RTL option. */
final class Xlsx
{
    public static function build(array $headers, array $rows, string $sheetName = 'Sheet1', bool $rtl = false): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . self::x(mb_substr($sheetName, 0, 31)) . '" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0F1F45"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="3"><xf/><xf fontId="1" fillId="2" applyFont="1" applyFill="1"/><xf numFmtId="164" applyNumberFormat="1"/></cellXfs></styleSheet>');

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
             . '<sheetViews><sheetView workbookViewId="0"' . ($rtl ? ' rightToLeft="1"' : '') . '><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
             . '<cols>';
        foreach ($headers as $i => $h) {
            $xml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . max(12, min(45, mb_strlen((string) $h) + 6)) . '" customWidth="1"/>';
        }
        $xml .= '</cols><sheetData>';
        $xml .= self::row(1, $headers, true);
        $r = 2;
        foreach ($rows as $row) {
            $xml .= self::row($r++, array_values($row), false);
        }
        $xml .= '</sheetData><autoFilter ref="A1:' . self::col(count($headers) - 1) . max(1, $r - 1) . '"/></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->close();
        $data = (string) file_get_contents($tmp);
        @unlink($tmp);
        return $data;
    }

    private static function row(int $r, array $cells, bool $header): string
    {
        $out = '<row r="' . $r . '">';
        foreach ($cells as $i => $v) {
            $ref = self::col($i) . $r;
            if (!$header && (is_int($v) || is_float($v) || (is_string($v) && preg_match('/^-?\d+\.\d{2}$/', $v)))) {
                $out .= '<c r="' . $ref . '" s="2"><v>' . (float) $v . '</v></c>';
            } elseif (!$header && is_string($v) && preg_match('/^-?\d{1,9}$/', $v) && !str_starts_with($v, '0')) {
                $out .= '<c r="' . $ref . '"><v>' . $v . '</v></c>';
            } else {
                $out .= '<c r="' . $ref . '" t="inlineStr"' . ($header ? ' s="1"' : '') . '><is><t xml:space="preserve">' . self::x((string) $v) . '</t></is></c>';
            }
        }
        return $out . '</row>';
    }

    private static function col(int $i): string
    {
        $s = '';
        $i++;
        while ($i > 0) {
            $m = ($i - 1) % 26;
            $s = chr(65 + $m) . $s;
            $i = intdiv($i - $m, 26);
        }
        return $s;
    }

    private static function x(string $s): string
    {
        $s = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $s) ?? '';
        // Neutralise spreadsheet formula injection
        if ($s !== '' && in_array($s[0], ['=', '+', '-', '@'], true) && !is_numeric($s)) {
            $s = "'" . $s;
        }
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
