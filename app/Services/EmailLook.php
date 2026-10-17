<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Lang;

/**
 * The look every email shares (the one of the document email): the header with the white SK mark, the gold line, a white
 * card and the company's contact details, in the reader's language (Arabic right to left), with a plain-text copy.
 * Mailer puts every system email (notifications, login codes, summaries, resets, Inbox replies) in frame(); the document
 * email has its own template (DocumentMail) built on the same vars().
 */
final class EmailLook
{
    /** Values every email template uses: language and direction, fonts, the logo, the company's contact details. */
    public static function vars(string $lang): array
    {
        $ar = $lang === 'ar';
        $brand = Lang::get('docmail.brand', [], $lang);
        $phone = (string) (Settings::get('company.phone_intl', '') ?: Settings::get('company.mobile', ''));
        $site = absolute_url('/');
        $poBox = trim((string) Settings::get('company.po_box', '6657'));
        $city = str_replace(' - ', ' – ', (string) (Settings::get($ar ? 'company.city_ar' : 'company.city_en', '') ?: ($ar ? 'الدوحة – قطر' : 'Doha – Qatar')));
        $font = $ar ? 'Segoe UI, Tahoma, Arial, sans-serif' : 'Segoe UI, Helvetica Neue, Helvetica, Arial, sans-serif';
        return [
            'LANG' => $ar ? 'ar' : 'en', 'DIR' => $ar ? 'rtl' : 'ltr', 'ALIGN' => $ar ? 'right' : 'left',
            'FONT' => $font, 'FONT_HEAD' => $ar ? $font : 'Georgia, Times New Roman, serif', 'HEAD_WEIGHT' => $ar ? 'bold' : 'normal',
            'TRACKING' => $ar ? '0' : '3px', // spaced capitals in English; Arabic letters must stay joined
            'LOGO_URL' => absolute_url('/assets/email/logo-white.png'), 'YEAR' => date('Y'), 'BRAND' => $brand,
            'CONTACT_ADDRESS' => ($poBox !== '' ? Lang::get('site.po_box', [], $lang) . ' ' . $poBox . ($ar ? '، ' : ', ') : '') . $city,
            'CONTACT_PHONE' => preg_match('/^\+974(\d{4})(\d{4})$/', $phone, $p) ? "+974 $p[1] $p[2]" : $phone,
            'CONTACT_PHONE_LINK' => (string) preg_replace('/[^+\d]/', '', $phone), 'CONTACT_EMAIL' => DocumentMail::replyTo(),
            'WEBSITE_URL' => $site, 'WEBSITE_LABEL' => (string) preg_replace('/^www\./', '', (string) parse_url($site, PHP_URL_HOST)),
            'TXT_RIGHTS' => Lang::get('docmail.rights', [], $lang),
        ];
    }

    /**
     * A system email in the shared frame: [html, plain text]. $bodyHtml is the email's own content, built by the system
     * (what people typed is escaped there). $replyable: replies are read (a Reply-To is set), else "sent automatically".
     * @return array{0: string, 1: string}
     */
    public static function frame(string $title, string $bodyHtml, string $lang, bool $replyable = false): array
    {
        $v = self::vars($lang);
        $note = Lang::get($replyable ? 'docmail.footer_note' : 'email.auto_note', ['brand' => $v['BRAND']], $lang);
        $v += ['TITLE' => $title, 'TXT_PREHEADER' => $title, 'TXT_FOOTER_NOTE' => $note];
        $html = render_email_template($v + ['BODY_HTML' => $bodyHtml], 'frame');
        $text = render_email_template($v + ['BODY_TEXT' => self::text($bodyHtml)], 'frame', 'txt');
        return [$html, $text];
    }

    /** The email's language when the sender doesn't say: Arabic when the subject (or else the text) has Arabic letters. */
    public static function langOf(string ...$texts): string
    {
        foreach ($texts as $t) {
            if (preg_match('/\p{Arabic}/u', strip_tags($t))) {
                return 'ar';
            }
        }
        return 'en';
    }

    /** A button (a table cell with the link, so Outlook shows it too). */
    public static function button(string $label, string $url): string
    {
        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:18px 0 8px"><tr>'
            . '<td bgcolor="#213562" style="border-radius:999px;background-color:#213562;mso-padding-alt:12px 28px;">'
            . '<a href="' . e($url) . '" target="_blank" style="display:inline-block;padding:12px 28px;font-size:15px;line-height:20px;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:999px;">'
            . e($label) . '</a></td></tr></table>';
    }

    /** A one-time code: big, spaced, easy to copy. */
    public static function code(string $code): string
    {
        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:16px 0"><tr>'
            . '<td bgcolor="#f8f5ef" dir="ltr" style="background-color:#f8f5ef;border:1px solid #e8dfcd;border-radius:12px;padding:14px 26px;'
            . 'font-family:Consolas,Menlo,Courier New,monospace;font-size:30px;line-height:36px;letter-spacing:8px;font-weight:bold;color:#13213f;">'
            . e($code) . '</td></tr></table>';
    }

    /** The plain-text copy of a system HTML fragment: line breaks kept, a link as "text: address", list items as "- ". */
    public static function text(string $html): string
    {
        $s = (string) preg_replace('#<(style|script|head|title)\b[^>]*>.*?</\1>#is', '', $html);
        $s = (string) preg_replace_callback('#<a\b[^>]*\bhref=("|\')(.*?)\1[^>]*>(.*?)</a>#is', function ($m) {
            $label = trim(html_entity_decode(strip_tags($m[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $url = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return $label === '' || $label === $url ? $url : $label . ': ' . $url;
        }, $s);
        $s = (string) preg_replace('#<br\s*/?>#i', "\n", $s);
        $s = (string) preg_replace('#<li\b[^>]*>#i', "\n- ", $s);
        $s = (string) preg_replace('#<hr\b[^>]*>#i', "\n----\n", $s);
        $s = (string) preg_replace('#</(p|div|h[1-6]|ul|ol|table|blockquote)>#i', "\n\n", $s);
        $s = (string) preg_replace('#</tr>#i', "\n", $s);
        $s = (string) preg_replace('#</t[dh]>#i', '  ', $s);
        $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = (string) preg_replace('/[ \t]+(\r\n|\n)/', "\n", $s);
        $s = (string) preg_replace('/(\n[ \t]*){3,}/', "\n\n", $s);
        return trim($s);
    }
}
