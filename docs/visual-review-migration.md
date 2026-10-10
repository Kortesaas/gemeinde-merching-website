# Migrated public website: visual review

Reviewed the actual `merching_migration` site at http://localhost:8089 on 10 October 2026. The demo site on port 8088 was used only for the existing synthetic regression fixtures. No migrated database records, factual text, source PDFs, publication states, or canonical paths were changed.

## Review coverage

Rendered pages were inspected in the live browser and through full-page browser-test screenshots at **390, 768, 1024 and 1440px**. The contact form additionally retains coverage at 320 and 1280px. Representative routes cover:

| Content | Routes / examples |
| --- | --- |
| Home and welcome | `/`, `/willkommen` |
| News | `/aktuelles`, the Brunnen railway-closure article with its real image and title |
| Services | `/buergerservice`, `/buergerservice/a-z`, `/buergerservice/leistungen/personalausweis` |
| Administration / council | `/rathaus-und-politik/verwaltung`, `/rathaus-und-politik/gemeinderat`, `/verzeichnisse/stellen/einwohnermeldeamt-6` |
| Budgets | `/haushaltsplaene`, `/haushaltsplaene/2026/gemeinde-merching` |
| Events | `/veranstaltungen`, `/veranstaltungen/offener-stammtisch-13`, `/veranstaltungen/veroeffentlichter-kalender` |
| Downloads / notices | `/formulare`, `/dokumente`, `/bekanntmachungen`, the 2027 cemetery-fee notice |
| Directories | `/vereine`, its second page, a club detail, a business detail with a particularly long title, `/gastronomiebetriebe` |
| Galleries | `/bildergalerie-merching`, `/galerien/naherholungsgebiet-mandichosee` |
| Mixed content / tables | `/bauen-und-wirtschaft`, `/standortfaktoren-leben-und-wohnen-in-merching`, `/leben/kinder-und-jugend` |
| Contact and long text | `/email-formular` / `/kontakt`, `/impressum`, `/datenschutz`, `/barrierefreiheit` |

The exact routes are recorded in `tests/Browser/migration.spec.js`. Local review screenshots and width comparison boards are in the ignored `migration-source/visual-review/` directory.

## Main changes

- Structured content can use the available canvas, while long prose retains the existing 46rem reading measure. Single resources keep a comfortable width; multi-resource sections use two columns where there is room.
- Homepage lead news now places its image beside the text on larger screens. The mobile welcome portrait sits beside the salutation, balancing the page without adding a large image before the message.
- Small imported portraits are no longer enlarged. Portrait-and-prose blocks form editorial layouts on wider screens; informational images retain their full aspect ratio and are bounded in size.
- News cards with municipal crests use compact symbol previews. Real photography still has appropriate visual prominence.
- Administration contacts fill the available columns without reserving an empty third column. Repeated job/responsibility labels appear once.
- Department contact panels precede long service lists in the DOM and appear first on mobile/tablet, with a desktop sidebar. Department service links use two columns on tablet.
- Budgets use a three-package overview on desktop, two columns on tablet and one on mobile. Individual source downloads are numbered, with filenames and file metadata separated for scanning, in the original source order.
- Repeated TablePress name/value records become semantic definition panels. Comparison tables stay tables with captions and column headers. Wide calendars have useful column widths, zebra rows and explicit keyboard-scroll instructions. Two-column tables wrap within mobile screens.
- Standalone navigation links use responsive indices. Gallery indices have real album previews, and gallery indices/albums use two columns on mobile.
- Organization listings show 24 entries per page, preserve filters in pagination, and use existing organization types as grouping labels when no category exists. Their complete imported overview remains available in a native expandable section. Mobile directory entries use compact separated rows.
- Formulare presents each identical referenced download/link once within a section; the stored blocks and deliberate repetitions under separate headings remain intact.
- The event calendar stays beside the list where space permits and starts collapsed on mobile. Native disclosure works without JavaScript; links to `#kalender` open it normally.
- Empty organization contact panels are replaced by a quiet statement that no further contact details are published. Long titles wrap within the layout.
- Accessibility-dialog close handling preserves a visitor's next focus target instead of a delayed event stealing it back.

Existing design tokens, local assets, safe Markdown, semantic HTML, reduced-motion/forced-colour settings, privacy behavior and server-rendered navigation are retained. No dependencies or remote resources were added.

## Validation

Browser checks include axe WCAG A/AA and best-practice rules, horizontal reflow, heading structure, image loading, keyboard navigation, anonymous storage/cookies, third-party requests, real search and budget downloads. Images below the fold are scrolled into view and decoded before the final screenshots. Organization pagination, table record preservation and per-section reference deduplication have feature coverage.

