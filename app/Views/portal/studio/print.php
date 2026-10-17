<script type="application/json" id="studio-data"><?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<div class="studio-print"><div class="pages" id="pages"></div></div>
<script src="<?= e(asset('js/studio-cfg.js')) ?>" defer></script>
<script src="<?= e(asset('vendor/studio-libs.js')) ?>" defer></script>
<script src="<?= e(asset('js/studio-render.js')) ?>" defer></script>
<script src="<?= e(asset('js/studio-editor.js')) ?>" defer></script>
