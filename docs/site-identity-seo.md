# Site identity, sharing and SEO

All metadata is rendered by Blade on the server. No extra JavaScript, network
service, tracking, cookie, browser-storage write or runtime dependency was added.

## Local identity assets

The exact existing `resources/images/wappen-merching-prototype.png` is reused,
without redrawing or replacing the logo. `scripts/identity/generate.mjs` derives
the committed assets using the existing local Playwright dependency and the
self-hosted Atkinson font, while blocking network requests. Regenerate with:

```sh
node scripts/identity/generate.mjs
```

- `/favicon.ico`: PNG-backed 16/32/48 px entries.
- `/identity/favicon-16.png`, `favicon-32.png`, `favicon-48.png`: transparent icons.
- `/identity/favicon.svg`: self-contained SVG embedding the original Wappen,
  rather than a newly drawn vector logo.
- `/identity/safari-pinned-tab.svg`: one black vector layer traced deterministically
  from the source's dark areas, retaining its gold areas as transparent cutouts.
  Uses Apple's 16×16 viewBox and the existing blue tint in `rel="mask-icon"`.
- `/identity/apple-touch-icon.png`: 180 px on white with padding.
- `/identity/icon-192.png`, `icon-512.png`: app/shortcut icons.
- `/identity/icon-maskable-512.png`: opaque icon with the Wappen inside the
  maskable safe area; no cropped coat of arms.
- `/identity/social-default.png`: 1200×630 PNG with the Wappen, Gemeinde Merching,
  existing Landkreis tagline and canonical host, in the existing design system.
- `/site.webmanifest`: local icons, name/language/theme, root start/scope and
  browser display mode. This adds shortcut identity, not a service worker,
  offline cache or separate fullscreen application.

These static files ship with the normal public release directory. Apache serves
the manifest as `application/manifest+json`. Identity paths are reserved against
CMS routes and redirects. Replace/regenerate these derivatives whenever the
approved source Wappen changes.

## Metadata and fallback order

`config/seo.php` and `SeoUrl` identify the production origin as
`https://www.gemeinde-merching.de` independently of local `APP_URL` and request
headers. Canonical, Open Graph, Twitter image, Schema.org and sitemap URLs use
that absolute HTTPS origin. Paths have no trailing slash except the root `/`;
tracking/filter query parameters are not copied into page canonicals.
Actual browser icon/manifest links remain root-relative, so development and
production load assets from their own origin with no cross-origin asset calls.

Titles prefer editorial `seo_title`, otherwise the public record's title or
the template's page title. The homepage uses the configured homepage SEO title
or municipality name. The municipality suffix is added once. Form-error titles
keep their existing `Fehler:` prefix.

Descriptions prefer nonempty editorial `meta_description`, summary, plain text
derived from description/body, a section-specific description, a generic sentence
naming the record, then the configured site default or built-in municipality
description. Whitespace is normalized and descriptions are limited to 180
characters. Blade escapes every attribute and structured-data value.

Open Graph includes title, description, URL, website/article type, site name,
German locale (`de_DE`), image, secure URL, dimensions, MIME type and alternative
text. X/Twitter includes `summary_large_image`, title, description, image and alt;
no unofficial municipal social-account handle is invented.

Content sharing images use the first eligible attached Media in the existing
editorial order; gallery pages use their ordered items. Publication windows,
deletion, accessible alternatives, raster type/dimensions and stored-file
existence are checked. The existing `/medien/{id}` endpoint rechecks public
ownership/authorization when a preview fetches the original image. Original
dimensions and MIME type are advertised, with no misleading crop dimensions.
Private, scheduled, inaccessible or missing images fall back to the branded
image. Homepage/listing pages use the stable branded default. No new CMS image
field or database migration was needed.

## Visibility, robots and sitemap

The production **and** `PUBLIC_INDEXING=true` gate remains required. Per-record
noindex and backend/contact/search restrictions stay in force. Draft/private
content remains unavailable; admin/proposal/error responses emit no public
canonical, Open Graph or municipality structured data. No preview route was added.

The existing live sitemap now also lists real fallback listing pages. Managed
routes and redirects take precedence, so a private/noindex managed page cannot
be reintroduced as a fallback entry. Public archives/downloads retain their
existing rules, and sitemap parts plus robots.txt use the same production origin.

## Structured data

`StructuredData` renders escaped Schema.org **microdata** in inert hidden HTML,
without inline scripts or any CSP relaxation. Public pages describe `WebPage`,
`WebSite`, `GovernmentOrganization` and `ImageObject`. Articles add `NewsArticle`
with known publication/modification dates and an author only when supplied.
Events add `Event`, dates in the existing site time zone (date-only for all-day
events), cancellation status, and only public venues/organizers or supplied free
text. Services add `GovernmentService`. Search and contact routes omit this data.
No invented prices, ratings, coordinates, opening hours or social profiles are
added. Schema vocabulary URLs are identifiers, not fetched resources.

Municipality details must come from the real imported/configured public content.
The organization currently emits its configured name, canonical URL and logo;
address, telephone and opening hours are omitted rather than filled with demo
defaults. Event venue addresses come directly from published venue records.
Development/demo records are preview content only and must be replaced with real
records before launch; their details must not be copied into production metadata.

References: [Apple pinned icon format](https://developer.apple.com/library/archive/documentation/AppleApplications/Reference/SafariWebContent/pinnedTabs/pinnedTabs.html), [Open Graph](https://ogp.me/),
[GovernmentOrganization](https://schema.org/GovernmentOrganization),
[GovernmentService](https://schema.org/GovernmentService),
[Event](https://schema.org/Event), and
[Google's supported structured-data formats](https://developers.google.com/search/docs/appearance/structured-data/intro-structured-data).

## Verification

`SeoMetadataTest` exercises rendered home, page, article, event, service, gallery,
listing, admin and error metadata; production/slashless URLs; escaped overrides;
Media eligibility/fallbacks; icon dimensions; sitemap exclusions and robots.
`seo-metadata.spec.js` checks real static asset delivery/MIME types, manifest,
server-rendered microdata with JavaScript disabled, production sharing values,
no foreign requests/CSP errors and no new public cookies/storage.
Live Discord/WhatsApp/Facebook/X previews depend on deployment, public crawler
access and those platforms' caches; no content was sent to an external validator.
