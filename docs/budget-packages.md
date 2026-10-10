# Council portraits and yearly budget PDF packages

## Council portraits

`CouncilMember.portrait_id` is an optional, restricted Media foreign key. Portrait selection participates in revision and proposal snapshots. Media usage includes live council members (including the recycle bin), revision snapshots and proposals; referenced portraits cannot be permanently deleted. Employee `Person` records are unchanged.

The public council page renders a 4:5 image with the Media focal position. Missing, private or deleted portraits use the repository's local `public/images/council-placeholder.svg`. Images have empty alt text alongside the visible member name. Upload images through Media and select them on the member; publish the image before publishing the member.

## Storage and editorial state

- `budget_plans`: year and Thema / Art (several independent packages per year, including supplements/corrections), editorial description/options, independent combined-PDF accessibility, normal publication/SEO/revision/proposal/editor fields, generation state and current output pointer.
- `budget_sources`: immutable original PDFs and secure upload metadata, upload actor/time and page count when inspected during generation. Files live on the existing private uploads disk.
- `budget_components`: ordered associations. Removing a row does not destroy its original file. Revisions/proposals contain only source IDs and positions, not storage paths, hashes or system generation state.
- `budget_generations`: immutable combined files and ordered source manifests with filenames, checksums, bytes, page counts and upload times.
- `budget_publications`: immutable successful publication records with title/year/status/date, topic, actor ID and name snapshot, canonical public URL, selected generation and accessibility state.

The package workflow reuses `UploadInspector` via `DocumentStorage` and its safe streamed file response. Immutable originals have their own records because ordinary Document file replacement deletes previous bytes, which would invalidate historical publication proofs. Budget sources and generated files do not appear as independent entries in the ordinary public Document catalog.

The editor first creates a draft, uploads several PDFs, saves their order, generates/previews the combined PDF, records its accessibility review and publishes. A small browser enhancement disables upload/generation while the editor has unsaved changes, prompting the editor to save first. Existing native row controls provide keyboard-operable up/down buttons and a numeric fallback without JavaScript. Additional files for a live plan remain private until selected by a publisher or an approved proposal. Source uploads, generation, publication records, ordinary edits and approvals are audited.

Direct save, approved proposals and revision restoration share the publication boundary. Changes to selected sources, order or year invalidate the fingerprint and reset the generated file's accessibility status. Publication rebuilds as necessary and fails atomically if generation fails. File writes are removed on transaction failure. Existing downloads always stream the last successfully published generation; they never invoke the merger or expose unpublished sources. Previous outputs and proofs remain available to authorized CMS users. Packages containing originals or proofs cannot be permanently deleted; they may be archived or moved to the recycle bin.

## PHP PDF generation and hosting limits

Generation uses bundled Composer packages `setasign/fpdi` and `setasign/fpdf`. No shell commands, SaaS, API, Ghostscript, Python or background worker is required at runtime. The PHP hosting needs the project's existing PHP/extensions and enough memory/time/upload capacity. Limits in `config/budgets.php`: 20 selected PDFs, 32 MiB total source bytes, 1,000 pages, 25 seconds checked between imported pages. Individual upload limits remain governed by `config/uploads.php` and the host's `upload_max_filesize`, `post_max_size`, `max_file_uploads`, memory limit and request timeout. Exceeding server POST size is handled by Laravel's existing request-size middleware.

Each original's bytes/size/checksum is validated when importing and before reusing an existing output for publication. Package edits and generation acquire a database row lock to serialize concurrent writers. Page dimensions/orientation are preserved using each imported page's CropBox. Output is generated once and stored with a SHA-256 checksum. Generation failure blocks publication and names the offending source where possible.

**Free FPDI parser limitations:** encrypted PDFs and compressed cross-reference/object streams are unsupported. Re-export incompatible inputs as unencrypted PDF 1.4 without compressed object streams. Source PDFs are kept intact. The merged output imports page appearance; PDF forms, document bookmarks, attachments, signatures and accessibility tags are not carried over. Active external links/annotations are not imported. The resulting file is never automatically marked accessible; review it independently and provide an appropriate public accessibility note. This implementation is unsuitable for preserving digitally signed source semantics in the combined file; originals remain available separately if the editor enables them.

Official reference: <https://manuals.setasign.com/fpdi-manual/v2/limitations/>.

## Upload proof

After successful publication the editor offers **Upload-Nachweis drucken**. This opens a private HTML page with a local browser print button. It displays a stored publication snapshot and the referenced immutable generation/source manifest. GET does not recalculate hashes or regenerate files. Historical records are retained and linked. Later title/year, actor name, file order or publication edits do not rewrite an earlier proof. Date/times display in Europe/Berlin, including seconds. A future publication is labelled planned. The proof is an internal traceability record, not a digital signature or an independent accessibility certificate.

Public listing: `/haushaltsplaene`; published packages also participate in the existing site search; one entry per package, grouped by year descending and topic/title. Detail routes default to `/haushaltsplaene/haushaltsplan-YYYY` and remain editable using the normal route system; demo detail routes use `/haushaltsplaene/2026`. Combined download: `/haushaltsplaene/YYYY/paket/ID/gesamt.pdf`. Old year-only download URLs remain usable when exactly one public package exists in that year; ambiguous year-only URLs return 404 instead of selecting an arbitrary package. Topic is recorded in revisions/proposals and new proofs; historical proofs without a recorded topic remain unchanged. Component downloads only exist publicly when enabled on the publication snapshot and selected in that generation. Drafts, future publications, expired/hidden entries, deleted plans and unselected sources follow existing visibility rules.

## Local demonstration

A fresh `DevelopmentDemoSeeder` includes the package/portrait demo. To extend an existing local demo without replacing its current content/settings/images:

```sh
docker compose exec -T app php artisan migrate
docker compose exec -T app php artisan permissions:sync
docker compose exec -T app php artisan db:seed --class=BudgetPortraitDemoSeeder
```

The additive seeder is local-only and idempotent. It adds published 2026 (three ordered PDFs and a proof), draft 2027 (three PDFs awaiting generation) and two fictional Media portraits. Other council members show the local placeholder. Existing records of those years and existing portrait choices are preserved. No WordPress migration occurs.

## Manual checks before real uploads

Try representative actual municipal PDFs, including landscape pages, and confirm the source/combined page order and completeness. Test host upload/memory/time limits. Review combined PDF accessibility after generation and after any change in source selection/order. Check browser print preview, the old proof after a new publication and employee-to-publisher proposal approval. PDFs with unsupported compression require a compatible export before publication.
