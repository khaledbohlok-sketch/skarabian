<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\DB;
use App\Core\ErrorHandler;
use App\Core\Lang;

/** In-app notifications plus optional email / WhatsApp alerts. */
final class Notifier
{
    /**
     * $title and $body are either finished text (stored as written) or a message from msg() — a language key with its
     * values — which is stored as the key + JSON values and translated when shown, in the reader's language (the
     * text columns keep the English wording as a fallback). Emails go out in the recipient's language.
     */
    public static function user(int $userId, string $type, string|array $title, string|array|null $body = null, ?string $url = null, bool $email = false, ?string $dedupe = null): void
    {
        try {
            $row = ['user_id' => $userId, 'type' => $type, 'title' => mb_substr(self::render($title, 'en'), 0, 200),
                    'body' => $body !== null ? mb_substr(self::render($body, 'en'), 0, 500) : null, 'url' => $url];
            if (self::keyed() && (is_array($title) || is_array($body))) {
                $row += is_array($title) ? ['msg_key' => $title['key'], 'msg_params' => json_encode($title['params'], JSON_UNESCAPED_UNICODE)] : [];
                $row += is_array($body) ? ['body_key' => $body['key'], 'body_params' => json_encode($body['params'], JSON_UNESCAPED_UNICODE)] : [];
            }
            if ($dedupe !== null) {
                $cols = array_keys($row);
                $n = DB::run(
                    'INSERT IGNORE INTO notifications (' . implode(', ', $cols) . ', dedupe_key) VALUES (' . implode(', ', array_fill(0, count($cols) + 1, '?')) . ')',
                    [...array_values($row), $dedupe]
                )->rowCount();
                if ($n === 0) {
                    return; // already notified
                }
            } else {
                DB::insert('notifications', $row);
            }
            if ($email) {
                $u = DB::row("SELECT email, lang FROM users WHERE id = ? AND status = 'active'", [$userId]);
                if ($u) {
                    $lang = (string) ($u['lang'] ?: 'en');
                    $link = $url ? EmailLook::button(Lang::get('common.open', [], $lang), absolute_url($url)) : '';
                    $text = self::render($body, $lang);
                    Mailer::send($u['email'], self::render($title, $lang), ($text !== '' ? '<p>' . nl2br(e($text)) . '</p>' : '') . $link, [], ['lang' => $lang]);
                }
            }
        } catch (\Throwable $e) {
            ErrorHandler::log($e);
        }
    }

    /**
     * A translatable message for user()/roles()/permitted(): a language key and its values. A value can be plain text,
     * another message (msg(...)), ['date' => 'Y-m-d'] (shown as a date), ['money' => amount] (QAR) or
     * ['en' => ..., 'ar' => ...] (e.g. a name in both languages).
     */
    public static function msg(string $key, array $params = []): array
    {
        return ['key' => $key, 'params' => $params];
    }

    /** A message (or plain text) in the given language (default: the current one). */
    public static function render(string|array|null $m, ?string $lang = null): string
    {
        if ($m === null) {
            return '';
        }
        if (is_string($m)) {
            return $m;
        }
        $prev = Lang::current();
        $lang ??= $prev;
        Lang::set($lang); // dates and amounts inside the message follow its language too
        try {
            $vals = [];
            foreach ((array) ($m['params'] ?? []) as $k => $v) {
                $vals[$k] = self::value($v, $lang);
            }
            // Longest names first, so ":name" never replaces the start of ":name_ar"
            uksort($vals, fn ($a, $b) => strlen((string) $b) <=> strlen((string) $a));
            return Lang::get((string) ($m['key'] ?? ''), $vals, $lang);
        } finally {
            Lang::set($prev);
        }
    }

    private static function value(mixed $v, string $lang): string
    {
        if (!is_array($v)) {
            return (string) $v;
        }
        if (isset($v['key'])) {
            return self::render($v, $lang);
        }
        if (array_key_exists('date', $v)) {
            return $v['date'] ? fmt_date((string) $v['date']) : '';
        }
        if (array_key_exists('money', $v)) {
            return money((float) $v['money'], 'QAR', false);
        }
        if (isset($v['en']) || isset($v['ar'])) {
            return (string) (($lang === 'ar' && !empty($v['ar'])) ? $v['ar'] : ($v['en'] ?? $v['ar']));
        }
        return '';
    }