| Check | Result |
| --- | --- |
| `npm run build` | Passed |
| Full PHPUnit suite, explicit `merching_testing` database | 477 passed, 3,017 assertions |
| Follow-up composition / organization / table feature tests after later changes | 35 passed; final table recheck 4 passed |
| Existing browser suite on 8088 with isolated parity fixtures | 76 passed; migration-only cases skipped in this mode |
| Real migration / contact / headings checks on 8089 | 14 passed, plus the added department check at all four widths |
| Real migration checks with all below-fold images loaded | 5 passed |
| Final affected browser regressions | 10 passed, then 6 passed after the department refinement |
| Laravel Pint / PHPStan | Passed, no static-analysis errors |
| Python inventory tests | 11 passed |
| `git diff --check` | Passed |

The full browser run uses one worker and a 180-second timeout to accommodate its multi-screen CMS and display-colour axe scans. The real-content run uses two workers. An early parallel run hit a temporary missing Vite manifest while a build was replacing assets; final browser runs used a completed build. The display-preference focus regression found during testing was fixed and passes in both the full suite and targeted rechecks.

The migrated Rathaus location has no standalone public route; its address/contact presentation was inspected on administration, contact and imprint pages. No synthetic location detail was substituted for the actual migrated content.

## Manual follow-up

- Review on a physical iPhone/Android device and in Firefox/Safari, including touch scrolling of the wide published calendar and enlarged text. Automated regression coverage uses Chromium; the live browser review also inspected the rendered layouts.
- Source asset quality still limits small portraits and some older photographs. The review avoids upscaling them; replacing these requires better original files.
- Some imported directory fields are blank, and the original Gastronomie table includes literal “zurück” remnants. Stored content is preserved; an editor can review those source details separately.
- The migrated `/vereine` catalog includes Gewerbe records as well as clubs. The layout labels those existing types clearly; review the intended editorial scope before changing classification/content.
- Review legacy prose formatting (including literal Markdown markers in Datenschutz) and source PDF accessibility. This layout review does not rewrite the statements or modify the documents.

## Requested refinements (10 October 2026)

- Homepage: added the mail icon beside “Nachricht schreiben”; increased the desktop mayor portrait from 10rem to up to 13rem; combined the visual paragraph flow after the salutation while preserving every word and the underlying paragraph semantics.
- Ortsrecht / Gemeindekurier: replaced the public decorative source illustration with the existing local Wappen in a responsive page heading. Source images remain in the media library.
- Leistungen A–Z: render alternative phone numbers on separate lines. Each link dials its own number, including expanded “bzw. -20” extensions; source labels stay unchanged.
- Bürgerserviceportal: text and the original logo form a desktop editorial row, with the logo centred in the right column; small screens stack them.
- Formulare: a short explanation distinguishes external provider forms (WEB badge) from stored PDF documents. Both use visibly linked titles and consistent rows. Original order, labels, destinations and files are preserved.
- Public external website / document links open in a new tab, including Markdown, tables, navigation and managed document routes. Response middleware adds `target="_blank"`, `rel="noopener noreferrer"` and an accessible new-tab description. Internal HTML pages, phone links, email links and in-page anchors retain their existing behavior; no JavaScript is required.
- Veranstaltungen: one complete selected month, without the former 20-event truncation; previous/next links replace both list and calendar. The current month is kept when filtering or resetting filters. Navigation works without JavaScript; enhanced navigation retains keyboard focus, announces the month/count, and preserves the mobile calendar disclosure. Public past events can be browsed by month; private/draft events remain excluded. Multi-day events are included in every relevant month, respecting exclusive all-day end dates.
- Bekanntmachungen: removed the duplicate imported PDF overview when its files are already represented by public notices; added the explicit “Bekanntmachungen finden” search section and direct PDF links in each row. A date without a verified legal publication date is labelled “Veröffentlicht am”.
- Ausschreibungen: the site owner confirmed the original Content Views selection was empty. Corrected the migration preparation and the local prepared manifest, replaced only this page's incorrectly expanded news links with an empty-state sentence, saved before/after revisions, and updated the page's search entry. Original articles and notices remain published in their proper sections.

Additional regression coverage checks the new link policy and separate telephone destinations, month filtering without truncation, spanning/all-day dates, the empty tender import, all affected real pages at 390/768/1024/1440px, safe document/external targets, and month switching with and without JavaScript.

Manual follow-up: check the externally hosted form services in a browser, the actual source PDFs' accessibility, and physical-device/Safari/Firefox rendering. The automated privacy review verifies the site itself makes no third-party requests; it does not submit applications to external services.

Final refinement validation: full PHP suite 485 passed (3,059 assertions); 45 affected PHP tests passed after the shared contact/phone refinement; all 76 existing browser cases passed across the full run and targeted rechecks; all 16 real-migration browser cases passed with isolated output directories; 12 inventory/conversion tests, Vite build, Pint (386 files), PHPStan and whitespace checks passed. The original parallel browser invocations collided in Playwright's shared trace-output directory; the affected cases and the three serial cases they skipped all passed when rerun into separate output directories. No application failure remained.
