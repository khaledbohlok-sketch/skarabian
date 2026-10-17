<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Session;
use App\Services\Settings;

/** HTML-escape for output (XSS protection). */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function __(string $key, array $params = []): string
{
    return Lang::get($key, $params);
}

function lang(): string
{
    return Lang::current();
}

function is_rtl(): bool
{
    return Lang::isRtl();
}

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

function setting(string $key, mixed $default = null): mixed
{
    return Settings::get($key, $default);
}

/** URL relative to the install base path. */
function url(string $path = '/', array $query = []): string
{
    $u = Request::basePath() . '/' . ltrim($path, '/');
    if ($query) {
        $u .= (str_contains($u, '?') ? '&' : '?') . http_build_query($query);
    }
    return $u === '' ? '/' : $u;
}

/** A same-site path to redirect to ("/portal/bills?x=1"), or the fallback for anything else ("https://x", "//x", "/\x"). */
function local_path(mixed $to, string $fallback): string
{
    return is_string($to) && str_starts_with($to, '/') && !str_starts_with($to, '//') && !str_contains($to, '\\') ? $to : $fallback;
}

function absolute_url(string $path = '/'): string
{
    return rtrim((string) Config::get('app.url'), '/') . '/' . ltrim($path, '/');
}

/** Public site URL in the current (or given) language. */
function site_url(string $path = '', ?string $lang = null): string
{
    $lang ??= Lang::current();
    return url('/' . $lang . ($path !== '' ? '/' . ltrim($path, '/') : ''));
}

/**
 * A file under /assets with a version taken from its CONTENT (?v=8 hex): a changed file always gets a new address, so
 * browsers and the offline cache (sw.js keeps every address forever) can never hold an old copy under a new name —
 * which a date-based version allowed when the server's two copies (app folder / public_html) were updated apart.
 */
function asset(string $path): string
{
    static $seen = [];
    $file = APP_ROOT . '/public/assets/' . ltrim($path, '/');
    $v = $seen[$file] ??= (is_file($file) ? substr((string) md5_file($file), 0, 8) : '1');
    return url('/assets/' . ltrim($path, '/')) . '?v=' . $v;
}

/** A real calendar day written as Y-m-d ("2026-02-30" is not: the database would refuse it with an error page). */
function valid_date(mixed $date): bool
{
    if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }
    $d = \DateTime::createFromFormat('!Y-m-d', $date);
    return $d !== false && $d->format('Y-m-d') === $date;
}

function csrf_field(): string
{
    return Csrf::field();
}

function csrf_token(): string
{
    return Csrf::token();
}

function can(string $module, string $action = 'view'): bool
{
    return Auth::can($module, $action);
}

/** The question before Delete: the Owner deletes at once (to the Trash), anyone else sends the Owner a request. */
function delete_confirm(string $ownerKey = 'common.confirm_delete'): string
{
    return __(Auth::isOwner() ? $ownerKey : 'common.confirm_delete_request');
}

function old(string $key, mixed $default = null): mixed
{
    return Session::old($key, $default);
}

function field_error(string $key): string
{
    $err = Session::error($key);
    return $err ? '<div class="field-error">' . e($err) . '</div>' : '';
}

/** Picks the localized column of a bilingual record (name_ar in Arabic, falling back to name_en). */
function loc(?array $row, string $field = 'name'): string
{
    if (!$row) {
        return '';
    }
    if (Lang::current() === 'ar' && !empty($row[$field . '_ar'])) {
        return (string) $row[$field . '_ar'];
    }
    return (string) ($row[$field . '_en'] ?? $row[$field . '_ar'] ?? $row[$field] ?? '');
}

/** An activity-log summary for display. The stored text is English and sealed in the tamper-evident chain,
 * so "Access denied: documents.view" is worded here in the user's language; other summaries show as stored. */
function activity_summary(array $a): string
{
    $s = (string) ($a['summary'] ?? '');
    // the fixed English phrases the system writes itself (sign-in, sign-out, upload), in the reader's language
    $fixed = ['Logged in' => 'activity.a_login', 'Logged out' => 'activity.a_logout', 'File uploaded' => 'activity.a_upload', 'Unknown user' => 'activity.unknown_user'];
    if (isset($fixed[$s])) {
        return __($fixed[$s]);
    }
    if (($a['action'] ?? '') !== 'access_denied' || !str_starts_with($s, 'Access denied: ')) {
        return $s;
    }
    [$m, $act] = array_pad(explode('.', substr($s, 15), 2), 2, '');
    $part = fn (string $prefix, string $raw) => Lang::has($prefix . $raw) ? __($prefix . $raw) : $raw;
    // the action as a permission ("View"), else the module's own wording of it ("studio.send_email": "Send by email")
    $action = Lang::has('roles.a_' . $act) ? __('roles.a_' . $act) : $part($m . '.', $act);
    return __('activity.a_access_denied') . ': ' . $part('roles.m_', $m) . ($act !== '' ? ' — ' . $action : '');
}

