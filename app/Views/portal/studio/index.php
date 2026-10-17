<?php
use App\Core\Auth;
$groupNames = ['office' => 'studio.group_office', 'horses' => 'studio.group_horses', 'staff' => 'studio.group_staff', 'tools' => 'studio.group_tools'];
// the gallery: a line icon per document type, and the languages it prints in (lists and tools are English only)
$typeIcon = ['fin' => 'chart', 'po' => 'box', 'inv' => 'wallet', 'pay' => 'payroll', 'letter' => 'edit', 'horses' => 'horse', 'profile' => 'tree',
    'diet' => 'feed', 'vet' => 'flask', 'cover' => 'link', 'board' => 'home', 'embryo' => 'embryo', 'staff' => 'users', 'idcard' => 'qr',
    'sal' => 'doc', 'letters' => 'mail', 'offer' => 'user', 'rem' => 'clock', 'reg' => 'folder'];
$enOnly = ['horses', 'staff', 'rem', 'reg'];
?>
<div class="page-head">
  <div><h1><?= e(__('nav.studio')) ?></h1><p class="muted"><?= e(__('studio.intro')) ?></p></div>
  <?php if (Auth::isOwner()): ?><div class="actions"><a class="btn" href="<?= e(url('/portal/studio/templates')) ?>"><?= e(__('studio.templates')) ?></a><a class="btn" href="<?= e(url('/portal/studio/permissions')) ?>"><?= e(__('studio.permissions')) ?></a></div><?php endif; ?>
</div>
<?php if ($groups): ?>
<section class="sg" aria-labelledby="sg-title" data-studio-gallery>
  <div class="sg-head">
    <h2 id="sg-title"><?= e(__('studio.new_document')) ?></h2>
    <div class="sg-tools" hidden data-sg-tools>
      <label class="sg-search"><?= icon('search') ?><span class="sr-only"><?= e(__('common.search')) ?></span><input type="search" placeholder="<?= e(__('studio.search_types')) ?>" data-sg-q autocomplete="off"></label>
      <div class="sg-chips" role="group" aria-label="<?= e(__('studio.type')) ?>">
        <button type="button" class="sg-chip" aria-pressed="true" data-sg-group=""><?= e(__('common.all')) ?></button>
        <?php foreach ($groupNames as $g => $label): if (empty($groups[$g])) { continue; } ?><button type="button" class="sg-chip" aria-pressed="false" data-sg-group="<?= e($g) ?>"><?= e(__($label)) ?></button><?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php foreach ($groupNames as $g => $label): if (empty($groups[$g])) { continue; } ?>
    <div class="sg-group" data-sg-g="<?= e($g) ?>">
      <h3 class="sg-gtitle"><?= e(__($label)) ?></h3>
      <div class="sg-grid">
        <?php foreach ($groups[$g] as $t): $name = __('studio.type_' . $t); $desc = __('studio.desc_' . $t); ?>
          <a class="sg-card" href="<?= e(url('/portal/studio/new/' . $t)) ?>" data-sg-text="<?= e(mb_strtolower($name . ' ' . $desc)) ?>">
            <span class="sg-ico"><?= icon($typeIcon[$t] ?? 'doc') ?></span>
            <span class="sg-name"><?= e($name) ?></span>
            <span class="sg-desc"><?= e($desc) ?></span>
            <span class="sg-lang" dir="ltr" title="<?= e(__('inbox.language')) ?>"><?= in_array($t, $enOnly, true) ? 'EN' : 'EN · AR' ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
  <p class="sg-none muted" hidden data-sg-none><?= e(__('studio.no_type_match')) ?></p>
