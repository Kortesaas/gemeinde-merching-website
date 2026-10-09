# Public website and editorial visual system

The final visual phase translates the approved public and CMS references into the
existing Laravel/Blade application. It introduces no SPA, prototype runtime,
content migration or deployment. Domain rules, publication checks, permissions,
proposals, history and the goneo release pipeline remain authoritative.

## Reference review

Both ignored HTML exports in `designsystem-inspiration/` were examined offline.
Their nested page bundles were extracted and all 13 public and 10 CMS reference
screens were rendered with external network access blocked. The reference runtime
was used only for inspection, never copied into the app.

Implementation screens are reviewed against those renders with the development
demo content (see [local-development.md](local-development.md#development-demo-content-local-only))
at 320, 390, 768, 1024 and 1440 px, public and CMS. Screenshots are review
evidence, not pixel snapshots in CI; the browser suite checks reflow and axe.

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
| Widths | Outer 80 rem, reading 46 rem, CMS sidebar 15.5 rem, aside 19 rem |
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
| Homepage | Search, live alert, managed service-menu shortcuts, current articles, upcoming events, published online resources, optional greeting (quote, name, role, link to a Grußwort page, optional image — all from Website-Einstellungen), settings-based contacts; empty sections disappear |
| Search form/results | Ordinary GET `/suche`, server snippets/type labels, type filter, pagination and helpful empty/no-results text |
| Catalog | Public visibility before filtering/pagination, categories, title filter, online filter, archive switch, life situations |
| A–Z | Native letter anchors, folded German umlauts, service/contact metadata; all matching letters remain available |
| Content | Generic reading column; optional contact/online aside; real fields and controlled blocks; update date and contextual feedback |
| Service detail | Requirements, items, fees, duration, notice, online state, offices/people/locations, downloads and related services |
| Article/event/notice | Dates, optional teaser/media, category, blocks, attachments, links and contacts; cancellation explicitly says “Abgesagt” |
| Documents | Type, size, description, date/year, neutral accessibility state, available alternative and public replacement |
| Vereine | `/vereine` lists active organisations grouped by category with contact data and website links; opt-in detail pages |
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

## Header, menus, search and progressive enhancement

The wide header is one row: Wappen and name, the main navigation, and an
icon-only search button (accessible name “Suchen”). There is no separate utility
row; “Kontakt” lives in the narrow-screen menu, the footer and the contact
sections. Between 64 and 80 rem the tagline yields and labels tighten so the
navigation stays on one line down to 1024 px; below 64 rem the dedicated narrow
layout (search icon + “Menü”) takes over.

Every top-level label is a normal link to its overview page. Sections with
children add a separate chevron toggle (a native `details`/`summary`, at least
24 px wide) that opens the expanded panel without leaving the page. With a mouse
on wide screens the panel also opens after a short hover pause over the entry and
closes on leaving; a click on the toggle right after a hover-open keeps it open.
Keyboard and touch use the toggle (Enter/Space, tap); Escape closes the panel and
returns focus to the toggle; focus or clicks outside the entry close it. Nothing is
reachable only by hover. The current section is marked by a small dot under its
label plus a visually hidden “(aktueller Bereich)”; the exact current page uses
`aria-current="page"`. Hovered or open entries get a soft pill background.

On narrow screens each label row links to the overview and ends in a +/− toggle
for its children. Without JavaScript the narrow menu is rendered open in the page
flow instead of as an overlay, so it never covers content.

The breadcrumb trail follows the first menu entry that points to the current
page. Each page should therefore appear once in the main menu; the demo gives
“Vereine” its own listing (`/vereine`, organisations grouped by category) instead
of reusing the directory page.

The search link has a real GET destination. Where supported, JavaScript opens a
native modal `dialog` (full-screen panel with brand bar), focuses the labelled
input and returns focus on close; “Alle Ergebnisse anzeigen” follows the typed
phrase. Suggestions use the existing `SiteSearch` service through
`/suche/vorschlaege`: debounced, abortable, same-origin, credential-free and
rate-limited; the typed text is emphasised with DOM text nodes only. On the
page (homepage, Bürgerservice, results) the list floats as a dropdown over the
content, so nothing below moves; it closes on Escape, an outside click or when
focus leaves the form. Inside the full-screen overlay it stays in the panel flow. The combobox
uses `aria-activedescendant`; arrows select, Enter opens or searches, Escape
clears suggestions first. Results show type tabs with counts, the managed synonym
hint and highlighted matches (escaped server-side before marking).

Site alerts render as a band below the header (warning/info on the homepage,
critical on every page). With JavaScript each band has a labelled close button
that hides it for the current page view; nothing is stored, so anonymous visits
stay free of cookies and browser storage.

Contact and feedback reuse existing CSRF, nonce, rate limiting, routing and
privacy behavior. Labels, required indicators, summary focus and field errors
remain server-rendered. JavaScript preserves valid contact details only in page
memory after server errors; it clears the message and stores nothing. The native
no-JavaScript POST remains usable. Recipients stay private.

## Motion

`resources/css/public/motion.css` contains all public motion and applies only
under `prefers-reduced-motion: no-preference` (the base stylesheet additionally
neutralises durations for reduced motion). Durations are 150–450 ms with an
ease-out curve: colour/border transitions on links, buttons, chips and inputs;
the expanded navigation panel, narrow-screen menu, search overlay, suggestion
list and accordions fade/slide in by a few pixels; row arrows nudge on hover and
focus; card and gallery images zoom by 3 % inside clipped frames; event date
badges tint on hover/focus. The always-open desktop navigation is never animated
on page load. The CMS uses the same restraint (section chevrons, panel reveals,
account menu, tile/card hover lift). Browser tests wait for running animations to
finish before axe scans, so contrast is checked in the settled state.


## CMS components and editorial workflows

`layouts.admin` has a sticky white sidebar (Wappen, “Redaktion”, permission-filtered
groups, open-review count) and a topbar with content search (to “Alle Inhalte”),
“Website ansehen” and an account menu (initials, name, role, account security,
logout). Narrow layouts use a native navigation disclosure instead of compressing
the sidebar. All CMS colours, badges and panels come from the shared tokens.

The dashboard shows four real summary tiles (open reviews, scheduled publications,
items expiring within 14 days, documents with unchecked accessibility), the review
queue with “Prüfen” actions, scheduled/expiring items, the next events, quality
issues (errors from automatic checks on recent drafts, missing image alternatives,
unchecked documents, cancelled events) and recent editorial work. Queries are
bounded and limited to permitted types; create actions appear only with permission.

Lists share one pattern: page header with eyebrow and primary action, a filter
card, a result count, status badges (text plus colour) and a table with captions
and scoped headers inside a labelled, focusable scroll region. Below 40 rem list
tables stack into labelled rows (explicit table roles keep semantics). Paginated
lists auto-load the relations their columns read. The media list adds a thumbnail
grid with alternative-text state.

Editors: a sticky bar (back to list, status badge, last change, “Ansehen”,
“Versionen”, “Speichern”), section tabs and card sections in a stable order —
Datei, Inhalt, Medien, Inhaltsbausteine, Beziehungen, SEO — with an aside for
Qualität, Veröffentlichung, URL and the change note plus a second save button.
Downloads & Links, Änderungsvorschläge, usage and a danger zone (recycle bin,
restore, permanent deletion with confirmation) follow. SEO starts collapsed unless
it contains errors. The form element wraps exactly the editable fields (it was
previously malformed, which dropped the multipart encoding for uploads; covered by
`EditorMarkupTest`). Relation checklists are selectable cards with an order field
and, for media, thumbnails.

The block editor renders each row as a numbered card with its type as title,
icon buttons “Nach oben”, “Nach unten”, “Entfernen” (full text as accessible
name and tooltip), a live status message and an “add block” bar. Without
JavaScript, position numbers and removal checkboxes remain usable.

Quality distinguishes “Fehler”, “Warnung” and “Empfehlung” by icon and word,
explains whether publication is blocked and links to fields. Proposal review shows
facts, a conflict notice, and per field the start value, the proposal and any newer
live value side by side, then the decision panel (approve with explicit conflict
confirmation; reject with a required reason). History is a timeline of versions
with author, date, note and a confirmed restore that creates a new version.


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

- The header has no utility row (“Leichte Sprache”/“Gebärdensprache” links in the
  reference point to content that does not exist yet); add them as footer or menu
  entries once the pages exist.
- The greeting image is a site-settings media reference, not a person field:
  people still have no portrait. The demo uses a neutral silhouette illustration.
- News listings use image cards (featured first card, designed fallback without
  image) instead of the reference's text list, so editorial images are visible;
  the homepage keeps a compact list with one lead image.

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