/** A device name as stored (Request::device(), "Windows · Chrome"), shown in the user's language: the product names
 * stay, the two generic words ("Unknown", "Browser") are translated. The stored text is kept: known devices match on it. */
function device_label(?string $d): string
{
    $d = (string) $d;
    if ($d === '') {
        return '—';
    }
    return implode(' · ', array_map(fn ($p) => match ($p) { 'Unknown' => __('common.unknown'), 'Browser' => __('account.browser'), default => $p }, explode(' · ', $d)));
}

/** A horse's bloodline (free text, written in English) in Arabic when it is made only of the classical strain names
 * ("Kuhaylan Ajuz" → "كحيلان عجوز"); anything else is shown as typed. */
function bloodline_label(?string $s): string
{
    $s = trim((string) $s);
    if ($s === '' || Lang::current() !== 'ar') {
        return $s;
    }
    static $ar = ['kuhaylan' => 'كحيلان', 'kuhailan' => 'كحيلان', 'kehilan' => 'كحيلان', 'kuhaila' => 'كحيلة', 'saqlawi' => 'صقلاوي', 'seglawi' => 'صقلاوي',
        'saklawi' => 'صقلاوي', 'abeyan' => 'عبيان', 'obayan' => 'عبيان', 'abayyan' => 'عبيان', 'hamdani' => 'حمداني', 'hadban' => 'هدبان', 'dahman' => 'دهمان',
        'shuwayman' => 'شويمان', 'jilfan' => 'جلفان', 'ajuz' => 'عجوز', 'jidran' => 'جدران', 'shahwan' => 'شهوان', 'enzahi' => 'انزحي'];
    $out = [];
    foreach (preg_split('/\s+/', $s) as $w) {
        $k = mb_strtolower($w);
        if (!isset($ar[$k])) {
            return $s;
        }
        $out[] = $ar[$k];
    }
    return implode(' ', $out);
}

/** A lookup value stored as its English text (unit "bag") shown in the user's language ("كيس"). */
function lookup_label(string $type, ?string $value): string
{
    static $ar = [];
    $value = (string) $value;
    if ($value === '' || Lang::current() !== 'ar') {
        return $value;
    }
    $ar[$type] ??= array_column(App\Core\DB::all('SELECT value_en, value_ar FROM lookups WHERE type = ?', [$type]), 'value_ar', 'value_en');
    return (string) (($ar[$type][$value] ?? '') ?: $value);
}

/**
 * Money; negative values carry a minus sign (rendered red by .neg). English: "4,500 QR" (decimals only when there
 * are some: "4,500.50 QR"); Arabic keeps "4,500.00 QAR" as the Owner chose. Other currencies keep their code.
 */
function money(mixed $amount, string $currency = 'QAR', bool $html = true): string
{
    if ($amount === null || $amount === '') {
        return $html ? '<span class="muted">—</span>' : '';
    }
    $n = round((float) $amount, 2);
    $rtl = \App\Core\Lang::isRtl();
    $decimals = !$rtl && fmod(abs($n), 1.0) < 0.005 ? 0 : 2;
    $label = !$rtl && $currency === 'QAR' ? 'QR' : $currency;
    $fig = ($n < 0 ? '-' : '') . number_format(abs($n), $decimals);
    if (!$html) {
        // Inside Arabic sentences keep "1,234.00 QAR" in reading order (left-to-right isolate)
        return $rtl ? "\u{2066}" . $fig . ' ' . $label . "\u{2069}" : $fig . ' ' . $label;
    }
    return '<span class="money' . ($n < 0 ? ' neg' : '') . '" dir="ltr">' . e($fig) . '<span class="cur"> ' . e($label) . '</span></span>';
}

/** Currency label as shown next to amounts: "QR" in English, the code itself otherwise. */
function cur_label(string $currency = 'QAR'): string
{
    return !\App\Core\Lang::isRtl() && $currency === 'QAR' ? 'QR' : $currency;
}

/**
 * A count with its noun in the right form: plural(1, 'count.horse') → "1 horse", plural(3, …) → "3 horses";
 * Arabic: _one (1), _two (2), _few (3–10), _many (11–99), else _other. Keys: <key>_one, <key>_other (+ Arabic forms).
 */
