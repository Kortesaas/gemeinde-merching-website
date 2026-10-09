# Site-wide functional foundation

> Historical phase record: subsequent visual implementation is documented in
> [visual-system.md](visual-system.md) and [visual-verification.md](visual-verification.md).

Phase 3 extends the existing Laravel monolith. No visual design, real content,
WordPress import, external service, worker or public search interface is added.
The design-system export has not been inspected. Run migrations and
`permissions:sync` before using the new CMS resources.

## Database and permissions

Migration `2026_10_09_100700_create_site_foundation_tables` adds:

- `media` with verified file metadata, accessibility/editorial metadata and the
  existing draft/published/archived publication window;
- six explicit media placement tables (`article_media`, `event_media`,
  `public_notice_media`, `service_media`, `life_situation_media`, `page_media`)
  with foreign keys, unique owner/media pairs and bounded sort order;
- `search_entries` with a composite MySQL FULLTEXT index,
  `search_synonyms`, `search_statistics`;
- one typed `site_settings` record, with references to existing locations,
  departments and contact topics;
- `external_resource_id` on navigation and SEO fields on ten routable types.

No existing data is replaced. Schema changes are additive. New permission
prefixes are `media`, `search-synonym`, `site-settings`. Media follows the
existing edit/publish/archive/force-delete separation; department editors can
prepare media, administrators and Chefredaktion can publish it. Event editors
can prepare media, as they can documents. Settings and synonyms are managed
by administration/Chefredaktion; reviewers may inspect them. Settings cannot
be deleted or created twice through the CMS.

## Media architecture

`MediaStorage` reuses `UploadInspector`: extension allowlist plus detected
MIME, byte limit, cryptographically random generated name, sanitised original
filename, private disk outside `public/`. Accepted raster images are decoded,
limited to `UPLOADS_MAX_IMAGE_PIXELS` (20 million by default), and re-encoded
with GD before storage. EXIF/GPS and appended non-image payloads are removed;
width/height, size and SHA-256 describe the stored bytes. Re-encoding may alter
JPEG quality and does not preserve EXIF orientation instructions. SVG and
executable formats remain forbidden. Existing non-image upload types are also
supported; downloadable municipal documents should use the richer Document
model instead of Media.

`alt_text = null` denotes an unchecked/missing alternative. Decorative status
is an explicit editorial choice: `is_decorative = true` stores and renders
`alt_text = ''`. A blank meaningful alternative does not count as reviewed.
Drafts may remain incomplete. Publishing a meaningful image requires nonempty
alt text; publishing content with media requires publicly reachable media.
The same checks run on approved proposals and restoration of live revisions.
Copyright/source, creator, caption and language are separate metadata. The
central alt is shared across ordinary placements. Gallery placements now allow
justified contextual overrides, and optional focal coordinates prepare future
cropping. Visual gallery design remains future work; see [content-parity.md](content-parity.md).

Accessible functional CRUD is at `/verwaltung/medien`. Content forms select
media and their order using native checkbox groups. These relations and media
metadata are captured by the existing revisions and proposals. File bytes are
not revision snapshots: replacing a file replaces the shared asset; previous
bytes are removed only after successful commit. Failed saves remove only the
new upload and keep the previous bytes.

Public `/medien/{id}` delivery requires published media and a reachable owner;
image accessibility is checked again. Anonymous delivery is stateless, same
origin, with `nosniff` and sandbox CSP. The minimal existing content renderer
can display structured images without inline styling or a gallery design.

Where-used includes soft-deleted owners and media IDs in all retained content
revisions and proposal payloads/base snapshots. Detaching a placement never
deletes bytes. Permanent deletion is denied while any such reference exists;
foreign keys protect current placements too. Retained history can therefore
keep a media file indefinitely. File deletion runs after the DB commit.
People retain no image/portrait relation.

## Search architecture

`Searchable::toSearchDocument()` is the source of titles, summaries, body,
aliases, responsibilities, categories/types and document metadata. The index
is synchronously maintained on model save/delete/restore, alias changes,
CMS relation saves, revision restoration and proposal application. Category
and tag changes rebuild derived keywords. Index writes occur in the same
transaction as editorial saves: computing a private proposal rolls them back.
`search:rebuild` repairs/recreates the complete index and runs on deployment.
Bulk SQL updates and raw pivot writes bypass Eloquent events; callers must
sync the affected record or rebuild afterwards. No third-party search server,
queue or publication cron is involved.

`SiteSearch::search(phrase, types, limit, offset)` returns a total and bounded
result page; it does not expose an HTTP interface yet. SQL parameters and
escaped LIKE wildcards protect query handling. MySQL FULLTEXT relevance is
combined with exact/title/keyword boosts and deterministic ties. A literal
substring fallback covers short terms, umlauts, compounds and hosts with
larger FULLTEXT token-size/stopword settings. It scans matching candidates
and validates live visibility before ranking/paging, appropriate for a small
municipal collection; benchmark actual imported volume before launch.

