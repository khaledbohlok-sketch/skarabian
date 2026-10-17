<?php use App\Core\Auth; ?>
<script type="application/json" id="scan-cfg"><?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<main class="scan" id="scan">
  <header class="scan-top">
    <img class="scan-mark" src="<?= e(asset('img/sk-mark-white.png')) ?>" alt="">
    <div class="scan-title"><b><?= e(__('attendance.scanner')) ?></b><span><span id="scanClock">--:--</span> · <?= e(Auth::user()['name'] ?? '') ?></span></div>
    <span class="scan-net" id="scanNet" aria-live="polite"></span>
    <form method="post" action="<?= e(url('/portal/logout')) ?>" class="scan-out"><?= csrf_field() ?><button type="submit"><?= e(__('auth.logout')) ?></button></form>
  </header>

  <?php if ($problem): ?>
  <section class="scan-msg bad" role="alert"><?= e($problem) ?></section>
  <?php else: ?>
  <section class="scan-cam" id="scanCam">
    <video id="scanVideo" playsinline muted autoplay></video>
    <div class="scan-frame" aria-hidden="true"></div>
    <p class="scan-hint" id="scanHint"><?= e(__('attendance.scan_hint')) ?></p>
    <button type="button" class="scan-start" id="scanStart"><?= e(__('attendance.scan_start')) ?></button>
  </section>

  <section class="scan-result" id="scanResult" hidden aria-live="assertive">
    <div class="sr-photo"><img id="srPhoto" alt="" hidden><span id="srInitials"></span></div>
    <div class="sr-text">
      <div class="sr-title" id="srTitle"></div>
      <div class="sr-name" id="srName" dir="ltr"></div>
      <div class="sr-name-ar" id="srNameAr" dir="rtl" lang="ar"></div>
    </div>
  </section>

  <footer class="scan-foot">
    <?php if ($cfg['pin']): ?><button type="button" class="scan-btn" id="scanType"><?= e(__('attendance.type_number')) ?></button><?php endif; ?>
  </footer>

  <dialog class="scan-dlg" id="typedDlg">
    <form method="dialog" id="typedForm">
      <h2><?= e(__('attendance.type_number')) ?></h2>
      <p class="muted"><?= e(__('attendance.type_number_help')) ?></p>
      <label><?= e(__('employees.emp_no')) ?><input type="text" name="emp_no" id="typedNo" required maxlength="20" autocomplete="off" dir="ltr" inputmode="text"></label>
      <label><?= e(__('attendance.manager_pin')) ?><input type="password" name="pin" id="typedPin" required inputmode="numeric" pattern="\d{4,8}" maxlength="8" autocomplete="off" dir="ltr"></label>
      <p class="scan-dlg-err" id="typedErr" role="alert"></p>
      <div class="scan-dlg-actions"><button type="submit" class="scan-btn primary" value="ok"><?= e(__('attendance.record')) ?></button><button type="button" class="scan-btn" id="typedCancel"><?= e(__('common.cancel')) ?></button></div>
    </form>
  </dialog>
  <?php endif; ?>
</main>