    /** A stored notification's title and body in the reader's language (rows saved before migration 022 keep their text). */
    public static function localize(array $n): array
    {
        foreach (['title' => ['msg_key', 'msg_params'], 'body' => ['body_key', 'body_params']] as $col => [$kCol, $pCol]) {
            if (!empty($n[$kCol])) {
                $params = json_decode((string) ($n[$pCol] ?? ''), true);
                $n[$col] = self::render(['key' => $n[$kCol], 'params' => is_array($params) ? $params : []]);
            } elseif (!empty($n[$col]) && ($key = self::knownKey((string) $n[$col])) !== null) {
                $n[$col] = __($key); // saved as finished English text (before it was stored as a key): a fixed wording is still translated
            }
        }
        return $n;
    }

    /** The language key whose English wording (with no values in it) is exactly this text, else null. */
    private static function knownKey(string $text): ?string
    {
        static $map = null;
        if ($map === null) {
            $map = [];
            foreach (Lang::strings('en') as $k => $v) {
                if (is_string($v) && !str_contains($v, ':') && preg_match('/^(notify|approvals|security|cron|backups|leave|inbox|attendance)\./', $k)) {
                    $map[$v] ??= $k;
                }
            }
        }
        return $map[$text] ?? null;
    }

    /** True once notifications can store a language key (migration 022). */
    private static function keyed(): bool
    {
        static $ok = null;
        return $ok ??= DB::hasColumn('notifications', 'msg_key');
    }

    /** Notifies all active users of the given role slugs. */
    public static function roles(array $slugs, string $type, string|array $title, string|array|null $body = null, ?string $url = null, bool $email = false, ?string $dedupe = null): void
    {
        $params = [];
        $in = DB::in($slugs, 'r', $params);
        $ids = DB::column("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug IN $in AND u.status = 'active' AND u.deleted_at IS NULL", $params);
        foreach ($ids as $id) {
            self::user((int) $id, $type, $title, $body, $url, $email, $dedupe);
        }
    }

    public static function owners(string $type, string|array $title, string|array|null $body = null, ?string $url = null, bool $email = false, ?string $dedupe = null): void
    {
        self::roles(['owner'], $type, $title, $body, $url, $email, $dedupe);
    }

    /** Users whose role (or temporary grant) allows module.action. */
    public static function permitted(string $module, string $action, string $type, string|array $title, string|array|null $body = null, ?string $url = null, bool $email = false, ?string $dedupe = null, ?int $exceptUserId = null): void
    {
        $ids = DB::column(
            "SELECT DISTINCT u.id FROM users u JOIN roles r ON r.id = u.role_id
             LEFT JOIN role_permissions rp ON rp.role_id = r.id AND rp.module = ? AND rp.action = ?
             WHERE u.status = 'active' AND u.deleted_at IS NULL AND (r.slug = 'owner' OR rp.role_id IS NOT NULL)",
            [$module, $action]
        );
        foreach ($ids as $id) {
            if ((int) $id !== $exceptUserId) {
                self::user((int) $id, $type, $title, $body, $url, $email, $dedupe);
            }
        }
    }

    public static function unreadCount(int $userId): int
    {
        return (int) DB::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [$userId]);
    }

    /** WhatsApp Cloud API text message. Returns false when not configured. */
    public static function whatsapp(string $phone, string $text): bool
    {
        $cfg = Config::get('whatsapp', []);
        $token = Settings::getEncrypted('whatsapp.token') ?: ($cfg['token'] ?? '');
        $phoneNumberId = Settings::get('whatsapp.phone_number_id') ?: ($cfg['phone_number_id'] ?? '');
        $apiVersion = Settings::get('whatsapp.api_version') ?: ($cfg['api_version'] ?? 'v20.0');
        if (empty($token) || empty($phoneNumberId)) {
            return false;
        }
        $to = preg_replace('/\D/', '', $phone);
        $payload = json_encode(['messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'text', 'text' => ['body' => mb_substr($text, 0, 4000)]]);
        $ch = curl_init('https://graph.facebook.com/' . $apiVersion . '/' . $phoneNumberId . '/messages');
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        ]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 300) {
            ErrorHandler::log('WhatsApp send failed: ' . $code . ' ' . $res);
            return false;
        }
        return true;
    }
}
