# Final visual verification — 2026-10-09

Implementation and reference adaptations: [visual-system.md](visual-system.md).
No real content was imported and no release was activated.

| Check | Result |
|---|---|
| Complete PHPUnit / MySQL | 412 tests, 2,309 assertions passed |
| Laravel Pint | Passed, 334 files |
| Larastan level 8 | Passed, no errors |
| Composer audit | No vulnerability advisories |
| npm audit, moderate threshold | No vulnerabilities |
| Production Vite build | Passed |
| Config / route / view caches | Built successfully; cached public requests returned 200 without cookies; development caches then cleared |
| Browser / axe | Full 40-test Chromium suite; public/CMS journeys, keyboard, no-JS, reflow, contact, privacy, forced colors and reduced motion |
| Whitespace check | `git diff --check` passed |

The final browser result is checked before handoff. Disposable local fixture
creation is opt-in, refuses non-local environments, and removes its records,
private files and temporary session after the suite. Local contact rate-limit
cache was reset between repeated browser runs; application limits were retained.
Existing tests were retained. Obsolete placeholder labels and assertions rejecting
any script/image anywhere were scoped to their original safety purpose: no inline
scripts, no executable editorial markup, and no editorial Markdown images.

Commands:

```sh
docker compose exec -T app composer check
npm audit --audit-level=moderate
npm run build
PARITY_BROWSER_FIXTURES=docker npx playwright test --workers=2
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan view:cache
scripts/release/build.sh
```

The release script runs from the clean committed tree, installs production PHP
dependencies, checks the platform, builds assets in Linux, strips development
files and produces a local tarball plus SHA-256 sidecar in `build/releases/`.
It does not deploy. Its resulting artifact is the final packaging evidence.

## Visual and accessibility evidence

46 local screenshots are retained in ignored `build/visual-review/2026-10-09/`.
Public: home, navigation, search panel/results, service landing/A–Z/detail,
article/event, documents/notices, directory, contact and 404 at 390/1440 px.
CMS: dashboard, overview, article/event/service editors, media, proposal, history
and expanded blocks at 768/1440 px. Reference sources were inspected offline;
major layouts, type, whitespace and hierarchy were compared manually.

Reflow checks cover 320/390/768/1024/1440 px, including synthetic long titles,
compound words, long filenames, seven contacts/downloads, multiple fee rows,
missing images and expired notices. Tables use controlled, named scroll regions.

Calculated sRGB contrast ratios:

| Pair | Ratio |
|---|---|
| Main text / white | 16.40:1 |
| Secondary text / white | 8.21:1 |
| Action/link blue / white | 6.32:1 |
| White / dark footer | 13.70:1 |
| Dark text / identity sky | 8.91:1 |
| Control border / white | 5.02:1 |

Automated axe scans are supplemented by keyboard/focus, local visual/reflow and
contrast review. They do not certify accessibility. Native screen-reader,
text-spacing/browser-zoom, real-content/PDF and formal accessibility review
remain launch gates in [accessibility.md](accessibility.md).

## Asset budget

The build contains one 58.26 kB self-hosted variable font, the supplied 17.02 kB
Wappen raster, approximately 23 kB public CSS / 14 kB CMS CSS and 7.94 kB JavaScript
(2.91 kB gzip). No external runtime or always-running Node service is introduced.
Public images use authorized in-memory responsive variants and lazy loading.
Real-world resize CPU/memory should be measured on the target hosting package.

## Next-phase gates

Before controlled migration, agree the real managed navigation/footer tree,
collection-page addresses and verified structured settings. No code blocker is
known from these checks. Migration and deployment require a separate instruction.

Before production launch: obtain and verify the official Wappen SVG/EPS source,
replace the documented prototype raster, supply approved legal/privacy and
accessibility content/links, verify municipal contact/opening-hours facts and
complete the manual accessibility and production operational checks. No mockup
facts or legal text were invented to conceal missing content.
