# Gemeinde Merching migration inventory — 10 October 2026

Inventory/analysis only. No WordPress content imported into Laravel; no demo data cleared; no old-site writes, form submissions/login, deployment or push. The only CMS change during this phase was the user-authorized budget model correction: several independent packages per year with **Thema / Art**, including a Nachtrag or Berichtigung. Existing demo records, source PDFs and historical proofs were preserved.

## Evidence and confidence

Primary source is `69246m55851_1.sql`, cross-checked with WXR, the secondary WPvivid DB, all 10 outer ZIPs (12 nested filesystem archives), and the live public site. Original input SHA-256/size manifests are in `source-provenance.csv`; all were rechecked unchanged. Raw SQL/XML, archive copies, PDF preflight copies, HTML and crawler state stay in ignored `migration-source/analysis-cache/`; no raw source data is committed.

**Use `wp_`, not `wpd754ae`.** The dump includes both prefixes. `wp_` has 3,517 post rows and the last modification is 2026-10-09; `wpd754ae` has 3,676 rows and last modification 2025-02-20. The secondary backup has only `wp_`; its editorial posts match the active primary rows. WXR ID/title relationships, current public content and matching home/siteurl also support `wp_`. No `wp-config.php` exists in the supplied filesystem archives, so prefix verification is evidence-based rather than a config-file confirmation. Do not merge both prefixes.

SQL/WXR discrepancies: **85 CRLF/LF representation differences only**, and **2 published Encyclopedia records omitted by WXR**. No substantive active-prefix post title/content/status/modified differences were found against the secondary DB. Postmeta was also compared against both sources; `postmeta-discrepancies.csv` records key-level differences without exporting values. Table counts/operational options can differ with backup time; `tables.csv` records them. WXR contains drafts/pending/private/helper posts and is not a list of public content.

## 1. Content discovered

| Source type / scope | Count |
| --- | --- |
| page | 55 |
| attachment | 579 |
| wpdmpro | 96 |
| post | 66 |
| tablepress_table | 13 |
| link_library_links | 61 |
| encyclopedia | 2 |
| nav_menu_item (published) | 51 |
| NextGEN galleries / pictures / album | 8 / 314 / 1 |
| Connections approved/public | 26 |
| Contact Form 7 definitions | 2 |

The 55 published pages are a subset of 72 total pages (9 draft, 7 pending, 1 private). SQL has 99 news posts (66 published, 29 draft, 2 trash, 2 auto-draft); WXR has 97, omitting the two auto-drafts. Download Manager has 102 packages (96 published, 5 draft, 1 trash). These counts exclude 2,114 revision rows and operational/helper CPTs from public migration totals. Full type/status counts are in `counts.json`, table counts in `tables.csv`, and public/editorial candidates in `content.csv`.

## 2. Public crawl and URL classification

**1872 URL requests recorded**, including 430 HTML responses, 537 PDF responses and 718 images. Counts include aliases, error pages and seeded source URLs, not that many unique editorial pages. Requests were GET-only, two workers, globally paced at 0.6 seconds, following robots.txt. External domains were recorded but never crawled; forms were counted but never submitted. Static styles/scripts and their local CSS assets were included. Download Manager `data-downloadurl` links were resolved with the transient `refresh` cache-buster removed; stable root `?wpdmdl=ID` URLs were checked independently. JavaScript was not executed. HTML/XML/CSS bodies are bounded at 2 MiB; file responses are sampled for status/type, not downloaded wholesale. Final queue: 0; robot exclusions: 0. Request errors/restrictions remain explicit in `urls.csv`.

Classifications: **75 duplicate/alias**, **1337 preserve exactly**, **255 migrate to another canonical URL + 301**, **69 broken/obsolete**, **136 manual review**. Targets are **proposals**, not implemented redirects. Trailing-slash pages usually retain the same slug with one 301 to the slashless canonical path; root stays `/`. WXR numeric CPT permalinks can be 404 while current managed downloads still work. Do not delete a document because a WXR convenience permalink is broken. 409 metadata/backup-only URL candidates have explicit manual-review classifications, without assumed public exposure.

