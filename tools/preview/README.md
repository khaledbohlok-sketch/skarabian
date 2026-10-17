# Website preview without a database

Renders the public website (version 3) from fixture data — the horses, parents, shows and results of
`database/data/horse_cards_2026-10-07.json`, sample news, gallery and team entries — so the design can be
reviewed, screenshotted and iterated without MySQL, uploads or the staff system.

The real `App\Core\DB` and `App\Services\SiteData` are replaced by the stubs in `stubs/`; everything else
(router, controllers, views, helpers, language files, assets) is the production code.

```bash
# photos: a folder of JPEGs named as in fixtures.php (name.jpg and name-thumb.jpg)
PREVIEW_PHOTOS=/path/to/photos php -S 127.0.0.1:8080 -t public tools/preview/router.php
# then open http://127.0.0.1:8080/en  (or /ar)

# screenshots of every page at phone and desktop widths (needs Playwright + Chromium)
node tools/preview/shoot.js http://127.0.0.1:8080 /path/to/out
```

Nothing here is loaded in production: `public/index.php` never includes this folder.
