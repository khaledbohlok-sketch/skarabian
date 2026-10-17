<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\SiteAssistant;
use App\Services\SiteData;

/**
 * The website chat box: one question in, one answer out (JSON). The conversation is kept in the visitor's session, so
 * a page cannot slip made-up answers into it. Every question is one line in the Activity log (without its text), which
 * also gives the limits: per visitor and for the whole website per day (Administration → AI assistant).
 */
class AssistantController extends Controller
{
    /** Seconds from one answer to the next question of the same visitor (the box itself sends one question at a time). */
    private const MIN_GAP = 2;

    /** Seconds a question counts as still being answered (Google's longest waits), so a second one meanwhile is refused. */
    private const BUSY = 120;

    public function ask(string $lang): void
    {
        $lang = Lang::current();
        if (!SiteAssistant::on()) {
            Response::json(['error' => __('site.chat_off')], 404);
        }
        $chat = $_SESSION['site_chat'] ?? ['turns' => [], 'last' => 0];
        if (Request::post('reset') === '1') {
            unset($_SESSION['site_chat']);
            Response::json(['ok' => true]);
        }
        $raw = Request::post('message');
        $message = is_string($raw) ? trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $raw)) : '';
        if ($message === '') {
            Response::json(['error' => __('site.chat_empty')], 422);
        }
        if (mb_strlen($message) > SiteAssistant::MAX_MESSAGE) {
            Response::json(['error' => __('site.chat_too_long', ['n' => SiteAssistant::MAX_MESSAGE])], 422);
        }
        if (time() - (int) $chat['last'] < self::MIN_GAP || time() - (int) ($chat['asking'] ?? 0) < self::BUSY) {
            Response::json(['error' => __('site.chat_slow')], 429);
        }
        $ip = Request::ip();
        if (SiteAssistant::usedTodayBy($ip) >= SiteAssistant::perVisitor()) {
            Response::json(['error' => __('site.chat_limit')] + $this->contacts(null), 429);
        }
        if (SiteAssistant::usedToday() >= SiteAssistant::siteCap()) {
            Response::json(['error' => __('site.chat_busy')] + $this->contacts(null), 429);
        }

        $chat['last'] = time(); // and again when answered: the pause counts from the answer
        // The session is let go while Google answers (up to a minute), so the visitor's other pages don't wait for it;
        // "asking" refuses a second question meanwhile, as the locked session did. Opened again to keep the answer.
        $_SESSION['site_chat'] = ['asking' => time()] + $chat;
        $reopen = session_status() === PHP_SESSION_ACTIVE;
        if ($reopen) {
            session_write_close();
        }
        unset($chat['asking']);
        try {
            $a = SiteAssistant::answer($message, $chat['turns'], $lang);
        } catch (\RuntimeException $e) {
            $chat['last'] = time();
            $this->keep($chat, $reopen);
            $this->log($lang, false, false);
            Response::json(['error' => __('site.chat_error')] + $this->contacts(null), 503);
        }
        // the visitor's words as they were sent (phone numbers and emails blanked), and the answer
        $chat['turns'][] = ['user', SiteAssistant::mask($message)];
        $chat['turns'][] = ['model', $a['reply']];
        $chat['turns'] = array_slice($chat['turns'], -SiteAssistant::HISTORY);
        $chat['last'] = time();
        $this->keep($chat, $reopen);
        $this->log($lang, true, $a['handoff']);

        $horse = $a['horse'] ? SiteData::horse($a['horse']) : null;
        Response::json([
            'reply' => $a['reply'],
            'links' => $a['links'],
            'handoff' => $a['handoff'],
        ] + $this->contacts($horse));
    }

    /**
     * Where a visitor goes next: WhatsApp (with a first line ready to send) and the inquiry form (the horse's own when
     * its page shows one, else the Contact page), as the website shows them.
     */
    private function contacts(?array $horse): array
    {
        $out = [];
        $name = $horse ? loc($horse) : '';
        $wa = preg_replace('/\D/', '', (string) setting('company.whatsapp', ''));
        if ($wa) {
            $out['whatsapp'] = 'https://wa.me/' . $wa . '?text=' . rawurlencode($name !== '' ? __('site.chat_wa_horse', ['horse' => $name]) : __('site.chat_wa_hello'));
        }
        if ($horse && SiteData::show('profile_inquiry')) {
            $out['inquiry'] = site_url('horses/' . $horse['slug']) . '#inquiry';
        } elseif (SiteData::pageOn('contact')) {
            $out['inquiry'] = site_url('contact') . '#inquiry';
        }
        if ($horse) {
            $out['horse'] = ['name' => $name, 'url' => site_url('horses/' . $horse['slug'])];
        }
        return $out;
    }

    /** Keeps the conversation in the visitor's session, opening it again when it was let go during the AI's answer. */
    private function keep(array $chat, bool $reopen): void
    {
        if ($reopen) {
            Session::start();
        }
        $_SESSION['site_chat'] = $chat;
    }

    /** One line per question (no text): what the limits count, and what Administration → AI assistant shows. */
    private function log(string $lang, bool $ok, bool $handoff): void
    {
        Audit::log('ai_site_chat', 'cms', null, null, null, ['lang' => $lang, 'answered' => $ok, 'handoff' => $handoff],
            'Website chat question', ['id' => null, 'name' => 'website']);
    }
}
