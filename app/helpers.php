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

function asset(string $path): string
{
    $file = APP_ROOT . '/public/assets/' . ltrim($path, '/');
    $v = is_file($file) ? substr((string) filemtime($file), -6) : '1';
    return url('/assets/' . ltrim($path, '/')) . '?v=' . $v;
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

/** Money in QAR, 2 decimals; negative values carry a minus sign (rendered red by .neg). */
function money(mixed $amount, string $currency = 'QAR', bool $html = true): string
{
    if ($amount === null || $amount === '') {
        return $html ? '<span class="muted">—</span>' : '';
    }
    $n = round((float) $amount, 2);
    $txt = ($n < 0 ? '-' : '') . number_format(abs($n), 2) . ' ' . $currency;
    if (!$html) {
        // Inside Arabic sentences keep "1,234.00 QAR" in reading order (left-to-right isolate)
        return \App\Core\Lang::isRtl() ? "\u{2066}" . $txt . "\u{2069}" : $txt;
    }
    return '<span class="money' . ($n < 0 ? ' neg' : '') . '" dir="ltr">' . e($txt) . '</span>';
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
    $ts = strtotime($date);
    return $ts ? date($withTime ? 'd M Y H:i' : 'd M Y', $ts) : '';
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
    if ($d->y >= 1) {
        return __('common.age_years', ['n' => $d->y]);
    }
    return __('common.age_months', ['n' => max(0, $d->m)]);
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

function status_badge(string $status, string $prefix = 'status'): string
{
    return '<span class="badge badge-' . e($status) . '">' . e(__($prefix . '.' . $status)) . '</span>';
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

function icon(string $name, string $class = ''): string
{
    return '<svg class="icon ' . e($class) . '" aria-hidden="true"><use href="' . e(url('/assets/img/icons.svg')) . '#' . e($name) . '"></use></svg>';
}
