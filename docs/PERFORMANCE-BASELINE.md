# Performance baseline

`PSH-S03-08`. Re-measured **2026-10-03** against the running site. This replaces the
2026-09-20 figures, which were wrong in one specific way described under *What the first
measurement got wrong*.

**Method.** For every resource: fetch it with `Accept-Encoding: gzip` **and read back the
`Content-Encoding` header of the response**, so the recorded size is what actually went
over the wire rather than what we hoped would. Resources are counted once: the stylesheet
and the scripts are shared by every page and cached with a far-future `Expires`.

Fonts are counted as the **two families the pages actually use**. The stylesheet declares
130 `@font-face` blocks, 120 of them Noto Sans JP that Boost ships; `--bs-font-sans-serif`
resolves to `Instrument Sans`, so those 120 are declared and never matched, and a browser
never fetches them. Summing every referenced font file gives 6.2 MB and means nothing.

## Per page

| Route | Document | Compressed |
|---|---|---|
| `/local/pshub/index.php` (home) | 8 324 B | gzip |
| `/local/pshub/catalogue.php` | 6 979 B | gzip |
| `/login/index.php` | 8 278 B | gzip |
| `/local/pshub/learn/index.php` | 13 784 B | gzip |
| `/local/pshub/learn/course.php` | 14 119 B | gzip |
| `/local/pshub/teacher/index.php` | 13 836 B | gzip |
| `/local/pshub/admin/index.php` | 13 354 B | gzip |

## Shared by every page, cached after the first visit

| What | Wire size | Compressed | Whose |
|---|---|---|---|
| `theme/yui_combo.php?…yui-moodlesimple.js` | 288 743 B | gzip | Moodle core (YUI 3.18) |
| `theme/styles.php/…/all` | 237 292 B | gzip | Boost + ours |
| `lib/javascript.php/…/polyfills/polyfill.js` | 60 482 B | gzip | Moodle core |
| `bricolage-grotesque-latin.woff2` | 41 344 B | — | ours |
| `instrument-sans-latin.woff2` | 30 092 B | — | ours |
| `lib/javascript.php/…/javascript-static.js` | 6 169 B | gzip | Moodle core |
| `lib/javascript.php/…/requirejs/require.min.js` | 6 581 B | gzip | Moodle core |
| **Total shared** | **≈ 671 KB** | | |

**First visit: ≈ 680–690 KB. Every page after that: 7–14 KB.**

**Our own code ships 2.1 KB of JavaScript, on one page** (updated 2026-10-10). Until the
session lobby there was none at all; it now loads `local_pshub/session_clock`, measured at
**2 111 B gzipped**, and no other route loads anything of ours. The AC-21 clause that
matters — "neither ships a React, Vue or equivalent framework bundle" — holds, and every
route, the lobby included, is usable with scripting off: the page prints when the room
opens and how long the class runs, and the module only makes the wait legible.

The figure is the commented **source**: with no node toolchain in this project
(`PSH-S01-07`), `requirejs.php` serves `amd/src` rather than the minified build. A build
with a source map would serve roughly 1 KB instead. Not worth a toolchain on its own, but
worth knowing where the number comes from.

**None of the four scripts is in `<head>`.** All sit at the end of `<body>` with no `defer`
or `async`, which means they do not block the first paint: on a slow connection the page
renders and the JavaScript arrives afterwards. That matters more for the felt experience
than the byte count does.

## What the first measurement got wrong

The 2026-09-20 run asked for gzip and recorded the response size **without checking whether
the response was actually compressed**. It was not, for the pages: Apache ships
`mod_deflate` commented out in this Laragon build, and HTML was going out raw at
**120 KB per page** while the document was being written down as "32 KB, gzip".

Fixed 2026-10-03, two ways:

1. `ini_set('zlib.output_compression', 'On')` in Moodle's `config.php`, which compresses
   PHP's output. Measured effect: the learner dashboard went from **120 198 B to 13 834 B**,
   the public home from 129 042 B to 15 580 B. Roughly **106 KB saved on every page view**.
2. `AddOutputFilterByType DEFLATE …` in `C:/laragon/etc/apache2/sites-enabled/01-pshub.conf`,
   which is the right place for it and takes effect at the next Apache restart. The
   `LoadModule` line lives in that same file so nothing outside this project is touched.

**In production the web server does this, not `config.php`.** Compression belongs in the
hosting requirements of decision 10, next to the PHP and database floors.

### One thing that looked broken and is not

A single request **does** receive the stylesheet uncompressed, at 1 379 458 B: the one that
rebuilds it after a theme cache reset. `css_send_cached_css()` calls
`min_enable_zlib_compression()`, which returns false once headers have been sent, and on the
build path they have. Every subsequent request is gzip at 237 292 B — verified three times
in a row at a settled revision. So it costs one request after a deployment, not one per
visitor, and it is the same build path as trap 23 in `ENVIRONMENT.md`.

## The budget, stated plainly

§13.2 sets **"total JavaScript per page < 50 KB compressed"** and **"all learner pages
< 3 s on 3G"**.

- **362 KB of the 370 KB of JavaScript is Moodle core**, 289 KB of it the YUI rollup core
  emits on every page. Ours is 0 KB. The 50 KB budget cannot be met while Moodle serves the
  pages, and nothing this project writes changes that.
- At a realistic 3G throughput of ~400 kbit/s, **690 KB is about 14 seconds cold** — but
  the document arrives in well under a second and renders without waiting for the scripts.
- The second and every later page is **7–14 KB**: well inside any budget.

### What is left to decide

The engineering is done; one product decision remains, and it is not ours to take:

| Option | What it costs |
|---|---|
| **Keep the budget as written** | It will never be met. The number stays in the spec as a permanent failure |
| **Restate it as "our code ships < 50 KB"** | True today (0 KB), measurable, and keeps the discipline it was written for |
| **Restate it as a first-paint target** | e.g. "content visible in < 3 s on 3G" — which the end-of-body loading already achieves, and which is what a learner actually experiences |
| **Drop Moodle's front end for learner pages** | Months of work, and it throws away the reason Moodle was chosen |

The recommendation is the second plus the third together: hold our own code to 50 KB, and
measure the experience by first paint rather than by total bytes. Both are honest, both are
testable, and neither pretends core's 362 KB is going away.
