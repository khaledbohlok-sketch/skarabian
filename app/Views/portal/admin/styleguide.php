<?php
/** Style guide (Owner only): every token of tokens.css and every shared component, in the theme chosen in the user menu.
 *  Each demo shows its markup — the class names module pages use. ?sample=letterhead renders the A4 paper shown in a frame. */
if (!empty($sample)):
    $ar = ($docLang ?? 'en') === 'ar';
    $L = fn ($en, $a) => $ar ? $a : $en;
?>
<div class="pages">
  <div class="page serif" dir="<?= $ar ? 'rtl' : 'ltr' ?>" lang="<?= $ar ? 'ar' : 'en' ?>">
    <img class="lh-wm" src="<?= e(asset('img/sk-mark.png')) ?>" alt="">
    <div class="lh-head<?= $ar ? ' rtl' : '' ?>">
      <?php if ($ar): ?><img class="lh-logo" src="<?= e(asset('img/sk-logo.png')) ?>" alt="SK Arabian"><?php else: ?><div class="lh-v1"><img src="<?= e(asset('img/sk-mark.png')) ?>" alt="SK Arabian"><span>Sk.Arabian</span></div><?php endif; ?>
      <div class="vq"><div class="meta"><span class="k"><?= $L('Ref.', 'المرجع:') ?></span> <b class="num">SKA-LTR-2026-0001</b><br><span class="k"><?= $L('Date:', 'التاريخ:') ?></span> <b><?= e($ar ? '5 أكتوبر 2026' : '5 Oct 2026') ?></b></div></div>
    </div>
    <div class="pbody"><div class="flow">
      <div class="of-p"><?= $L('To whom it may concern', 'إلى من يهمه الأمر') ?></div>
      <div class="cl-h"><small><?= $L('Subject', 'الموضوع') ?></small><b><?= $L('Sample letter on the official letterhead', 'نموذج خطاب على الورق الرسمي') ?></b></div>
      <div class="of-p"><?= $L('This page shows how a document from SK Arabian Studio looks on paper: the letterhead, the reference and date block, the faint mark in the middle and the contact bar at the foot. Printed documents are always white paper, whatever theme the screen uses.',
        'تعرض هذه الصفحة شكل المستند الصادر من استوديو إس كي العربية على الورق: الترويسة، والمرجع والتاريخ، والشعار الخافت في المنتصف، وشريط بيانات الاتصال في الأسفل. المستندات المطبوعة دائماً على ورق أبيض أيّاً كان مظهر الشاشة.') ?></div>
      <div class="of-p"><?= $L('Kind regards,', 'وتفضلوا بقبول فائق الاحترام،') ?></div>
    </div></div>
    <div class="lh-note"><span></span><span class="pnum">1 / 1</span></div>
    <div class="lh-foot" dir="ltr" lang="en"><span>C.R.: <?= e(doc_contact('cr')) ?></span><span>Mob.: <?= e(doc_contact('mobile')) ?></span><span>Email: <?= e(doc_contact('email')) ?></span><span>P.O.Box: <?= e(doc_contact('po_box')) ?>, <?= e(doc_contact('city')) ?></span></div>
  </div>
</div>
<?php return; endif;

// ---- tokens straight from tokens.css
$css = (string) @file_get_contents(APP_ROOT . '/public/assets/css/tokens.css');
$root = preg_match('/:root\s*\{(.*?)\n\}/s', $css, $m) ? $m[1] : '';
preg_match_all('/--([a-z0-9-]+)\s*:\s*([^;]+);/', $root, $mm, PREG_SET_ORDER);
$tok = [];
foreach ($mm as [, $k, $v]) {
    $tok[$k] = trim($v);
}
$by = fn (string $re) => array_filter($tok, fn ($k) => (bool) preg_match($re, $k), ARRAY_FILTER_USE_KEY);
// a demo: the component, then its markup
$demo = function (string $title, string $html, string $note = '') {
    return '<div class="sg-demo"><h3>' . e($title) . '</h3>' . ($note ? '<p class="muted small">' . e($note) . '</p>' : '')
        . '<div class="sg-stage">' . $html . '</div><details class="sg-code"><summary>Markup</summary><pre><code>' . e(trim($html)) . '</code></pre></details></div>';
};
$statuses = ['active', 'paid', 'completed', 'received', 'present', 'pregnant', 'foaled', 'in', 'approved', 'frozen', 'holiday', 'transfer', 'scheduled', 'pending', 'partially_paid', 'new', 'open', 'on_leave', 'leave', 'late', 'out', 'issued', 'fresh', 'adjustment', 'transferred', 'overdue', 'rejected', 'failed', 'deceased', 'absent', 'not_pregnant', 'lost', 'suspended', 'cancelled', 'left', 'inactive', 'archived', 'sold', 'draft'];
?>
<div class="page-head">
  <div>
    <ol class="breadcrumbs" aria-label="Breadcrumb"><li><a href="<?= e(url('/portal')) ?>"><?= e(__('nav.dashboard')) ?></a></li><li><a href="<?= e(url('/portal/settings')) ?>"><?= e(__('nav.admin')) ?></a></li><li aria-current="page"><?= e(__('styleguide.title')) ?></li></ol>
    <h1><?= e(__('styleguide.title')) ?></h1>
    <p><?= e(__('styleguide.intro')) ?></p>
  </div>
  <div class="actions"><a class="btn" href="#sg-components"><?= e(__('styleguide.components')) ?></a><a class="btn" href="#sg-letterhead"><?= icon('doc') ?> <?= e(__('styleguide.letterhead')) ?></a></div>
