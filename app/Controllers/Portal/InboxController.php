<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Controllers\Controller;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Services\Mailer;
use App\Services\Notifier;
use App\Services\Settings;

/** Messages sent from the website contact and horse inquiry forms. */
class InboxController extends Controller
{
    private const STATUSES = ['new', 'read', 'replied', 'closed'];
    private const TYPES = ['general', 'horse', 'visit', 'media'];

    public function index(): void
    {
        Auth::requirePerm('inbox', 'view');
        $status = in_array(Request::query('status'), self::STATUSES, true) ? Request::query('status') : null;
        $type = in_array(Request::query('type'), self::TYPES, true) ? Request::query('type') : null;
        $q = trim((string) Request::query('q'));
        $mine = Request::query('mine') === '1';
        $where = ['i.deleted_at IS NULL'];
        $params = [];
        if ($status) {
            $where[] = 'i.status = :st';
            $params['st'] = $status;
        } elseif (!$q) {
            $where[] = "i.status <> 'closed'";
        }
        if ($type) {
            $where[] = 'i.type = :ty';
            $params['ty'] = $type;
        }
        if ($mine) {
            $where[] = 'i.assigned_to = :me';
            $params['me'] = Auth::id();
        }
        if ($q !== '') {
            $where[] = '(i.name LIKE :q1 OR i.email LIKE :q2 OR i.phone LIKE :q3 OR i.message LIKE :q4)';
            $params += ['q1' => "%$q%", 'q2' => "%$q%", 'q3' => "%$q%", 'q4' => "%$q%"];
        }
        $page = max(1, (int) Request::query('page', 1));
        $per = in_array((int) Request::query('per'), [25, 50, 100], true) ? (int) Request::query('per') : 25;
        $w = implode(' AND ', $where);
        $total = (int) DB::value("SELECT COUNT(*) FROM inquiries i WHERE $w", $params);
        $rows = DB::all("SELECT i.*, h.name_en AS horse, u.name AS assignee FROM inquiries i LEFT JOIN horses h ON h.id = i.horse_id LEFT JOIN users u ON u.id = i.assigned_to
            WHERE $w ORDER BY i.status = 'new' DESC, i.created_at DESC LIMIT $per OFFSET " . (($page - 1) * $per), $params);
        $counts = DB::pairs("SELECT status, COUNT(*) FROM inquiries WHERE deleted_at IS NULL GROUP BY status");
        $this->view('portal/inbox/index', [
            'title' => __('nav.inbox'), 'rows' => $rows, 'counts' => $counts, 'status' => $status, 'type' => $type, 'q' => $q, 'mine' => $mine,
            'page' => $page, 'pages' => (int) ceil($total / $per), 'total' => $total,
        ]);
    }

    public function show(string $id): void
    {
        Auth::requirePerm('inbox', 'view');
        $m = $this->find($id);
        if ($m['status'] === 'new') {
            DB::update('inquiries', ['status' => 'read', 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $m['id']]);
            $m['status'] = 'read';
        }
        $thread = DB::all('SELECT t.*, u.name AS author FROM inquiry_messages t LEFT JOIN users u ON u.id = t.created_by WHERE t.inquiry_id = ? ORDER BY t.created_at, t.id', [$m['id']]);
        $others = $m['email'] || $m['phone'] ? DB::all('SELECT id, created_at, type, status FROM inquiries WHERE id <> :id AND deleted_at IS NULL AND ((:e1 <> \'\' AND email = :e2) OR (:p1 <> \'\' AND phone = :p2)) ORDER BY created_at DESC LIMIT 10',
            ['id' => $m['id'], 'e1' => (string) $m['email'], 'e2' => (string) $m['email'], 'p1' => (string) $m['phone'], 'p2' => (string) $m['phone']]) : [];
        $this->view('portal/inbox/show', [
            'title' => __('inbox.message_from', ['name' => $m['name']]), 'm' => $m, 'thread' => $thread, 'others' => $others,
            'staff' => $this->staff(), 'canEdit' => Auth::can('inbox', 'edit'), 'canDelete' => Auth::can('inbox', 'delete'),
            'signature' => $this->signature(),
        ]);
    }

    public function update(string $id): void
    {
        Auth::requirePerm('inbox', 'edit');
        $m = $this->find($id);
        $now = date('Y-m-d H:i:s');
        $back = '/portal/inbox/' . $m['id'];
        switch (Request::post('action')) {
            case 'status':
                $st = Request::post('status');
                if (!in_array($st, self::STATUSES, true)) {
                    Response::notFound();
                }
                DB::update('inquiries', ['status' => $st, 'updated_at' => $now], 'id = :id', ['id' => $m['id']]);
                Audit::log('update', 'inbox', 'inquiry', (int) $m['id'], ['status' => $m['status']], ['status' => $st], 'Inquiry status: ' . $st);
                $this->flash('success', __('common.saved'));
                $this->redirect($st === 'closed' ? '/portal/inbox' : $back);
            case 'assign':
                $uid = (int) Request::post('assigned_to') ?: null;
                if ($uid && !isset($this->staff()[$uid])) {
                    Response::notFound();
                }
                DB::update('inquiries', ['assigned_to' => $uid, 'updated_at' => $now], 'id = :id', ['id' => $m['id']]);
                Audit::log('update', 'inbox', 'inquiry', (int) $m['id'], ['assigned_to' => $m['assigned_to']], ['assigned_to' => $uid], 'Inquiry assigned');
                if ($uid && $uid !== Auth::id()) {
                    Notifier::user($uid, 'inquiry', __('inbox.assigned_to_you', ['name' => $m['name']]), mb_substr($m['message'], 0, 200), $back, true);
                }
                $this->flash('success', __('common.saved'));
                $this->redirect($back);
            case 'note':
                $body = trim((string) Request::post('body'));
                if ($body === '') {
                    $this->flash('error', __('validation.required'));
                    $this->redirect($back);
                }
                DB::insert('inquiry_messages', ['inquiry_id' => $m['id'], 'kind' => 'note', 'body' => mb_substr($body, 0, 5000), 'created_by' => Auth::id()]);
                $this->flash('success', __('common.saved'));
                $this->redirect($back . '#thread');
            case 'reply':
                $body = trim((string) Request::post('body'));
                $subject = trim((string) Request::post('subject')) ?: __('inbox.reply_subject');
                if (!$m['email'] || !filter_var($m['email'], FILTER_VALIDATE_EMAIL)) {
                    $this->flash('error', __('inbox.no_email'));
                    $this->redirect($back);
                }
                if ($body === '') {
                    $this->flash('error', __('validation.required'));
                    $this->redirect($back);
                }
                $quote = '<hr><p style="color:#777">' . e(fmt_date($m['created_at'], true)) . ' — ' . e($m['name']) . ':</p><blockquote style="color:#777;margin:0 0 0 8px">' . nl2br(e($m['message'])) . '</blockquote>';
                $ok = Mailer::send($m['email'], mb_substr($subject, 0, 150), '<div dir="' . ($m['lang'] === 'ar' ? 'rtl' : 'ltr') . '">' . nl2br(e($body)) . $quote . '</div>');
                DB::insert('inquiry_messages', ['inquiry_id' => $m['id'], 'kind' => 'reply', 'body' => mb_substr($body, 0, 10000), 'sent_to' => $m['email'], 'sent_ok' => $ok ? 1 : 0, 'created_by' => Auth::id()]);
                if ($ok) {
                    DB::update('inquiries', ['status' => 'replied', 'replied_at' => $now, 'updated_at' => $now, 'assigned_to' => $m['assigned_to'] ?: Auth::id()], 'id = :id', ['id' => $m['id']]);
                }
                Audit::log('inquiry_reply', 'inbox', 'inquiry', (int) $m['id'], null, ['to' => $m['email'], 'sent' => $ok], 'Reply sent to ' . $m['email']);
                $this->flash($ok ? 'success' : 'error', __($ok ? 'inbox.reply_sent' : 'inbox.reply_failed'));
                $this->redirect($back . '#thread');
            case 'contact':
                // Save the sender as a client contact (Finance → Parties) so they can be invoiced later
                Auth::requirePerm('finance', 'create');
                if ($m['party_id']) {
                    $this->redirect('/portal/parties/' . $m['party_id']);
                }
                $existing = $m['email'] ? DB::value('SELECT id FROM parties WHERE email = ? AND deleted_at IS NULL LIMIT 1', [$m['email']]) : null;
                $pid = $existing ?: DB::insert('parties', [
                    'type' => 'client', 'name_en' => $m['name'], 'phone' => $m['phone'], 'email' => $m['email'], 'country' => $m['country'],
                    'notes' => __('inbox.from_website', ['date' => fmt_date($m['created_at'])]), 'created_by' => Auth::id(),
                ]);
                DB::update('inquiries', ['party_id' => $pid], 'id = :id', ['id' => $m['id']]);
                if (!$existing) {
                    Audit::log('create', 'finance', 'party', (int) $pid, null, ['name_en' => $m['name']], 'Contact created from inquiry #' . $m['id']);
                }
                $this->flash('success', __('inbox.contact_saved'));
                $this->redirect($back);
            case 'delete':
                Auth::requirePerm('inbox', 'delete');
                DB::transaction(function () use ($m, $now) {
                    DB::update('inquiries', ['deleted_at' => $now], 'id = :id', ['id' => $m['id']]);
                    DB::insert('trash', ['record_type' => 'inquiry', 'record_table' => 'inquiries', 'record_id' => $m['id'],
                        'label' => mb_substr(__('inbox.singular') . ': ' . $m['name'], 0, 255), 'module' => 'inbox', 'deleted_by' => Auth::id()]);
                });
                Audit::log('delete', 'inbox', 'inquiry', (int) $m['id'], ['name' => $m['name'], 'email' => $m['email']], null, 'Inquiry deleted');
                $this->flash('success', __('common.moved_to_trash'));
                $this->redirect('/portal/inbox');
        }
        Response::notFound();
    }

    private function find(string $id): array
    {
        return DB::row('SELECT i.*, h.name_en AS horse, h.slug AS horse_slug, p.name_en AS party FROM inquiries i LEFT JOIN horses h ON h.id = i.horse_id
            LEFT JOIN parties p ON p.id = i.party_id WHERE i.id = ? AND i.deleted_at IS NULL', [(int) $id]) ?? Response::notFound();
    }

    /** Active users who can open the inbox (Owners always can). */
    private function staff(): array
    {
        return DB::pairs("SELECT DISTINCT u.id, u.name FROM users u JOIN roles r ON r.id = u.role_id
            WHERE u.status = 'active' AND u.deleted_at IS NULL
              AND (r.slug = 'owner' OR EXISTS (SELECT 1 FROM role_permissions p WHERE p.role_id = r.id AND p.module = 'inbox' AND p.action = 'view'))
            ORDER BY u.name");
    }

    private function signature(): string
    {
        $u = Auth::user();
        return "\n\n" . $u['name'] . "\n" . Settings::get('company.name_en', 'SK Arabians') . "\n" . Settings::get('company.mobile', '') . ' · ' . Settings::get('company.email', '');
    }
}
