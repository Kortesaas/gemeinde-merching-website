# Accessibility (Barrierefreiheit)

**Target:** WCAG 2.2 level AA, which covers the web requirements of
EN 301 549 and BITV 2.0 that apply to public bodies in Bavaria
(BayBGG / BayBITV). Accessibility is a first-class requirement and is built in
from the start, not added at the end.

> Automated tests find only a part of all accessibility barriers. Passing them
> does **not** prove that the website is accessible.

## Already implemented in the foundation

Base layout (`resources/views/layouts/base.blade.php`) and backend pages:

- `<html lang="de">`; foreign-language phrases marked (`lang="en"`).
- Meaningful, unique page titles (`Seite – Bereich – Gemeinde Merching`),
  prefixed with „Fehler:“ when a form has validation errors.
- Landmarks: `header`, `nav` (with `aria-label`), `main`, `footer`; exactly one
  `h1` per page and a logical heading hierarchy.
- Skip link „Zum Inhalt springen“ as first focusable element, moves focus to
  `main`.
- Viewport without zoom restrictions (`user-scalable`/`maximum-scale` never
  used); rem-based, fluid layout that reflows at 320 CSS px (400 % zoom).
- Clearly visible keyboard focus (`:focus-visible`, 3 px outline plus contrast
  halo), never removed.
- Only native elements: links for navigation, `<button>` for actions (logout
  is a form button, not a link).
- Forms (`components/form/field.blade.php`, `error-summary.blade.php`):
  - always-visible `<label>` connected via `for`/`id`; no placeholder-only
    labels (no placeholders at all);
  - hints and errors connected via `aria-describedby`, `aria-invalid="true"`
    on errors;
  - error text prefixed with „Fehler:“ and a thick border – not colour alone;
  - error summary at the top with links to the fields; it receives focus on
    page load (`autofocus`, no JavaScript);
  - `novalidate` so the server's accessible messages are used consistently
    instead of browser bubbles; `required` is still announced.
- Login/MFA: `autocomplete="username"`, `current-password`, `new-password`,
  `one-time-code`; `inputmode="numeric"` for TOTP; pasting and password
  managers are never blocked; no time pressure besides the 5-minute challenge
  window (restart is possible at any time).
- QR code has a text alternative and the setup key is also shown as text.
- Status messages use `role="status"`.
- Final shared tokens use dark text on sky-blue surfaces, contrasting action blue,
  a dark-blue footer and visible black/yellow focus. Target sizes ≥ 44 px for
  important controls; current contrast measurements are in [visual-verification.md](visual-verification.md).
- `prefers-reduced-motion` respected.
- Core navigation, reading, search and forms work without JavaScript; menus,
  suggestions and editorial controls are progressively enhanced.

## Backend (functional CMS screens)

The generic admin forms reuse the accessible components: visible labels with
„(Pflichtfeld)“, hints/errors via `aria-describedby`, checkbox groups as
`fieldset`/`legend` with labelled order inputs, error summary with focus,
`datetime-local` inputs labelled with the site time zone, tables with
captions and `scope`, state shown as text. During phase 2, 13 admin screens
(dashboard, lists, create/edit forms incl. placements, revisions, users) and
the error state were checked with axe in a real browser session: no
detectable violations. Keyboard and screen-reader testing of the final CMS
design is still required.

## Automated checks

```bash
npm test
```

Runs Playwright + axe-core (`tests/Browser/accessibility.spec.js`) against the
running application (default `http://localhost:8088`, override with
`BASE_URL`). Checks: axe rules for WCAG 2.0/2.1/2.2 A+AA and best practices on
the public homepage, admin login (incl. error state), password-reset page
and 404 page; skip link and focus order; error summary focus; reflow at 320 px.
PHPUnit additionally asserts the login markup (labels, autocomplete, no
placeholders, no inline scripts).

Contact form and confirmation, error focus/labels, 320 px reflow and
same-origin requests are also covered. Media alt state and the reusable
quality framework are described in [site-foundation.md](site-foundation.md).
Every new page/component must be added to these tests.

## Manual testing (required before go-live and for every new template)

- **Keyboard only:** every function reachable and operable with Tab/Shift+Tab,
  Enter, Space, arrow keys where expected; visible focus; no keyboard traps;
  logical order.
- **Zoom and reflow:** 200 % and 400 % browser zoom, 320 px width, text-only
  zoom, text spacing (WCAG 1.4.12 bookmarklet).
- **Screen readers:** NVDA + Firefox and NVDA + Chrome (Windows),
  VoiceOver + Safari (macOS and iOS), TalkBack + Chrome (Android) –
  headings/landmark navigation, forms, error handling, status messages.
- **Contrast** of the final design tokens (also focus, hover, disabled states,
  text on images).
- **Content:** alternative texts, link texts, plain language, PDFs (tagged,
  PDF/UA) – editors need guidance in the CMS.
- **Formal review:** a BITV/WCAG test by an independent tester before launch.

## Legal follow-ups (later phases)

- „Erklärung zur Barrierefreiheit“ (accessibility statement) and a feedback
  mechanism are mandatory for public-sector websites; link them from every
  page.
- Check whether additional content in Leichte Sprache / Deutsche
  Gebärdensprache is required or desired.

## Controlled components

Block headings are restricted to H2–H4 after the page H1; skipped levels are
publishing errors. Images use central reviewed alternatives, with justified
gallery context overrides. Native details/summary implements accordions.
Row editors provide labelled fields, numeric keyboard ordering and explicit
removal; adding/reordering works without JavaScript. Fee tables have captions
and scoped headers. Unchecked documents still warn rather than falsely claiming
accessibility. Location notes appear only when entered, without inferred claims.
See [content-parity.md](content-parity.md) and the optional local Docker browser
checks described in [local-development.md](local-development.md).

## Final visual phase

The final suite also covers public home, navigation, suggestions/results, service
landing/A–Z/detail, article/event, document/notice/directory, contact and error
views; authenticated dashboard, overview/lists, article/service/event/page
editors, media, quality, proposals and history. Every editor disclosure is expanded
for the axe scan. Reflow is checked at 320/390/768/1024/1440 CSS px. Keyboard tests
include modal focus return, combobox selection, Escape at successive disclosure
levels, native no-JavaScript navigation/search, block add/up/down/remove and
form error focus. Forced-colors/reduced-motion emulation also passes.

Local screenshots and contrast/reflow were reviewed. This does not replace the
manual assistive-technology, text-spacing/zoom, real-content/PDF and formal checks
listed above. No screen-reader certification is claimed. See
[visual-system.md](visual-system.md) and [visual-verification.md](visual-verification.md).
