# Login design brief

Paste the block below into Claude Design (or any design tool). It is written to be
self-contained — everything a designer needs that they could not guess.

---

Design the **sign-in screen** for PS Language Hub.

## The product, in one line

Online language school where **every single course is a live class** — 50 minutes with a real
teacher on video, never a recorded lesson. That is the whole differentiator.

## Who signs in

Adults in **Cameroon and francophone West/Central Africa**. Mostly on **Android phones**, often
on **2G/3G**. Interface language is **French by default**, English second. They are paying real
money for scarce bandwidth, so the screen must feel fast and trustworthy, not lavish.

## Brand

**Logo mark**: a white letter **P** whose bowl doubles as a speech bubble, with a small tail at
its lower left and **three white dots** inside it (the "someone is speaking" indicator). A thick
**gradient ribbon** sweeps through it forming an **S**. A small **blue globe** with meridian lines
sits at the upper right.

**Colours — exact values, do not substitute:**

| Role | Value |
|---|---|
| Navy (ground, text, primary action) | `#0A1A40` |
| Signature gradient (the logo ribbon, 4 stops) | `#2E6FE6` → `#E8336F` → `#F7941D` → `#FBC02D` |
| Blues (interaction, links, focus) | `#0B3FA0` `#1F6FE0` `#2E8BFF` `#5BA3FF` `#CFE2FF` |
| White / pale blue surfaces | `#FFFFFF` `#F5F8FD` |

**Type**: **Outfit** at 600 and 700 for display and headings only. Body text is the **system UI
stack** — deliberate, it downloads nothing on a 2G connection. Do not introduce a third family.
Do not use a serif.

**Devise**: `LEARN. SPEAK. SUCCEED.` — the three words in blue, pink, orange respectively.

## The rule that organises the palette

Three colour families, three fixed jobs, never swapped:

- **Navy** — structure: ground, chrome, primary action, body text
- **Blue** — interaction: links, focus rings, secondary action
- **Gradient** — *live*: a class running or about to start

The gradient means "a class is happening". On a sign-in screen no class is happening, so the
gradient may appear **only** as brand identity — inside the logo, or one hairline — and never as
a large decorative wash. That restraint is the idea, not a limitation.

## Hard accessibility constraint

**The pink stop `#E8336F` cannot carry text.** White on it measures 4.4:1 and navy on it 3.6:1,
both below WCAG AA at body size. So: **never place type on the gradient.** If a control must sit
on gradient, use only the orange→yellow half with **navy** ink, which reaches 5.8:1.

Every text/background pair must pass **AA (4.5:1)**. State the ratio for the primary button and
for any text over colour.

## What is fixed and cannot be redesigned

The form is rendered by Moodle and its markup must not change. Design *around* these exact
elements, in this order:

1. Heading: **"Welcome back"** (fr: *Bon retour*)
2. Subtitle: **"Log in to PS Language Hub"**
3. Field **"Username or email"** — envelope icon inside, left
4. Field **"Password"** — padlock icon inside left, **eye reveal button inside right**
5. Right-aligned link **"Forgot password?"**
6. Full-width primary button **"Log in"**
7. Below: a language selector (`English (en)`) and a `Cookies notice` link

The two fields must render at **identical width**. The password field's reveal button sits
*inside* the field, not beside or below it.

## Requirements

- **One composition that works from 360px to 1920px.** Do not design a wide layout with a
  separate narrow fallback — most users are on phones, and a desktop-only brand panel is exactly
  what failed before.
- **Server-rendered, no JavaScript framework.** Every effect must be plain CSS: gradients,
  shadows, borders, transforms, `backdrop-filter`. No canvas, no WebGL, no Lottie.
- **Total page weight matters.** One 32 KB font file is already budgeted. Any imagery must be
  CSS or inline SVG — no photographs.
- Press feedback on the button: scale to 0.97 over ~160ms.

## Explicitly avoid

- Purple or violet anywhere — not in the palette
- AI-style mesh or aurora gradient backgrounds
- A generic centred card floating on a dark blurred blob
- Glassmorphism as decoration rather than as hierarchy
- Stock photography of smiling students
- Any text set on the gradient
- Fake statistics ("10,000 learners"), invented testimonials, or trust badges
- Em-dashes in any visible copy

## Deliver

**Three genuinely distinct directions**, each as two artboards: **390 × 844** (phone) and
**1440 × 900** (desktop). Distinct means different compositional ideas, not three colourways of
one idea.

For each direction give a one-sentence rationale, the hex values used, and the contrast ratio of
the primary button.

Lean **simple and confident**. Restraint reads as competence here; ornament reads as a startup
that will not be around next year.