</section>
<?php endif; ?>
<?php if ($drafts): $emptyDrafts = count(array_filter($drafts, fn ($d) => !(int) $d['line_count'])); ?>
<section class="card">
  <div class="card-head"><h2><?= e(__('studio.drafts')) ?> <span class="muted">(<?= count($drafts) ?>)</span></h2>
    <?php if ($emptyDrafts > 1): ?><form method="post" action="<?= e(url('/portal/studio/drafts/discard-empty')) ?>" class="inline" data-confirm="<?= e(__('studio.discard_empty_confirm', ['n' => $emptyDrafts])) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-danger" type="submit"><?= e(__('studio.discard_empty', ['n' => $emptyDrafts])) ?></button></form><?php endif; ?></div>
  <p class="muted small"><?= e(__('studio.drafts_help')) ?></p>
  <div class="table-wrap"><table class="table">
    <thead><tr><th><?= e(__('studio.title')) ?></th><th><?= e(__('studio.fin_period')) ?></th><th class="num"><?= e(__('studio.draft_lines')) ?></th><th><?= e(__('common.date')) ?></th><th></th></tr></thead>
    <tbody><?php foreach ($drafts as $d): ?><tr>
      <td data-label="<?= e(__('studio.title')) ?>"><a href="<?= e(url('/portal/studio/' . $d['id'])) ?>"><b><?= e($d['title'] !== '' ? $d['title'] : __('studio.type_fin')) ?></b></a> <?= status_badge('draft') ?></td>
      <td data-label="<?= e(__('studio.fin_period')) ?>" class="nowrap"><?= $d['period_from'] && $d['period_to'] ? e(fmt_date($d['period_from'])) . ' ' . arrow('to') . ' ' . e(fmt_date($d['period_to'])) : '—' ?></td>
      <td data-label="<?= e(__('studio.draft_lines')) ?>" class="num"><?= (int) $d['line_count'] ?></td>
      <td data-label="<?= e(__('common.date')) ?>"><?= e(fmt_date($d['created_at'])) ?></td>
      <td class="nowrap"><a class="btn btn-xs" href="<?= e(url('/portal/studio/' . $d['id'])) ?>"><?= e(__('common.continue')) ?></a>
        <form method="post" action="<?= e(url('/portal/studio/' . $d['id'] . '/discard')) ?>" class="inline" data-confirm="<?= e(__('studio.discard_confirm')) ?>"><?= csrf_field() ?><button class="btn btn-xs btn-danger" type="submit"><?= e(__('studio.discard')) ?></button></form></td>
    </tr><?php endforeach; ?></tbody>
  </table></div>
