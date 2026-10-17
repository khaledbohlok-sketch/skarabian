<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Import of pedigrees for horses that already exist in the system, from an Excel workbook (.xlsx) or a CSV file
 * (SheetReader). Expected header columns (any order, case-insensitive, in one of the first rows): Horse, then one per
 * ancestor named by the way back to it: Sire, Dam, Sire Sire, Sire Dam, Dam Sire, Dam Dam, Sire Sire Sire … up to the
 * 5th generation (Dam Dam Dam Dam Dam); see columns(). Ancestor names that don't match an existing horse are created
 * as external horses (pedigree-only), exactly like the pedigree editor's inline "+ Add new".
 */
final class PedigreeImportService
{
    /** Column header => pedigree path: "Sire" => s, "Dam Sire" => ds … "Dam Dam Dam Dam Dam" => ddddd (HorseService::pedigreePaths()). */
    public static function columns(): array
    {
        $cols = [];
        foreach (HorseService::pedigreePaths() as $path) {
            $cols[implode(' ', array_map(fn ($c) => $c === 's' ? 'Sire' : 'Dam', str_split($path)))] = $path;
        }
        return $cols;
    }

    /** @return array{0:int, 1:string[]} [pedigrees updated, warning/error messages] */
    public static function run(string $path, string $name = ''): array
    {
        try {
            $book = SheetReader::read($path, $name);
        } catch (\DomainException $e) {
            return [0, [$e->getMessage()]];
        }
        // the first sheet with a "Horse" heading in one of its first rows
        $map = null;
        $rows = [];
        $headRow = 0;
        foreach ($book['sheets'] as $sheetRows) {
            $seen = 0;
            foreach ($sheetRows as $rowNo => $cells) {
                if (++$seen > 10) {
                    break;
                }
                $m = [];
                foreach ($cells as $i => $h) {
                    $m[self::norm((string) $h)] ??= $i;
                }
                if (isset($m['horse'])) {
                    [$map, $rows, $headRow] = [$m, $sheetRows, (int) $rowNo];
                    break 2;
                }
            }
        }
        if ($map === null) {
            return [0, [__('horses.pedigree_import_missing_column', ['col' => 'Horse'])]];
        }
        $updated = 0;
        $errors = [];
        foreach ($rows as $line => $row) {
            if ($line <= $headRow) {
                continue;
            }
            $get = fn (string $key) => isset($map[$key]) ? trim((string) ($row[$map[$key]] ?? '')) : '';
            try {
                $updated += self::importRow($get, (int) $line, $errors);
            } catch (\Throwable $e) {
                $errors[] = __('horses.pedigree_import_row_error', ['line' => $line, 'error' => $e->getMessage()]);
            }
        }
        return [$updated, $errors];
    }

    private static function norm(string $s): string
    {
        return preg_replace('/[^a-z]/', '', mb_strtolower(trim($s)));
    }

    private static function importRow(callable $get, int $line, array &$errors): int
    {
        $horseName = $get('horse');
        if ($horseName === '') {
            return 0;
        }
        $horseId = DB::value("SELECT id FROM horses WHERE deleted_at IS NULL AND (name_en = ? OR name_ar = ?) LIMIT 1", [$horseName, $horseName]);
        if (!$horseId) {
            $errors[] = __('horses.pedigree_import_horse_not_found', ['name' => $horseName]);
            return 0;
        }
        $tree = [];
        foreach (self::columns() as $col => $path) {
            $name = $get(self::norm($col));
            if ($name === '') {
                continue;
            }
            $id = DB::value("SELECT id FROM horses WHERE deleted_at IS NULL AND (name_en = ? OR name_ar = ?) LIMIT 1", [$name, $name]);
            $tree[$path] = $id ? (int) $id : ['name_en' => $name];
        }
        if (!$tree) {
            return 0;
        }
        HorseService::savePedigree((int) $horseId, $tree);
        return 1;
    }
}
