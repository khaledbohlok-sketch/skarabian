<?php use App\Services\QrCode; ?>
<style>
.qr-sheet{display:grid;grid-template-columns:repeat(2,90mm);gap:8mm;justify-content:center;padding:10mm 0}
.qr-tag{width:90mm;height:120mm;background:#fff;border:.4mm solid #213562;border-radius:4mm;padding:6mm;display:flex;flex-direction:column;align-items:center;justify-content:space-between;break-inside:avoid;box-shadow:0 2px 10px rgba(0,0,0,.08)}
.qr-tag img.logo{height:14mm}.qr-tag svg{width:58mm;height:58mm}
.qr-tag h2{margin:0;font:700 17pt "IBM Plex Sans",sans-serif;color:#213562;text-align:center}
.qr-tag p{margin:0;font:500 11pt "IBM Plex Sans Arabic",sans-serif;color:#B08D57;text-align:center}
.qr-tag small{font:8pt "IBM Plex Sans",sans-serif;color:#6b7488;text-align:center}
@media print{.qr-sheet{padding:0}.qr-tag{box-shadow:none}}
</style>
<div class="qr-sheet">
<?php foreach ($horses as $h): ?>
  <div class="qr-tag">
    <img class="logo" src="<?= e(asset('img/sk-logo.png')) ?>" alt="SK Arabians">
    <?= QrCode::svg(absolute_url('/portal/h/' . $h['id']), 4, '#213562') ?>
    <div><h2><?= e($h['name_en']) ?></h2><?php if ($h['name_ar']): ?><p><?= e($h['name_ar']) ?></p><?php endif; ?></div>
    <small><?= e(__('horses.qr_note')) ?></small>
  </div>
<?php endforeach; ?>
</div>
