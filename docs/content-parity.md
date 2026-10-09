# Content composition and editor capability

This phase adds the approved content capabilities ahead of visual implementation.
Laravel/PHP/MySQL, Blade, the security/privacy middleware and the existing editorial
workflow remain the architecture. No real content was imported or seeded.

## Inspiration boundary

The two ignored `designsystem-inspiration/` HTML exports were inspected offline.
Their bundled HTML screen descriptions show bounded article components, gallery
placements, service detail/fee fields, event notices and contextual feedback.
The exports are UX inspiration: none of their React/Babel/runtime code, demo
municipal facts, portraits, URLs, styling or external dependencies were copied.
The application still uses its existing functional layouts. Final styling,
homepage/header/footer/navigation/search appearance and Wappen integration wait.
The pre-existing recurrence metadata was left alone; no recurrence engine was added.

## Controlled composition

`Page`, `Article`, `Event`, `PublicNotice`, `Service` and `LifeSituation` implement
`HasContentBlocks`. A `content_blocks` row is one ordered component with:

- a stable allowlisted owner morph alias and owner ID;
- an allowlisted type, explicit `sort_order`, optional text/heading and H2–H4 level;
- named, nullable foreign keys and Eloquent relations to Media, Gallery, Document,
  Person, Department, Service, Event, Location and ExternalResource.

There is no JSON content blob. References cannot be supplied as arbitrary model
names or hidden opaque IDs inside JSON. The polymorphic owner is justified because
the same editorial composition belongs to six known content types; its allowlist
is checked on persistence and owned blocks are removed on permanent owner deletion.
Known reference targets use real foreign keys with RESTRICT deletion.

Supported components: text, heading, image, gallery, callout, contact person,
department, document/download, related service, related event, accordion, managed
external service/link and location/map information. Each reference component has
one target; multiple related entries use multiple ordered blocks. This keeps the
initial editor bounded and avoids nested page-builder trees. Galleries own their
ordered images. Text components support safe Markdown paragraphs/lists/links;
headings belong in explicit heading components. HTML, script/iframe markup,
unsafe link schemes and pasted Markdown images are rejected. There are no editor
style controls or executable block types. Blade still escapes ordinary fields;
`SafeMarkdown` is the only rich text renderer.

The page title supplies H1. Heading components offer H2, H3 and H4. Skipping a
level produces a publishing error; drafts can retain the issue for review.
Accordions use native `details`/`summary`. External/map components render managed
links and privacy notes, never iframes, remote image tiles or automatic requests.

### Editing, ordering and compatibility

The reusable `Rows` field renders labelled native inputs, reference selectors,
position numbers and an explicit remove checkbox. Three free slots appear after
the current rows; save to add rows and receive more slots. Reordering works by
editing numbers with the keyboard, without JavaScript or drag-and-drop. Saves sort
positions, preserve input order for ties and normalize positions to 0…n−1. Rows
are bounded to 100 per collection. Blank add slots are ignored; incomplete filled
rows fail. Child identity is not public: rows may be recreated during composition
edits/restoration while their FK targets keep stable identities.

The browser forms send a start marker and a completion marker after all fields.
If PHP/shared-host input limits truncate a submission, it fails validation instead
of silently losing trailing rows. For large compositions review `max_input_vars`
and request limits on the host. The functional editor is deliberately verbose;
the next visual phase can improve its presentation without changing its semantics.

Existing `body`/`description` Markdown remains stored and rendered before the new
blocks. No text was silently converted or discarded. Editors may move text into
blocks deliberately and clear the legacy body in the same reviewed edit.

### Revisions, proposals, search and references

Blocks use the existing revision child-collection mechanism. Schema 3 snapshots
capture composition fields and FK references, not child row IDs. Revisions stay
immutable, and restoring creates a new revision. Older snapshots remain readable;
an older snapshot lacking a new collection restores that collection as empty.
Existing publication state is never restored implicitly.

Proposal payloads are computed in rolled-back transactions as before. Live block,
gallery, fee and council collections remain unchanged until approval. Reviewers
see labelled row details; conflicts on a collection require the existing explicit
confirmation. Publication quality checks run on direct saves, approvals and live
revision restores. Persistence validation also runs when restoring child rows.
An unavailable/deleted reference is not silently substituted during restore.

Search indexes the owner's block heading/text alongside its legacy content and
structured service details/fees. Referenced private record titles are not copied
into the owner's search text. People shown by a public contact block can appear
as search results pointing to that page; they still have no profile URL.
Gallery and council-term records are indexed and become results only when public
and intentionally routed. Normal resource saves, child changes and restores keep
the MySQL index synchronized; raw SQL/bulk mutations still require `search:rebuild`.

`ReferenceProtection` includes live compositions, soft-deleted owners, gallery and
council membership collections, retained revisions and proposal payload/base
snapshots. Where-used displays those contexts. Permanent deletion is refused while
any such reference remains, with FK RESTRICT as a second defence for live rows.
Detaching a component never deletes its target or physical file. Soft deletion
withdraws a referenced target publicly; affected public components omit it and
quality checks report the issue before republishing. Unlimited revision retention
can keep referenced targets indefinitely, as intended by the existing policy.

## Galleries and focal points

`Gallery` has title, description, normal draft/published/archived lifecycle,
editor tracking, revisions, proposals and ordered `GalleryItem` children. A public
route is opt-in. An unrouted published gallery can be rendered inside a public
block. An unrouted gallery alone does not make its media downloadable; it needs a
public page context. Private proposals do not expose media.