`SearchVisibility` consults current status/windows/deletion/routes at query
time, so activation/expiry require no rebuild. Publicly retained archives
remain searchable. Documents without a detail route use their existing stable
download address. Unrouted departments, organizations and locations have no
invented public URL. Active people can be searched only when referenced by a
reachable public service, department, page or article; results link to that
context, never a person profile. Recipient lists, accounts and proposals are
outside the searchable type allowlist.

Managed synonym groups are at `/verwaltung/suchbegriffe`: one phrase plus
semicolon-separated alternatives, active flag, authorization, audit and
revisions. Exact whole-phrase matches expand in either direction, one hop,
up to twenty terms. Existing service aliases also work. This deliberately
avoids recursive expansion and uncontrolled vocabulary growth. Multiword
partial synonym expansion, stemming and typo correction remain future work.
Verify MySQL FULLTEXT availability and benchmark German queries on goneo.

## Search statistics and retention

Statistics are separate from search indexing and disabled by default:
`SEARCH_STATISTICS_ENABLED=false`. No collection takes place until a caller
explicitly invokes `SearchStatistics::record`. Stored fields are phrase
(max. 150 characters), result count, optional click flag (currently null) and
UTC timestamp. No IP, user agent, user/profile/session ID or browser identifier
exists in this table. No click-tracking endpoint or persistent visitor token
is introduced.

Obvious e-mail addresses, URLs and long/phone-like numbers are discarded.
This is minimization, not proof of anonymisation: citizens can type personal
names or other sensitive text into free-form search. Keep collection disabled
until its purpose, lawful basis, notice and retention are approved. The
technical default retention, if enabled, is 30 days; zero prevents recording.
Expired rows are pruned on `record`, by `search:prune-statistics`, and at release
activation. Schedule the pruning command if statistics are enabled and a
strict wall-clock deletion deadline is required during periods without use.

## Contact form and mail

`/kontakt` provides native labelled fields: active contact topic, name,
e-mail, message, optional phone. Contact topics expose only `publicData()`;
internal recipient addresses remain encrypted and are never present in the
HTML/JSON, session, revisions or audit metadata.

Only contact form/confirmation routes opt into `web`; other public routes,
sitemaps, downloads and images keep the stateless middleware group. The
necessary session cookie stores CSRF, issue time and a random single-use nonce,
without IP/user agent. These routes are `no-store` and `noindex`. Session
blocking uses the existing cache locks to prevent concurrent token reuse.
Validation responses omit all contact fields from flashed old input. Values
must be re-entered after errors; this is a deliberate privacy tradeoff to
review with the final form usability/accessibility work.

Spam controls: hidden honeypot (excluded from the keyboard/accessibility tree),
minimum fill time (3 seconds), maximum age (one hour), replay rejection,
five submissions/hour/client by default, and a limit on links in the message.
No CAPTCHA service, tracker or external browser request is used. Rate-limit
keys use a keyed HMAC of the client IP and UTC day, held transiently in cache
for the limiter window; no raw IP is stored. This remains security processing
of network data, separate from search statistics. It cannot identify a person
behind a shared municipal network. Thresholds are configurable.

Delivery is synchronous over the configured mailer. The application's
configured From address stays fixed; a validated visitor address becomes
Reply-To. Subject is fixed, body is a plain-text template, headers reject
control-character injection. Logging/failover transports are refused for
contact messages, preventing silent message-body logging. SMTP/sendmail are
allowed; the array test transport is allowed only in tests. Mail failures
produce a generic accessible error and minimal `contact.delivery_failed`
audit metadata, without reporting potentially sensitive transport exceptions.
Success stores only `contact.sent`, topic ID and timestamp in the existing
audit log (its separate configured retention); it stores no contact message.
Mailboxes/SMTP infrastructure necessarily receive the message and need their
own operational retention policy. Honeypot submissions receive the ordinary
confirmation without sending or recording content.

Local SMTP is Mailpit from Compose. Password reset continues using Laravel's
broker/notification and existing deferred sending through the same environment
mail configuration. `ContactDelivery` and `ContactMessage` isolate citizen
mail generation. Future proposal notifications can use the same mail transport
and dedicated mailables; no notifications are sent in this phase. Production
SMTP/sender/domain configuration and secrets are deployment inputs, never Git.
`deploy:check` rejects non-SMTP/sendmail production mailers and checks GD.

## Structured site settings

`/verwaltung/einstellungen` manages the singleton, validated typed fields,
revisions and audit events. Municipality name and default SEO values feed the
public base layout; no invented municipality/address data is seeded. Until
settings exist the already configured `APP_NAME` remains the fallback.

Rathaus address/opening hours reference a Location; central phone/e-mail and
Bauhof reference Departments; central contact form topic references a
ContactRoute; recycling information references a Location. The postal/legal
contact overrides are explicit text fields. `SiteConfiguration` and the
settings relations provide these values to future templates, without copying
structured facts into an unrestricted key/value table. Existing directory
updates are immediately reflected at every reference. Foreign keys prevent
physical deletion of referenced records.

