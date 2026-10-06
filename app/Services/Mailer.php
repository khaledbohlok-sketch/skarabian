<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\ErrorHandler;

/**
 * Minimal SMTP client (SSL/STARTTLS, AUTH LOGIN) with optional attachments.
 * driver "log" writes mails to storage/logs/mail.log (local/staging), "mail" uses PHP mail().
 */
final class Mailer
{
    /** @param array<array{name:string,content:string,mime:string}> $attachments */
    public static function send(string $to, string $subject, string $htmlBody, array $attachments = []): bool
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $c = Config::get('mail');
        $from = $c['from'] ?? 'no-reply@localhost';
        $fromName = $c['from_name'] ?? 'SK Arabians';
        $html = self::wrap($subject, $htmlBody);
        $boundary = 'b' . bin2hex(random_bytes(8));
        $headers = [
            'From: ' . self::encodeHeader($fromName) . " <$from>",
            'To: <' . $to . '>',
            'Subject: ' . self::encodeHeader($subject),
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(10)) . '@' . (parse_url((string) Config::get('app.url'), PHP_URL_HOST) ?: 'localhost') . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
        ];
        $body = "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
              . chunk_split(base64_encode($html)) . "\r\n";
        foreach ($attachments as $a) {
            $body .= "--$boundary\r\nContent-Type: {$a['mime']}; name=\"" . addslashes($a['name']) . "\"\r\n"
                   . "Content-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"" . addslashes($a['name']) . "\"\r\n\r\n"
                   . chunk_split(base64_encode($a['content'])) . "\r\n";
        }
        $body .= "--$boundary--\r\n";

        try {
            return match ($c['driver'] ?? 'log') {
                'smtp'  => self::smtp($c, $from, $to, implode("\r\n", $headers) . "\r\n\r\n" . $body),
                'mail'  => mail($to, self::encodeHeader($subject), $body, implode("\r\n", array_filter($headers, fn ($h) => !str_starts_with($h, 'To:') && !str_starts_with($h, 'Subject:')))),
                default => (bool) file_put_contents(Config::storagePath('logs/mail.log'), '[' . date('c') . "] TO $to | $subject\n" . strip_tags($htmlBody) . "\n\n", FILE_APPEND),
            };
        } catch (\Throwable $e) {
            ErrorHandler::log($e, ['mail_to' => $to]);
            return false;
        }
    }

    private static function smtp(array $c, string $from, string $to, string $data): bool
    {
        $host = ($c['encryption'] ?? '') === 'ssl' ? 'ssl://' . $c['host'] : $c['host'];
        $fp = @stream_socket_client($host . ':' . $c['port'], $errno, $errstr, 15);
        if (!$fp) {
            throw new \RuntimeException("SMTP connect failed: $errstr");
        }
        stream_set_timeout($fp, 15);
        $read = function () use ($fp): string {
            $out = '';
            while (($line = fgets($fp, 515)) !== false) {
                $out .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $out;
        };
        $cmd = function (string $command, array $expect) use ($fp, $read): string {
            fwrite($fp, $command . "\r\n");
            $resp = $read();
            if (!in_array((int) substr($resp, 0, 3), $expect, true)) {
                throw new \RuntimeException('SMTP error after ' . explode(' ', $command)[0] . ': ' . trim($resp));
            }
            return $resp;
        };
        $read();
        $ehlo = parse_url((string) Config::get('app.url'), PHP_URL_HOST) ?: 'localhost';
        $cmd("EHLO $ehlo", [250]);
        if (($c['encryption'] ?? '') === 'tls') {
            $cmd('STARTTLS', [220]);
            stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
            $cmd("EHLO $ehlo", [250]);
        }
        if (!empty($c['username'])) {
            $cmd('AUTH LOGIN', [334]);
            $cmd(base64_encode($c['username']), [334]);
            $cmd(base64_encode((string) $c['password']), [235]);
        }
        $cmd("MAIL FROM:<$from>", [250]);
        $cmd("RCPT TO:<$to>", [250, 251]);
        $cmd('DATA', [354]);
        $cmd(preg_replace('/^\./m', '..', $data) . "\r\n.", [250]);
        $cmd('QUIT', [221]);
        fclose($fp);
        return true;
    }

    private static function encodeHeader(string $s): string
    {
        return preg_match('/[^\x20-\x7e]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    }

    private static function wrap(string $title, string $body): string
    {
        return '<!doctype html><html><body style="margin:0;background:#f3f5f9;font-family:Arial,Tahoma,sans-serif;color:#13213f">'
            . '<div style="max-width:600px;margin:0 auto;background:#fff;border-top:4px solid #0f1f45">'
            . '<div style="padding:18px 24px;background:#0f1f45;color:#fff;font-size:18px">SK Arabians</div>'
            . '<div style="padding:24px">' . '<h2 style="margin-top:0;font-size:18px">' . e($title) . '</h2>' . $body . '</div>'
            . '<div style="padding:12px 24px;background:#eceff4;font-size:12px;color:#55607a">SK Arabians · Doha - Qatar</div></div></body></html>';
    }
}
