# Approved screen designs

> **`screens-v2/` supersedes this set for anything not yet built.** Ten screens covering the
> public site, the learner app, the teacher space and the admin console. What each one maps
> to, which sprint it belongs in, and the four things it assumes the platform does not have
> yet, are in **[IMPLEMENTATION-MAP.md](IMPLEMENTATION-MAP.md)**. The tokens below still
> hold — v2 uses the same type and palette.


The design set generated in Claude Design and approved on 2026-09-19. It is the reference
for every S3 page, not just the login screen.

`screens/` holds one self-contained HTML file per screen, extracted from the single
`PS Language Hub — Écrans détaillés.html` export (which nests each screen in a bundler
manifest and is too large to read directly). Open any of them in a browser.

| File | Screen |
|---|---|
| `Accueil_PS_Language_Hub.html` | Public home — hero, featured courses, how it works, low-bandwidth section, app, testimonials, footer |
| `Catalogue_des_cours_PS_Language_Hub.html` | Catalogue with filters |
| `Fiche_cours_Anglais_conversation_B1.html` | Course detail |
| `Tableau_de_bord_apprenant_PS_Language_Hu.html` | Learner dashboard |
| `Classe_virtuelle_PS_Language_Hub.html` | Live class, with the 45-minute warning |
| `Accueil_mobile_PS_Language_Hub.html` | Home, 390px |
| `Session_live_mobile_mode_audio_seul_PS_L.html` | Live session, audio-only mode |
| `Paiement_mobile_PS_Language_Hub.html` | Mobile Money checkout |

## The system the screens are drawn in

| Token | Value |
|---|---|
| Display face | **Bricolage Grotesque**, weights 500–800 |
| Text face | **Instrument Sans**, weights 400–700 |
| Navy | `#0A1A40`, with `#13275C` and `#3C4E7A` |
| Blue | `#1F6FE0` interaction, `#0B3FA0` ink, `#5BA3FF` light, `#CFE2FF` borders |
| Accents | pink `#E8336F` / `#F05A8B`, orange `#F7941D`, yellow `#FBC02D` |
| Surfaces | white, `#F2F7FF`, borders `#E2EAF7` |
| Radius | 12px controls, 16px cards, 999px badges |

Both faces are variable, OFL-licensed, and **self-hosted** in
`theme/pslanguagehub/fonts/` as latin subsets (41 KB + 30 KB). They are never fetched from
Google Fonts: a third-party DNS lookup and TLS handshake on the critical path is exactly
what §5.5 exists to avoid on a 2G-first product.

## Built so far

| Screen | State |
|---|---|
| Login | **Built and verified** — `shots/login-desktop-1440.png`, `shots/login-mobile-390.png`, against `shots/login-mockup-1440.png` |
| Public home | **Built and verified** — `shots/home-desktop-fr.png`, `shots/home-mobile-390.png` |
| Catalogue | **Built and verified** — `shots/catalogue-desktop.png` |
| Course detail | **Built and verified** — `shots/course-desktop-fr.png` |
| Everything else | Later sprints |

### Where the build departs from the design, and why

| Design shows | Built instead | Why |
|---|---|---|
| Two named testimonials with photos | Nothing | The quotes are invented. Publishing fabricated customer quotes on a live site is a lie to the visitor, whatever the design says. The slot stays empty until real learners say something real. |
| A teacher's name, module plan, next three live sessions with seats left, skills, XP on the course page | Course summary, price, what a place carries | None of it exists in the data model yet — sessions land in S5, certificates in S14. A made-up session date on the page a learner pays from is the worst possible place to invent. |
| "Download on Google Play" as a button | A dashed grey label, "coming soon" | The app ships in S12. A store link that goes nowhere is a broken promise on the page that makes the first promise. |
| Legal links in the footer | Grey text, "coming soon" | The pages do not exist yet. |
| Course cards with photographs | The course's image when one is set, a language plate when not | **Changed 2026-09-27 at your request: cards can carry an image.** It comes from Moodle's own course image (the course form's overview file), so nothing new has to be uploaded twice. The plate is not a placeholder — it means a course is publishable the day it is written, and a catalogue still scans when nobody has found a photograph. Images are lazy-loaded, and they are served by `local/pshub/image.php`, not `pluginfile.php`, because that one obeys `forcelogin` and would send every anonymous visitor's browser to the login page instead of a picture. |