## SEO, sitemaps and routing

Routable resources expose optional `seo_title`, `meta_description`,
`seo_noindex`, included in revisions and proposals. `SeoMetadata` supplies an
escaped title/description, canonical URL, robots, Open Graph and X cards through
the existing server-rendered layout. Public Media overrides and a branded default
sharing image are supported; page-specific descriptions have safe text fallbacks.
See [site-identity-seo.md](site-identity-seo.md). Downloads also send canonical
Link and robots headers. The global production/indexing switch and backend
noindex remain stronger than a record's setting.

`/sitemap.xml` uses canonical active PublicRoutes plus reachable downloads
without their own route, plus unshadowed fallback listing pages, on the fixed
production metadata origin. It excludes drafts, scheduled entries,
expired content without public archives, inactive/deleted records, private
proposals, contact routes/pages, admin routes, aliases, redirects and per-record
noindex. Intentionally public archives for articles/notices/events/documents
remain included. Missing document files are excluded. URLs are slashless
except root. Above 10,000 URLs it returns a sitemap index with paginated
`/sitemap/{page}.xml` parts. XML is escaped, includes UTC lastmod where known,
and is generated on request without stale publication-window caches or cron.

Production robots.txt disallows the backend/contact area and advertises the
sitemap; nonproduction/disabled indexing still disallows everything. All new
functional paths are reserved against content-route collisions. Existing
one-hop host/slash/legacy redirects and 404 for unavailable content remain.
Explicit managed 410 redirects retain the existing Gone behavior. Archiving
does not invent a 410 or revert content to draft.

`StructuredDataProvider` is the extension contract for later verified Schema.org
records. No JSON-LD or municipality facts are invented/rendered yet. Implement
and review a provider together with the later public templates/CSP treatment.

## Navigation

Functional existing CRUD supports an internal canonical route, a managed
ExternalResource, or a validated external URL (exactly one target). Parent
and child must share a menu. Cycle traversal uses a visited set rather than
a fixed twenty-level limit. Saves under CMS transactions lock navigation rows;
`NavigationManager::move` locks, validates, records revision and audit. Order
is bounded 0–65535; ties use ID. Moving navigation never assigns public paths.
Model rules also protect revision restoration. Parents with children cannot
be deleted accidentally; move/remove children first.

`NavigationManager::tree` returns ordered nodes for later templates, hiding
inactive subtrees and unreachable targets. Draft/scheduled/deactivated
internal targets and unavailable managed links do not appear in the public
tree. Navigation snapshots are now separate content revisions, while audit
records continue recording safe changed-field metadata.

## Quality checks

`QualityCheck` provides `supports(model)` and `inspect(model)`; new type-specific
checks can be added to `QualityChecks`. Issues have stable codes, severity,
message and field. `ContentBasics` checks titles, active/valid routes, publication
windows, inspectable Markdown heading levels, weak link labels and external
embed/image markers. `ReferencedAssets` checks media purpose/publication,
unreachable referenced assets, document accessibility and external-link
syntax/privacy notes. No arbitrary network link crawl is performed (avoids
SSRF and external requests); reachability requires later controlled checking.

Errors block public saves/approval/restoration; a missing route warns (a
disconnected record has no public page, and existing revisions/proposals must
remain editable). Invalid assigned routes are errors. Warnings and recommendations
are displayed in a simple accessible admin panel and do not block. Missing
meta descriptions are recommendations; unchecked/inaccessible documents warn
rather than falsely being declared accessible. Drafts can be incomplete.
A plain document accessibility status is not a PDF/UA validation result.
Automated checks cannot assess the semantic accuracy of an alt text, legal
content or overall WCAG conformity: manual testing remains required.

## Verification and operations

- PHPUnit on MySQL tests uploads, metadata, cleanup, references/history,
  media proposals/permissions, indexing/aliases/types/ranking/visibility,
  statistics minimization/retention, contact routing/security/errors/mail,
  settings audit/revisions, SEO/sitemap/archives/navigation and quality levels.
- Playwright/axe adds contact form, confirmation, accessible errors, mobile
  reflow and same-origin requests to the existing baseline browser suite.
- Required gates remain `composer check`, `npm run build`, `npm run audit`,
  `npm test`. Mailpit is also used for a real local SMTP delivery smoke test.
- Deployment runs migrations, permission sync, search rebuild and statistics
  pruning using PHP CLI only. Existing release packaging remains compatible.

Remaining launch inputs: production SMTP/sender, municipality-approved content
and structured settings, statistics collection/retention decision if desired,
audit/mailbox retention policy and manual accessibility review. None are
required to implement the technical foundation or start the later authorized
visual phase; no visual work starts automatically.

## Content capability extension

The approved follow-up adds relational content blocks, galleries and focal points,
structured service fees/details, event operational status, location accessibility,
safe contact feedback context and separate council/committee records.
See [content-parity.md](content-parity.md) for decisions, revision compatibility
and reference protection. The established foundation guarantees remain in force.
