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

Future stateful public features (e.g. a contact form) may set a strictly
necessary session cookie **only on those routes**, and only when they are
actually used.

## Backend

| Data | Why | Retention |
|---|---|---|
| Account (name, e-mail, password hash, active flag, last login) | Authentication, accountability | While the account exists |
| TOTP secret (encrypted), recovery-code hashes | MFA | While MFA is enabled |
| Session (encrypted payload, user ID, last activity) | Login state | Idle 60 min / browser close / absolute 10 h; expired rows removed by lottery |
| Session cookie (`__Host-merching_session`) | Technically necessary for login | Session cookie (browser close) |
| Audit events (user, action, record, time, safe metadata) | Accountability, security investigations | To be defined (open issue) |
| Rate-limit entries (SHA-256 of IP, counter) | Brute-force protection | ≤ 5 minutes in the cache table |
| Password reset tokens (hashed) | Password reset | 60 minutes |

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
in production). Mail content contains only the reset link.

## Checklist for new features

1. Does it need personal data at all? Collect the minimum.
2. Does it set a cookie or use storage? Only if strictly necessary, only on
   the routes that need it.
3. Does it load anything from another host? Then it needs a privacy decision
   and a CSP change – default answer: self-host or don't.
4. What is logged? No personal data or secrets.
5. How long is it kept, and how is it deleted?
6. Update this document and the privacy policy.