function plural(int|float|string $n, string $key): string
{
    $n = (int) $n;
    $m = abs($n) % 100;
    $forms = !\App\Core\Lang::isRtl() ? [abs($n) === 1 ? 'one' : 'other']
        : match (true) {
            abs($n) === 1 => ['one'],
            abs($n) === 2 => ['two', 'other'],
            $m >= 3 && $m <= 10 => ['few', 'other'],
            $m >= 11 => ['many', 'other'],
            default => ['other'],
        };
    foreach ($forms as $f) {
        if (\App\Core\Lang::has($key . '_' . $f)) {
            return __($key . '_' . $f, ['n' => number_format($n)]);
        }
    }
    return __($key . '_other', ['n' => number_format($n)]);
}

/**
 * Staff system / Studio screen theme chosen in the user menu (cookie sk_theme, remembered per browser): 'dark'
 * (Cinematic Dark, the default), 'light' or 'system' (follows the device). Only decides which stylesheet layer applies.
 */
function ui_theme(): string
{
    $t = (string) ($_COOKIE['sk_theme'] ?? 'dark');
    return in_array($t, ['dark', 'light', 'system'], true) ? $t : 'dark';
}

/** "reduce" when the user chose Account → Reduce animations (sk_motion cookie), else "full"; set on <html data-motion>. */
function ui_motion(): string
{
    return ($_COOKIE['sk_motion'] ?? '') === 'reduce' ? 'reduce' : 'full';
}

/** The media attribute of the dark layer (portal-cinematic.css) for the chosen theme — set by the server, so no flash. */
function theme_media(): string
{
    return ['dark' => 'screen', 'light' => 'not all', 'system' => 'screen and (prefers-color-scheme: dark)'][ui_theme()];
}

/**
 * What a horse is, for search titles and photo descriptions: "Grey Arabian Stallion" / "فحل عربي أصيل" (the colour
 * only when the website shows colours). Used as "D SAMED – Grey Arabian Stallion".
 */
function horse_kind(array $h): string
{
    $colour = \App\Services\SiteData::show('profile_colour') && !empty($h['color_en']) ? loc(['name_en' => $h['color_en'], 'name_ar' => $h['color_ar'] ?? null]) : '';
    $key = 'site.seo_kind_' . ($h['category'] ?? '');
    return trim((string) preg_replace('/\s+/u', ' ', Lang::has($key) ? __($key, ['colour' => $colour]) : __('site.seo_kind_horse', ['colour' => $colour])));
}

/** A Qatar phone number as people read it: "+97433581816" or "33581816" → "+974 3358 1816" (others as typed). */
function phone_display(?string $phone): string
{
    $d = preg_replace('/\D/', '', (string) $phone);
    if (strlen($d) === 11 && str_starts_with($d, '974')) {
        $d = substr($d, 3);
    }
    return strlen($d) === 8 ? '+974 ' . substr($d, 0, 4) . ' ' . substr($d, 4) : trim((string) $phone);
}

/** Contact details of the WEBSITE and the portal screens (Website → Contact details): +974 3358 1816, contact@skarabian.com. */
function site_contact(string $what): string
{
    return match ($what) {
        'phone' => phone_display((string) (setting('company.mobile', '') ?: setting('company.phone_intl', '') ?: '+97433581816')),
        'tel' => '+' . (preg_replace('/\D/', '', phone_display((string) (setting('company.phone_intl', '') ?: setting('company.mobile', '') ?: '+97433581816'))) ?: '97433581816'),
        'email' => (string) (setting('company.email', '') ?: 'contact@skarabian.com'),
        // "P.O. Box 6657, Doha – Qatar" / "ص.ب 6657، الدوحة – قطر"
        'po_box' => __('site.po_box') . ' ' . (setting('company.po_box', '') ?: '6657') . (Lang::isRtl() ? '، ' : ', ')
            . str_replace(' - ', ' – ', (string) (setting(Lang::isRtl() ? 'company.city_ar' : 'company.city_en', '') ?: (Lang::isRtl() ? 'الدوحة – قطر' : 'Doha – Qatar'))),
        'whatsapp' => preg_replace('/\D/', '', (string) setting('company.whatsapp', '')) ?: '97433581816',
        default => '',
    };
}

/** Contact details printed on DOCUMENTS (letterheads, Studio, printouts): Mob. 5536 6699, sk.qa@hotmail.com, C.R. 231961. */
function doc_contact(string $what): string
{
    return match ($what) {
        'mobile' => (string) (setting('company.doc_mobile', '') ?: '5536 6699'),
        'email' => (string) (setting('company.doc_email', '') ?: 'sk.qa@hotmail.com'),
        'cr' => (string) (setting('company.cr', '') ?: '231961'),
        'po_box' => (string) (setting('company.po_box', '') ?: '6657'),
        'city' => 'Doha – Qatar',
        'city_ar' => 'الدوحة – قطر',
        default => '',
    };
}

