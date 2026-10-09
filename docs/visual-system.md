# Public website and editorial visual system

The final visual phase translates the approved public and CMS references into the
existing Laravel/Blade application. It introduces no SPA, prototype runtime,
content migration or deployment. Domain rules, publication checks, permissions,
proposals, history and the goneo release pipeline remain authoritative.

## Reference review

Both ignored HTML exports in `designsystem-inspiration/` were examined offline.
Their nested source and assets were inspected, and all 13 public and 10 CMS
reference screens were rendered locally with external network access blocked.
The reference runtime was used only for inspection, never copied into the app.

The review covers public desktop/mobile home, navigation, search panel,
suggestions, results, header/footer, service landing, service detail and A–Z;
and CMS dashboard, overview/navigation, article/event/service editors,
documents, people/departments, media and publication quality.

Local implementation screenshots are in `build/visual-review/2026-10-09/`
(ignored): 46 captures, public screens at 390/1440 px and CMS screens at
768/1440 px. They contain disposable synthetic fixtures, not verified official
information. Fixture records, private files and temporary login sessions are
removed after capture. Screenshots are review evidence, not pixel snapshots in CI.

## Identity, typography and tokens

`resources/css/tokens.css` is shared by public and CMS entry points.

| Role | Token / value |
|---|---|
| Main text | `--color-text`, `#16202e` |
| Secondary text | `--color-text-muted`, `#4b4f58` |
| Identity surface | `--color-sky`, `#94c3f7`, dark text |
| Link/action | `--color-link`, `#0b5cc2`; hover `#0a4a9c` |
| Footer | `--color-dark`, `#0f2d57`, white text |
| Focus | 3 px black outline, yellow contrast halo; OS colors when forced |
| Background/panels | White / `#f4f7fb` |
| Borders | `#66707e` on controls; `#d6deea` decorative rules |
| Type | Atkinson Hyperlegible, public base 1.125 rem, line height 1.6 |
| Spacing | Named 0.25–4 rem steps; section spacing fluid 3–6 rem |
| Widths | Outer 76 rem, reading 46 rem, sidebar 15 rem, aside 19 rem |
| Corners/shadow | 0.5/1 rem corners; one restrained panel shadow |
| Breakpoints | 40, 64 and 80 rem |

Native CSS cannot interpolate custom properties into media conditions, so the
three named breakpoint values are repeated in those conditions. Component
geometry belongs in CSS, not inline Blade styles. Gold is confined to the focus
halo; it is not a dominant surface or action color.

