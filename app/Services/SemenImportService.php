<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;

/**
 * CSV import of an existing semen stock sheet (export your spreadsheet as CSV first — this app has no
 * Composer/third-party library on the server, so only CSV is read directly; .xlsx is not parsed).
 * Expected header columns (any order, case-insensitive): Stallion, Tank, Canister, Straws, Straws per
 * Dose, Report Date, Straw Size, Straw Color, Owner, Batch (optional), Notes (optional).
 * Each row becomes one semen_stock record plus an initial IN movement for its starting straws.
 */
final class SemenImportService
{
    /** @return array{0:int, 1:string[]} [rows created, warning/error messages] */
    public static function run(string $csvPath): array
    {
        $fh = fopen($csvPath, 'r');
        if (!$fh) {
            return [0, [__('files.upload_failed')]];
        }
        $header = fgetcsv($fh);
        if (!$header) {
            fclose($fh);
            return [0, [__('semen_stock.import_empty')]];
        }
        $map = [];
        foreach ($header as $i => $h) {
            $map[self::norm((string) $h)] = $i;
        }
        $required = ['stallion', 'tank', 'canister', 'straws'];
        foreach ($required as $r) {
            if (!isset($map[$r])) {
                fclose($fh);
                return [0, [__('semen_stock.import_missing_column', ['col' => $r])]];
            }
        }
        $created = 0;
        $errors = [];
        $line = 1;
        while (($row = fgetcsv($fh)) !== false) {
            $line++;
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue; // blank line
            }
            $get = fn (string $key) => isset($map[$key]) ? trim((string) ($row[$map[$key]] ?? '')) : '';
            try {
                $created += self::importRow($get, $line, $errors);
            } catch (\Throwable $e) {
                $errors[] = __('semen_stock.import_row_error', ['line' => $line, 'error' => $e->getMessage()]);
            }
        }
        fclose($fh);
        return [$created, $errors];
    }

    private static function norm(string $s): string
    {
        $s = mb_strtolower(trim($s));
        return match (true) {
            str_contains($s, 'stallion') => 'stallion',
            str_contains($s, 'tank') => 'tank',
            str_contains($s, 'canister') => 'canister',
            str_contains($s, 'per dose') || str_contains($s, 'per_dose') => 'per_dose',
            str_contains($s, 'straw') && str_contains($s, 'size') => 'size',
            str_contains($s, 'color') || str_contains($s, 'colour') => 'color',
            str_contains($s, 'straw') => 'straws',
            str_contains($s, 'report') => 'report_date',
            str_contains($s, 'owner') => 'owner',
            str_contains($s, 'batch') || str_contains($s, 'part') => 'batch',
            str_contains($s, 'note') => 'notes',
            default => $s,
        };
    }

    private static function importRow(callable $get, int $line, array &$errors): int
    {
        $stallionName = $get('stallion');
        $tankName = $get('tank');
        $canisterName = $get('canister');
        $straws = (int) preg_replace('/\D/', '', $get('straws') ?: '0');
        if ($tankName === '' || $canisterName === '') {
            $errors[] = __('semen_stock.import_row_error', ['line' => $line, 'error' => __('semen_stock.import_need_tank_canister')]);
            return 0;
        }

        // Stallion: match an existing horse by name, otherwise keep it as free text
        $stallionId = null;
        if ($stallionName !== '') {
            $stallionId = DB::value("SELECT id FROM horses WHERE sex = 'male' AND deleted_at IS NULL AND (name_en = ? OR name_ar = ?) LIMIT 1", [$stallionName, $stallionName]);
        }

        // Tank / canister: find or create by name
        $tankId = DB::value('SELECT id FROM tanks WHERE name = ? AND deleted_at IS NULL', [$tankName]);
        if (!$tankId) {
            $tankId = DB::insert('tanks', ['name' => $tankName, 'created_by' => Auth::id()]);
        }
        $canisterId = DB::value('SELECT id FROM canisters WHERE tank_id = ? AND name = ? AND deleted_at IS NULL', [$tankId, $canisterName]);
        if (!$canisterId) {
            $canisterId = DB::insert('canisters', ['tank_id' => $tankId, 'name' => $canisterName, 'created_by' => Auth::id()]);
        }

        // Straws per dose: "8" or "6-8" / "6–8"
        $min = $max = 1;
        $perDose = $get('per_dose');
        if ($perDose !== '' && preg_match('/(\d+)\s*[-–]\s*(\d+)/', $perDose, $m)) {
            $min = (int) $m[1];
            $max = (int) $m[2];
        } elseif ($perDose !== '' && preg_match('/\d+/', $perDose, $m)) {
            $min = $max = (int) $m[0];
        }
        if ($min < 1) {
            $min = 1;
        }
        if ($max < $min) {
            $max = $min;
        }

        // Straw color: match by name; unmatched colors are left blank with a warning (colors are a managed list)
        $colorId = null;
        $colorName = $get('color');
        if ($colorName !== '') {
            $colorId = DB::value('SELECT id FROM straw_colors WHERE name_en = ? OR name_ar = ?', [$colorName, $colorName]);
            if (!$colorId) {
                $errors[] = __('semen_stock.import_unknown_color', ['line' => $line, 'color' => $colorName]);
            }
        }

        // Owner: match an existing party by name; otherwise keep SK Arabians and note the name
        $ownerType = 'sk';
        $ownerPartyId = null;
        $notesExtra = [];
        $ownerName = $get('owner');
        if ($ownerName !== '' && !self::isSk($ownerName)) {
            $ownerPartyId = DB::value('SELECT id FROM parties WHERE name_en = ? AND deleted_at IS NULL', [$ownerName]);
            if ($ownerPartyId) {
                $ownerType = 'client';
            } else {
                $notesExtra[] = __('semen_stock.import_owner_note', ['name' => $ownerName]);
            }
        }

        $reportDate = self::parseDate($get('report_date'));
        $notes = trim(implode(' ', array_filter([$get('notes'), ...$notesExtra])));

        $id = DB::insert('semen_stock', [
            'stallion_id' => $stallionId, 'stallion_name_free' => $stallionId ? null : ($stallionName ?: null),
            'batch_label' => $get('batch') ?: null, 'canister_id' => $canisterId,
            'straws_per_dose_min' => $min, 'straws_per_dose_max' => $max, 'straw_size' => $get('size') ?: null,
            'color_id' => $colorId, 'owner_type' => $ownerType, 'owner_party_id' => $ownerPartyId,
            'report_date' => $reportDate, 'notes' => $notes ?: null, 'created_by' => Auth::id(),
        ]);
        if ($straws > 0) {
            SemenStockService::recordIn($id, $straws, $reportDate ?? date('Y-m-d'), __('semen_stock.import_initial_note'));
        }
        return 1;
    }

    /** The stud itself as the owner ("SK", "SK Arabians", "اس كي ارابيان" …): the whole name, so "Al Askar Stud" is a client. */
    private static function isSk(string $name): bool
    {
        $n = mb_strtolower(strtr($name, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي']));
        $n = trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $n));
        $n = (string) preg_replace('/^s k(?= |$)/', 'sk', $n); // "S.K. Arabians"
        return in_array($n, ['sk', 'sk arabian', 'sk arabians', 'sk stud', 'sk arabian stud', 'sk arabians stud', 'اس كي', 'اس كي ارابيان', 'اس كي عربيان'], true);
    }

    private static function parseDate(string $s): ?string
    {
        if ($s === '') {
            return null;
        }
        $t = strtotime($s);
        return $t ? date('Y-m-d', $t) : null;
    }
}