</section>
<?php endif; ?>
<section class="card sg-recent">
  <div class="card-head"><h2><?= e(__($show === 'void' ? 'studio.archive_void' : 'studio.recent_docs')) ?> <span class="muted">(<?= (int) $total ?>)</span></h2>
    <?php if ($show === '' && $voided): ?><a class="btn btn-sm ghost" href="<?= e(query_url(['show' => 'void', 'page' => null])) ?>"><?= e(__('studio.see_voided', ['n' => $voided])) ?></a><?php endif; ?>
    <?php if ($show !== ''): ?><a class="btn btn-sm ghost" href="<?= e(query_url(['show' => null, 'page' => null])) ?>"><?= arrow('back') ?> <?= e(__('studio.back_to_issued')) ?></a><?php endif; ?></div>
  <?php $filtered = ($_GET['type'] ?? '') !== ''; ?>
  <form method="get" class="filters<?= $filtered ? ' open' : '' ?>">
    <?php if ($show !== ''): ?><input type="hidden" name="show" value="<?= e($show) ?>"><?php endif; // a search in the voided ones stays there ?>
    <?php if (!empty($_GET['per'])): ?><input type="hidden" name="per" value="<?= (int) $per ?>"><?php endif; // and the chosen page size ?>
    <label class="f-search"><span class="sr-only"><?= e(__('common.search')) ?></span><input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="<?= e(__('studio.search_ph')) ?>"></label>
    <label class="f-item"><span><?= e(__('studio.type')) ?></span><select name="type"><option value=""><?= e(__('common.all')) ?></option>
      <?php if ($printouts): ?><option value="printout"<?= selected($_GET['type'] ?? '', 'printout') ?>><?= e(__('studio.type_printout')) ?></option><?php endif; ?>
      <?php foreach (array_keys(\App\Services\StudioDocs::TYPES) as $t): if (in_array($t, \App\Services\StudioDocs::UNNUMBERED, true)) { continue; } ?><option value="<?= e($t) ?>"<?= selected($_GET['type'] ?? '', $t) ?>><?= e(__('studio.type_' . $t)) ?></option><?php endforeach; ?>
    </select></label>
    <div class="f-buttons"><button class="btn f-toggle" type="button" data-toggle-filters aria-expanded="<?= $filtered ? 'true' : 'false' ?>"><?= icon('search') ?> <?= e(__('common.filters')) ?></button><button class="btn btn-primary" type="submit"><?= e(__('common.apply')) ?></button></div>
  </form>
  <?php if ($docs): $here = current_path() . ($_GET ? '?' . http_build_query($_GET) : ''); ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th><?= e(__('studio.ref')) ?></th><th><?= e(__('studio.title')) ?></th><th><?= e(__('common.date')) ?></th><th><?= e(__('studio.by_col')) ?></th><th></th></tr></thead>
    <tbody><?php foreach ($docs as $d):
      // a Studio document, or a printed list / report (its kept copy prints again with the same reference and QR code)
      $prt = $d['src'] === 'prt';
      $open = $prt ? '/portal/printouts/' . $d['token'] : '/portal/studio/' . $d['id']; ?>
    <tr class="<?= $d['is_void'] ? 'row-muted' : '' ?>">
      <td data-label="<?= e(__('studio.ref')) ?>"><a href="<?= e(url($open)) ?>"<?= $prt ? ' target="_blank"' : '' ?>><b><?= e($d['ref_no']) ?></b></a><?= $d['is_void'] ? ' <span class="badge badge-cancelled">' . e(__('studio.void')) . '</span>' : '' ?></td>
      <td data-label="<?= e(__('studio.title')) ?>"><?= e($d['title']) ?><?= $prt ? ' <span class="muted">· ' . e(__('print.kind_' . $d['kind'])) . '</span>' : '' ?> <small class="muted">(<?= e(strtoupper($d['lang'])) ?>)</small></td>
      <td data-label="<?= e(__('common.date')) ?>"><?= e(fmt_date($d['ddate'])) ?></td>
      <td data-label="<?= e(__('studio.by_col')) ?>"><?= e($d['author'] ?? '—') ?></td>
      <td class="nowrap"><div class="doc-row-actions"><?php if (!$d['is_void']): ?>
        <a class="btn btn-xs" href="<?= e(url($prt ? $open : $open . '/print')) ?>" target="_blank"><?= icon('print') ?> <?= e(__('common.print')) ?></a>
        <?php if ($canVoid): ?><form method="post" action="<?= e(url($open . '/void')) ?>" data-confirm="<?= e(__('studio.confirm_void_ref', ['ref' => $d['ref_no']])) ?>"><?= csrf_field() ?><input type="hidden" name="back" value="<?= e($here) ?>"><button class="btn btn-xs btn-danger" type="submit"><?= icon('x') ?> <?= e(__('studio.void_short')) ?></button></form><?php endif; ?>
      <?php else: ?><a class="btn btn-xs" href="<?= e(url($open)) ?>"<?= $prt ? ' target="_blank"' : '' ?>><?= icon('doc') ?> <?= e(__('common.open')) ?></a><?php endif; ?></div></td>
    </tr><?php endforeach; ?></tbody>
  </table></div>
  <?= \App\Core\View::partial('portal/crud/pagination', ['page' => $page, 'pages' => $pages, 'per' => $per]) ?>
  <?php else: ?><div class="sg-empty"><?= icon('doc') ?><p><?= e(__($show === 'void' ? 'studio.no_voided' : 'studio.no_documents')) ?></p></div><?php endif; ?>
</section>