</div>

<!-- ============================================================ tokens -->
<section class="card" id="sg-colours">
  <div class="card-head"><h2><?= e(__('styleguide.colours')) ?></h2><span class="muted small">tokens.css</span></div>
  <?php foreach (['Staff system (--p-*)' => '/^p-/', 'Website, Studio, documents (--c-*)' => '/^c-(?!ok|warn|bad|info)/', 'Status (--c-ok / warn / bad / info)' => '/^c-(ok|warn|bad|info)/'] as $label => $re): ?>
    <h3 class="mt"><?= e($label) ?></h3>
    <div class="sg-swatches">
      <?php foreach ($by($re) as $k => $v): ?><div class="sg-swatch"><span style="background:var(--<?= e($k) ?>)"></span><b>--<?= e($k) ?></b><small dir="ltr"><?= e($v) ?></small></div><?php endforeach; ?>
    </div>
  <?php endforeach; ?>
  <h3 class="mt">Semantic (this theme)</h3>
  <div class="sg-swatches">
    <?php foreach (['bg', 'card', 'raise', 'sunk', 'line', 'line-2', 'ink', 'ink-2', 'muted', 'title', 'accent', 'accent-ink', 'strong', 'mark', 'chrome', 'ok-t', 'ok-s', 'warn-t', 'warn-s', 'bad-t', 'bad-s', 'info-t', 'info-s', 'gold-t', 'gold-s', 'chart-a', 'chart-b'] as $k): ?><div class="sg-swatch"><span style="background:var(--<?= e($k) ?>)"></span><b>--<?= e($k) ?></b></div><?php endforeach; ?>
  </div>
</section>

<section class="card" id="sg-type">
  <div class="card-head"><h2><?= e(__('styleguide.type')) ?></h2></div>
  <p class="sg-font" style="font-family:var(--font-display)">Marcellus / Amiri — <?= e(__('styleguide.title')) ?> · SK Arabians</p>
  <p class="sg-font">Inter / IBM Plex Sans Arabic — <?= e(__('app.portal_name')) ?> · <span class="money">4,500 QR</span> · 0123456789</p>
  <div class="sg-scale">
    <?php foreach ($by('/^fs-/') as $k => $v): ?><div><small class="muted" dir="ltr">--<?= e($k) ?> · <?= e($v) ?></small><span style="font-size:var(--<?= e($k) ?>)">SK Arabians <?= e(__('nav.horses')) ?></span></div><?php endforeach; ?>
  </div>
</section>

<section class="card" id="sg-space">
  <div class="card-head"><h2><?= e(__('styleguide.spacing')) ?></h2></div>
  <div class="sg-bars"><?php foreach ($by('/^space-/') as $k => $v): ?><div><span style="width:var(--<?= e($k) ?>)"></span><small dir="ltr">--<?= e($k) ?> · <?= e($v) ?></small></div><?php endforeach; ?></div>
  <div class="sg-boxes mt"><?php foreach ($by('/^radius-/') as $k => $v): ?><div style="border-radius:var(--<?= e($k) ?>)"><small dir="ltr">--<?= e($k) ?><br><?= e($v) ?></small></div><?php endforeach; ?></div>
  <div class="sg-boxes mt"><?php foreach ($by('/^(elev|glow|focus)-/') as $k => $v): ?><div style="box-shadow:var(--<?= e($k) ?>)"><small dir="ltr">--<?= e($k) ?></small></div><?php endforeach; ?></div>
  <div class="sg-boxes mt"><?php foreach ($by('/^ctl/') as $k => $v): ?><div style="height:var(--<?= e($k) ?>);min-height:0"><small dir="ltr">--<?= e($k) ?> · <?= e($v) ?></small></div><?php endforeach; ?></div>
