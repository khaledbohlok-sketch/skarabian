<?php
/** A team member's round photo on the website, or their initials in gold on navy until a photo is added. $x = SiteData::experts() row. */
use App\Services\SiteData;

if ($x['photo_id']): ?><img class="expert-photo" src="<?= e(SiteData::img((int) $x['photo_id'], 'thumb')) ?>" alt="<?= e(loc($x)) ?>" loading="lazy" width="124" height="124"><?php
else: ?><span class="expert-photo expert-ph" aria-hidden="true"><?= e(SiteData::initials(loc($x))) ?></span><?php
endif;
