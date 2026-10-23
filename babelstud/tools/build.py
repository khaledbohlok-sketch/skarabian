#!/usr/bin/env python3
"""Builds the Babel Stud site: index.html (English, LTR) and ar/index.html (Arabic, RTL) from content/strings.json.

    python3 tools/build.py

Every string comes from the content file; the two editions share one markup so a change lands in both.
"""
import html
import json
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
STR = json.load(open(os.path.join(ROOT, 'content', 'strings.json'), encoding='utf-8'))
VERSION = '1'


def e(s) -> str:
    return html.escape(str(s), quote=True)


class Page:
    def __init__(self, lang: str):
        self.lang = lang
        self.rtl = lang == 'ar'
        self.root = '../' if lang == 'ar' else ''
        self.other = 'en' if lang == 'ar' else 'ar'

    # a string in this edition: t('hero.title'); a plain string (no languages) is returned as is
    def t(self, key: str) -> str:
        node = STR
        for part in key.split('.'):
            node = node[int(part)] if part.isdigit() else node[part]
        if isinstance(node, dict) and self.lang in node:
            return node[self.lang]
        return node

    def o(self, key: str) -> str:  # the other edition's string (the calligraphic ghosts, the bilingual nameplate)
        node = STR
        for part in key.split('.'):
            node = node[int(part)] if part.isdigit() else node[part]
        return node[self.other] if isinstance(node, dict) else node

    def asset(self, path: str) -> str:
        return f'{self.root}assets/{path}?v={VERSION}'

    # a headline ready for its entrance: every word in a mask, optionally the last word in gold foil / one word italic
    def split(self, text: str, gold: bool = True, italic=None, cover: bool = False) -> str:
        words = text.split()
        last = len(words) - 1
        if italic is not None and italic < 0:
            italic = len(words) + italic
        out = []
        for i, w in enumerate(words):
            is_gold = gold and i == last and last > 0
            is_it = (not is_gold) and italic is not None and i == italic and last > 0
            cls = 'w' + (' w-key' if is_gold and cover else '') + (' w-it' if is_it else '')
            style = f'--i:{i}' + (f';--chars:{len(w)}' if is_gold and cover else '')
            out.append(f'<span class="{cls}"><span{" class=\"foil\"" if is_gold else ""} style="{style}">{e(w)}</span></span>')
        return ' '.join(out)

    def orn(self, sym: str, cls: str = '') -> str:
        return f'<svg class="orn {cls}" aria-hidden="true"><use href="#{sym}"></use></svg>'


def head(p: Page) -> str:
    title = f"{p.t('brand')} | {'Arabian Horses, Championships & Training' if not p.rtl else 'خيل عربية أصيلة، بطولات وتدريب'}"
    desc = p.t('hero.text')
    pre = ['Marcellus-400', 'CormorantGaramond-300i', 'Inter-var-latin'] if not p.rtl else ['Amiri-700-12', 'Amiri-400-11', 'IBMPlexSansArabic-400-4']
    preloads = ''.join(f'<link rel="preload" href="{p.root}assets/fonts/{f}.woff2" as="font" type="font/woff2" crossorigin>' for f in pre)
    return f'''<!doctype html>
<html lang="{p.lang}" dir="{'rtl' if p.rtl else 'ltr'}" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{e(title)}</title>
<meta name="description" content="{e(desc)}">
<meta name="theme-color" content="#1a0408">
<link rel="alternate" hreflang="en" href="{p.root}index.html">
<link rel="alternate" hreflang="ar" href="{p.root}ar/index.html">
<link rel="icon" href="{p.asset('img/favicon.png')}" type="image/png">
<meta property="og:title" content="{e(title)}"><meta property="og:description" content="{e(desc)}"><meta property="og:image" content="{p.root}assets/img/photos/mayyaset-trot.jpg"><meta property="og:type" content="website">
{preloads}
<link rel="preload" href="{p.root}assets/img/photos/mayyaset-trot.jpg" as="image" fetchpriority="high">
<script src="{p.asset('js/site-head.js')}"></script>
<link rel="stylesheet" href="{p.asset('css/fonts.css')}">
<link rel="stylesheet" href="{p.asset('css/babel.css')}">
</head>'''


NAV = ['about', 'facilities', 'achievements', 'horses', 'competition', 'gallery', 'contact']