The supplied variable **Atkinson Hyperlegible Next Latin** asset is self-hosted
as `resources/fonts/atkinson-hyperlegible-next-latin-variable.woff` (58,268 bytes,
weights 200–800). CSS uses the family alias “Atkinson Hyperlegible” and a robust
Segoe UI/system/Arial fallback with `font-display: swap`. The unused Mono and
repeated prototype font assets are not shipped. The original font name metadata
is retained. The SIL Open Font License is included in `resources/fonts/OFL.txt`;
its source is the [official font repository](https://github.com/google/fonts/blob/main/ofl/atkinsonhyperlegiblenext/OFL.txt).
There are no Google Fonts or CDN requests.

**Wappen launch gate:** the only supplied coat of arms is the prototype raster
recreation. `resources/images/wappen-merching-prototype.png` is used unchanged,
with empty alt beside the written municipality name. Obtain and verify the
municipality’s official SVG/EPS source before launch. Do not redraw or assume
this raster is an authoritative heraldic master. `public.wappen` selects the
asset; import it through Vite when replacing it so the manifest resolves it.
The header has a fixed presentation box and requires no redesign for the swap.

## Public components and content

`layouts.public` composes the shared document, skip link, brand, managed menu,
search entry, visible-navigation breadcrumb trail, main and footer. Main and
footer navigation come exclusively from `NavigationManager` / `NavigationItem`.
Moving a menu entry affects its trail, not its canonical content URL.

Configure footer entries for the actual legal, privacy and accessibility pages
when verified content is available. Do not ship fabricated legal pages or use
mockup destinations. Until these records are provided, their links are absent.
The footer and homepage resolve Rathaus/address/opening hours and central
contacts through the same `SiteSettings` relationships. Missing information is
omitted; no second copy of municipal facts is maintained in templates.

`config/public.php` controls listing endpoints and homepage section switches.
These are presentation configuration, not a second navigation tree. Listing
paths are fallbacks: managed content routes and legacy redirects take precedence.
A managed Page at a configured listing path contributes its title, body and
blocks. Case/slash variants preserve the query and redirect to the canonical
listing path; origin normalization resolves the same final address. Content
paths stay slashless, with production origin `https://www.gemeinde-merching.de`.

| Component | Behavior |
|---|---|
| Homepage | Search, live alert, managed service-menu shortcuts, current articles, upcoming events, published online resources, settings-based contacts; empty sections disappear |
| Search form/results | Ordinary GET `/suche`, server snippets/type labels, type filter, pagination and helpful empty/no-results text |
| Catalog | Public visibility before filtering/pagination, categories, title filter, online filter, archive switch, life situations |
| A–Z | Native letter anchors, folded German umlauts, service/contact metadata; all matching letters remain available |
| Content | Generic reading column; optional contact/online aside; real fields and controlled blocks; update date and contextual feedback |
| Service detail | Requirements, items, fees, duration, notice, online state, offices/people/locations, downloads and related services |
| Article/event/notice | Dates, optional teaser/media, category, blocks, attachments, links and contacts; cancellation explicitly says “Abgesagt” |
| Documents | Type, size, description, date/year, neutral accessibility state, available alternative and public replacement |
| Directory | Active departments, employee contacts, organizations, locations and public council terms; opt-in detail routes only, no employee portraits |
| Errors | Database-independent branded 403/404/410/429/maintenance shells and existing safe status handling |

Past reachable events appear in the archive even when automatic expiry is off.
Expired notices/documents retain the existing historical-public rules. A public
replacement moves an older document into the archive; a draft/private or missing
replacement does not reveal its existence or change public listings. Original
public document addresses remain downloadable. Article retention still follows
its existing model; the UI does not make private expired articles public.

Fee columns with no data are omitted. Real fee tables retain scoped headers and
a caption in a named, keyboard-focusable scroll region, with amounts kept together.
Long titles, compound words, long filenames, seven contacts/downloads, multiple
fees, missing images, archived notices and empty collections are exercised by
the synthetic checks.

The existing SEO metadata supplies title, description, canonical, robots and
Open Graph values. Existing structured-data hooks and sitemap generation remain
unchanged. Search results/suggestions stay noindex; staging indexing restrictions
continue to override templates.

## Menus, search and progressive enhancement

Native `details`/`summary` controls support desktop expanded menus and a separate
narrow-screen menu. With JavaScript, the narrow menu begins collapsed; desktop
branches close their peers. Escape closes the nearest open branch, then the
mobile menu, returning focus to the corresponding summary. There is no hover-only
navigation or menu-role imitation.

The search link has a real GET destination. Where supported, JavaScript opens a
native modal `dialog`, focuses the labelled search input and returns focus on
close. Narrow screens use the full-height panel. Native dialog behavior provides
focus containment and Escape.

Suggestions use the existing `SiteSearch` service through `/suche/vorschlaege`,
not a copied browser dataset. Requests are debounced, abortable, same-origin,
credential-free, rate-limited and never logged as search statistics. Service-only
entry points retain their type filter. The combobox/listbox uses input focus and
`aria-activedescendant`; arrows select, Enter follows a selected suggestion or
submits normal GET search, and Escape clears suggestions first. Text is inserted
with `textContent`. Publication is checked on every backend result.

Managed synonyms continue to work through the real backend. No fictional typo
engine or mockup “did you mean” claim is introduced. Full-result statistics are
recorded only when existing configuration explicitly enables them.

Contact and feedback reuse existing CSRF, nonce, rate limiting, routing and
privacy behavior. Labels, required indicators, summary focus and field errors
remain server-rendered. JavaScript preserves valid contact details only in page
memory after server errors; it clears the message and stores nothing in browser
storage or flashed session input. The native no-JavaScript POST remains usable
and retains the stricter existing input-clearing behavior. Network failure gives
an inline alert and enables retry. Recipients stay private.

## CMS components and editorial workflows

`layouts.admin` uses a 15 rem sidebar, permission-filtered resource groups, account
and logout controls, a light main surface and white panels. Narrow layouts use a
native navigation disclosure rather than compressing the sidebar.

Dashboard panels show real permitted review requests, own proposals, recently
changed records, scheduled/expiring content, unchecked documents, missing media
alternatives and upcoming cancelled events. Queries are bounded; review types
are selected by publication rights before loading, and policy checks still run.
The additive `100900` migration indexes editorial ordering and quality queries.
No decorative counts imply completeness.

The cross-entity overview is deliberately bounded to the latest 50 matching
records per permitted type, with an explicit explanation and links to the full
per-entity lists. Native GET search/type filters and pagination remain available.
Entity lists retain publication filters, recycle bin and existing authorized
actions. Data tables retain captions/headers and named controlled scroll regions;
hidden labels stay inside that region so they do not cause viewport overflow.

`EditorSections` groups the real field definitions into content, relationships,
media/accessibility, search-engine fields and blocks. No database fields are
silently removed. Publication/URL/revision note use a separate column at 80 rem.
Optional groups collapse progressively with JavaScript, except validation errors;
without JavaScript all groups begin open and remain native disclosures. Editor
and quality anchors open enclosing groups. Uploads and document/link placements
retain their server forms and resource authorization.

Row controls add allowed block types, reorder with up/down buttons while retaining
focus, and mark removal after confirmation. A live message explains the action.
The selected block type shows relevant controls and clears incompatible reference
IDs/heading levels. Three blank slots are provided by the backend; saving supplies
more. Without JavaScript, numeric position and labelled removal checkboxes remain
usable. This is a controlled editor, without colors/fonts/CSS/layout choices.

The central media screen has a thumbnail grid plus a full metadata table, upload
and edit links, alt/decorative state, dimensions/size, copyright/source, focal point,
where-used and existing safe deletion feedback. It adds no portraits.

Quality appears at the top of existing editors. “Fehler”, “Warnung” and
“Empfehlung” are textual, publication blocking is explained, and links identify
fields or placements. These are the existing automated checks, not a claim that
PDFs, prose, every link or every accessibility requirement have been verified.

Proposal screens distinguish the published record, starting version, proposed
field/relation/block differences and any newer live conflicts. Grouped proposal
editing, submit/withdraw, review, required rejection reason and approval retain
all existing policy and self-approval rules. History shows dates, editors, notes,
readable collection snapshots and an explicit restore disclosure; progressive
confirmation protects the action. Restore still creates a new revision.

## Media delivery and performance

Images use central Media records and original private files. Public variants at
480/960/1440 px are derived in memory as WebP after the same live-owner and alt
checks as original delivery. Admin thumbnails reuse this derivation after the
existing authenticated media policy. Invalid widths are rejected. Originals and
hashes are untouched; no duplicate stored files are created and no public symlink
bypasses authorization.

`srcset` descriptors reflect actual widths, avoiding fake upscaled candidates for
small originals. `sizes`, intrinsic dimensions, lazy loading and async decoding
limit payload and shifts. Gallery crops use central focal points rounded to 1%
CSS classes to comply with the existing strict CSP. Captions, contextual alt,
decorative state and copyright remain editorial metadata. Native image links
open the original; a lightbox is deliberately omitted.

Derived responses are not stored or given a long-lived cache: reachability is
rechecked. Benchmark CPU/memory with realistic image sizes on goneo before launch;
the existing upload pixel limit remains authoritative. There is no production
Node process. Current asset measurements are recorded in the verification report.
Ordinary public views have no cookies, browser storage, analytics, trackers or
third-party requests. External maps/resources remain explicit links.

## Responsive and accessibility review

At 40 rem public grids stack and search becomes a narrow-screen panel. At 64 rem
public contact asides and CMS navigation change composition. At 80 rem CMS editors
gain the publication column. Fluid rem typography, zero minimum grid widths,
wrapping titles and controlled tables preserve reflow.

Browser checks cover 320, 390, 768, 1024 and 1440 CSS px. Keyboard checks exercise
skip links, disclosures, search focus/selection/Escape, no-JavaScript navigation
and search, row ordering/add/remove, form error focus and restore disclosure.
Axe checks cover public journeys and authenticated CMS, including expanded editor
groups. Reduced-motion and forced-colors emulation are checked; no custom contrast
mode is provided. Contrast, reflow and local screenshots were reviewed manually.

These checks do not constitute a formal WCAG/BITV assessment. Native screen-reader
checks with VoiceOver/Safari and NVDA/Firefox/Chrome, mobile assistive technology,
text-spacing/zoom tests and content/PDF accessibility review remain required before
launch, as described in `docs/accessibility.md`.

## Intentional adaptations from the mockups

- Dark-blue footer follows the approved written identity direction instead of the
  reference’s light-gray footer.
- Navigation labels, legal destinations, contacts, content, shortcut counts and
  homepage sections follow real records/configuration; mockup sample facts and
  fabricated pages are not imported.
- Explicit labels/native GET filters and at least 44 px important controls replace
  small tabs, icon-only controls or unlabelled prototype inputs.
- A–Z uses native letter anchors and readable rows; categories are genuine records.
- The event presentation is a chronological list/archive, not a new recurrence or
  calendar engine. Existing all-day/time-zone rules remain authoritative.
- Quality is integrated in each editor rather than a fake completed-check screen;
  no unsupported counts, automated PDF certification or link-crawler claims.
- Editors use safe Markdown and controlled blocks, native upload forms and explicit
  save; no rich-text runtime, arbitrary layout choices, autosave or drag-only UI.
- People remain photo-free and opt-in route rules govern directory pages.
- Maps/social/video do not load external content on page load. Gallery interaction
  is native image navigation, avoiding an unnecessary modal system.
- Bounded dashboard/overview queries and the real permissions/resources replace
  prototype sections that assumed a different data model.

## Verification and handoff

See `docs/visual-verification.md` for exact commands, results, screenshots and
release-artifact evidence. Before real-content migration, agree the managed Page
addresses for configurable catalogs and the intended NavigationItem/footer trees.
Migration is a separate controlled task; none is started by this visual phase.
