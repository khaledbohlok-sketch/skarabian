<?php // A ✨ Translate button under an English or Arabic field: fills it from its partner field in the other language
// (public/assets/js/ai.js). $form: the form's name for the server (a portal form's address, or cms-home); $field and
// $from: the names of this field and of its partner. ?>
<button type="button" class="btn btn-sm ghost ai-translate" data-ai-translate="<?= e($field) ?>" data-ai-from="<?= e($from) ?>" data-ai-to="<?= e(substr($field, -2)) ?>" data-ai-form="<?= e($form) ?>" data-ai-url="<?= e(url('/portal/ai/translate')) ?>" title="<?= e(__('ai.translate_help')) ?>">✨ <?= e(__(str_ends_with($field, '_ar') ? 'ai.translate_to_ar' : 'ai.translate_to_en')) ?></button>