def header(p: Page) -> str:
    links = ''.join(f'<a href="#{k}">{e(p.t("nav." + k))}</a>' for k in NAV)
    other_url = f'{p.root}ar/index.html' if not p.rtl else f'{p.root}index.html'
    other_label = 'العربية' if not p.rtl else 'English'
    call = 'Call' if not p.rtl else 'اتصل بنا'
    return f'''<header class="top" data-header>
  <div class="wrap top-inner">
    <a class="brand" href="#top" aria-label="{e(p.t('brand'))}">
      <img src="{p.asset('img/emblem.jpg')}" alt="" width="48" height="48">
      <span class="brand-name"><b>{e(p.t('brand'))}</b><small>{e(p.t('tagline'))}</small></span>
    </a>
    <nav class="main-nav" aria-label="{'Menu' if not p.rtl else 'القائمة'}">{links}</nav>
    <div class="top-tools">
      <a class="lang" href="{other_url}" hreflang="{p.other}" lang="{p.other}">{other_label}</a>
      <a class="btn btn-gold btn-sm top-call" href="tel:{e(STR['contact']['tel'])}">{call}</a>
      <button class="menu-btn" type="button" data-menu-open aria-controls="menu" aria-expanded="false" aria-label="{'Menu' if not p.rtl else 'القائمة'}"><span></span><span></span></button>
    </div>
  </div>
</header>
<div class="menu" id="menu" data-menu hidden>
  <div class="menu-head"><span class="brand-name"><b>{e(p.t('brand'))}</b></span><button class="menu-close" type="button" data-menu-close aria-label="{'Close' if not p.rtl else 'إغلاق'}">×</button></div>
  <nav class="menu-links">{''.join(f'<a href="#{k}" style="--i:{i}"><i>0{i + 1}</i>{e(p.t("nav." + k))}</a>' for i, k in enumerate(NAV))}</nav>
  <div class="menu-foot"><a class="lang" href="{other_url}" hreflang="{p.other}" lang="{p.other}">{other_label}</a><a class="btn btn-gold" href="tel:{e(STR['contact']['tel'])}">{e(STR['contact']['phone'])}</a></div>
</div>'''


def hero(p: Page) -> str:
    return f'''<section class="hero" id="home" aria-labelledby="hero-title" data-hero>
  <div class="hero-media" aria-hidden="true">
    <img class="hero-img" src="{p.root}assets/img/photos/mayyaset-trot.jpg" alt="" width="1440" height="1439" fetchpriority="high" decoding="async">
  </div>
  <div class="hero-veil" aria-hidden="true"></div>
  <div class="hero-grain" aria-hidden="true"></div>
  <svg class="hero-frame" aria-hidden="true" viewBox="0 0 100 100" preserveAspectRatio="none"><rect x=".5" y=".5" width="99" height="99" pathLength="1" vector-effect="non-scaling-stroke"/></svg>
  <span class="hero-folio start" aria-hidden="true">{'Amman · Jordan · 31.95° N' if not p.rtl else 'عمّان · الأردن · 31.95° N'}</span>
  <span class="hero-folio end" aria-hidden="true" lang="{p.other}">{e(p.o('brand'))}</span>
  <div class="wrap hero-inner" data-hero-parallax>
    <div class="hero-copy">
      <span class="k hero-in" style="--d:0">{e(p.t('kicker'))}</span>
      <h1 id="hero-title" class="split split-now cover">{p.split(p.t('hero.title'), True, None, True)}</h1>
      <p class="accent hero-in" style="--d:3">{e(p.t('hero.text'))}</p>
      <div class="actions hero-in" style="--d:4">
        <a class="btn btn-gold btn-lg" href="#competition" data-magnetic>{e(p.t('hero.cta1'))}</a>
        <a class="link-arrow down" href="#horses">{e(p.t('hero.cta2'))} <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v16m-6-6 6 6 6-6"/></svg></a>
      </div>
    </div>
    <div class="hero-foot hero-in" style="--d:5">
      <a class="hero-caption" href="#horses"><span class="hero-caption-k">{e(p.t('horse.kicker'))}</span><strong>{e(p.t('horse.name'))}</strong></a>
      <span class="hero-credit">{e(p.t('horse.credit'))}</span>
    </div>
  </div>
  <a class="hero-scroll seal" href="#competition" aria-label="{'Scroll' if not p.rtl else 'انزل'}"><svg viewBox="0 0 100 100" aria-hidden="true"><use href="#seal"></use></svg><img src="{p.asset('img/emblem.jpg')}" alt="" width="28" height="28"></a>
</section>'''


