<div class="error-page"><h1><?= e(__('common.access_denied')) ?></h1><p><?= e(__('common.access_denied_text')) ?></p>
<p><a class="btn btn-primary" href="<?= e(url('/portal')) ?>"><?= e(__('common.go_home')) ?></a><?php if (!\App\Core\Auth::check()): ?> <a class="btn btn-ghost" href="<?= e(site_url()) ?>"><?= e(__('site.home')) ?></a><?php endif; ?></p></div>
