# Plugin-specific inventory

Evidence: primary production SQL, WXR, all nested archives, and the GET-only live crawl. Versions below are backup header values, not security assessments. SQL contains 33 active plugin paths; 37 plugin headers were found. WPvivid lists itself active but its plugin source is absent from these backup archives, a backup exclusion rather than proof it was inactive. No Elementor plugin or `_elementor_data` content was found.

## Public content mechanisms

- **Download Manager:** 102 package posts total, 96 published; `__wpdm_files`, categories and category/tree shortcodes drive public file lists. Files outside normal dated uploads occur in `uploads/download-manager-files`. Direct paths may return 403 while managed downloads work. Never infer a missing source from 403.
- **WP-Filebase:** 52 file records / 13 categories and files under `uploads/filebase`. Legacy data remains even though the plugin is not active in the plugin list. Compare copies/IDs against Download Manager and preserve live legacy query/file URLs. One attachment points to a dynamic thumbnail rather than a normal original.
- **TablePress:** 13 tables with a separate shortcode-ID -> post-ID map, not equal IDs. `tablepress.csv` links the actual uses. Council (ID 7), services A–Z (ID 5), administration (ID 1), associations (ID 4), statistics (8–10) and calendar (ID 15) contain real public information. Tables 2/3 and old homepage intro tables need explicit obsolete/orphan review. Do not parse every row as a person/service/event.
- **NextGEN/Imagely:** 8 gallery records, 314 picture records and 1 album. Gallery/post helper CPTs are drafts even when galleries render publicly, so post status alone is inadequate. Album order is base64-encoded JSON; gallery items have explicit order/exclusion and preview relationships. Originals live under `wp-content/gallery`, outside uploads; 61 originals are absent from the backup but verified live. Generated gallery thumbnails must not substitute for originals.
- **Link Library:** 79 CPT rows (61 published, 5 private, 13 draft), 64 legacy link rows (including nonvisible links), categories and metadata. `legacy_link_id`/URLs support deduplication. Submitter identities, email/recipient values and operational visit counters were not exported.
- **Connections:** 26 approved/public organization records, 6 address rows and 9 phone rows. The plugin is not active in the DB plugin list; `[connections]` remains in page content. This is legacy evidence, not automatic authority over the live TablePress directories.
- **PublishPress Future:** 4 workflow CPT drafts, 126 action-argument records and expiration postmeta. 21 enabled actions relate to currently published records; review action/newStatus and Europe/Berlin timezone before mapping expiry. Scheduler/debug logs are operational, not public content.
- **Contact Form 7:** 2 form definitions with public field-type inventories. Mail templates/internal recipients and submissions are intentionally excluded. CAPTCHA/privacy acceptance require mapping to the existing contact workflow, not copying plugin code.
- **Legacy calendars:** TotalSoft config tables contain 4/2 config rows but no event rows; old event taxonomies remain. Actual current calendar evidence is TablePress table 15, with 83 rows after header requiring date/heading separation.
- **Content Views/List Category Posts:** 6 view definitions and category-list shortcode dependencies exist. Convert query-driven presentation to the new catalogs/filters; do not carry view-builder/plugin HTML.
- **PDF Embedder, HTML Sitemap, cookie/accessibility widgets:** convert embeds to secure Document downloads and managed links, use the CMS's sitemap and existing consent/accessibility controls. Plugin skins/scripts are not migration content.
- **Encyclopedia:** 3 SQL posts, 2 published, absent from WXR; manual review needed. WP Help (32 entries), compatibility jobs, user/security/cache/backup tables and theme/global-style data are operational/editorial support, not public content to import.

## Installed plugin headers

| Name | Directory | Version | DB activity |
| --- | --- | --- | --- |
| AccessiYes Accessibility Widget for ADA, EAA & WCAG Readiness | accessibility-widget | 3.2.6 | not active in DB |
| Advanced Image Styles | advanced-image-styles | 0.4.1 | active |
| Import / Export Customizer Settings | astra-import-export | 1.1.0 | active |
| Astra Widgets | astra-widgets | 1.2.17 | active |
| Authenticator | authenticator | 1.3.1 | not active in DB |
| BackWPup | backwpup | 5.7.5 | not active in DB |
| Better Plugin Compatibility Control | better-plugin-compatibility-control | 7.1.0 | active |
| Classic Editor | classic-editor | 1.7.0 | active |
| Classic Widgets | classic-widgets | 0.3 | active |
| CMS Tree Page View | cms-tree-page-view | 2.5.1 | active |
| Contact Form 7 | contact-form-7 | 6.1.7 | active |
| Content Views - Post Grid & Filter (Shortcode, Blocks, Elementor Widgets) | content-views-query-and-display-post-page | 4.5.1.1 | active |
| Cookie Cracker | cookie-cracker | 1.2 | active |
| Customizer Search | customizer-search | 1.2.1 | active |
| Download Manager | download-manager | 3.3.68 | active |
| XML Sitemap Generator for Google | google-sitemap-generator | 4.1.24 | active |
| Gutenberg | gutenberg | 23.8.0 | not active in DB |
| HTML Page Sitemap (Block and Shortcode) | html-sitemap | 2.2 | active |
| Link Library | link-library | 7.9.5 | active |
| List category posts | list-category-posts | 0.96.0 | active |
| Local Google Fonts | local-google-fonts | 0.24.0 | active |
| NextGEN Gallery | nextgen-gallery | 4.4.0 | active |
| PDF Embedder | pdf-embedder | 5.0.2 | active |
| Web Accessibility - WCAG Scanning, Guided Fixes, Usability Widget | pojo-accessibility | 4.1.4 | active |
| PublishPress Future Free | post-expirator | 4.10.5 | active |
| Content Views Pro | pt-content-views-pro | 7.4.1 | active |
| Really Simple CAPTCHA | really-simple-captcha | 2.4 | active |
| Redirector | redirector | 3.0.1 | active |
| Sidebar Manager | sidebar-manager | 2.0.0 | active |
| TablePress | tablepress | 3.3.4 | active |
| User Locker | user-locker | 1.2 | active |
| User Role Editor | user-role-editor | 4.66.1 | active |
| Wordfence Security | wordfence | 9.0.2 | active |
| Database Backup for WordPress | wp-db-backup | 2.5.3 | not active in DB |
| WP Help | wp-help | 1.7.5 | active |
| WP Rollback | wp-rollback | 3.1.2 | active |
| WPDM - Extended Short-codes | wpdm-extended-shortcodes | 3.0.4 | active |
