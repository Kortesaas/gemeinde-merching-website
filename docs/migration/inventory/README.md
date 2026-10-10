# Inventory reports and read-only tooling

Read `summary.md` first, then `decisions.csv` and `plugin-inventory.md`. Machine-readable files are UTF-8 CSV/JSON; dates and source IDs retain source meaning. Rows are candidates, never an instruction to publish/delete. Reports intentionally omit raw content bodies, private form data, internal contact recipients, credentials/hashes and arbitrary option values.

## Reproduce locally

```sh
python3 scripts/migration/inventory.py
python3 scripts/migration/enrich.py
python3 scripts/migration/crawl.py
python3 scripts/migration/report.py
python3 -m unittest discover -s tests/Inventory -v
```

Python standard library suffices for SQL/WXR/ZIP/crawl/report. Budget PDF preflight additionally uses local `pdfinfo` and the project's existing Docker/PHP FPDI vendor package. It never bootstraps Laravel or connects to its DB. Missing PDF runtimes are recorded as untested, not supported. Inventory and enrich write only sanitized reports plus raw cache below ignored `migration-source/analysis-cache`. SQL is parsed, never executed. Nested ZIPs are copied into the ignored cache and read directly; no arbitrary ZIP paths are extracted. Every original source gets an unchanged-byte hash check.

Download Manager links may be in `data-downloadurl`, not ordinary hrefs; the public `refresh` cache-buster is removed only when a numeric `wpdmdl` is present, without discarding authentication/password parameters. Stable root download URLs are also seeded from published package IDs. The crawler resumes private cached state, retries recorded network/client errors, observes robots.txt and globally limits GET start times to one per 0.6 seconds with two workers. Requests never use login/cookies, POST/form submissions, wp-admin, wp-login, XML-RPC or REST mutation endpoints. Query strings are allowlisted; recipient/token/password/nonce URLs are excluded. External URLs are recorded, not fetched. It includes anchors, images/srcsets, iframes, sitemaps, styles/scripts/feeds and local CSS assets; it does not execute JavaScript or exhaust arbitrary search/filter combinations. File requests sample headers/body; HTTP 206 is a normal Range response. A finite graph and no pending queue are not proof that every historically indexed URL has been found. Add access/search-console backlink exports later for further orphan/URL evidence, without inferring their contents now.

## Report scopes

- `content.csv`, `counts.json`, `tables.csv`: active-prefix published/attachment candidates, all post type/status totals, schemas/counts only for operational/private tables.
- `mapping.csv`, `manual-review.csv`, `decisions.csv`: proposed domain conversion and concrete unresolved decisions.
- `urls.csv`, `crawl-counts.json`, `redirect-candidates.csv`, `observed-redirects.csv`, `source-live-crosscheck.csv`, `uncrawled-url-candidates.csv`: request/canonical/redirect evidence and explicit public/unknown candidates. Redirect proposals are not executed.
- `internal-links.csv`, `broken-links.csv`, `orphan-candidates.csv`, `external-links.csv`, `live-external-links.csv`: link relationships, actual incoming failures and unverified external destinations.
- `documents.csv`, `media.csv`, `filesystem.csv`, `duplicates.csv`, `missing-files.csv`, `filebase.csv`, `managed-downloads.csv`: individual source files, physical hashes, attachment dimensions/scaled originals, duplicate groups and missing-backup/live evidence. Thumbnail/orphan flags are candidates; only original/reference relationships establish provenance. Do not expose all backed-up files.
- `galleries.csv`, `gallery-items.csv`, `albums.csv`, `council-portrait-candidates.csv`: ordered gallery/album/council relationships; blank council separator rows are not members.
- `tablepress.csv`, `table-row-candidates.csv`, `shortcodes.csv`, `html-conversion.csv`, `event-candidates.csv`, `organization-candidates.csv`: structured/plugin conversion evidence. Row counts are not guaranteed domain entity counts.
- `taxonomies.csv`, `content-taxonomies.csv`, `menus.csv`, `link-library.csv`: category/menu/link assignments and legacy-ID deduplication candidates.
- `scheduled.csv`, `future-actions.csv`, `forms.csv`, `legacy-calendar.csv`, `postmeta-keys.csv`: public scheduling/form type/schema evidence; no recipient or arbitrary meta values.
- `budget-components.csv`, `budget-pdf-preflight.csv`: 25 year/topic components and actual local parser checks. Compressed-object presence alone is not failure; the parser result is authoritative for this preflight. No merged files or public accessibility status were imported.
- `discrepancies.csv`, `postmeta-discrepancies.csv`, `source-provenance.csv`, `findings.json`: cross-source differences, unchanged original-source fingerprints and high-level computed counts.

Raw source/HTML/state/PDF files remain sensitive and gitignored. Re-running stages replaces report projections; run them in the listed order. Keep backup sources immutable. Report generation is idempotent (avoid appending duplicate workflow decisions).