def announce(p: Page) -> str:
    return f'''<section class="announce" id="competition" aria-labelledby="h-comp">
  <div class="wrap announce-inner reveal">
    <div class="announce-text">
      <span class="k">{e(p.t('competition.kicker'))}</span>
      <h2 id="h-comp" class="split">{p.split(p.t('competition.title'), False, -1)}</h2>
      <p>{e(p.t('competition.text'))}</p>
    </div>
    <div class="announce-plate" aria-hidden="true">
      <span class="plate-num">01</span>
      <span class="plate-k">{e(p.t('competition.featured'))}</span>
      <span class="plate-soon">{e(p.t('competition.soon'))}</span>
    </div>
  </div>
</section>'''


def index(p: Page, n: int, label: str) -> str:
    return f'<span class="index"><b>{n:02d}</b> {e(label)}</span>'


def about(p: Page) -> str:
    return f'''<section class="chapter about" id="about" aria-labelledby="h-about">
  <span class="threshold" aria-hidden="true">{p.orn('rosette')}</span>
  <div class="wrap">
    <div class="about-grid">
      <div class="about-text" data-ghost="{e(p.o('about.kicker'))}">
        <div class="reveal">{index(p, 1, p.t('about.kicker'))}</div>
        <h2 id="h-about" class="split reveal">{p.split(p.t('about.title'), False, -1)}</h2>
        <p class="opens reveal">{e(p.t('about.text'))}</p>
      </div>
      <figure class="about-seal reveal" aria-hidden="true">
        <img src="{p.asset('img/emblem.jpg')}" alt="" width="800" height="800" loading="lazy" decoding="async">
      </figure>
    </div>
    <div class="manifesto stagger reveal">
      <div class="manifesto-item"><span class="k">{e(p.t('facilities.vision.kicker'))}</span><h3>{e(p.t('facilities.vision.title'))}</h3><p>{e(p.t('facilities.vision.text'))}</p></div>
      <div class="manifesto-item"><span class="k">{e(p.t('facilities.mission.kicker'))}</span><h3>{e(p.t('facilities.mission.title'))}</h3><p>{e(p.t('facilities.mission.text'))}</p></div>
    </div>
  </div>
</section>'''


def facilities(p: Page) -> str:
    items = ''.join(f'''<li class="plate" style="--i:{i}"><span class="plate-n">0{i + 1}</span><h3>{e(it['title'][p.lang])}</h3><p>{e(it['text'][p.lang])}</p></li>''' for i, it in enumerate(STR['facilities']['items']))
    return f'''<section class="chapter facilities" id="facilities" aria-labelledby="h-fac">
  <span class="threshold" aria-hidden="true">{p.orn('rosette')}</span>
  <div class="wrap">
    <div class="chapter-head split-head reveal" data-ghost="{e(p.o('nav.facilities'))}">
      <div>{index(p, 2, p.t('facilities.kicker'))}<h2 id="h-fac" class="split">{p.split(p.t('facilities.title'), False, 3 if not p.rtl else -1)}</h2></div>
      <p class="lead">{e(p.t('facilities.text'))}</p>
    </div>
    <ol class="plates stagger reveal">{items}</ol>
  </div>
</section>'''


