# Visual verification — 2026-10-09

Implementation and reference adaptations: [visual-system.md](visual-system.md).
No real content was imported, nothing was deployed and nothing was pushed.

## Display preferences verification — 2026-10-09

The optional [display preferences](display-preferences.md) use a fixed bottom-right
launcher and compact, anchored overlay with no screen dimming. The final public
templates and shared default tokens were checked with:

| Check | Result |
|---|---|
| `docker compose exec -T app composer check` | Pint: 347 files; PHPStan level 8: no errors; PHPUnit: 427 tests / 2,577 assertions; Composer audit: no advisories |
| `npm run audit` | 0 vulnerabilities |
| `npm run build` | Passed; public CSS 83.73 kB / 15.39 kB gzip; shared JS 20.78 kB / 7.15 kB gzip |
| `PARITY_BROWSER_FIXTURES=docker npm test` | All 61 tests passed, including all optional public-content/CMS fixtures and 20 new display tests |
| Display behavior | Keyboard open/close/focus return, outside-click dismissal, fixed icon and compact overlay at mobile/desktop widths, 44 px targets, 150% text reflow at 320/390/768/1024/1440 px, all 24 palette combinations on home/contact/events, reduced motion, forced colours and no JavaScript; image slots retain exact geometry and overlaid descriptions can be keyboard-scrolled |
| Privacy / speech | No public browsing cookies or default storage writes, no added external requests, no CSP violations; explicit local display choices persist/sync across pages/tabs, reset deletes them, denied/corrupt storage fails gracefully. Deterministic speech mocks test local-only selection, absent/delayed/remote voices, synthesis failure, chunking, omitted forms/closed details, stopping/reset/page departure |

Desktop and mobile light/dark screenshots were visually reviewed. The local
Chromium browser exposed installed German voices marked local. Speech behavior
was tested with API doubles, not an audible native-engine verification.
VoiceOver/NVDA, Safari/iOS, browser zoom/text spacing and native audible speech
remain manual platform checks; no formal WCAG conformance is claimed by these
automated checks.

The following results record the earlier visual-system phase.

| Check | Result |
|---|---|
| PHPUnit (MySQL) | 422 tests, 2,539 assertions passed |
| Laravel Pint | Passed, 343 files |
| Larastan level 8 | No errors (including `database/seeders`) |
| Composer audit | No advisories |
| npm audit (moderate) | 0 vulnerabilities |
| Production Vite build | Passed — app CSS 65.4 kB (11.7 kB gzip), CMS CSS 35.7 kB (7.3 kB gzip), JS 10.5 kB (3.7 kB gzip), font 58 kB |
| Config / route / view cache | Built; cached public requests returned 200 with no `Set-Cookie`; caches cleared afterwards |
| Playwright + axe (Chromium) | 40 of 40 passed with `PARITY_BROWSER_FIXTURES=docker` (keyboard, no-JS, reflow 320–1440, forced colours, reduced motion, privacy, CMS) |
| Fresh database | `migrate:fresh --seed` → `DevelopmentDemoSeeder` succeeded; also covered by `DevelopmentDemoContentTest` |
| Demo admin | Password + documented TOTP reach the dashboard locally; refused in production (tests) |

## Commands

```sh
docker compose exec -T app composer check
npm audit --audit-level=moderate
npm run build
docker compose exec -T app php artisan migrate:fresh --seed
docker compose exec -T app php artisan db:seed --class=DevelopmentDemoSeeder
docker compose exec -T app php artisan cache:clear   # resets local contact rate limits
PARITY_BROWSER_FIXTURES=docker npx playwright test --workers=2
```

## Manual visual review

Public pages and CMS screens were rendered with the demo content and compared
with the reference renders at 320, 390, 768, 1024 and 1440 px: homepage (with and
without the dismissed alert), expanded navigation (hover and keyboard), narrow
menu, search overlay with suggestions, results, Bürgerservice landing, A–Z,
service detail, life situation, news grid and detail, events, notices, documents,
directory, council, gallery, contact and error pages; CMS dashboard, lists,
article/service editors, proposal review, history and media. An automated sweep
found no page-level horizontal overflow and no non-200 responses on these routes.

Issues found and fixed during this review include: lazy-loading crashes (life
situations on the Bürgerservice page, person search results, images placed in
two galleries, CMS lists with category columns), a malformed editor `<form>` tag
that dropped the upload encoding, a broken image block template, the overlay menu
covering content without JavaScript, desktop navigation fading in on every page
load, and visually hidden table labels widening phone layouts.

Axe scans run after short UI animations settle. Automated checks do not replace
the screen-reader, zoom/text-spacing and content/PDF reviews listed in
[accessibility.md](accessibility.md).

## Before launch

Replace the prototype Wappen with the verified official source, supply approved
legal, privacy and accessibility pages and links, verify all municipal facts
during the separate migration, and benchmark image derivation on the goneo
package. Never run the demo seeder on staging or production; `deploy:check`
fails if demo accounts exist.
