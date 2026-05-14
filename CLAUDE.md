# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

> **Read [memory.md](memory.md) at the start of every session.** It is a running log of modifications made to this website. After making any change to the site, append a new entry to `memory.md` describing what changed and why.

## Project

Static marketing site for **Samoma Industries** — three hand-written HTML pages styled as a close clone of Zemez's **Grandviz** WordPress theme (financial/corporate, navy + gold, triangle geometric accents). No build tooling, no package manager, no framework.

## Running the site

No build, install, or test commands exist. To view changes:

- Open `index.html` directly in a browser, **or**
- Serve the folder over HTTP, e.g. `python -m http.server 8000` and visit `http://localhost:8000/`.

Google Fonts (Manrope, Playfair Display) and Unsplash photos are pulled from CDNs at runtime — an internet connection is needed for the site to look right.

## Architecture

### Page set
Four sibling HTML files at the root: `index.html`, `about.html`, `services.html`, `login.html`. They link to each other via relative hrefs and share `assets/css/style.css` + `assets/js/main.js`. `login.html` uses a split-screen `.auth-shell` layout (navy aside + cream form panel) instead of the marketing section stack used by the other three.

### No templating — header & footer are duplicated
Each page contains its own copy of `<header class="site-header">…</header>` and `<footer class="site-footer">…</footer>`. **When you change navigation, brand, contact info, or footer content, edit all four HTML files.** The only per-page differences are which `<a>` in `.nav-links` carries the `active` class. The `.nav-cta` block also contains a `.btn.btn-primary.btn-compact` "Client Portal" link to `login.html` which hides below 980px (the mobile menu has the same link as a regular nav item).

### CSS: single stylesheet, design-token driven
`assets/css/style.css` is the entire design system. It is organized top-down: CSS custom properties on `:root` → base/typography → utility classes (`.container`, `.eyebrow`, `.btn*`) → components (`.site-header`, `.hero`, `.service-card`, `.about-preview`, `.stats-band`, `.cta-band`, `.team-card`, `.page-hero`, `.cases-grid`, `.process-grid`, `.values-grid`, `.site-footer`) → `.reveal` animation hook → responsive breakpoints at 980px and 600px.

Conventions:
- All colors, radii, shadows, and transition timings live in `:root` variables. **Don't hardcode colors** — extend the token set if a new shade is needed. `--danger` (`#c0392b`) is used only by `.form-field.has-error` states.
- The signature **triangle motif** (Grandviz callback) is rendered via `clip-path: polygon(50% 0, 100% 100%, 0 100%)` — used on the brand mark, hero floating accent, service icons (diamond variant), badge icon, about-image corner, page-hero decorations, footer accent, CTA band shapes, and `.about-features li::before` bullets. Buttons use a parallelogram clip-path on `.btn-primary`. Keep this consistent when adding decorative elements.
- Component cards use a hover state that swaps to dark navy (`.service-card:hover`) — child elements (`h3`, `.service-icon`, `.service-link`) react to the parent hover via descendant selectors; preserve this pattern.

### JavaScript: one IIFE, no dependencies
`assets/js/main.js` is a single vanilla IIFE that wires up five behaviors. Add new behavior inside the same IIFE rather than introducing modules or libraries:

1. `.site-header` toggles `.scrolled` when `window.scrollY > 30`.
2. `.nav-toggle` toggles `.open` on `.nav-links` and `.nav-toggle` (mobile menu), and locks `body` scroll.
3. `IntersectionObserver` adds `.is-visible` to any element with `.reveal` (threshold 0.12). Mark new sections/cards with `class="reveal"` to get the fade-up entrance for free.
4. Animated counters: any element with `data-count="N"` (optional `data-suffix="%"` or `+`) is animated from 0 → N when scrolled into view. Initial inner text should be `0`.
5. Login form (client-side stub): validates `#login-form` on submit — email regex on `[data-field="email"]` and non-empty check on `[data-field="password"]`. Toggles `.has-error` on the parent `.form-field` to reveal `.form-error` text and red border. On success, disables the submit button, swaps its label to "Signing in…", and redirects to `dashboard.html` after 900ms. No real authentication — `dashboard.html` is a placeholder that does not exist yet.

A `#year` element in each footer is populated with the current year on load.

### Image assets
All photography is referenced as inline `background-image` URLs to Unsplash CDN (in HTML `style="…"` attributes) or in `style.css` (one URL: the about-preview photo on the home page, ~line 327, and the hero image, ~line 245). `assets/images/` exists but is empty — drop local files here and update the URLs when swapping in real Samoma assets.

## Design fidelity

The styling intentionally mirrors Grandviz (Zemez, WordPress/Elementor theme). When extending the design, the visual cues that define it:

- **Palette:** deep navy backgrounds (`--navy-900` `#0a1226`), gold accent (`--gold-500` `#d4a857`), cream surfaces (`--cream-50` `#fbf8f1`). Body text on light is `--ink-700`.
- **Type:** Playfair Display (italic accent words in headings, e.g. `<em>industrial</em>` in the home hero), Manrope for everything else. Eyebrows are uppercase Manrope with a 28×2px gold rule before the text via `.eyebrow::before`.
- **Layout signature:** sections at 110px vertical padding (70px mobile); `.stats-band` overlaps adjacent sections via negative margin + bottom clip-path; `.cta-band` is full-bleed gold with triangle decorations.
