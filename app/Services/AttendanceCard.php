<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Crypto;
use App\Core\DB;

/**
 * The QR on a staff ID card: "SKA1.{employee id}.{card no}.{signature}" — no name, QID or phone. The signature is the
 * first 16 characters (96 bits) of the base64url HMAC-SHA256 of "{employee id}.{card no}" with a key derived from the
 * app key for this one purpose, so a card can't be made up or changed. "Reissue card" raises the card number: the QR
 * of the old (lost) card stops working at once. Cards printed before database update 024 carried plain text.
 */
final class AttendanceCard
{
    private const PREFIX = 'SKA1';
    private const PURPOSE = 'attendance-card';

    /** After database update 024 (employees.card_no). */
    public static function available(): bool
    {
        return DB::hasColumn('employees', 'card_no');
    }

    public static function qr(int $employeeId, int $cardNo): string
    {
        return self::PREFIX . '.' . $employeeId . '.' . $cardNo . '.' . self::sign($employeeId, $cardNo);
    }

    /**
     * What a scanned QR says: [employee id, card no, problem] — problem '' for a genuine card, 'old_card' for a card
     * printed before the signed QR, 'invalid' for anything else (another QR code, a changed or made-up one).
     * @return array{0: ?int, 1: ?int, 2: string}
     */
    public static function parse(string $text): array
    {
        $text = trim($text);
        if (preg_match('/^SKA1\.([1-9]\d{0,8})\.([1-9]\d{0,4})\.([A-Za-z0-9_-]{16})$/', $text, $m)) {
            return hash_equals(self::sign((int) $m[1], (int) $m[2]), $m[3]) ? [(int) $m[1], (int) $m[2], ''] : [null, null, 'invalid'];
        }
        return [null, null, str_starts_with($text, 'SK Arabian for Trading |') ? 'old_card' : 'invalid'];
    }

    /** The employee's current card number (1 until a card is reissued, and before database update 024). */
    public static function cardNo(int $employeeId): int
    {
        return self::available() ? max(1, (int) DB::value('SELECT card_no FROM employees WHERE id = ?', [$employeeId])) : 1;
    }

    /** A new card number for the employee: the old card's QR stops working. Returns the new number. */
    public static function reissue(int $employeeId, string $label): int
    {
        $old = self::cardNo($employeeId);
        DB::run('UPDATE employees SET card_no = card_no + 1, updated_at = NOW() WHERE id = ?', [$employeeId]);
        $new = self::cardNo($employeeId);
        Audit::log('reissue_card', 'hr', 'employee', $employeeId, ['card_no' => $old], ['card_no' => $new], 'ID card reissued: ' . $label);
        return $new;
    }

    private static function sign(int $employeeId, int $cardNo): string
    {
        $mac = (string) hex2bin(Crypto::hmac($employeeId . '.' . $cardNo, self::PURPOSE));
        return substr(rtrim(strtr(base64_encode($mac), '+/', '-_'), '='), 0, 16);
    }
}
