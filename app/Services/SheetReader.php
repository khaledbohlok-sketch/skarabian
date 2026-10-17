<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Reads a spreadsheet the user uploads, without any library: Excel .xlsx (a zip of XML files) or CSV (UTF-8, UTF-16 or
 * the Arabic Windows code page Excel uses for "CSV (Comma delimited)"; comma, semicolon or tab).
 *
 * Returns ['sheets' => [sheet name => [row number => [column index => value]]], 'date1904' => bool]: row numbers are the
 * ones Excel shows, column index 0 is A, empty cells are left out. A value is a string, or an int/float for a number
 * cell; a cell formatted as a date comes back as 'YYYY-MM-DD'.
 *
 * Anything else (an old .xls, a damaged or very large file, more than MAX_ROWS rows) is refused with a message for the
 * user (\DomainException). Parts with a DOCTYPE are refused and nothing is ever fetched from the network.
 */
final class SheetReader
{
    public const MAX_BYTES = 5_000_000;
    public const MAX_ROWS = 2000;
    /**
     * One XML part / the whole workbook, unzipped: a "zip bomb" is refused before anything is unpacked. A sheet of
     * MAX_ROWS rows is a few MB; a part is read into memory whole, so this also keeps within a 128 MB PHP limit.
     */
    private const MAX_PART = 20_000_000;
    private const MAX_TOTAL = 60_000_000;
    private const REL_NS = ['http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'http://purl.oclc.org/ooxml/officeDocument/relationships'];

    public static function read(string $path, string $name = ''): array
    {
        $size = is_file($path) ? (int) filesize($path) : 0;
        if ($size === 0) {
            throw new \DomainException(__('sheet.empty'));
        }
        if ($size > self::MAX_BYTES) {
            throw new \DomainException(__('sheet.too_big', ['mb' => (int) (self::MAX_BYTES / 1_000_000)]));
        }
        $head = (string) file_get_contents($path, false, null, 0, 8);
        if (str_starts_with($head, "PK\x03\x04")) {
            return self::xlsx($path);
        }
        if (str_starts_with($head, "\xD0\xCF\x11\xE0")) {
            throw new \DomainException(__('sheet.old_xls'));
        }
        if (preg_match('/\.(xlsx|xlsm|xls|ods|numbers)$/i', $name)) {
            throw new \DomainException(__('sheet.unreadable'));
        }
        return ['sheets' => ['' => self::csv((string) file_get_contents($path))], 'date1904' => false];
    }

    /** An Excel date number as 'YYYY-MM-DD' (Excel counts days from 1900, with its famous 29 February 1900; Mac files may count from 1904). */
    public static function excelDate(float $serial, bool $date1904 = false): ?string
    {
        if ($serial < 1 || $serial > 2958465) {
            return null;
        }
        $days = (int) floor($serial);
        $base = new \DateTimeImmutable($date1904 ? '1904-01-01' : ($days >= 61 ? '1899-12-30' : '1899-12-31'));
        return $base->modify('+' . $days . ' days')->format('Y-m-d');
    }

    /** The Excel date number of 'YYYY-MM-DD' (1900 system), for writing real date cells. */
    public static function excelSerial(string $date): int
    {
        return (int) (new \DateTimeImmutable('1899-12-30'))->diff(new \DateTimeImmutable($date))->format('%r%a');
    }

    // ------------------------------------------------------------------ .xlsx

    private static function xlsx(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \DomainException(__('sheet.unreadable'));
        }
        $prev = libxml_use_internal_errors(true);
        try {
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $total += (int) (($zip->statIndex($i) ?: [])['size'] ?? 0);
            }
            if ($zip->numFiles > 5000 || $total > self::MAX_TOTAL) {
                throw new \DomainException(__('sheet.too_big', ['mb' => (int) (self::MAX_BYTES / 1_000_000)]));
            }
            // the main part (normally xl/workbook.xml) and the parts it points to
            $book = 'xl/workbook.xml';
            foreach (self::rels(self::part($zip, '_rels/.rels', false) ?? '', '') as $r) {
                if (str_ends_with($r['type'], '/officeDocument')) {
                    $book = $r['target'];
                }
            }
            $dir = str_contains($book, '/') ? substr($book, 0, (int) strrpos($book, '/')) : '';
            $rels = [];
            $shared = $styles = null;
            foreach (self::rels(self::part($zip, ($dir !== '' ? $dir . '/' : '') . '_rels/' . basename($book) . '.rels', false) ?? '', $dir) as $r) {
                $rels[$r['id']] = $r['target'];
                if (str_ends_with($r['type'], '/sharedStrings')) {
                    $shared = $r['target'];
                } elseif (str_ends_with($r['type'], '/styles')) {
                    $styles = $r['target'];
                }
            }
            [$sheets, $date1904] = self::workbook(self::part($zip, $book));
            $strings = $shared ? self::sharedStrings(self::part($zip, $shared, false) ?? '') : [];
            $dateStyles = $styles ? self::dateStyles(self::part($zip, $styles, false) ?? '') : [];
            $out = [];
            foreach ($sheets as [$name, $rid]) {
                $target = $rels[$rid] ?? null;
                if ($target === null || isset($out[$name])) {
                    continue;
                }
                $xml = self::part($zip, $target, false);
                if ($xml !== null) {
                    $out[$name] = self::sheet($xml, $strings, $dateStyles, $date1904);
                }
            }
            if (!$out) {
                throw new \DomainException(__('sheet.unreadable'));
            }
            return ['sheets' => $out, 'date1904' => $date1904];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
            $zip->close();
        }
    }

    /** One part of the zip, size-checked; a part that declares a DOCTYPE or entities is refused. */
    private static function part(\ZipArchive $zip, string $name, bool $required = true): ?string
    {
        $i = $zip->locateName($name, \ZipArchive::FL_NOCASE);
        if ($i === false) {
            if ($required) {
                throw new \DomainException(__('sheet.unreadable'));
            }
            return null;
        }
        if ((int) (($zip->statIndex($i) ?: [])['size'] ?? 0) > self::MAX_PART) {
            throw new \DomainException(__('sheet.too_big', ['mb' => (int) (self::MAX_BYTES / 1_000_000)]));
        }
        $xml = $zip->getFromIndex($i);
        if ($xml === false || stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            throw new \DomainException(__('sheet.unreadable'));
        }
        return $xml;
    }

    private static function reader(string $xml): \XMLReader
    {
        libxml_clear_errors();
        $x = new \XMLReader();
        if ($xml === '' || !$x->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new \DomainException(__('sheet.unreadable'));
        }
        return $x;
    }

    /** Relationships of a part: [['id', 'type', 'target' (path inside the zip)]]. */
    private static function rels(string $xml, string $dir): array
    {
        if ($xml === '') {
            return [];
        }
        $out = [];
        $x = self::reader($xml);
        while (self::next($x)) {
            if ($x->nodeType === \XMLReader::ELEMENT && $x->localName === 'Relationship' && $x->getAttribute('TargetMode') !== 'External') {
                $out[] = ['id' => (string) $x->getAttribute('Id'), 'type' => (string) $x->getAttribute('Type'), 'target' => self::path($dir, (string) $x->getAttribute('Target'))];
            }
        }
        return $out;
    }

    /** A relationship target as a path inside the zip ("worksheets/sheet1.xml" next to xl/workbook.xml → xl/worksheets/sheet1.xml). */
    private static function path(string $dir, string $target): string
    {
        $parts = [];
        foreach (explode('/', str_starts_with($target, '/') ? ltrim($target, '/') : ($dir !== '' ? $dir . '/' : '') . $target) as $p) {
            if ($p === '..') {
                array_pop($parts);
            } elseif ($p !== '' && $p !== '.') {
                $parts[] = $p;
            }
        }
        return implode('/', $parts);
    }

    /** @return array{0: array<int, array{0:string, 1:string}>, 1: bool} the sheets in order ([name, relationship id]) and the 1904 date system flag */
    private static function workbook(string $xml): array
    {
        $sheets = [];
        $date1904 = false;
        $x = self::reader($xml);
        while (self::next($x)) {
            if ($x->nodeType !== \XMLReader::ELEMENT) {
                continue;
            }
            if ($x->localName === 'workbookPr') {
                $date1904 = in_array(strtolower((string) $x->getAttribute('date1904')), ['1', 'true'], true);
            } elseif ($x->localName === 'sheet') {
                $rid = null;
                foreach (self::REL_NS as $ns) {
                    $rid ??= $x->getAttributeNs('id', $ns);
                }
                if ($rid !== null) {
                    $sheets[] = [trim((string) $x->getAttribute('name')), $rid];
                }
            }
        }
        return [$sheets, $date1904];
    }

    /** The shared strings table (every text typed in the workbook), rich-text runs joined, phonetic hints left out. */
    private static function sharedStrings(string $xml): array
    {
        if ($xml === '') {
            return [];
        }
        $out = [];
        $x = self::reader($xml);
        while (self::next($x)) {
            if ($x->nodeType === \XMLReader::ELEMENT && $x->localName === 'si') {
                $out[] = $x->isEmptyElement ? '' : self::text($x);
            }
        }
        return $out;
    }

    /** The text inside the element the reader is on (si / is): every <t>, except inside <rPh>. Leaves the reader at its end. */
    private static function text(\XMLReader $x): string
    {
        $depth = $x->depth;
        $s = '';
        $advance = true;
        while (true) {
            if ($advance && !self::next($x)) {
                break;
            }
            $advance = true;
            if ($x->nodeType === \XMLReader::END_ELEMENT && $x->depth === $depth) {
                break;
            }
            if ($x->nodeType !== \XMLReader::ELEMENT) {
                continue;
            }
            if ($x->localName === 'rPh') {
                $x->next(); // past the whole phonetic run: the reader is now on whatever follows it
                $advance = false;
                continue;
            }
            if ($x->localName === 't' && !$x->isEmptyElement) {
                $s .= $x->readString();
            }
        }
        return $s;
    }

    /** Style numbers (the "s" of a cell) whose number format shows a date. */
    private static function dateStyles(string $xml): array
    {
        if ($xml === '') {
            return [];
        }
        $custom = [];
        $xfs = [];
        $inXfs = false;
        $x = self::reader($xml);
        while (self::next($x)) {
            if ($x->nodeType === \XMLReader::END_ELEMENT && $x->localName === 'cellXfs') {
                $inXfs = false;
            }
            if ($x->nodeType !== \XMLReader::ELEMENT) {
                continue;
            }
            if ($x->localName === 'numFmt') {
                $custom[(int) $x->getAttribute('numFmtId')] = (string) $x->getAttribute('formatCode');
            } elseif ($x->localName === 'cellXfs') {
                $inXfs = !$x->isEmptyElement;
            } elseif ($inXfs && $x->localName === 'xf') {
                $xfs[] = (int) $x->getAttribute('numFmtId');
            }
        }
        $out = [];
        foreach ($xfs as $i => $id) {
            if (self::isDateFormat($id, $custom[$id] ?? null)) {
                $out[$i] = true;
            }
        }
        return $out;
    }

    private static function isDateFormat(int $id, ?string $code): bool
    {
        if (($id >= 14 && $id <= 17) || $id === 22 || ($id >= 27 && $id <= 36) || ($id >= 50 && $id <= 58)) {
            return true; // Excel's built-in date formats (and their East-Asian variants)
        }
        if ($code === null || $id < 164) {
            return false;
        }
        $code = (string) preg_replace('/"[^"]*"|\[[^\]]*\]|\\\\.|_.|\*./', '', $code); // quoted text, [colour]/[$-locale], escapes, padding
        return (bool) preg_match('/[dy]/i', $code); // a day or a year: a date (a format with only h:mm is a time)
    }

    /** One worksheet: [row number => [column index => value]]. */
    private static function sheet(string $xml, array $strings, array $dateStyles, bool $date1904): array
    {
        $rows = [];
        $count = 0;
        $rowNo = 0;
        $col = -1;
        $x = self::reader($xml);
        while (self::next($x)) {
            if ($x->nodeType !== \XMLReader::ELEMENT) {
                continue;
            }
            if ($x->localName === 'row') {
                $r = (int) $x->getAttribute('r');
                $rowNo = $r > 0 ? $r : $rowNo + 1;
                $col = -1;
                continue;
            }
            if ($x->localName !== 'c') {
                continue;
            }
            $ref = (string) $x->getAttribute('r');
            $col = $ref !== '' ? self::colIndex($ref) : $col + 1;
            if ($ref !== '' && preg_match('/\d+$/', $ref, $m)) {
                $rowNo = (int) $m[0];
            }
            $type = (string) ($x->getAttribute('t') ?? '');
            $style = (int) $x->getAttribute('s');
            if ($x->isEmptyElement) {
                continue;
            }
            $raw = null;
            $inline = null;
            $depth = $x->depth;
            while (self::next($x)) {
                if ($x->nodeType === \XMLReader::END_ELEMENT && $x->depth === $depth) {
                    break;
                }
                if ($x->nodeType !== \XMLReader::ELEMENT) {
                    continue;
                }
                if ($x->localName === 'v') {
                    $raw = $x->isEmptyElement ? '' : $x->readString();
                } elseif ($x->localName === 'is') {
                    $inline = $x->isEmptyElement ? '' : self::text($x);
                }
            }
            $value = match ($type) {
                's' => $strings[(int) $raw] ?? '',
                'inlineStr' => $inline ?? (string) $raw,
                'str' => (string) $raw,
                'b' => $raw === '1' || strtolower((string) $raw) === 'true' ? 'TRUE' : 'FALSE',
                'e' => '',
                'd' => preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $raw) ? substr((string) $raw, 0, 10) : (string) $raw,
                default => self::number((string) $raw, isset($dateStyles[$style]), $date1904),
            };
            if (is_string($value)) {
                $value = trim(str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $value));
                if ($value === '') {
                    continue;
                }
            } elseif ($value === null) {
                continue;
            }
            if (!isset($rows[$rowNo]) && ++$count > self::MAX_ROWS + 50) {
                throw new \DomainException(__('sheet.too_many_rows', ['n' => self::MAX_ROWS]));
            }
            $rows[$rowNo][$col] = $value;
        }
        ksort($rows);
        foreach ($rows as &$r) {
            ksort($r);
        }
        return $rows;
    }

    private static function number(string $raw, bool $isDate, bool $date1904): int|float|string|null
    {
        $raw = trim($raw);
        if ($raw === '' || !is_numeric($raw)) {
            return $raw === '' ? null : $raw;
        }
        if ($isDate) {
            return self::excelDate((float) $raw, $date1904) ?? $raw;
        }
        if (preg_match('/^-?\d{1,18}$/', $raw)) {
            return (int) $raw;
        }
        return (float) $raw;
    }

    /** "AB12" → 27 (A = 0). */
    private static function colIndex(string $ref): int
    {
        $n = 0;
        foreach (str_split(strtoupper((string) preg_replace('/[^A-Za-z]/', '', $ref))) as $c) {
            $n = $n * 26 + (ord($c) - 64);
        }
        return max(0, $n - 1);
    }

    /** XMLReader::read() that turns a parse error into the user's "cannot read this file". */
    private static function next(\XMLReader $x): bool
    {
        $ok = $x->read();
        if (!$ok && ($err = libxml_get_last_error()) && $err->level >= LIBXML_ERR_ERROR) {
            throw new \DomainException(__('sheet.unreadable'));
        }
        return $ok;
    }

    // ------------------------------------------------------------------ CSV

    private static function csv(string $data): array
    {
        if (str_starts_with($data, "\xEF\xBB\xBF")) {
            $data = substr($data, 3);
        } elseif (str_starts_with($data, "\xFF\xFE")) {
            $data = (string) mb_convert_encoding(substr($data, 2), 'UTF-8', 'UTF-16LE'); // Excel's "Unicode text"
        } elseif (str_starts_with($data, "\xFE\xFF")) {
            $data = (string) mb_convert_encoding(substr($data, 2), 'UTF-8', 'UTF-16BE');
        }
        if (str_contains($data, "\0")) {
            throw new \DomainException(__('sheet.unreadable'));
        }
        if (!mb_check_encoding($data, 'UTF-8')) {
            $conv = @iconv('WINDOWS-1256', 'UTF-8//IGNORE', $data); // Arabic Windows: what Excel's plain "CSV" uses there
            $data = is_string($conv) ? $conv : (string) mb_convert_encoding($data, 'UTF-8', 'ISO-8859-1');
        }
        $first = '';
        foreach (preg_split('/\r\n|\r|\n/', $data) ?: [] as $line) {
            if (trim($line) !== '') {
                $first = (string) preg_replace('/"[^"]*"/', '', $line);
                break;
            }
        }
        $counts = [',' => substr_count($first, ','), ';' => substr_count($first, ';'), "\t" => substr_count($first, "\t")];
        arsort($counts);
        $delim = (string) array_key_first($counts);
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $data);
        rewind($fh);
        $rows = [];
        $n = 0;
        while (($cells = fgetcsv($fh, 0, $delim, '"', '')) !== false) {
            $n++;
            $line = [];
            foreach ($cells as $i => $v) {
                $v = trim(str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], (string) $v));
                if ($v !== '') {
                    $line[$i] = $v;
                }
            }
            if ($line) {
                if (count($rows) >= self::MAX_ROWS + 50) {
                    fclose($fh);
                    throw new \DomainException(__('sheet.too_many_rows', ['n' => self::MAX_ROWS]));
                }
                $rows[$n] = $line;
            }
        }
        fclose($fh);
        return $rows;
    }
}
