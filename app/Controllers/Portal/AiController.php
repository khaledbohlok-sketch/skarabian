<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Resources\Registry;
use App\Services\Ai;
use App\Services\Cache;
use App\Services\SiteAssistant;

/**
 * Administration → AI assistant (Owner): Google's free AI key, the ✨ Translate buttons on the website forms and the
 * website chat box. Also answers the ✨ Translate buttons.
 */
final class AiController extends Controller
{
    private const SETTINGS = [
        'ai.enabled'     => ['bool', 'ai.set_enabled'],
        'ai.api_key'     => ['encrypted', 'ai.set_key'],
        'ai.model'       => ['text', 'ai.set_model', Ai::DEFAULT_MODEL],
        'ai.daily_limit' => ['int', 'ai.set_limit', '200'],
    ];

    private const CHAT = [
        'ai.public_chat'        => ['bool', 'ai.set_chat'],
        'ai.public_daily_limit' => ['int', 'ai.set_chat_limit', '30'],
        'ai.public_daily_cap'   => ['int', 'ai.set_chat_cap', '500'],
    ];

    public function settings(): void
    {
        Auth::requireOwner();
        if (Request::isPost()) {
            $defs = self::SETTINGS;
            $errors = [];
            $model = Request::post('ai__model');
            if (!is_string($model) || !preg_match('/^[a-z0-9][a-z0-9.\-]{2,80}$/', trim($model))) {
                unset($defs['ai.model']); // Google's model names only ("gemini-3.5-flash-lite"); the saved one stays
                $errors['ai__model'] = __('ai.err_model');
            }
            $errors += AdminController::saveSettings($defs) + AdminController::saveSettings(self::CHAT);
            \App\Services\Settings::set('ai.json_mode', null); // the model may have changed: find again how it takes JSON answers
            Session::errors($errors);
            Cache::bump(); // the website shows or hides its chat box at once
            if (!$errors && Request::post('action') === 'test') {
                $this->test();
            }
            $this->flash($errors ? 'error' : 'success', __($errors ? 'validation.fix_errors' : 'common.saved'));
            $this->redirect('/portal/settings/ai');
        }
        $this->view('portal/admin/ai', [
            'title' => __('ai.title'), 'defs' => self::SETTINGS, 'chat' => self::CHAT,
            'enabled' => Ai::enabled(), 'hasKey' => \App\Services\Settings::has('ai.api_key'), 'chatOn' => SiteAssistant::on(),
            'today' => DB::pairs("SELECT action, COUNT(*) FROM activity_log WHERE action IN ('ai_translate', 'ai_site_chat', 'ai_test') AND created_at >= CURDATE() GROUP BY action"),
        ]);
    }

    /** "Save and test the key": one tiny request; says whether Google accepts the key (and how fast it answered). */
    private function test(): never
    {
        try {
            $s = Ai::test();
            Audit::log('ai_test', 'settings', null, null, null, ['ok' => true], 'AI key tested: accepted');
            $this->flash('success', __('ai.test_ok', ['s' => number_format($s, 1)]));
        } catch (\RuntimeException $e) {
            $key = str_starts_with($e->getMessage(), 'ai.') ? $e->getMessage() : 'ai.err_failed';
            Audit::log('ai_test', 'settings', null, null, null, ['ok' => false, 'error' => $key], 'AI key tested: ' . $key);
            $this->flash('error', __('ai.test_failed', ['why' => __($key)]));
        }
        $this->redirect('/portal/settings/ai');
    }

    /** POST /portal/ai/translate {form, field, to, text} → {text}: a ✨ Translate button on a website form. */
    public function translate(): void
    {
        Auth::requireLogin();
        $in = fn (string $k): string => is_string($v = Request::post($k, '')) ? trim($v) : ''; // a list sent instead of text counts as empty
        $form = $in('form');
        $field = $in('field');
        $to = $in('to');
        $text = $in('text');
        $target = $this->target($form, $field);
        if (!$target || !in_array($to, ['en', 'ar'], true) || !str_ends_with($field, '_' . $to)) {
            Response::json(['error' => __('ai.err_failed')], 422);
        }
        [$module, $label] = $target;
        if (!Ai::allowedFor($module, $form)) {
            Auth::deny($module . '.ai_translate');
        }
        if ($text === '') {
            Response::json(['error' => __('ai.err_empty')], 422);
        }
        if (mb_strlen($text) > Ai::MAX_INPUT_CHARS) {
            Response::json(['error' => __('ai.err_long')], 422);
        }
        if (Ai::usedToday((int) Auth::id()) >= Ai::dailyLimit()) {
            Response::json(['error' => __('ai.err_limit')], 429);
        }
        // nothing is kept in the session from here: let it go, so the user's other pages don't wait for Google (up to a minute)
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        try {
            $out = Ai::translate($text, $to, \App\Core\Lang::get($label, [], 'en'));
        } catch (\RuntimeException $e) {
            Response::json(['error' => __(str_starts_with($e->getMessage(), 'ai.') ? $e->getMessage() : 'ai.err_failed')], 502);
        }
        Audit::log('ai_translate', $module, null, null, null, ['form' => $form, 'field' => $field, 'to' => $to, 'chars' => mb_strlen($text)], 'AI translation: ' . $form . ' · ' . $field);
        Response::json(['text' => $out]);
    }

    /**
     * [module, label] of a field with a ✨ Translate button: an English or Arabic text field of a website form that
     * has its partner in the other language. Null for anything else.
     */
    private function target(string $form, string $field): ?array
    {
        if (!preg_match('/^([a-z0-9_]+)_(en|ar)$/', $field, $m) || !in_array($form, Ai::TRANSLATE_RESOURCES, true)) {
            return null;
        }
        $partner = $m[1] . '_' . ($m[2] === 'en' ? 'ar' : 'en');
        if ($form === 'cms-home') { // Website → Homepage & texts: settings, sent as site__hero_title_en
            $defs = CmsController::HOME;
            $def = $defs[str_replace('__', '.', $field)] ?? null;
            return $def && in_array($def[0], ['text', 'textarea'], true) && isset($defs[str_replace('__', '.', $partner)]) ? ['cms', $def[1]] : null;
        }
        $res = Registry::get($form);
        $fields = $res ? $res->fields() : [];
        $f = $fields[$field] ?? null;
        return $f && in_array($f['type'] ?? 'text', ['text', 'textarea'], true) && empty($f['readonly']) && isset($fields[$partner]) ? [$res->module, $f['label']] : null;
    }
}