Each placement references central Media, with an optional caption override and
optional alternative-text override. A nonempty alt override needs an explicit
context explanation; decorative images cannot have meaningful alt overrides.
Absent overrides inherit central caption/alt. Copyright/source always comes from
Media. Publication requires at least one public image and valid alternatives.
No files are duplicated and no portrait field was added to employees or councils.
Rendering is a simple sequence of semantic figures, without final gallery visuals.

Media now stores optional `focal_x`/`focal_y` percentages, each 0–100. The default
and effective fallback are 50/50 (centre). `focalPoint()` supplies effective values
to future image components. Fields participate in revisions/proposals/audits;
changing them does not alter bytes, hashes or dimensions. No crop UI/style or
destructive crop operation is part of this phase.

## Service, event and location details

Service has separate prerequisite, required-items, processing-duration and
important-notice text fields. Existing Document placements still supply actual
forms/files. Ordered `ServiceFee` rows have description, validity/context, nullable
decimal EUR amount and note. Missing amounts mean unknown/variable, not zero.
No HTML fee-table blob or service-specific hardcoding is used. New attributes
and fee rows participate in revisions, proposals and search.

Online modes are: not specified, unavailable, information/preparation, online
application and appointment. Positive modes require a public managed external
resource before publication. The link does not load the provider automatically.

Event `operational_status` (scheduled/cancelled) is independent of publication.
Cancellation does not hide a useful published event. Public output and related
event links mark it “Abgesagt”; search text also carries the status. Optional
`schedule_notice` explains movement/rescheduling. Real date changes and notices
use ordinary revisions/proposals and the existing UTC/Berlin time rules.

Location `accessibility_note` is optional public text. Empty means not entered,
with no inferred accessibility claim. Event locations, location blocks and
deliberately routed location pages read this central value. It is revisionable.

## Content feedback

The prepared action is `/kontakt?feedback=/canonical-public-path`. A later visual
component may link there; no final “Fehler melden” placement was added.
`FeedbackContext` accepts only an existing, active canonical path whose record is
public now. External URLs, query-bearing URLs, private/admin paths, unknown paths
and former aliases are rejected. Title/type/path are derived from the record.

The existing contact form displays that safe context and keeps it server-side with
the necessary, short-lived form nonce. Context is rechecked before delivery.
Submitted source URL/title/type fields cannot override it. The central contact
route setting optionally preselects the topic; the visitor chooses from ordinary
public topic labels. Internal recipient addresses remain encrypted and absent
from public output. No new recipient/topic or email address was invented.

Messages use the existing plain-text mail service, CSRF, validation, rate limits,
honeypot, timing checks and one-use token. Context reaches the email only, and is
not added to audit metadata. No message body is permanently stored or flashed to
validation sessions/logs. Ordinary public GET requests remain stateless.

## Gemeinderat and committees

Council records are separate from employee `Person` records:

- `CouncilTerm`: title, description, optional start/end dates, explicit historical
  flag and ordered council memberships. Its public route is opt-in.
- `CouncilMember`: public name in `title`, optional description and own editorial
  lifecycle. No automatic profile URL or portrait.
- `CouncilMembership`: term/member FKs, role, optional grouping/list/party and order.
  Role/grouping belong to the term, so a later term does not rewrite history.
- `Committee`: term FK, title, description, order and own lifecycle.
- `CommitteeMembership`: committee/member FKs, role and order.

The modest model publishes information, not elections. No ballots, vote counts,
electoral calculations or recurrence/term-rollover engine exist. Members and
committees use revisions/proposals; membership edits belong to their term or
committee's proposal/revision. A public committee's members must be public council
members in that same term. Each member occurs once per roster. Historical terms
and previously published archived council records remain available intentionally;
draft/never-published/deleted members are omitted. No historical facts are invented.

New permission prefixes are `gallery`, `wahlperioden`, `ratsmitglieder` and
`ausschuesse`. General editorial roles may prepare these records; publication stays
with publishers. Event-only editors can view galleries for event blocks but do not gain gallery
or council publication authority.
Synchronize permissions on deployment as usual.

## Archive regression and verification

Document download logic was retained. Regression tests prove that expired and
superseded previously published archive documents still deliver the same bytes at
stable and deliberately assigned legacy paths, with safe headers and no cookies.
Drafts and archived records that were never published remain inaccessible.

Feature tests cover bounded row/type/order validation, HTML/script rejection,
revision restoration including pre-block snapshots, private proposals and approval,
target deletion/history protection, galleries/overrides/focal metadata, service fees,
event notices, location accessibility, feedback privacy and council relationships.
The full previous PHPUnit suite and all PHP gates remain required.

Verification for this phase: the complete suite passed with 401 tests and 2,176
assertions. A final focused rerun passed 52 tests and 433 assertions. Pint,
Larastan level 8, Composer/npm audits, the frontend production build, route/view
caching and search rebuild passed. All 22 opt-in Playwright checks passed,
including axe on the row editor and public components. Disposable browser
fixtures were removed after the run.

`PARITY_BROWSER_FIXTURES=docker npm test` additionally runs four disposable local
Docker checks for block/gallery/fee/feedback axe markup and keyboard operations
with JavaScript disabled. Default `npm test` retains the existing environment-neutral
checks and skips these fixture-dependent additions. The helper refuses non-local
environments, and deletes its synthetic content, temporary employee/session and
image after the serial test group. Manual WCAG review still follows with the design.

No new dependency, external service, queue worker, cron requirement or production
Node/Docker/Composer requirement was introduced. The migration is additive.
