# CMS usability polish — 2026-10-09

This pass refines the existing Laravel/Blade backend at `/verwaltung`.
The left navigation, permissions, MFA, proposal approval, revisions and audit
logging remain in place. No schema changes, dependencies, deployment or real
content migration are involved.

## Findings and changes

| Finding | Change |
|---|---|
| Long editors mix unrelated tasks, settings have one large generic group | Task-oriented groups for contact details, addresses, event timing, service requirements, fees, document validity/accessibility, homepage and greeting settings |
| Blank fee/member/gallery rows and a large block-type button wall dominate the form | Blank rows appear after “Eintrag hinzufügen”; blocks use a labelled type selector and one add action. Numeric ordering and blank-row editing remain available without JavaScript |
| Optional image selections, relations and technical settings make editors too long | Native disclosures for optional groups, selected-entry counts, a compact section chooser; required groups and groups with validation errors open automatically |
| Inputs stretch regardless of their contents; hint length misaligns neighbours | Compact year/order/ZIP/language/room fields, bounded phone/date/time controls, wider text areas; helper text after controls |
| Checkbox hints wrap independently and controls drift away from labels | Two-column checkbox layout; stable labelled selection cards and selection styling that follows the actual checkbox |
| Save actions compete with history/view links; publication follows quality advice | One prominent sticky-bar action; secondary actions in “Weitere”; publication precedes save and quality; optional revision note |
| Save wording does not explain public visibility | Draft, publication, scheduling and archiving action labels; feedback uses configured site time and preserves public archive semantics |
| Media is displayed twice and the editor lacks an image preview | Gallery first, optional file table, linked titles and actual authenticated image preview |
| Download/link tables require sideways scrolling on phones | Labelled stacked rows, compact ordering fields, distinct accessible table-region names |
| Navigation shows three mutually exclusive targets simultaneously | A target-type selector reveals the relevant original field. No-JS fallback retains all targets and server validation |
| Navigation editors crash with lazy loading disabled | Eager-load canonical route targets; regression covers create and edit with multiple content targets |
| Mobile menu disagrees with CSS at exactly 1024px | Match the existing 64rem navigation breakpoint; compact phone brand/menu and tablet topbar |
| Account editing lacks hierarchy and role context | Identity and access panels, short role descriptions, internal-only wording, account/MFA state and consistent save actions |
| Recovery/security options are visually flat | Bounded security panel, compact six-digit input, recovery-code disclosure and confirmation before regenerating codes |
| Menus have inconsistent dismissal and history looks “published” even for drafts | Escape/outside-click/focus-leaving dismissal; neutral “Aktueller Stand” revision badge and visible restore disclosure indicator |

## Verification

The actual running application on `http://localhost:8088` was inspected with
Chromium, using local fictional demo records and the documented password + TOTP
login. Automated fixture tests use disposable local accounts and records and
clean them up afterward.

Screens checked include dashboard, overview, all resource lists and available
create/edit forms, article/page/service/event editors, documents/media/galleries,
people/departments, organizations/locations, council data, navigation/redirects,
quality panels, proposals/review, revisions/restoration, settings, users and
account security. Layout sweeps run at 390, 768, 1024 and 1440px; the fixture
suite also checks 320px and expanded optional sections. Screenshots were reviewed
at desktop, tablet and phone widths, including lower editor sections and menus.

Final verification passed: 436 PHP tests (2,762 assertions), 69 browser tests,
PHPStan level 8, Pint, production build and dependency audits. The rendered
sweep checked 86 screens at four widths (344 successful responses) with no
page-level overflow or JavaScript errors; the expanded axe sweep found no
violations. Full command results are recorded in
[visual-verification.md](visual-verification.md). Browser regressions cover keyboard block add/order/remove,
no-JavaScript editing, publication/scheduling/archive wording, navigation target
selection, menu Escape/focus return, validation focus/value retention and optional
section reopening, expanded forms, account management, reduced motion and forced
colours. Axe checks include expanded controls, not just the initial view.

## Manual review

- Try a normal Fachbereich employee's real editing sequence: create a draft,
  propose a change to published content, submit it, and have a different employee
  approve or reject it. Confirm the group names fit local terminology.
- Review the “Weitere” menu, optional section defaults and selected-entry counts
  with employees using realistic record volumes.
- Check Safari/iPad/iPhone date controls and sticky actions with the on-screen
  keyboard; use VoiceOver/NVDA and browser zoom/text spacing for platform QA.
- Verify real document accessibility, publication windows and archive policy.
  Automated quality advice does not certify files or content.

No real accounts or public content were changed during the visual inspection.
No approval/rejection, restoration, deletion or recovery-code regeneration was
performed against demo content; workflow mutations in tests use disposable fixtures.