function num(mixed $n, int $decimals = 0): string
{
    return number_format((float) $n, $decimals);
}

function fmt_date(?string $date, bool $withTime = false): string
{
    if (!$date || str_starts_with($date, '0000')) {
        return '';
    }
    if (preg_match('/^\d{4}$/', $date)) {
        return $date; // only the year is known (a show saved as "year only", see show_date_sql())
    }
    $ts = strtotime($date);
    if (!$ts) {
        return '';
    }
    if (Lang::isRtl()) {
        return date('j', $ts) . ' ' . ar_month((int) date('n', $ts)) . ' ' . date('Y', $ts) . ($withTime ? ' ' . date('H:i', $ts) : '');
    }
    return date($withTime ? 'j M Y H:i' : 'j M Y', $ts);   // one format everywhere: "5 Oct 2026"
}

/**
 * SQL for a show's date as shown: "2019" when only the year is known (shows.year_only, stored as 1 January), otherwise
 * the start date. fmt_date() prints a bare year as it is. Before database update 018 there is no year_only column.
 */
function show_date_sql(string $alias): string
{
    return \App\Core\DB::hasColumn('shows', 'year_only')
        ? "IF($alias.year_only = 1, DATE_FORMAT($alias.start_date, '%Y'), $alias.start_date)"
        : "$alias.start_date";
}

/** Month (and year) in the current language: "October 2026" / "أكتوبر 2026"; $short gives "Oct" in English. */
function fmt_month(string $date, bool $short = false, bool $withYear = true): string
{
    $ts = strtotime($date);
    if (!$ts) {
        return '';
    }
    $m = Lang::isRtl() ? ar_month((int) date('n', $ts)) : date($short ? 'M' : 'F', $ts);
    return $withYear ? $m . ' ' . date('Y', $ts) : $m;
}

function ar_month(int $n): string
{
    return ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'][$n - 1] ?? '';
}

function today(): string
{
    return date('Y-m-d');
}

/** Age in years/months from a date of birth. */
function horse_age(?string $dob): string
{
    if (!$dob) {
        return '';
    }
    $d = (new DateTime($dob))->diff(new DateTime());
    // 1 year / 2 years… in English; سنة واحدة، سنتان، 3–10 سنوات، 11 سنة… in Arabic
    $say = fn (int $n, string $unit) => match (true) {
        $n === 1 => __("common.age_$unit"),
        $n === 2 => __("common.age_{$unit}s_2"),
        $n >= 3 && $n <= 10 => __("common.age_{$unit}s_few", ['n' => $n]),
        default => __("common.age_{$unit}s", ['n' => $n]),
    };
    return $d->y >= 1 ? $say($d->y, 'year') : $say(max(0, $d->m), 'month');
}

function slugify(string $text): string
{
    $t = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text), '-'));
    return $t !== '' ? $t : bin2hex(random_bytes(4));
}