def achievements(p: Page) -> str:
    gold = [x for x in STR['achievements']['items'] if x['medal'] == 'gold']
    silver = [x for x in STR['achievements']['items'] if x['medal'] == 'silver']
    rows = ''.join(f'''<li class="honour {it['medal']}" style="--i:{i}"><span class="medal {it['medal']}" aria-hidden="true"><svg viewBox="0 0 64 88"><use href="#medal"></use></svg></span><span class="honour-text"><strong>{e(it['show'][p.lang])}</strong><small>{e(it['place'][p.lang])}</small></span><i class="leader" aria-hidden="true"></i><span class="honour-title">{e(p.t('achievements.' + it['medal']))}</span></li>''' for i, it in enumerate(STR['achievements']['items']))
    countries = 'three countries' if not p.rtl else 'ثلاث دول'
    return f'''<section class="chapter achievements night" id="achievements" aria-labelledby="h-ach">
  <span class="threshold" aria-hidden="true">{p.orn('rosette')}</span>
  <div class="wrap">
    <div class="chapter-head split-head reveal" data-ghost="{e(p.o('nav.achievements'))}">
      <div>{index(p, 3, p.t('achievements.kicker'))}<h2 id="h-ach" class="split">{p.split(p.t('achievements.title'), False, 1 if not p.rtl else -1)}</h2></div>
      <p class="lead">{e(p.t('achievements.text'))}</p>
    </div>
    <div class="cabinet stagger reveal">
      <div class="cabinet-item gold" style="--i:0"><svg viewBox="0 0 64 88" aria-hidden="true"><use href="#medal"></use></svg><b data-count="{len(gold)}">{len(gold)}</b><span>{e(p.t('achievements.gold'))}</span></div>
      <div class="cabinet-item silver" style="--i:1"><svg viewBox="0 0 64 88" aria-hidden="true"><use href="#medal"></use></svg><b data-count="{len(silver)}">{len(silver)}</b><span>{e(p.t('achievements.silver'))}</span></div>
      <div class="cabinet-item circuit" style="--i:2"><svg class="orn" viewBox="0 0 48 48" aria-hidden="true"><use href="#rosette"></use></svg><b>3</b><span>{countries}</span></div>
    </div>
    <ol class="honours stagger reveal">{rows}</ol>
  </div>
</section>'''


def horse(p: Page) -> str:
    h = STR['horse']
    titles = ''.join(f'''<li><span class="medal sm {t['medal']}" aria-hidden="true"><svg viewBox="0 0 64 88"><use href="#medal"></use></svg></span><span>{e(t[p.lang])}</span></li>''' for t in h['titles'])
    return f'''<section class="chapter horse night" id="horses" aria-labelledby="h-horse">
  <span class="threshold" aria-hidden="true">{p.orn('rosette')}</span>
  <div class="wrap">
    <div class="chapter-head chapter-head-row reveal" data-ghost="{e(p.o('horse.kicker'))}">
      <div>{index(p, 4, p.t('horse.kicker'))}<h2 id="h-horse" class="split">{p.split(p.t('nav.horses'), False)}</h2></div>
    </div>
  </div>
  <article class="wall">
    <div class="wall-media arched framed wipe reveal" data-wall>
      <img src="{p.root}assets/img/photos/mayyaset-handler-1.jpg" alt="{e(h['name'][p.lang])}" loading="lazy" decoding="async" width="1440" height="1440">
      <svg class="orn-corner bl" aria-hidden="true"><use href="#corner"></use></svg><svg class="orn-corner br" aria-hidden="true"><use href="#corner"></use></svg>
    </div>
    <div class="wall-text reveal">
      <span class="wall-ghost" aria-hidden="true">{e(h['name']['en'])}</span>
      <span class="k">{e(p.t('horse.owner_line'))}</span>
      <h3 class="wall-name">{e(h['name'][p.lang])}</h3>
      <p class="alt-name" lang="{p.other}">{e(h['name'][p.other])}</p>
      <p class="lineage"><b>{e(h['sire'])}</b> <span class="x">{p.orn('rosette', 'orn-rosette')}</span> <b>{e(h['dam'])}</b></p>
      <ul class="titles">{titles}</ul>
      <p class="muted">{e(p.t('horse.text'))} {e(p.t('horse.owned'))}</p>
      <div class="actions"><a class="btn btn-line" href="#gallery">{e(p.t('gallery.kicker'))}</a><a class="link-arrow" href="{e(STR['instagram']['url'])}" target="_blank" rel="noopener">{e(STR['instagram']['handle'])} <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 17 17 7M8 7h9v9"/></svg></a></div>
      <small class="credit">{e(p.t('horse.credit'))}</small>
    </div>
  </article>
</section>'''


GALLERY = [('mayyaset-trot-2', 1440, 1439), ('mayyaset-head', 1440, 1440), ('mayyaset-handler-2', 1440, 1440), ('chestnut-show', 1800, 1012), ('grey-lawn', 1800, 1011), ('mayyaset-trot', 1440, 1439)]