</section>

<section class="card" id="sg-layers">
  <div class="card-head"><h2><?= e(__('styleguide.layers')) ?></h2></div>
  <div class="table-wrap"><table class="table compact"><thead><tr><th>Token</th><th class="num">Value</th></tr></thead><tbody>
    <?php foreach ($by('/^(z-|dur|ease|app-|topbar|sidebar|site-max|blur)/') as $k => $v): ?><tr><td data-label="Token"><code>--<?= e($k) ?></code></td><td class="num" data-label="Value" dir="ltr"><?= e($v) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</section>

<!-- ============================================================ components -->
<h2 class="mt" id="sg-components"><?= e(__('styleguide.components')) ?></h2>
<div class="sg-grid">
<?= $demo('Buttons', '<div class="actions">
  <button class="btn btn-primary" type="button">' . icon('plus') . ' Primary</button>
  <button class="btn" type="button">Secondary</button>
  <button class="btn ghost" type="button">Ghost</button>
  <button class="btn btn-danger" type="button">' . icon('trash') . ' Danger</button>
  <button class="btn btn-icon" type="button" aria-label="Edit">' . icon('edit') . '</button>
</div>
<div class="actions mt">
  <button class="btn btn-sm" type="button">Small 36</button>
  <button class="btn" type="button">Medium 42</button>
  <button class="btn btn-lg" type="button">Large 48</button>
  <button class="btn btn-primary is-busy" type="button" disabled>Saving</button>
  <button class="btn" type="button" disabled>Disabled</button>
</div>', 'One pill shape, one primary per page. Submit buttons get .is-busy automatically while their form is sent.') ?>

<?= $demo('Text fields', '<div class="grid-fields">
  <div class="field col-6"><label for="sg1">Name <span class="req">*</span></label><input id="sg1" type="text" placeholder="Type here"></div>
  <div class="field col-6"><label for="sg2">Phone</label><input id="sg2" type="tel" dir="ltr" placeholder="+974 5555 5555"><small class="help">Formatted when you leave the field.</small></div>
  <div class="field col-6 field-invalid"><label for="sg3">Email</label><input id="sg3" type="email" value="not-an-email"><div class="field-error">Invalid value</div></div>
  <div class="field col-6"><label for="sg4">Password</label><input id="sg4" type="password" value="secret123"></div>
  <div class="field col-12"><label for="sg5">Notes</label><textarea id="sg5" rows="3"></textarea></div>
</div>', 'Labels above, 42px controls, help and errors under the field.') ?>

<?= $demo('Selects and dates', '<div class="grid-fields">
  <div class="field col-6"><label for="sg6">Status</label><select id="sg6"><option value="">Choose…</option><option>Active</option><option>On leave</option><option>Left</option></select></div>
  <div class="field col-6"><label for="sg7">Date</label><input id="sg7" type="date" value="2026-10-05"></div>
</div>', 'Every <select> becomes the shared dropdown (dropdown.js); dates show "5 Oct 2026" and are sent as 2026-10-05.') ?>

<?= $demo('Choices', '<div class="actions">
  <label class="check"><input type="checkbox" checked> Checkbox</label>
  <label class="check"><input type="radio" name="sgr" checked> Radio A</label>
  <label class="check"><input type="radio" name="sgr"> Radio B</label>
  <label class="switch"><input type="checkbox" checked><span></span> Switch</label>
</div>
<div class="segment mt" role="radiogroup"><label class="segment-opt"><input type="radio" name="sgs" checked><span>In</span></label><label class="segment-opt"><input type="radio" name="sgs"><span>Out</span></label><label class="segment-opt"><input type="radio" name="sgs"><span>Transfer</span></label></div>') ?>

<?= $demo('Badges (status → colour)', '<div class="chips">' . implode('', array_map(fn ($s) => status_badge($s), $statuses)) . '</div>', 'status_badge($value) — one badge style; every stored status has its colour (portal.css section 9).') ?>

<?= $demo('Pills and chips', '<div class="actions"><span class="pill">12</span><span class="pill ok">3</span><span class="pill warn">5 Oct 2026</span><span class="pill bad">Overdue</span><span class="pill info">Info</span></div>
<div class="chips mt"><a class="chip" href="#">Fresh: <b>2</b></a><span class="chip gold">Gold chip</span></div>') ?>

<?= $demo('KPI cards', '<div class="kpi-grid">
  <a class="kpi" href="#"><span class="kpi-icon">' . icon('horse') . '</span><span class="kpi-label">Horses</span><span class="kpi-value">42</span><span class="kpi-sub">Active SK horses</span></a>
  <a class="kpi is-bad" href="#"><span class="kpi-icon">' . icon('wallet') . '</span><span class="kpi-label">Open bills</span><span class="kpi-value">7</span><span class="kpi-sub">Overdue: 2 · <span class="money">4,500<span class="cur"> QR</span></span></span></a>
</div>') ?>

<?= $demo('Stat strip', '<div class="stat-grid"><div class="stat dark"><div class="k">Net</div><div class="v"><span class="money">12,400<span class="cur"> QR</span></span></div></div><div class="stat"><div class="k">Income</div><div class="v">18</div><div class="s">this month</div></div><div class="stat alert-bad"><div class="k">Overdue</div><div class="v">2</div></div></div>') ?>

<?= $demo('Card', '<section class="card"><div class="card-head"><h2>Card title</h2><a href="#">View all</a></div><p>Cards hold one topic. Lists inside use .alert-list.</p><ul class="alert-list"><li><a href="#">A line that opens a page</a><span class="pill warn">5 Oct 2026</span></li><li><span>Another line</span><span class="pill">3</span></li></ul></section>') ?>

<?= $demo('Alerts', '<div class="alert alert-success" role="status">Saved.</div><div class="alert alert-warning" role="status">Two-step sign-in is required soon. <a href="#">Set it up</a></div><div class="alert alert-error" role="alert">Something needs fixing.</div><div class="alert alert-info" role="status">For your information.</div>') ?>

<?= $demo('Toasts', '<div class="actions"><button class="btn" type="button" data-sg-toast="success">Success toast</button><button class="btn" type="button" data-sg-toast="error">Error toast (stays)</button></div>', 'Modal.toast(message, type, {sticky}) — the server\'s flash messages appear the same way.') ?>

<?= $demo('Modal', '<div class="actions"><button class="btn" type="button" data-sg-modal="sm">Small 420</button><button class="btn" type="button" data-sg-modal="md">Medium 600</button><button class="btn" type="button" data-sg-modal="lg">Large 860</button><button class="btn btn-danger" type="button" data-confirm-click="Delete this sample?">Confirm</button></div>', 'Modal.custom({size, title}) / Modal.confirm({...}) / data-confirm on a form. A bottom sheet on phones.') ?>

<?= $demo('Dropdown menu', '<div class="dd"><button class="btn" type="button" aria-haspopup="menu" aria-expanded="false" aria-controls="sg-menu" data-dd="sg-menu">' . icon('more-horizontal') . ' Actions</button>
<div class="dd-menu" id="sg-menu" role="menu" hidden><a role="menuitem" href="#">' . icon('edit') . '<span>Edit</span></a><a role="menuitem" href="#">' . icon('download') . '<span>Excel</span></a><div class="dd-sep" role="separator"></div><button type="button" role="menuitem">' . icon('trash') . '<span>Delete</span></button></div></div>', 'data-dd="ID" on the button; arrows, Esc, outside click; flips above near the bottom.') ?>

<?= $demo('Tabs', '<nav class="tabs" role="tablist"><a role="tab" class="on" aria-selected="true" href="#">Details</a><a role="tab" href="#">Health <span class="count">3</span></a><a role="tab" href="#">Breeding</a><a role="tab" href="#">Documents <span class="count">12</span></a></nav>', 'Tabs that do not fit go under "More" by themselves.') ?>

<?= $demo('Table', '<div class="table-wrap"><table class="table"><thead><tr><th>Number</th><th>Party</th><th>Status</th><th class="num">Amount</th></tr></thead><tbody>
<tr><td class="is-title" data-label="Number"><a href="#">BILL-2026-0012</a></td><td data-label="Party">Vet Zone Clinic</td><td class="nowrap" data-label="Status">' . status_badge('paid') . '</td><td class="num" data-label="Amount">' . money(4500) . '</td></tr>
<tr class="row-alert"><td class="is-title" data-label="Number"><a href="#">BILL-2026-0013</a></td><td data-label="Party">Feed supplier</td><td class="nowrap" data-label="Status">' . status_badge('overdue') . '</td><td class="num" data-label="Amount">' . money(1250.5) . '</td></tr>
</tbody></table></div>', 'Cells: .num (end-aligned, tabular), .nowrap, .is-title; data-label for the phone cards.') ?>

<?= $demo('Pagination', '<nav class="pagination" aria-label="Pages"><span class="range">1–25 of 312</span><span class="current" aria-current="page">1</span><a href="#">2</a><a href="#">3</a><span class="gap">…</span><a href="#">13</a><a class="pg-arrow" href="#" aria-label="Next page">' . icon('chevron-right') . '</a><span class="per"><a class="on" href="#">25</a><a href="#">50</a><a href="#">100</a></span></nav>') ?>

<?= $demo('Empty state', \App\Core\View::partial('portal/partials/empty', ['card' => false, 'icon' => 'search', 'message' => __('ui.empty_filtered'), 'clear' => '/portal/style-guide', 'cta' => ['href' => '/portal/style-guide', 'label' => __('common.new')]]), "View::partial('portal/partials/empty', [icon, title, message, cta, clear, card])") ?>

<?= $demo('Skeletons', '<div><span class="skel w-40"></span><span class="skel w-80"></span><span class="skel w-60"></span><span class="skel h-lg"></span></div>', 'While content loads by script (picker results, frames).') ?>

<?= $demo('Avatars and tooltips', '<div class="actions"><span class="avatar sm">S</span><span class="avatar">K</span><span class="avatar lg">A</span><button class="btn btn-icon" type="button" aria-label="Print" data-tip="Print this page">' . icon('print') . '</button></div>', 'data-tip="text" on any element.') ?>

<?= $demo('Breadcrumbs', '<ol class="breadcrumbs" aria-label="Breadcrumb"><li><a href="#">Horses</a></li><li><a href="#">Mares</a></li><li aria-current="page">SK Amira</li></ol><a class="crumb" href="#">' . icon('arrow-left', 'i-16 flip-rtl') . ' Horses</a>') ?>

<?= $demo('Timeline (ownership, transfers, history)', '<ol class="timeline vertical">
  <li><time datetime="2024-03-01">1 Mar 2024</time><div class="tl-body"><span class="tl-kind">Bought</span><a href="#">From Al Shaqab</a><small>Contract SKA-2024-0007</small></div></li>
  <li><time datetime="2025-06-12">12 Jun 2025</time><div class="tl-body"><span class="tl-kind">Transferred</span><span>To SK Arabians stud</span></div></li>
  <li><time datetime="2026-10-05">5 Oct 2026</time><div class="tl-body"><span class="tl-kind">Sold</span><span>To a private owner</span></div></li>
</ol>', '.timeline (two columns) or .timeline.vertical (a line with dots).') ?>

<?= $demo('Icons (Lucide, 16 / 20 / 24)', '<div class="actions sg-icons">' . implode('', array_map(fn ($n) => '<span data-tip="' . $n . '">' . icon($n, 'i-24') . '</span>', ['home', 'horse', 'embryo', 'flask', 'users', 'wallet', 'payroll', 'box', 'chart', 'doc', 'folder', 'globe', 'mail', 'shield', 'search', 'bell', 'plus', 'edit', 'trash', 'download', 'upload', 'print', 'filter', 'calendar', 'eye', 'sun', 'moon', 'monitor', 'log-out', 'chevron-down', 'more-horizontal', 'whatsapp', 'instagram'])) . '</div>', "icon('name', 'i-16|i-20|i-24') — directional icons take 'flip-rtl'.") ?>
</div>

<section class="card" id="sg-letterhead">
  <div class="card-head"><h2><?= e(__('styleguide.letterhead')) ?></h2><span class="muted small">studio-doc.css · A4</span></div>
  <div class="sg-papers">
    <div class="sg-paper"><iframe src="<?= e(url('/portal/style-guide?sample=letterhead&lang=en')) ?>" title="<?= e(__('styleguide.letterhead')) ?> (EN)" loading="lazy" tabindex="-1"></iframe></div>
    <div class="sg-paper"><iframe src="<?= e(url('/portal/style-guide?sample=letterhead&lang=ar')) ?>" title="<?= e(__('styleguide.letterhead')) ?> (AR)" loading="lazy" tabindex="-1"></iframe></div>
  </div>
</section>
