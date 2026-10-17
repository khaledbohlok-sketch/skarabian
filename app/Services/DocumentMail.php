<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\ErrorHandler;
use App\Core\Lang;

/**
 * The email that carries an issued Studio document (Studio → document → Send by e-mail): templates/email/document.html
 * with its plain-text copy document.txt, in the document's language (Arabic right to left), the PDF attached.
 * Sent as "SK Arabian"; replies go to the company email of Website → Contact details (contact@skarabian.com when none).
 */
final class DocumentMail
{
    public const FROM_NAME = 'SK Arabian';
    public const REPLY_TO = 'contact@skarabian.com';

    /** Sends the PDF; false (Mailer::$lastError says why) when it could not be built or sent. */
    public static function send(array $doc, string $to, string $recipientName, string $fileName, string $pdf): bool
    {
        try {
            $name = $recipientName !== '' ? $recipientName : self::knownName($to, self::lang($doc));
            $m = self::build($doc, $name, $fileName, strlen($pdf));
        } catch (\Throwable $e) {
            ErrorHandler::log($e, ['mail_to' => $to]);
            Mailer::$lastError = mb_substr($e->getMessage(), 0, 300);
            return false;
        }
        return Mailer::send($to, $m['subject'], $m['html'], [['name' => $fileName, 'mime' => 'application/pdf', 'content' => $pdf]],
            ['text' => $m['text'], 'reply_to' => self::replyTo(), 'from_name' => self::FROM_NAME, 'wrap' => false]);
    }

    /**
     * The email without sending it: subject ("REFERENCE — type"), HTML, plain text and the values put in the template.
     * @return array{subject: string, html: string, text: string, vars: array<string, string>}
     */
    public static function build(array $doc, string $recipientName, string $fileName, int $fileSize, ?string $lang = null): array
    {
        $lang = in_array($lang, ['en', 'ar'], true) ? $lang : self::lang($doc);
        $ar = $lang === 'ar';
        $brand = Lang::get('docmail.brand', [], $lang);
        $t = fn (string $key, array $p = []): string => Lang::get('docmail.' . $key, $p + ['brand' => $brand], $lang);
        $type = Lang::get('studio.type_' . $doc['doc_type'], [], $lang);
        $vars = EmailLook::vars($lang) + [ // the shared look: language, fonts, logo, contact details
            'REFERENCE' => (string) $doc['ref_no'], 'DOC_TYPE' => $type, 'DOC_TITLE' => self::heading($doc),
            'RECIPIENT_NAME' => $recipientName !== '' ? $recipientName : $t('sir_madam'),
            'ISSUED_DATE' => self::longDate((string) $doc['doc_date'], $ar), 'ISSUED_BY' => self::issuer($doc) ?: $brand,
            'FILE_NAME' => $fileName, 'FILE_SIZE' => self::size($fileSize),
            'VERIFY_URL' => absolute_url('/verify/' . $doc['verify_token']),
            'TXT_PREHEADER' => $t('preheader', ['type' => $type, 'ref' => $doc['ref_no']]),
        ];
        foreach (['dear', 'dear_end', 'intro', 'reference', 'document', 'issued', 'issued_by', 'attachment', 'attached', 'verify_title',
                  'verify_text', 'verify_button', 'verify_link', 'regards', 'team', 'footer_note', 'rights'] as $key) {
            $vars['TXT_' . strtoupper($key)] = $t($key);
        }
        return [
            'subject' => $doc['ref_no'] . ' — ' . $type,
            'html' => render_email_template($vars, 'document'),
            'text' => render_email_template($vars, 'document', 'txt'),
            'vars' => $vars,
        ];
    }

    /** The email's language: the document's own. */
    public static function lang(array $doc): string
    {
        return ($doc['lang'] ?? '') === 'ar' ? 'ar' : 'en';
    }

    /** Replies go to the company email the website and letterhead show (Website → Contact details). */
    public static function replyTo(): string
    {
        $email = trim((string) Settings::get('company.email', ''));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : self::REPLY_TO;
    }

    /** The greeting's name when none was typed: the one client / supplier, employee or user with this email address. */
    public static function knownName(string $email, string $lang): string
    {
        foreach (['parties', 'employees'] as $table) {
            $rows = DB::all("SELECT DISTINCT name_en, name_ar FROM $table WHERE email = ? AND deleted_at IS NULL LIMIT 2", [$email]);
            if (count($rows) === 1) {
                return trim((string) (($lang === 'ar' ? $rows[0]['name_ar'] : '') ?: ($rows[0]['name_en'] ?: $rows[0]['name_ar'])));
            }
        }
        $rows = DB::all('SELECT DISTINCT name FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 2', [$email]);
        return count($rows) === 1 ? trim((string) $rows[0]['name']) : '';
    }

    /** The document's heading: its title without the type in front ("Financial Report — Outstanding Payables" → "Outstanding Payables"). */
    private static function heading(array $doc): string
    {
        $title = trim((string) $doc['title']);
        foreach (['en', 'ar'] as $l) { // the title was written in the language of whoever issued it
            $prefix = Lang::get('studio.type_' . $doc['doc_type'], [], $l) . ' — ';
            if (str_starts_with($title, $prefix) && trim(substr($title, strlen($prefix))) !== '') {
                return trim(substr($title, strlen($prefix)));
            }
        }
        return $title;
    }

    private static function issuer(array $doc): string
    {
        if (array_key_exists('author', $doc)) {
            return trim((string) $doc['author']);
        }
        return empty($doc['created_by']) ? '' : trim((string) DB::value('SELECT name FROM users WHERE id = ?', [(int) $doc['created_by']]));
    }

    /** "17 September 2026" / "17 سبتمبر 2026" */
    private static function longDate(string $date, bool $ar): string
    {
        $ts = strtotime($date);
        if (!$ts || str_starts_with($date, '0000')) {
            return '';
        }
        return $ar ? date('j', $ts) . ' ' . ar_month((int) date('n', $ts)) . ' ' . date('Y', $ts) : date('j F Y', $ts);
    }

    /** As the Documents library shows sizes: "184 KB", "1.2 MB". */
    private static function size(int $bytes): string
    {
        return $bytes >= 1048576 ? number_format($bytes / 1048576, 1) . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';
    }
}