`observed-redirects.csv` records actual hops; `redirect-candidates.csv` proposes alias/canonical actions. `internal-links.csv`, `broken-links.csv`, `source-live-crosscheck.csv` and `orphan-candidates.csv` distinguish linked failures, seeded candidates and content provenance. Stable managed download checks: 96 published package IDs checked; 95 return PDFs. Package 8722 (streetlight-reporting online service) has no nonempty file source and returns HTML; review it as an ExternalResource candidate rather than assuming a missing PDF. `managed-downloads.csv` distinguishes successful delivery from non-PDF/error responses. Protected Download Manager paths return **403**, not “file missing”; managed download URLs must be preserved separately. RSS/feed URLs and query-based routes require an explicit compatibility decision.

## 3. Documents, media and galleries

The content filesystem contains **3071 files**, including **770 PDFs** and **2284 images** (including generated derivatives). `media.csv` has 579 WordPress attachment records plus 314 NextGEN picture records; these overlap physical files and are not additive unique file counts. `documents.csv` inventories 787 distinct downloadable file paths (including Word/archive candidates), while `filebase.csv` separately retains 52 legacy file references. `filesystem.csv` distinguishes registered originals, thumbnail candidates and unreferenced candidates. 308 SHA-256 duplicate groups account for 356 redundant byte-identical copies; identical bytes alone do not prove identical legal/editorial meaning.

**61 NextGEN originals absent from backup are still reachable live as images.** They must be recovered before the old site is taken down; full original recovery was not performed in this analysis. One additional attachment is a synthetic WP-Filebase thumbnail reference rather than a normal original path. None of the 52 legacy Filebase paths is missing in the supplied backup. Unreferenced files are candidate orphans, not a deletion list. Generated thumbnails, scaled originals, WordPress metadata and gallery/album ordering require explicit source choice; employee photographs must not be imported as Person portraits.

All PDFs remain **accessibility not verified**. A `Tagged: yes` PDF flag is insufficient to claim accessibility. The report contains no extracted PDF text or private submissions.

## 4. Important plugin content

There is **no Elementor content**. Classic/Gutenberg HTML plus shortcodes are the main transformation inputs. Found: 13 TablePress tables, 8 NextGEN galleries/314 images/1 album, 96 published Download Manager packages, 52 Filebase records, 61 published Link Library CPT records plus legacy link tables, 26 Connections organizations, 2 Contact Form 7 definitions, 6 Content Views definitions, and 21 enabled Future actions on published posts. See `plugin-inventory.md`, shortcode/table maps, gallery item/album relationships and expiry inventories. Security logs, user credentials, MFA secrets, form submissions/mail templates and backup/cache data were excluded from reports.

## 5. What maps with high confidence

Ordinary news records -> Article with original dates, context categories/tags and SourceReference; ordinary editorial pages -> Page; verified original images -> Media; NextGEN -> Gallery/ordered GalleryItem; Download Manager/Filebase -> secure Document with reviewed deduplication; public link URL/name -> ExternalResource; menus -> NavigationItem/PublicRoute relationships. Source identity and URL/hash relationships can be automated. Publication, PDF accessibility, alt/copyright gaps and ambiguous private/orphan context must still be reviewed before publishing.

No real records or redirects have been created. `mapping.csv` is a proposal ledger, not an importer.

## 6. Transformation work

Sanitize basic paragraphs/lists/links into safe Markdown/text blocks; lift headings, images, galleries and downloads into explicit controlled blocks. Replace `[pdf-embedder]` with secure Document components, `[wpdm_category]`/tree and category listings with catalogs, and gallery/table/form shortcodes with structured records or reviewed alternatives. Do not retain WordPress/Elementor/plugin wrappers, inline styles, scripts, iframes or visual-builder JSON. A separate dry-run converter with per-block diagnostics and manual fallbacks is needed before import.

TablePress is structured public data: administration -> Person/Department, service A–Z -> Service/contact references, clubs/trades -> Organization, council -> CouncilTerm/CouncilMember, calendar -> Event. It has **83 rows after the calendar header, 81 with full-date candidates**; headings/date ranges are not automatic Event records. LifeSituation, Location, PublicNotice, Organization type, official notice dates and committees need editorial mapping, not title-only guesses. External portal/map services become managed links rather than automatic third-party embeds.

## 7. Manual review

26 nonblank council entries and 26 portrait references were found; 4 separator rows are excluded from member counts. Review names/roles/term dates and any actual committee relationships, select originals and apply focal crops. Do not copy administration-table photos into employees. TablePress service/directory rows need duplicate/department/organization reconciliation. Old homepage intro/obsolete service tables and inactive Connections data need retention decisions. Future actions must retain the original Europe/Berlin meaning and current enabled/newStatus semantics; historical jobs must not become current expiry rules. Contact routing recipients are configured privately, outside reports/imported public content.

