/* Screenshots of every public page at phone and desktop widths. node tools/preview/shoot.js <base url> <out dir> [pages…] */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
(async () => {
  const base = process.argv[2] || 'http://127.0.0.1:8080';
  const out = process.argv[3] || 'shots';
  const only = process.argv.slice(4);
  fs.mkdirSync(out, { recursive: true });
  const pages = only.length ? only : ['/en', '/en/horses', '/en/horses/d-samed', '/en/champions', '/en/news', '/en/news/d-samed-gold-senior-stallion-all-nations-cup', '/en/about', '/en/contact', '/ar', '/ar/horses/d-samed'];
  const sizes = { phone: { width: 390, height: 844, deviceScaleFactor: 2, isMobile: true, hasTouch: true }, desktop: { width: 1440, height: 900, deviceScaleFactor: 1 } };
  const browser = await chromium.launch();
  for (const [name, vp] of Object.entries(sizes)) {
    const ctx = await browser.newContext({ viewport: { width: vp.width, height: vp.height }, deviceScaleFactor: vp.deviceScaleFactor, isMobile: !!vp.isMobile, hasTouch: !!vp.hasTouch });
    const page = await ctx.newPage();
    const errors = [];
    page.on('pageerror', e => errors.push('pageerror: ' + e.message));
    page.on('console', m => { if (m.type() === 'error') errors.push('console: ' + m.text()); });
    for (const p of pages) {
      const res = await page.goto(base + p, { waitUntil: 'networkidle', timeout: 60000 });
      await page.evaluate(() => document.fonts && document.fonts.ready);
      // scroll through the page so lazy images load, then back to the top
      await page.evaluate(async () => { const h = document.body.scrollHeight; for (let y = 0; y < h; y += 500) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 120)); } window.scrollTo(0, 0); });
      await page.waitForLoadState('networkidle');
      // every picture in the page has arrived (the hero's waiting slides excepted)
      await page.waitForFunction(() => Array.from(document.images).every(i => i.dataset.src && !i.getAttribute('src') || (i.complete && i.naturalWidth > 0)), null, { timeout: 20000 }).catch(() => {});
      await page.waitForTimeout(600);
      const file = path.join(out, name + '-' + (p.replace(/^\//, '').replace(/\//g, '_') || 'home') + '.png');
      await page.screenshot({ path: file, fullPage: true });
      console.log(res.status(), p, '->', file);
    }
    if (errors.length) console.log(name, 'errors:\n  ' + errors.join('\n  '));
    await ctx.close();
  }
  await browser.close();
})();