def gallery(p: Page) -> str:
    items = ''.join(f'''<li style="--i:{i}"><a class="{'framed' if i in (0, 3) else ''}" href="{p.root}assets/img/photos/{n}.jpg" data-lb="{p.root}assets/img/photos/{n}.jpg" data-caption="{e(p.t('brand'))} · {e(p.t('horse.credit'))}"><img src="{p.root}assets/img/photos/{n}-thumb.jpg" alt="" loading="lazy" decoding="async" width="720" height="{int(720 * hh / w)}"><span class="cap" aria-hidden="true">{e(p.t('brand'))}</span></a></li>''' for i, (n, w, hh) in enumerate(GALLERY))
    return f'''<section class="chapter gallery" id="gallery" aria-labelledby="h-gal" data-film>
  <span class="threshold" aria-hidden="true">{p.orn('rosette')}</span>
  <div class="wrap">
    <div class="chapter-head chapter-head-row reveal" data-ghost="{e(p.o('gallery.kicker'))}">
      <div>{index(p, 5, p.t('gallery.kicker'))}<h2 id="h-gal" class="split">{p.split(p.t('gallery.title'), False, -1)}</h2></div>
      <p class="lead">{e(p.t('gallery.text'))} · {e(p.t('horse.credit'))}</p>
    </div>
    <ul class="sheet stagger reveal" data-lb-group>{items}</ul>
    <div class="row-foot" aria-hidden="true"><span class="row-progress"><i></i></span><span class="row-n" dir="ltr"><b>1</b> / {len(GALLERY)}</span></div>
  </div>
</section>
<section class="band" aria-labelledby="h-ig">
  <div class="wrap band-inner reveal">
    <div><span class="k">{e(p.t('instagram.kicker'))}</span><h2 id="h-ig">{e(STR['instagram']['handle'])}</h2><p>{e(p.t('instagram.text'))}</p></div>
    <a class="btn btn-gold btn-lg" href="{e(STR['instagram']['url'])}" target="_blank" rel="noopener">{e(p.t('instagram.cta'))}</a>
  </div>
</section>'''


def contact(p: Page) -> str:
    c = STR['contact']
    L = lambda k: e(c['labels'][k][p.lang])
    return f'''<section class="chapter contact night" id="contact" aria-labelledby="h-contact">
  <span class="threshold" aria-hidden="true">{p.orn('rosette')}</span>
  <div class="wrap contact-grid">
    <div class="contact-text reveal" data-ghost="{e(p.o('contact.kicker'))}">
      {index(p, 6, p.t('contact.kicker'))}
      <h2 id="h-contact" class="split">{p.split(p.t('contact.title'), False, -1)}</h2>
      <p class="lead">{e(p.t('contact.text'))}</p>
      <div class="actions"><a class="btn btn-gold btn-lg" href="tel:{e(c['tel'])}">{e(c['phone'])}</a><a class="btn btn-line btn-lg" href="https://wa.me/{e(c['tel'].lstrip('+'))}" target="_blank" rel="noopener">WhatsApp</a></div>
    </div>
    <dl class="card reveal">
      <div><dt>{L('phone')}</dt><dd><a href="tel:{e(c['tel'])}" dir="ltr">{e(c['phone'])}</a></dd></div>
      <div><dt>{L('email')}</dt><dd><a href="mailto:{e(c['email'])}" dir="ltr">{e(c['email'])}</a></dd></div>
      <div><dt>{L('address')}</dt><dd>{e(c['address'][p.lang])}</dd></div>
      <div><dt>{L('instagram')}</dt><dd><a href="{e(STR['instagram']['url'])}" target="_blank" rel="noopener" dir="ltr">{e(STR['instagram']['handle'])}</a></dd></div>
    </dl>
  </div>
</section>'''


