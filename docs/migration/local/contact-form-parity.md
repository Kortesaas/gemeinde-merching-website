# Email form comparison — 10 October 2026

Source: [the live eMail-Formular](https://www.gemeinde-merching.de/email-formular/) and the active primary `wp_` form 1087 referenced by public page 138. The live HTML was checked read-only; no form was submitted to the old website. The alternative backup prefix is not used for recipient routing.

The dropdown uses all 15 existing category labels in the original order, mapped to the existing email addresses. “Aliases” here means the public category names, as clarified by the user; no email-server aliases are created. [contact-categories.csv](contact-categories.csv) lists labels and counts without addresses. Addresses are selectively extracted into the ignored prepared manifest and stored through encrypted `ContactRoute.recipients`; the routing configuration never enters the form’s public HTML, search, revisions, audit metadata or this report. Independently published contact information remains available in the municipal directory. The first category retains the existing central route identity.

| Legacy feature | New implementation |
|---|---|
| 15 recipient categories | All 15 original labels and backing addresses, including shared role mailboxes |
| General category selected initially | General contact route selected; visitors can choose any active category |
| Required name and email | Visible labels and server validation; email used as Reply-To |
| Required postal address | Street/house number, postal code and city with autocomplete |
| Required subject | Validated subject included in the staff email and receipt |
| Optional message | Optional for ordinary enquiries; larger message limit retained |
| Email or postal reply | Explicit radio choice included in both email copies |
| Privacy acceptance | Required checkbox linked to the local privacy page |
| Image CAPTCHA | Existing accessible honeypot, timing/token, CSRF and rate-limit protection replaces the image challenge |
| Automatic sender confirmation | Plain-text receipt with enquiry copy, address and reply preference; shows the category label rather than the hidden recipient address |
| Submission feedback | Accessible validation summary and success page; receipt failure preserves successful staff delivery and tells visitors not to resend |
| Existing form URL | `/email-formular` serves the complete native form, posting through `/kontakt`; query/trailing-slash legacy mappings remain one hop |

The optional telephone field and contextual website-error reporting remain available. Contextual feedback stays a short form without requiring a postal address or subject. Both forms require privacy acknowledgement. Citizen details and messages are not retained as database messages or flashed into the session; valid details remain only in the browser after validation errors.

[contact-delivery.json](contact-delivery.json) records a synthetic local Standesamt enquiry: Mailpit captured both the staff email and sender confirmation, including the postal address and postal-reply preference. No live email was sent. The integrity audit checks all 15 mappings, activation/order, encryption/serialization and stable route IDs across repeat import. Browser checks compare every dropdown label with the saved live HTML and verify reflow, accessibility, no third-party requests/storage and absence of recipient addresses at 320/1280px.
