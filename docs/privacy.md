# Privacy (Datenschutz)

The website of a German municipality must follow privacy by design and by
default (Art. 25 GDPR) and must not require consent for merely reading it
(§ 25 TDDDG). The foundation is built so that no consent banner is needed.

> This document describes the technical implementation. It does not replace
> the legal review by the municipality's data protection officer or the
> privacy policy (Datenschutzerklärung) text.

## Public website

| Topic | Implementation |
|---|---|
| Cookies | **None** for visitors reading the website. Public routes run in the stateless `public` middleware group: no session, no CSRF cookie, no tracking. Tested in PHPUnit (`PlaceholderPageTest`) and in the browser (`tests/Browser/privacy.spec.js`). |
| Browser storage | No `localStorage`/`sessionStorage` (browser test). |
| Third-party requests | None. No Google Fonts or other font CDNs (system fonts now, self-hosted fonts later), no external JS/CSS libraries, no CDNs, no embeds. Tested: rendered HTML contains no foreign hosts; the browser makes no request to another origin. The CSP (`default-src 'self'`) also blocks accidental external resources. |
| Analytics / tracking | None: no Google Analytics, Tag Manager, Meta Pixel, tracking pixels, social widgets, fingerprinting or profiling. |
| Consent banner | Not built and not needed as long as only strictly necessary technology is used. Any future third-party integration (maps, videos, …) requires an individual privacy decision first (e.g. two-click solution, self-hosting). |
| Indexing | Unfinished installations are always `noindex` (see architecture.md). |

The contact form/confirmation at `/kontakt` opts into a necessary session
cookie only on its own routes. It is no-store/noindex and does not retain
message bodies in the database or validation session. See
[site-foundation.md](site-foundation.md#contact-form-and-mail). Search statistics
are disabled by default, with configurable retention and no visitor identifiers.

## Backend

| Data | Why | Retention |
|---|---|---|
| Account (name, e-mail, password hash, active flag, last login) | Authentication, accountability | While the account exists |
| TOTP secret (encrypted), recovery-code hashes | MFA | While MFA is enabled |
| Session (encrypted payload, user ID, last activity) | Login state | Idle 60 min / browser close / absolute 10 h; expired rows removed by lottery |
| Session cookie (`__Host-merching_session`) | Technically necessary for login | Session cookie (browser close) |
| Audit events (user, action, record, time, safe metadata) | Accountability, security investigations | 730 days (`AUDIT_RETENTION_DAYS`) – **provisional, to be confirmed with the Datenschutzbeauftragte before launch**; deleted automatically without cron |
| Rate-limit entries (SHA-256 of IP, counter) | Brute-force protection | ≤ 5 minutes in the cache table |
| Password reset tokens (hashed) | Password reset | 60 minutes |
| Content revisions (editorial snapshots, editor, time) | Editorial history, restore | Keep all until a policy is decided (`REVISION_RETENTION_DAYS`) |
| Contact-route recipient addresses (encrypted) | Internal routing of the contact form | While the route exists |
| Person records (public contact data of staff, no photos) | Public contact information | Deactivated, not deleted, while referenced |

The audit retention period applies only to audit events. Future content
revisions/versions (articles, pages, documents) are a separate topic and will
get their own retention rules together with the content model.

Staff contact data is limited to what is intended for publication (name,
function, phone, public e-mail, room, availability); there is deliberately no
portrait field. Demo/test data never uses real personal data.

Deliberately **not** stored by the application: IP addresses (sessions, audit
log), user agents, attempted login names, request bodies.

## Logs

- Application logs (`storage/logs`) contain errors/warnings only, with secrets
  redacted; no request data is logged by default. 14 days retention.
- **Web server access logs are kept by goneo** (outside the application) and
  usually contain IP addresses. Check goneo's retention/anonymisation settings
  in the customer centre and describe them in the privacy policy.

## E-mail

Password-reset mails are sent via the configured SMTP server (goneo mailbox
in production). Mail content contains only the reset link. Contact messages go to the selected
internal topic via fixed From/validated Reply-To. No mail-body logging is
allowed; application audit retains only delivery action and topic ID. SMTP and
municipal mailbox retention need an operational policy.

## Checklist for new features

1. Does it need personal data at all? Collect the minimum.
2. Does it set a cookie or use storage? Only if strictly necessary, only on
   the routes that need it.
3. Does it load anything from another host? Then it needs a privacy decision
   and a CSP change – default answer: self-host or don't.
4. What is logged? No personal data or secrets.
5. How long is it kept, and how is it deleted?
6. Update this document and the privacy policy.

## Content feedback and composition

Content feedback reuses the existing contact flow. Context is derived from a
public canonical record, held with the short-lived form nonce and rechecked
before mail delivery. Browser-supplied URL/title fields cannot override it.
The mail includes that context; audit metadata does not. No message-body table,
extra persistent identifier or third-party service was introduced.
External-resource/map blocks remain explicit outbound links. Media focal metadata
never restores removed EXIF/GPS data. Council information is separate from employee
records and is published only through the ordinary editorial workflow. No demo
municipal content or portraits were seeded. See [content-parity.md](content-parity.md).
