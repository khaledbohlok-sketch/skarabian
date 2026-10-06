<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\DB;
use App\Core\ErrorHandler;

/** In-app notifications plus optional email / WhatsApp alerts. */
final class Notifier
{
    public static function user(int $userId, string $type, string $title, ?string $body = null, ?string $url = null, bool $email = false, ?string $dedupe = null): void
    {
        try {
            if ($dedupe !== null) {
                $n = DB::run(
                    'INSERT IGNORE INTO notifications (user_id, type, title, body, url, dedupe_key) VALUES (?, ?, ?, ?, ?, ?)',
                    [$userId, $type, mb_substr($title, 0, 200), $body !== null ? mb_substr($body, 0, 500) : null, $url, $dedupe]
                )->rowCount();
                if ($n === 0) {
                    return; // already notified
                }
            } else {
                DB::insert('notifications', ['user_id' => $userId, 'type' => $type, 'title' => mb_substr($title, 0, 200), 'body' => $body !== null ? mb_substr($body, 0, 500) : null, 'url' => $url]);
            }
            if ($email) {
                $u = DB::row("SELECT email FROM users WHERE id = ? AND status = 'active'", [$userId]);
                if ($u) {
                    $link = $url ? '<p><a href="' . e(absolute_url($url)) . '" style="background:#0f1f45;color:#fff;padding:10px 18px;text-decoration:none;border-radius:4px">' . e(__('common.open')) . '</a></p>' : '';
                    Mailer::send($u['email'], $title, '<p>' . nl2br(e((string) $body)) . '</p>' . $link);
                }
            }
        } catch (\Throwable $e) {
            ErrorHandler::log($e);
        }
    }

    /** Notifies all active users of the given role slugs. */
    public static function roles(array $slugs, string $type, string $title, ?string $body = null, ?string $url = null, bool $email = false, ?string $dedupe = null): void
    {
        $params = [];
        $in = DB::in($slugs, 'r', $params);
        $ids = DB::column("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug IN $in AND u.status = 'active' AND u.deleted_at IS NULL", $params);
        foreach ($ids as $id) {
            self::user((int) $id, $type, $title, $body, $url, $email, $dedupe);
        }
    }

    public static function owners(string $type, string $title, ?string $body = null, ?string $url = null, bool $email = false, ?string $dedupe = null): void
    {
        self::roles(['owner'], $type, $title, $body, $url, $email, $dedupe);
    }

    /** Users whose role (or temporary grant) allows module.action. */
    public static function permitted(string $module, string $action, string $type, string $title, ?string $body = null, ?string $url = null, bool $email = false, ?string $dedupe = null, ?int $exceptUserId = null): void
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
        $c = Config::get('whatsapp');
        if (empty($c['token']) || empty($c['phone_number_id'])) {
            return false;
        }
        $to = preg_replace('/\D/', '', $phone);
        $payload = json_encode(['messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'text', 'text' => ['body' => mb_substr($text, 0, 4000)]]);
        $ch = curl_init('https://graph.facebook.com/' . ($c['api_version'] ?? 'v20.0') . '/' . $c['phone_number_id'] . '/messages');
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $c['token'], 'Content-Type: application/json'],
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
