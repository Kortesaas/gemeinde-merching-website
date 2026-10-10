# Real local content migration — 10 October 2026

Review the redesigned site at **http://localhost:8089**. The existing demo app remains at http://localhost:8088. The separate `merching_migration` database contains real public municipal content and no demo source references or imported users. No deployment, push, old-site mutation or old-site form submission was performed.

## Imported content

| Type | Count |
|---|---:|
| Pages, including five modern landing/source-calendar pages | 53 |
| Articles | 66 |
| Services | 153 |
| Events | 80 |
| Documents: 168 PDFs and 2 publicly linked DOCX originals | 170 |
| Media | 432 |
| Galleries / public gallery items | 8 / 312 |
| Organizations | 78 |
| External resources | 52 |
| Public notices | 16 |
| People / departments / town-hall location | 21 / 34 / 1 |
| Council terms / members / committees | 1 / 26 / 3 |
| Budget packages / ordered originals | 3 / 25 |

There are 1,195 imported records/assets, 1,392 source references and 684 search entries. Counts are model counts, not a count of unique facts: the calendar table and specialized events intentionally preserve the same information in complementary forms. Notices and their original documents also have distinct model identities.

## Structure decisions

[structure-decisions.csv](structure-decisions.csv) reconciles all 19 demo pages, 50 navigation entries, 21 demo services, seven life situations and seven functional sections (104 decisions).

- **Kept:** the redesigned Bürgerservice, A–Z, Rathaus/Politik, Leben, Bauen/Wirtschaft and directory architecture; search, archives, display controls, feedback, contact, publication and editorial workflows. Four new landing pages link to verified real content.
- **Replaced:** fictional people, contacts, greeting, news, events, documents, council portraits and budgets with public source records. All 26 council portraits have verified row relationships; no portraits are attached to employee Person records. Missing future portraits use the existing abstract placeholder.
- **Merged:** standalone legacy citizen-service pages into Services; A–Z offices into structured departments; repeated employee rows into the same person; demo childcare and lake pages into real children/youth and bathing-water pages. Their former new-site paths redirect to the real pages. Life-situation subjects use verified Services/Pages rather than fabricated guides.
- **Removed:** demo facts, synthetic alerts, quality fixtures and WordPress presentation helpers. Repeated source listings no longer precede the new searchable catalogues. Author/category/plugin archive aliases consolidate into current catalogues.
- **Kept empty:** unverified committee memberships, additional historical council terms, additional budget topics and unused LifeSituation scaffolding. The models/workflows remain available; fictional records are not published.

The homepage uses the real Rathaus image, municipal contact details, opening hours and Helmut Luichtl greeting. Menus use real destinations within the new architecture. Crest previews use a neutral background, contain the crest without cropping and keep normal photo-frame proportions, including old “Logo Merching” filenames. Other authorities’ original crests retain their identity.

## Preservation and editorial review

The primary active WordPress SQL data provides identities, public statuses and original publication dates. WXR, filesystem/WPvivid backups, existing inventory and the public crawl supply cross-checks, paths and public relationships. The importer never executes the SQL dump. Original sources are unchanged; preparation verifies their recorded hashes.

Files enter the import through published/password-free packages or successful public HTML links. NextGEN exclusions remain excluded. Byte-identical already-public originals and backed-up original/resized variants share safe legacy destinations. Successful seeded file GETs alone do **not** authorize publishing private-parent or unlinked backup assets. Re-encoded image delivery strips EXIF/GPS; immutable source originals remain in ignored migration storage. The source/delivery checksum distinction is deliberate. All PDFs remain **not checked** for accessibility.

[preparation-review.csv](preparation-review.csv) and [import-result.json](import-result.json) record 117 imported-but-review warnings, five ambiguous mappings, three budget merge issues, two missing-source rows, 92 intentional skips and one successful external-package conversion. Review priorities:

1. **Legal pages:** the public legacy Impressum, privacy and accessibility information is preserved, but must be reviewed for the Laravel website. The obsolete cookie-plugin edit shortcode is flagged; no cookie/plugin functionality or claim of compliance is carried over.
2. **Events:** 58 rows have no single clock time. Their original time wording remains visible; the import does not invent midnight, all-day status, cancellation or schedule-change notices. Three ambiguous/separator rows remain in the complete accessible [source calendar](http://localhost:8089/veranstaltungen/veroeffentlichter-kalender).
3. **Alternative text:** 25 imported media items have no verified alt text. Inline placement remains subject to the existing accessibility rules; verified legacy original links remain available. Copyright/creator information is not invented.
4. **Notices:** 16 package-category notices retain source publication dates/documents; statutory notice/Aushang dates are unverified. One article’s ideal notice classification remains flagged.
5. **Directories/classification:** two public SQL-only Encyclopedia entries and four empty download categories require review. No committee membership is inferred. Three third-party embeds are represented by ordinary external links.
6. **Missing original:** `/wp-content/uploads/2024/02/Abschaffung-Kinderreisepass.pdf` is absent and its live URL returned 404. Article WP 8567 remains public and links to the separately verified public notice WPDM 8132 on the same topic; the report does not claim these are the same original file. The two missing rows describe this single file and its embedding article.

## Galleries, tables and budgets

All **61** missing NextGEN originals were recovered by paced, read-only public GETs before import. [recovered-files.csv](recovered-files.csv) records original URL, path, gallery, size, SHA-256 and recovery status. Eight galleries contain 312 public items in original order; two excluded source images remain excluded. The integrity audit checks each membership/order and stored file checksum.

[tablepress.csv](tablepress.csv) covers all 13 tables: five use structured records plus controlled-table/source preservation (employees, A–Z, clubs, council, calendar), three use accessible controlled tables (location/business/leisure facts), and five unused/obsolete helpers are explicitly skipped. The table block has a caption, column headers, keyboard-accessible horizontal scrolling and controlled Markdown cells; scripts, inline HTML/plugin runtime and unsafe iframe presentation are not imported. Employee portrait columns are omitted from employee contacts.

The three **2026** packages are Gemeinde Merching (10 files), Grundschulverband (7) and Mittelschulverband (8). All 25 original PDFs are downloadable in published order and protected by immutable source-only publication manifests/checksums. The three main PDFs remain unsupported by FPDI: no combined derivative is claimed or offered, and the UI states that it is unavailable. No PDF is marked accessible without verification. Other years/topics can use the existing budget model when verified sources become available.

## URL coverage, search and privacy

[url-mappings.csv](url-mappings.csv) contains **1,564** mappings. [redirect-validation.csv](redirect-validation.csv) checks them and **734** unique destinations: zero failed destinations or legacy checks. Known `wpdmdl`, `p`, `page_id`, attachment and Filebase identities, observed package-path/query variants, upload/gallery originals, gallery album URLs and consolidated archives resolve directly to their public destination. Publication checks remain active, malformed/unknown IDs return 404, and query redirects are one hop. Canonical paths are slashless except `/`.

[crawl-coverage.csv](crawl-coverage.csv) accounts for all **1,872** inventoried URLs: 1,205 mapped, 196 obsolete presentation/feed/runtime assets intentionally skipped, and 471 explicit review rows. No successful current public HTML link remains unresolved. The review rows comprise 240 unlinked reachable PDFs, 161 unlinked reachable images, six other unlinked resources and 64 old URLs already returning 404. Each row records its reason and incoming public pages, if any. They are not silently imported merely because they appeared in the source inventory. [source-content-coverage.csv](source-content-coverage.csv) covers every content inventory record separately.

Search is rebuilt from normal models/controlled blocks; real Personalausweis search, gallery navigation and budget downloads pass browser checks. Public search delivery still applies current publication/context visibility checks. The audit confirms zero foreign/demo source references and zero users. Passwords, private form recipients/submissions, plugin secrets, security history, backup records and WordPress caches are excluded. The contact recipient comes only from an explicitly public municipal address; local SMTP delivery goes to Mailpit.

## Validation and tests

[integrity.json](integrity.json) reports zero failures across 1,195 target identities, 1,063 source/import publication timestamps, all 602 stored/prepared original files, gallery order and 25 budget originals. [idempotency.json](idempotency.json) confirms identical target IDs, source-reference counts, URL counts and search counts before and after repeat import.

- PHPUnit: **468 passed**, 2,962 assertions.
- Pint: passed; PHPStan/Larastan: no errors.
- Composer/npm audits: no reported vulnerabilities; production Vite build: passed.
- Python inventory/conversion suite: **11 passed**.
- Existing Playwright suite: **76 passed**; the three migration-only tests are separately opt-in.
- Real migration Playwright: **3 passed**, including 16 representative paths at 390/1440px, axe WCAG/best-practice scans, no third-party requests, no visitor cookies, reflow, crest framing, search, gallery and original budget downloads.
- Manual browser review: homepage, desktop news/crest cards, mobile council portraits and ordered budget downloads. The complete source calendar and long controlled tables are also covered by responsive axe checks.

Transient test-artifact folder collisions were resolved with separate output directories. Repeated local contact-form tests hit the existing hourly limiter; only synthetic local browser counters were reset for the final run. The limiter remains enabled.

## Local rerun and review

Sources, recovered originals, extracted/prepared files and detailed tool logs are ignored under `migration-source/`; sanitized reports are tracked here. The importer refuses a nonlocal environment, a database without the `_migration` suffix, or a database containing demo source references. Reruns rebuild migration-owned blocks/navigation/associations while preserving source identity and immutable budget receipts. Re-import before editorial editing: a rerun intentionally restores the prepared source content over edits to imported records.

```sh
docker compose -f compose.yaml -f compose.migration.yaml up -d --build migration-app
docker compose -f compose.yaml -f compose.migration.yaml exec -T migration-app php artisan migrate --force
python3 scripts/migration/prepare.py
docker compose -f compose.yaml -f compose.migration.yaml exec -T migration-app php artisan migration:import-public
docker compose -f compose.yaml -f compose.migration.yaml exec -T migration-app php scripts/migration/audit.php
python3 scripts/migration/decisions.py
python3 scripts/migration/validate.py
BASE_URL=http://localhost:8089 REAL_MIGRATION=1 npx playwright test tests/Browser/migration.spec.js --output=migration-source/test-results-migration
```

No CMS login is imported or invented. To review/edit in the CMS, create a fresh local administrator through the existing interactive command: `docker compose -f compose.yaml -f compose.migration.yaml exec migration-app php artisan admin:create`.

Manually inspect the real greeting/contact hours, service responsibilities, council roles, source-calendar exceptions, long-table content and notice/legal text. The remaining difference from WordPress is intentional presentation/consolidation plus the explicit editorial/source issues above, rather than a reconstruction of its menus/plugin layouts.