function selected(mixed $a, mixed $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function checked(mixed $v): string
{
    return $v ? ' checked' : '';
}

/** Days until a date (negative when in the past). */
function days_until(?string $date): ?int
{
    if (!$date) {
        return null;
    }
    return (int) floor((strtotime($date) - strtotime(today())) / 86400);
}

/** CSS class for document expiry: expired (red), expiring within 60 days (amber). */
function expiry_class(?string $date): string
{
    $d = days_until($date);
    if ($d === null) {
        return '';
    }
    return $d < 0 ? 'exp-expired' : ($d <= 60 ? 'exp-soon' : 'exp-ok');
}

/** A select option's label: translation keys ("horse.cat_mare") are translated, plain text (a name, "0.5 ml") is shown as is. */
function opt_label(mixed $label): string
{
    $l = (string) $label;
    return preg_match('/^[a-z][a-z0-9_]*\.[a-z0-9_.]+$/', $l) ? __($l) : $l;
}

/** Label of a stored value: "status.active" for the shared status list, "horse.status_active", "invoices.type_boarding"... for the rest. */
function enum_label(string $prefix, string $value): string
{
    return __(str_contains($prefix, '.') ? $prefix . '_' . $value : $prefix . '.' . $value);
}

function status_badge(string $status, string $prefix = 'status'): string
{
    return '<span class="badge badge-' . e($status) . '">' . e(enum_label($prefix, $status)) . '</span>';
}

function current_path(): string
{
    return Request::path();
}

/** Builds the current list URL with modified query parameters (filters, sort, page). */
function query_url(array $changes): string
{
    $q = array_merge($_GET, $changes);
    foreach ($q as $k => $v) {
        if ($v === null || $v === '') {
            unset($q[$k]);
        }
    }
    return url(Request::path(), $q);
}

/** Arrows that point the right way in both languages: 'back' (← / →), 'prev' and 'next' (pagination), 'to' (a from → to range). */
function arrow(string $kind): string
{
    $rtl = Lang::isRtl();
    return match ($kind) {
        'back' => $rtl ? '→' : '←',
        'prev' => $rtl ? '›' : '‹',
        'next' => $rtl ? '‹' : '›',
        default => $rtl ? '←' : '→',
    };
}

/**
 * A website headline ready for its entrance: every word in its own mask (site.js lifts them line by line) and the last
 * word in metallic gold. Escaped like e(); screen readers and search engines read the same words, and Arabic letters
 * stay joined because words are never split. $gold = false: masks only.
 */
function split_title(string $text, bool $gold = true): string
{
    $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $last = count($words) - 1;
    $out = [];
    foreach ($words as $i => $w) {
        $cls = $gold && $i === $last && $last > 0 ? ' class="gold-text"' : '';
        $out[] = '<span class="w"><span' . $cls . ' style="--i:' . $i . '">' . e($w) . '</span></span>';
    }
    return implode(' ', $out);
}

/** A headline with its last word in metallic gold, no masks (titles that do not animate in). Escaped like e(). */
function gold_title(string $text): string
{
    $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (count($words) < 2) {
        return e($text);
    }
    $lastWord = array_pop($words);
    return e(implode(' ', $words)) . ' <span class="gold-text">' . e($lastWord) . '</span>';
}

function icon(string $name, string $class = ''): string
{
    // versioned like the other files (browsers keep icons.svg for 30 days: a new icon must not wait for that)
    static $sprite = null;
    $sprite ??= asset('img/icons.svg');
    return '<svg class="icon ' . e($class) . '" aria-hidden="true"><use href="' . e($sprite) . '#' . e($name) . '"></use></svg>';
}

/**
 * An email made from templates/email/<name>.html (or the plain-text copy <name>.txt): every {{KEY}} becomes
 * $vars['KEY'], HTML-escaped in the .html one. Values whose key ends in _URL (VERIFY_URL, LOGO_URL…) must be whole
 * http(s) addresses without spaces, quotes or angle brackets, else nothing is built (\InvalidArgumentException).
 * Values whose key ends in _HTML are HTML the system built (anything people typed already escaped in it): put in as
 * they are (as plain text in a .txt template). {{# notes #}} for whoever edits the template are not sent; a {{KEY}}
 * without a value is left out.
 */
function render_email_template(array $vars, string $name = 'document', string $format = 'html'): string
{
    if (!preg_match('/^[a-z0-9_-]+$/', $name) || !in_array($format, ['html', 'txt'], true)) {
        throw new InvalidArgumentException('Unknown email template: ' . $name . '.' . $format);
    }
    $file = APP_ROOT . '/templates/email/' . $name . '.' . $format;
    if (!is_file($file)) {
        throw new RuntimeException('Email template missing: templates/email/' . $name . '.' . $format);
    }
    $tpl = preg_replace('/\{\{#.*?#\}\}(?:\r\n|\n|\r)?/s', '', (string) file_get_contents($file)); // not \R: see Migrator::statements()
    $map = [];
    foreach ($vars as $key => $value) {
        if (!is_string($key) || !preg_match('/^[A-Z][A-Z0-9_]*$/', $key) || !(is_scalar($value) || $value === null)) {
            throw new InvalidArgumentException('Bad email template value: ' . $key);
        }
        $value = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', (string) $value);
        if (str_ends_with($key, '_URL') && (filter_var($value, FILTER_VALIDATE_URL) === false
            || !in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true) || preg_match('/[\s"\'<>`\\\\{}|^]/', $value))) {
            throw new InvalidArgumentException($key . ' is not a web address');
        }
        if (str_ends_with($key, '_HTML')) {
            $map['{{' . $key . '}}'] = $format === 'html' ? $value : html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            continue;
        }
        $map['{{' . $key . '}}'] = $format === 'html' ? e($value) : $value;
    }
    preg_match_all('/\{\{[A-Z][A-Z0-9_]*\}\}/', $tpl, $m);
    foreach ($m[0] as $placeholder) {
        $map[$placeholder] ??= '';
    }
    return strtr($tpl, $map); // one pass: a value that looks like {{KEY}} is never replaced again
}
