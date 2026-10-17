<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\ErrorHandler;

/**
 * Minimal SMTP client (SSL/STARTTLS, AUTH LOGIN) with optional attachments; every email has the shared SK Arabian look
 * (EmailLook) and a plain-text copy. Driver "log" writes mails to storage/logs/mail.log (local/staging), "mail" uses mail().
 */
final class Mailer
{
    /** Why the last send() failed (shown by Settings → Send test email); null after a send that worked. */
    public static ?string $lastError = null;

    /**
     * $htmlBody is the email's own content: it goes into the shared frame (EmailLook: the SK Arabian header, the contact
     * details, a plain-text copy), in the reader's language ($options['lang'], else Arabic when the subject has Arabic).
     * @param array<array{name:string,content:string,mime:string}> $attachments
     * @param array{text?:string, reply_to?:string, from_name?:string, wrap?:bool, lang?:string} $options
     *   text = the plain-text copy (made from the HTML when not given), reply_to = the Reply-To address, from_name =
     *   instead of the configured sender name, wrap = false when $htmlBody is a whole email page already (DocumentMail),
     *   lang = 'en' | 'ar' for the frame.
     */
    public static function send(string $to, string $subject, string $htmlBody, array $attachments = [], array $options = []): bool
    {
        self::$lastError = null;
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            self::$lastError = 'invalid address';
            return false;
        }
        $replyTo = (string) ($options['reply_to'] ?? '');
        if ($replyTo !== '' && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            self::$lastError = 'invalid reply-to address';
            return false;
        }
        $c = Config::get('mail');
        $from = $c['from'] ?? 'no-reply@localhost';
        try {
            [$headers, $body, $text] = self::compose($to, $subject, $htmlBody, $attachments, $options);
            $ok = match ($c['driver'] ?? 'log') {
                'smtp'  => self::smtp($c, $from, $to, implode("\r\n", $headers) . "\r\n\r\n" . $body),
                'mail'  => mail($to, self::encodeHeader($subject), $body, implode("\r\n", array_filter($headers, fn ($h) => !str_starts_with($h, 'To:') && !str_starts_with($h, 'Subject:')))),
                default => (bool) file_put_contents(Config::storagePath('logs/mail.log'), '[' . date('c') . "] TO $to | $subject\n" . ($replyTo !== '' ? "Reply-To: $replyTo\n" : '')
                    . self::redact($text ?? strip_tags($htmlBody)) . "\n"
                    . ($attachments ? 'Attachments: ' . implode(', ', array_map(fn ($a) => $a['name'] . ' (' . $a['mime'] . ', ' . strlen($a['content']) . ' bytes)', $attachments)) . "\n" : '') . "\n", FILE_APPEND),
            };
            if (!$ok) {
                self::$lastError = ($c['driver'] ?? 'log') === 'mail' ? 'the server\'s mail() refused the message' : 'the mail log could not be written';
            }
            return $ok;
        } catch (\Throwable $e) {
            ErrorHandler::log($e, ['mail_to' => $to]);
            self::$lastError = mb_substr($e->getMessage(), 0, 300);
            return false;
        }
    }

    /**
     * The message as send() hands it over: [header lines, MIME body, its plain text]. The plain text and the HTML
     * (quoted-printable) go as multipart/alternative, inside multipart/mixed when there are attachments; a whole page
     * given without a plain text (wrap false, no text) goes alone as HTML (base64).
     * @return array{0: list<string>, 1: string, 2: ?string}
     */
    public static function compose(string $to, string $subject, string $htmlBody, array $attachments = [], array $options = []): array
    {
        $c = Config::get('mail');
        $from = $c['from'] ?? 'no-reply@localhost';
        $fromName = (string) (($options['from_name'] ?? '') ?: ($c['from_name'] ?? 'SK Arabians'));
        if ($options['wrap'] ?? true) {
            $lang = in_array($options['lang'] ?? null, ['en', 'ar'], true) ? $options['lang'] : EmailLook::langOf($subject, $htmlBody);
            [$html, $framedText] = EmailLook::frame($subject, $htmlBody, $lang, !empty($options['reply_to']));
            $options['text'] ??= $framedText;
        } else {
            $html = $htmlBody;
        }
        $boundary = 'b' . bin2hex(random_bytes(8));
        $headers = [
            'From: ' . self::encodeHeader($fromName) . " <$from>",
            'To: <' . $to . '>',
            'Subject: ' . self::encodeHeader($subject),
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(10)) . '@' . (parse_url((string) Config::get('app.url'), PHP_URL_HOST) ?: 'localhost') . '>',
            'MIME-Version: 1.0',
        ];
        if (!empty($options['reply_to'])) {
            array_splice($headers, 1, 0, ['Reply-To: ' . self::encodeHeader($fromName) . ' <' . $options['reply_to'] . '>']);
        }
        if (!isset($options['text'])) {
            $first = "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html));
        } else {
            $alt = 'a' . bin2hex(random_bytes(8));
            $first = "Content-Type: multipart/alternative; boundary=\"$alt\"\r\n\r\n"
                   . "--$alt\r\n" . self::textPart('plain', (string) $options['text'])
                   . "--$alt\r\n" . self::textPart('html', $html)
                   . "--$alt--\r\n";
        }
        $text = isset($options['text']) ? (string) $options['text'] : null;
        if (isset($alt) && !$attachments) { // nothing attached: the two alternatives are the whole message
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $alt . '"';
            return [$headers, substr($first, strpos($first, "\r\n\r\n") + 4), $text];
        }
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
        $body = "--$boundary\r\n" . $first . "\r\n";
        foreach ($attachments as $a) {
            $body .= "--$boundary\r\nContent-Type: {$a['mime']}; name=\"" . addslashes($a['name']) . "\"\r\n"
                   . "Content-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"" . addslashes($a['name']) . "\"\r\n\r\n"
                   . chunk_split(base64_encode($a['content'])) . "\r\n";
        }
        $body .= "--$boundary--\r\n";
        return [$headers, $body, $text];
    }

    /** A text/plain or text/html part in quoted-printable (lines of at most 76 characters, CRLF line ends). */
    private static function textPart(string $subtype, string $content): string
    {
        return "Content-Type: text/$subtype; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
             . quoted_printable_encode((string) preg_replace('/\r\n|\r|\n/', "\r\n", $content)) . "\r\n";
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
        // $step names the command in an error; the AUTH lines pass one so the (base64) user name and password never reach the log
        $cmd = function (string $command, array $expect, ?string $step = null) use ($fp, $read): string {
            fwrite($fp, $command . "\r\n");
            $resp = $read();
            if (!in_array((int) substr($resp, 0, 3), $expect, true)) {
                throw new \RuntimeException('SMTP error after ' . ($step ?? explode(' ', $command)[0]) . ': ' . trim($resp));
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
            $cmd(base64_encode($c['username']), [334], 'AUTH (user name)');
            $cmd(base64_encode((string) $c['password']), [235], 'AUTH (password)');
        }
        $cmd("MAIL FROM:<$from>", [250]);
        $cmd("RCPT TO:<$to>", [250, 251]);
        $cmd('DATA', [354]);
        $cmd(preg_replace('/^\./m', '..', $data) . "\r\n.", [250]);
        $cmd('QUIT', [221]);
        fclose($fp);
        return true;
    }

    /**
     * The "log" driver keeps mails in a plain file: one-time codes and link tokens (2FA codes, password-reset links)
     * are blanked there so the log cannot be used to sign in. The real email (smtp / mail) is not changed.
     */
    public static function redact(string $text): string
    {
        $text = preg_replace('/(?<![\d.,])\d{6}(?![\d.,])/', '******', $text);
        return preg_replace('/[A-Za-z0-9_-]{32,}/', '[redacted]', $text);
    }

    private static function encodeHeader(string $s): string
    {
        $s = str_replace(["\r", "\n"], ' ', $s); // a line break would start a new header
        return preg_match('/[^\x20-\x7e]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    }
}