`manual-review.csv` and `decisions.csv` list concrete decisions. `0` seed-only page/post candidates and unreferenced files are not proven orphans. External links were inventoried without third-party requests; availability of external targets remains unverified.

## 8. Missing, broken and backup discrepancies

Crawl status counts: 350 HTTP 200, 69 HTTP 404, 1378 HTTP 206, 75 HTTP 403. 74 observed incoming edges point at live 404/410 targets; seeded/noncanonical 404s are separate from reachable-page broken links. The 61 missing gallery originals are a **filesystem backup gap**, not a live outage. The dynamic thumbnail attachment needs manual resolution. Two SQL public Encyclopedia records are absent from WXR. Inactive-prefix content and plugin helper drafts must not be confused with current public records. All differences are explicit, with no inferred accessibility or deletion decisions.

## 9. URL/redirect risks

Keep indexed editorial slugs, category paths, old host/scheme aliases and one-hop trailing-slash redirects. Root query URLs such as `?wpdmdl=ID`, `?p=ID`, `?page_id=ID`, old Filebase query URLs and direct `/wp-content/uploads/...` / `/wp-content/gallery/...` paths need deterministic legacy-ID/path -> published file/content delivery. The current Laravel Redirect model is **path-only**, rejects `?`, and cannot directly distinguish query downloads at `/`; an adapter/allowlisted compatibility route is required before cutover. Never expose a protected/private source merely because it exists in a backup. Preserve public file identity even after secure storage moves originals outside `public/`.

No redirects were implemented during this phase. Proposed canonical changes require review against menu links, archive/pagination and direct file backlinks; WXR GUIDs are not canonical authority.

## 10. Current CMS gaps and the approved budget correction

Implemented and tested: **multiple budget packages per year**, with free-text Thema / Art rather than uniqueness by year or Herausgeber. A main plan, school-association plan, Nachtrag and correction can coexist. Each package has its own ordered originals, combined file, public URL, revisions/proposals and immutable proof. Package-specific download routes include the package ID; old year-only routes only serve a uniquely identifiable public package and return 404 if ambiguous. Historical proofs were not rewritten or given invented topic labels.

Remaining gaps: no generic controlled accessible table block; no root-query legacy download/ID resolver; no reviewed direct-media/file compatibility layer; no equivalent dynamic query/view-builder or nested album representation (use controlled catalogs/ordered gallery blocks); old GIF/DOC/ZIP formats exceed the secure upload allowlist; RSS/feed continuation is not implemented. Existing contact routing can represent public contact tasks, but exact old field/privacy workflow needs manual configuration. Committee memberships and legal/publication semantics cannot be inferred solely from old HTML.

## 11. Fix or decide before importing

1. **Budget PDFs:** 25 components in order: Gemeinde (10), Grundschulverband (7), Mittelschulverband (8), all 2026. The main 232-page Gemeinde, 94-page Grundschulverband and 102-page Mittelschulverband PDFs are rejected by the current FPDI parser. The other 22 pass source parsing (not a full merge/accessibility certification). Obtain compatible exports or a licensed/local parser solution suitable for goneo; no paid dependency/service was purchased. See `budget-pdf-preflight.csv` and `budget-components.csv`.
2. Decide structured conversions versus a bounded accessible table component for remaining statistics/information tables.
3. Design and test legacy query/download/direct-file delivery without bypassing publication or access checks.
4. Recover missing original gallery bytes, reconcile duplicate content and choose which private/draft/historic files remain unexposed.
5. Review expiry, notice archive semantics, unknown alt/copyright/accessibility and exact contact workflow. Agree on safe conversions for incompatible file formats.

**Readiness:** ready to design and build an importer with a strict dry-run/manual-review boundary; **not ready to run a complete production import** until the blocking PDF, URL and table decisions are resolved. This phase stops here. No migration execution, deployment or push occurred.

## Verification and report index

The CMS correction passed four read-only CMS responsive/accessibility checks and 462 PHP tests (2,924 assertions), Pint, PHPStan and Composer audit; build and 5 browser tests passed at 390/768/1024/1440px. Read-only tooling has 7 offline parser/safety tests. Input hashes were rechecked unchanged; raw sources/cache are ignored and untracked. See `README.md` for report scopes, commands and crawl limitations.