def footer(p: Page) -> str:
    return f'''<footer class="foot">
  <div class="wrap">
    <div class="foot-seal seal" aria-hidden="true"><svg viewBox="0 0 100 100"><use href="#seal"></use></svg><img src="{p.asset('img/emblem.jpg')}" alt="" width="36" height="36" loading="lazy"></div>
    <p class="wordmark" aria-hidden="true"><span class="wm-foil">{e(p.t('brand').split()[0]) if not p.rtl else e(p.t('brand'))}</span>{'' if p.rtl else ' <span class="wm-line">' + e(' '.join(p.t('brand').split()[1:])) + '</span>'}</p>
    <p class="wordmark-other" lang="{p.other}" dir="{'ltr' if p.rtl else 'rtl'}" aria-hidden="true">{e(p.o('brand'))}</p>
    <div class="foot-bottom"><span>{e(p.t('footer.copy'))}</span><a href="{e(STR['instagram']['url'])}" target="_blank" rel="noopener">{e(p.t('contact.labels.instagram'))} {e(STR['instagram']['handle'])}</a></div>
  </div>
</footer>
<nav class="dock" data-dock hidden aria-label="{e(p.t('contact.kicker'))}">
  <span class="dock-chapter" data-dock-chapter>{e(p.t('brand'))}</span>
  <a class="btn btn-line btn-sm" href="{e(STR['instagram']['url'])}" target="_blank" rel="noopener" aria-label="Instagram"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/></svg></a>
  <a class="btn btn-gold btn-sm" href="tel:{e(STR['contact']['tel'])}">{'Call' if not p.rtl else 'اتصل بنا'}</a>
</nav>'''


def sprite(p: Page) -> str:
    return '''<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
  <symbol id="rosette" viewBox="0 0 48 48"><g fill="none" stroke="currentColor" stroke-width="1"><rect x="12" y="12" width="24" height="24"/><rect x="12" y="12" width="24" height="24" transform="rotate(45 24 24)"/><circle cx="24" cy="24" r="3.2"/><circle cx="24" cy="24" r="1" fill="currentColor" stroke="none"/></g></symbol>
  <symbol id="corner" viewBox="0 0 64 64"><g fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round"><path d="M2 62V22C2 11 11 2 22 2h40"/><path opacity=".55" d="M8 62V26c0-10 8-18 18-18h36"/><path d="M14 42c0-11 8-18 19-18M9 46c-4-11 2-21 11-25M30 12c-6 2-10 6-12 12"/><circle cx="20" cy="20" r="1.8" fill="currentColor"/></g></symbol>
  <symbol id="medal" viewBox="0 0 64 88"><path fill="currentColor" opacity=".28" d="M20 0h24l-6 30H26z"/><path fill="currentColor" opacity=".16" d="M14 0h12l6 30-8 6z"/><path fill="currentColor" opacity=".16" d="M38 0h12l-10 36-8-6z"/><circle cx="32" cy="58" r="27" fill="currentColor" opacity=".22"/><circle cx="32" cy="58" r="27" fill="none" stroke="currentColor" stroke-width="1.5"/><circle cx="32" cy="58" r="21" fill="none" stroke="currentColor" stroke-width="1" opacity=".7"/><path fill="currentColor" d="M32 42.5 35 51l9-3-6 7.5 6 7.5-9-3-3 8.5-3-8.5-9 3 6-7.5-6-7.5 9 3z"/></symbol>
  <symbol id="seal" viewBox="0 0 100 100"><circle cx="50" cy="50" r="49" fill="none" stroke="currentColor" stroke-width=".8"/><circle cx="50" cy="50" r="37.5" fill="none" stroke="currentColor" stroke-width=".5" opacity=".8"/><path id="seal-arc" d="M50 50m-43 0a43 43 0 1 1 86 0a43 43 0 1 1-86 0" fill="none"/><text font-family="Inter, system-ui, sans-serif" font-weight="600" font-size="6" letter-spacing="2" fill="currentColor"><textPath href="#seal-arc">BABEL STUD · AMMAN · JORDAN · ARABIAN HORSES ·</textPath></text></symbol>
  <clipPath id="arch-clip" clipPathUnits="objectBoundingBox"><path d="M0 1V0.26C0 0.12 0.18 0.03 0.5 0C0.82 0.03 1 0.12 1 0.26V1Z"/></clipPath>
</svg>'''


def page(p: Page) -> str:
    return (head(p) + '\n<body>\n' + sprite(p) + header(p) + '\n<main id="top">\n' + hero(p) + announce(p) + about(p) + facilities(p) + achievements(p) + horse(p) + gallery(p) + contact(p)
            + '\n</main>\n' + footer(p) + f'\n<script src="{p.asset("js/site.js")}" defer></script>\n</body>\n</html>\n')


if __name__ == '__main__':
    for lang, out in (('en', 'index.html'), ('ar', 'ar/index.html')):
        path = os.path.join(ROOT, out)
        os.makedirs(os.path.dirname(path), exist_ok=True)
        open(path, 'w', encoding='utf-8').write(page(Page(lang)))
        print('built', out)
