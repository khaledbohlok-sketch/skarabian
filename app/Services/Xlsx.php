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

    /**
     * A workbook of several sheets (the import templates). Per sheet: 'name', 'headers', 'rows', and optionally
     * 'text' (column indexes kept as typed text: a 15-digit microchip is not turned into 9.85E+14), 'dates' (columns
     * shown as "12 Mar 2020", so day and month cannot be mixed up; 'YYYY-MM-DD' values become real dates), 'lists'
     * (column index => a range on another sheet, e.g. "'Lists'!$A$2:$A$3": a drop-down on every row), 'widths',
     * 'wrap' (columns whose text wraps) and 'filter' (filter buttons on the header row).
     * $listError = [title, message]: Excel's warning when a typed value is not in the drop-down (it can still be kept).
     */
    public static function workbook(array $sheets, bool $rtl = false, array $listError = []): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        $types = $rels = $list = '';
        $n = 0;
        foreach (array_values($sheets) as $i => $s) {
            $n = $i + 1;
            $types .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $rels .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
            $list .= '<sheet name="' . self::attr(self::sheetName((string) $s['name'])) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
            $zip->addFromString('xl/worksheets/sheet' . $n . '.xml', self::sheetXml($s, $rtl, $i === 0, $listError));
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' . $types . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView activeTab="0"/></bookViews><sheets>' . $list . '</sheets></workbook>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="2"><numFmt numFmtId="164" formatCode="#,##0.00"/><numFmt numFmtId="165" formatCode="d mmm yyyy"/></numFmts>'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/><family val="2"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0F1F45"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="6">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'                                             // 0 normal
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'                  // 1 header
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'                      // 2 money
            . '<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'                       // 3 text
            . '<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'                      // 4 date
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>' // 5 wrapped
            . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
        $zip->close();
        $data = (string) file_get_contents($tmp);
        @unlink($tmp);
        return $data;
    }

    private static function sheetXml(array $s, bool $rtl, bool $active, array $listError): string
    {
        $headers = array_values($s['headers'] ?? []);
        $rows = array_values($s['rows'] ?? []);
        $text = array_flip($s['text'] ?? []);
        $dates = array_flip($s['dates'] ?? []);
        $wrap = array_flip($s['wrap'] ?? []);
        $ncols = count($headers);
        foreach ($rows as $row) {
            $ncols = max($ncols, count($row));
        }
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
             . '<sheetViews><sheetView workbookViewId="0"' . ($rtl ? ' rightToLeft="1"' : '') . ($active ? ' tabSelected="1"' : '') . '>'
             . ($headers ? '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A2" sqref="A2"/>' : '')
             . '</sheetView></sheetViews><sheetFormatPr defaultRowHeight="15"/>';
        if ($ncols) {
            $xml .= '<cols>';
            for ($i = 0; $i < $ncols; $i++) {
                $w = (int) ($s['widths'][$i] ?? max(12, min(45, mb_strlen((string) ($headers[$i] ?? '')) + 6)));
                $style = isset($text[$i]) ? 3 : (isset($dates[$i]) ? 4 : (isset($wrap[$i]) ? 5 : 0));
                $xml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '"' . ($style ? ' style="' . $style . '"' : '') . ' customWidth="1"/>';
            }
            $xml .= '</cols>';
        }
        $xml .= '<sheetData>';
        $r = 1;
        if ($headers) {
            $xml .= self::row($r++, $headers, true);
        }
        foreach ($rows as $row) {
            $xml .= '<row r="' . $r . '">';
            foreach (array_values($row) as $i => $v) {
                if ($v === null || $v === '') {
                    continue;
                }
                $ref = self::col($i) . $r;
                if (isset($dates[$i]) && is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                    $xml .= '<c r="' . $ref . '" s="4"><v>' . SheetReader::excelSerial($v) . '</v></c>';
                } elseif (!isset($text[$i]) && (is_int($v) || is_float($v))) {
                    $xml .= '<c r="' . $ref . '"' . (is_float($v) ? ' s="2"' : '') . '><v>' . $v . '</v></c>';
                } else {
                    $style = isset($text[$i]) ? ' s="3"' : (isset($wrap[$i]) ? ' s="5"' : '');
                    $xml .= '<c r="' . $ref . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">' . self::x((string) $v) . '</t></is></c>';
                }
            }
            $xml .= '</row>';
            $r++;
        }
        $xml .= '</sheetData>';
        if (!empty($s['filter']) && $headers && $rows) {
            $xml .= '<autoFilter ref="A1:' . self::col($ncols - 1) . ($r - 1) . '"/>';
        }
        if (!empty($s['lists'])) {
            $last = max(2001, $r + 500);
            $xml .= '<dataValidations count="' . count($s['lists']) . '">';
            foreach ($s['lists'] as $i => $range) {
                $c = self::col((int) $i);
                $xml .= '<dataValidation type="list" errorStyle="warning" allowBlank="1" showErrorMessage="1"'
                      . ($listError ? ' errorTitle="' . self::attr(mb_substr((string) $listError[0], 0, 32)) . '" error="' . self::attr(mb_substr((string) $listError[1], 0, 255)) . '"' : '')
                      . ' sqref="' . $c . '2:' . $c . $last . '"><formula1>' . self::attr((string) $range) . '</formula1></dataValidation>';
            }
            $xml .= '</dataValidations>';
        }
        return $xml . '<pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.3" footer="0.3"/></worksheet>';
    }

    /** A sheet name Excel accepts: no : \ / ? * [ ], at most 31 characters. */
    public static function sheetName(string $name): string
    {
        $name = trim((string) preg_replace('/[:\\\\\/?*\[\]]/u', ' ', $name), " '");
        return mb_substr($name !== '' ? $name : 'Sheet', 0, 31);
    }

    private static function attr(string $s): string
    {
        $s = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $s) ?? '';
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
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

    /** Column letters of a 0-based index: 0 → A, 27 → AB. */
    public static function col(int $i): string
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
