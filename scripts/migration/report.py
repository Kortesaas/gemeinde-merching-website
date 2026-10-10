#!/usr/bin/env python3
"""Finish sanitized inventory projections and human-readable migration findings."""
import collections,csv,hashlib,json,re,sys
import xml.etree.ElementTree as ET
from pathlib import Path
from urllib.parse import unquote,urlparse,parse_qsl
from inventory import write_csv,digest
from crawl import normalize

OUT=Path('docs/migration/inventory');SRC=Path('migration-source')
def rows(name):
    with (OUT/name).open(encoding='utf-8',newline='') as f:return list(csv.DictReader(f))
def save(name,items):write_csv(OUT,name,items)
def mdtable(items,headings):
    def cell(v):return str(v).replace('|','\\|').replace('\n',' ')
    return '| '+' | '.join(headings)+' |\n| '+' | '.join('---' for _ in headings)+' |\n'+'\n'.join('| '+' | '.join(cell(v) for v in r)+' |' for r in items)

def main():
    c=json.loads((OUT/'counts.json').read_text());p=json.loads((OUT/'plugin-counts.json').read_text());crawl=json.loads((OUT/'crawl-counts.json').read_text());state=json.loads((SRC/'analysis-cache/crawl-state.json').read_text());files=rows('filesystem.csv');media=rows('media.csv');public=rows('content.csv');urls=rows('urls.csv');links=rows('internal-links.csv');missing=rows('missing-files.csv');maps=rows('mapping.csv');review=[r for r in rows('manual-review.csv') if r.get('source_id')!='workflow'];events=rows('event-candidates.csv');tables=rows('tablepress.csv');budgets=rows('budget-components.csv');pdfs=rows('budget-pdf-preflight.csv')
    # Original bytes remain unchanged throughout analysis.
    for r in rows('source-provenance.csv'):
        path=SRC/r['source']
        if digest(path)!=r['sha256']:raise RuntimeError('Source changed during analysis; stop and investigate privately')
    parsed=json.loads((SRC/'analysis-cache/parsed.json').read_text());prefix=parsed['prefix']
    published_ids={r['source_id'] for r in public};primary_meta={};secondary_meta={}
    for source,target in [('production',primary_meta),('secondary',secondary_meta)]:
        for r in parsed[source]['data'].get(prefix+'postmeta',[]):
            if r['post_id'] in published_ids:target[(r['post_id'],r['meta_key'])]=r['meta_value'] or ''
    ns={'wp':'http://wordpress.org/export/1.2/'};wxr_meta={}
    for item in ET.parse(next(SRC.glob('*.xml'))).findall('./channel/item'):
        id=item.findtext('wp:post_id',namespaces=ns)
        if id not in published_ids:continue
        for meta in item.findall('wp:postmeta',ns):wxr_meta[(id,meta.findtext('wp:meta_key',namespaces=ns))]=meta.findtext('wp:meta_value',namespaces=ns) or ''
    meta_diffs=[]
    for label,other in [('secondary DB',secondary_meta),('WXR',wxr_meta)]:
        for key in sorted(set(primary_meta)|set(other)):
            # Only report key/type of discrepancy; never raw values or private plugin fields.
            id,name=key
            if re.search('email|mail|recipient|submitter|secret|password|nonce|token',name or '',re.I):continue
            a,b=primary_meta.get(key),other.get(key)
            if a!=b:
                normalized=a is not None and b is not None and a.replace('\r\n','\n')==b.replace('\r\n','\n')
                meta_diffs.append({'source_id':id,'key':name,'comparison':'SQL/'+label,'issue':'operational editor metadata omitted/differs' if name in {'_edit_lock','_edit_last'} else 'WXR export-only TablePress ID mapping' if name=='_tablepress_export_table_id' else 'line-ending representation only' if normalized else 'key absent or value differs; inspect private source cache','value_exported':False})
    save('postmeta-discrepancies.csv',meta_diffs)
    sourcehashes=collections.Counter(f['sha256'] for f in files);registered={m['path'] for m in media if m['path']};referenced=collections.defaultdict(set)
    for r in links:referenced[unquote(urlparse(r['target_url']).path).lstrip('/')].add(r['source_url'])
    for f in files:
        f['registered_original']=f['path'] in registered
        f['thumbnail_candidate']=bool(re.search(r'-\d+x\d+\.[^.]+$',f['path']) or '/thumbs/' in f['path'])
        f['live_incoming_pages']=len(referenced.get(f['path'],set()))
    save('filesystem.csv',files)
    for r in missing:
        if r['path']:
            u=normalize('https://www.gemeinde-merching.de/'+r['path']);live=state.get(u,{})
            r.update(source_url=u,live_status=live.get('status','not crawled'),live_type=live.get('type',''),resolution='Recover original from live site or a complete backup before cutover' if live.get('status') in (200,206) and live.get('type')=='image' else 'Manual review')
        else:r.update(source_url='',live_status='dynamic attachment reference',live_type='',resolution='Not a regular original file; resolve legacy WP-Filebase thumbnail/replace with verified asset')
    save('missing-files.csv',missing)
    filebase=rows('filebase.csv')
    documents=rows('documents.csv');docpaths={r['path'] for r in documents}
    for f in files:
        if f['extension'] in {'.doc','.docx','.xlsx','.xls','.odt','.ods','.ppt','.pptx','.zip'} and f['path'] not in docpaths:
            documents.append({'source':'filesystem candidate','title':'','path':f['path'],'source_url':'https://www.gemeinde-merching.de/'+f['path'],'public_package_url':'','exists_in_backup':True,'bytes':f['bytes'],'sha256':f['sha256'],'year_candidates':'','budget_candidate':False,'accessibility':'not verified'})
            docpaths.add(f['path'])
    save('documents.csv',documents);unique_docpaths=len(docpaths)
    filebase_missing=sum(r['exists_in_backup']=='False' for r in filebase)
    incoming=collections.Counter(r['target_url'] for r in links if r['kind'] not in {'sitemap'})
    orphans=[]
    for r in public:
        if r['type'] not in {'page','post'}:continue
        url=normalize(r['url']);live=state.get(url,{})
        if live.get('status') in (200,206) and incoming[url]==0:
            orphans.append({'source_id':r['source_id'],'url':url,'title':r['title'],'reason':'Seed-only candidate: no observed incoming HTML link (sitemap edges excluded); not proof of obsolete content'})
    save('orphan-candidates.csv',orphans)
    broken=[]
    for r in links:
        live=state.get(r['target_url'],{})
        if live.get('status') in (404,410):broken.append(r|{'status':live['status'],'reason':'Observed internal link target missing'})
    save('broken-links.csv',broken)
    cross=[]
    for r in public:
        url=normalize(r['url']);live=state.get(url,{})
        cross.append({'source_id':r['source_id'],'type':r['type'],'title':r['title'],'discovered_source_url':r['url'],'requested_url':url,'live_status':live.get('status','not crawled/filtered'),'live_canonical':live.get('canonical',''),'live_type':live.get('type',''),'wxr_present':r['wxr_present'],'review':'Attachment exposure requires public context; inherit is not publication permission' if r['type']=='attachment' else 'WXR numeric CPT links can be noncanonical/broken; use live navigation and managed downloads'})
    save('source-live-crosscheck.csv',cross)
    managed=[]
    for r in public:
        if r['type']!='wpdmpro':continue
        id=r['source_id'];root='https://www.gemeinde-merching.de/?wpdmdl='+id;live=state.get(root,{})
        observed=[u for u in state if dict(parse_qsl(urlparse(u).query)).get('wpdmdl')==id]
        managed.append({'source_id':id,'title':r['title'],'stable_root_url':root,'live_status':live.get('status','not crawled'),'live_type':live.get('type',''),'observed_download_urls':'|'.join(sorted(observed)),'review':'Verified PDF delivery; preserve managed URL/ID' if live.get('type')=='PDF' and live.get('status') in (200,206) else 'Not a successful PDF response; inspect privately, do not silently publish a guessed substitute'})
    save('managed-downloads.csv',managed)
    redirects=[]
    for r in state.values():
        for n,hop in enumerate(r.get('redirect_chain',[])):redirects.append({'request_url':r['url'],'hop':n+1,'source_url':hop['from'],'status':hop['status'],'target_url':hop['to']})
    save('observed-redirects.csv',redirects)
    # Fill classification for public paths discoverable from file metadata but not fetched.
    discovered={r['url'] for r in urls};unverified=[]
    for name in ['documents.csv','media.csv','filebase.csv']:
        for r in rows(name):
            u=normalize(r.get('source_url',''))
            if u and u not in discovered:
                unverified.append({'url':u,'source':name,'classification':'manual review','reason':'Backup/metadata-only candidate; public availability and ownership not established; do not automatically expose'})
                discovered.add(u)
    save('uncrawled-url-candidates.csv',unverified)
    # Topic choice is now supported, but source legal body and numbering still need editorial review.
    for r in maps:
        if r['proposed_model'].startswith('ContentBlock table'):r['proposed_model']='Structured records or reviewed table alternative';r['confidence']='review';r['transformation']='No generic table block exists; resolve table to entities or add a controlled accessible table'
        if r['source']=='post:8722':
            r['proposed_model']='ExternalResource candidate';r['confidence']='review';r['transformation']='Online streetlight-reporting service; file list contains no nonempty source, managed endpoint is not a PDF; verify public link instead of forcing a Document'
        if r['source_type']=='wpdmpro' and r['source'].split(':')[-1] in {b['source_id'] for b in budgets}:
            r['proposed_model']='BudgetPlan source component';r['confidence']='review';r['transformation']='Group by reviewed topic/year and numbered component order; never import 25 components as 25 standalone annual plans'
    save('mapping.csv',maps)
    issues=[
        ('blocking','PDF merge compatibility','3 actual main 2026 budget PDFs rejected by existing FPDI parser; obtain compatible exports or select a hosting-compatible supported parser before importing budgets'),
        ('blocking','Legacy URL delivery','Root query download URLs (?wpdmdl=...) and direct /wp-content/ paths need reviewed secure compatibility delivery. Existing Redirect/PublicRoute only match paths; no query redirect rules implemented'),
        ('blocking','TablePress conversion','No generic controlled table block; map contacts/services/council/events/organizations to structured records; decide accessible alternatives for remaining statistics tables'),
        ('high','Incomplete filesystem backup','61 NextGEN originals absent from backup but live image GETs succeed; recover original bytes before cutover'),
        ('high','Private attachment boundaries','WordPress inherit attachment status does not authorize public exposure; validate parent/public references before publishing any orphan'),
        ('high','Source/model semantics','61 Link Library CPT records, 64 legacy links and 26 Connections records can overlap; deduplicate using public URLs/legacy IDs without importing submitters/internal emails'),
        ('high','Category semantics','Choose one context category for each record; map Topaktuell to featured status and review additional category/tag relationships rather than dropping them'),
        ('high','Expiry','21 enabled Future actions on published posts; timezone Europe/Berlin and newStatus/action must be mapped. Historical disabled jobs and private/draft records are not public data'),
        ('high','Media upload compatibility','4 GIFs, 2 DOC files and 7 ZIPs in content backup; current secure uploads do not accept these formats. Preserve safe convertible content or explicitly review legacy-file handling; do not widen allowlist blindly'),
        ('medium','Calendar parsing','83 TablePress rows after header include headings/separators/date ranges, not 83 guaranteed events. Confirm parsed dates, organizers, venues and duplicates'),
        ('medium','Forms','2 Contact Form 7 definitions; migrate public workflow/labels to existing form, configure internal recipients privately; no submissions, credentials or mail templates in reports'),
        ('medium','Council','26 nonblank council entries/portrait references; separator rows are not members; review term dates, functions and committee memberships; employees remain without portraits'),
        ('medium','External services','Portal/BayernAtlas/captcha/widget embeds must become managed links or existing privacy-safe UI, rather than carrying plugin scripts/iframes'),
        ('medium','Drafts and historical data','17 nonpublished pages, 33 nonpublished posts, 6 nonpublished download packages and inactive-prefix content require an explicit retention decision; do not publish automatically'),
        ('medium','Accessibility','No PDF accessibility claims inferred from Tagged=yes or source labels. All 770 PDFs remain not verified; merged output requires independent review'),
    ]
    save('decisions.csv',[{'priority':a,'area':b,'decision':v} for a,b,v in issues])
    review.extend({'source_id':'workflow','type':b,'issue':v} for a,b,v in issues);save('manual-review.csv',review)
    # Plugin inventories distinguish active public mechanisms from leftover tables.
    plugin_rows=[]
    active={v.split('/')[0] for v in c['active_plugin_paths']}
    for slug,info in sorted(c['plugins'].items()):plugin_rows.append((info['name'],slug,info['version'],'active' if slug in active else 'not active in DB'))
    plugin_md='''# Plugin-specific inventory

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

'''+mdtable(plugin_rows,['Name','Directory','Version','DB activity'])+'\n'
    (OUT/'plugin-inventory.md').write_text(plugin_md)
    findings={'physical_content_files':len(files),'unique_sha256_files':len(sourcehashes),'image_files':sum(f['extension'] in {'.jpg','.jpeg','.png','.gif','.webp','.avif','.svg','.tif','.tiff','.bmp'} for f in files),'pdf_files':sum(f['extension']=='.pdf' for f in files),'source_media_records':len(media),'unique_document_paths':unique_docpaths,'duplicate_groups':sum(v>1 for v in sourcehashes.values()),'redundant_identical_copies':sum(v-1 for v in sourcehashes.values()),'missing_backup_originals':sum(r['path']!='' for r in missing),'missing_dynamic_references':sum(r['path']=='' for r in missing),'filebase_missing':filebase_missing,'seed_only_page_candidates':len(orphans),'broken_link_edges':len(broken),'uncrawled_url_candidates':len(unverified),'event_rows':len(events),'event_rows_with_full_date_candidate':sum(r['date_parse_candidate']=='True' for r in events),'source_hashes_verified_unchanged':True,'managed_downloads_checked':len(managed),'managed_pdf_responses':sum(r['live_type']=='PDF' and r['live_status'] in (200,206) for r in managed),'postmeta_discrepancies':len(meta_diffs),'readiness':'Ready to design importer; not ready to run a complete production import until blocking decisions/preflights are resolved'}
    (OUT/'findings.json').write_text(json.dumps(findings,indent=2)+'\n')
    content_table=mdtable([(k,v) for k,v in c['public_content_types'].items()]+[('nav_menu_item (published)',51),('NextGEN galleries / pictures / album','8 / 314 / 1'),('Connections approved/public',26),('Contact Form 7 definitions',2)],['Source type / scope','Count'])
    summary=f'''# Gemeinde Merching migration inventory — 10 October 2026

Inventory/analysis only. No WordPress content imported into Laravel; no demo data cleared; no old-site writes, form submissions/login, deployment or push. The only CMS change during this phase was the user-authorized budget model correction: several independent packages per year with **Thema / Art**, including a Nachtrag or Berichtigung. Existing demo records, source PDFs and historical proofs were preserved.

## Evidence and confidence

Primary source is `69246m55851_1.sql`, cross-checked with WXR, the secondary WPvivid DB, all 10 outer ZIPs (12 nested filesystem archives), and the live public site. Original input SHA-256/size manifests are in `source-provenance.csv`; all were rechecked unchanged. Raw SQL/XML, archive copies, PDF preflight copies, HTML and crawler state stay in ignored `migration-source/analysis-cache/`; no raw source data is committed.

**Use `wp_`, not `wpd754ae`.** The dump includes both prefixes. `wp_` has 3,517 post rows and the last modification is 2026-10-09; `wpd754ae` has 3,676 rows and last modification 2025-02-20. The secondary backup has only `wp_`; its editorial posts match the active primary rows. WXR ID/title relationships, current public content and matching home/siteurl also support `wp_`. No `wp-config.php` exists in the supplied filesystem archives, so prefix verification is evidence-based rather than a config-file confirmation. Do not merge both prefixes.

SQL/WXR discrepancies: **85 CRLF/LF representation differences only**, and **2 published Encyclopedia records omitted by WXR**. No substantive active-prefix post title/content/status/modified differences were found against the secondary DB. Postmeta was also compared against both sources; `postmeta-discrepancies.csv` records key-level differences without exporting values. Table counts/operational options can differ with backup time; `tables.csv` records them. WXR contains drafts/pending/private/helper posts and is not a list of public content.

## 1. Content discovered

{content_table}

The 55 published pages are a subset of 72 total pages (9 draft, 7 pending, 1 private). SQL has 99 news posts (66 published, 29 draft, 2 trash, 2 auto-draft); WXR has 97, omitting the two auto-drafts. Download Manager has 102 packages (96 published, 5 draft, 1 trash). These counts exclude 2,114 revision rows and operational/helper CPTs from public migration totals. Full type/status counts are in `counts.json`, table counts in `tables.csv`, and public/editorial candidates in `content.csv`.

## 2. Public crawl and URL classification

**{crawl['crawled']} URL requests recorded**, including {crawl['types'].get('page',0)} HTML responses, {crawl['types'].get('PDF',0)} PDF responses and {crawl['types'].get('image',0)} images. Counts include aliases, error pages and seeded source URLs, not that many unique editorial pages. Requests were GET-only, two workers, globally paced at 0.6 seconds, following robots.txt. External domains were recorded but never crawled; forms were counted but never submitted. Static styles/scripts and their local CSS assets were included. Download Manager `data-downloadurl` links were resolved with the transient `refresh` cache-buster removed; stable root `?wpdmdl=ID` URLs were checked independently. JavaScript was not executed. HTML/XML/CSS bodies are bounded at 2 MiB; file responses are sampled for status/type, not downloaded wholesale. Final queue: {crawl['pending']}; robot exclusions: {crawl['robots_blocked']}. Request errors/restrictions remain explicit in `urls.csv`.

Classifications: {', '.join(f"**{n} {name}**" for name,n in crawl['classifications'].items())}. Targets are **proposals**, not implemented redirects. Trailing-slash pages usually retain the same slug with one 301 to the slashless canonical path; root stays `/`. WXR numeric CPT permalinks can be 404 while current managed downloads still work. Do not delete a document because a WXR convenience permalink is broken. {findings['uncrawled_url_candidates']} metadata/backup-only URL candidates have explicit manual-review classifications, without assumed public exposure.

`observed-redirects.csv` records actual hops; `redirect-candidates.csv` proposes alias/canonical actions. `internal-links.csv`, `broken-links.csv`, `source-live-crosscheck.csv` and `orphan-candidates.csv` distinguish linked failures, seeded candidates and content provenance. Stable managed download checks: {len(managed)} published package IDs checked; {sum(r['live_type']=='PDF' and r['live_status'] in (200,206) for r in managed)} return PDFs. Package 8722 (streetlight-reporting online service) has no nonempty file source and returns HTML; review it as an ExternalResource candidate rather than assuming a missing PDF. `managed-downloads.csv` distinguishes successful delivery from non-PDF/error responses. Protected Download Manager paths return **403**, not “file missing”; managed download URLs must be preserved separately. RSS/feed URLs and query-based routes require an explicit compatibility decision.

## 3. Documents, media and galleries

The content filesystem contains **{len(files)} files**, including **{findings['pdf_files']} PDFs** and **{findings['image_files']} images** (including generated derivatives). `media.csv` has 579 WordPress attachment records plus 314 NextGEN picture records; these overlap physical files and are not additive unique file counts. `documents.csv` inventories {unique_docpaths} distinct downloadable file paths (including Word/archive candidates), while `filebase.csv` separately retains 52 legacy file references. `filesystem.csv` distinguishes registered originals, thumbnail candidates and unreferenced candidates. {findings['duplicate_groups']} SHA-256 duplicate groups account for {findings['redundant_identical_copies']} redundant byte-identical copies; identical bytes alone do not prove identical legal/editorial meaning.

**61 NextGEN originals absent from backup are still reachable live as images.** They must be recovered before the old site is taken down; full original recovery was not performed in this analysis. One additional attachment is a synthetic WP-Filebase thumbnail reference rather than a normal original path. None of the 52 legacy Filebase paths is missing in the supplied backup. Unreferenced files are candidate orphans, not a deletion list. Generated thumbnails, scaled originals, WordPress metadata and gallery/album ordering require explicit source choice; employee photographs must not be imported as Person portraits.

All PDFs remain **accessibility not verified**. A `Tagged: yes` PDF flag is insufficient to claim accessibility. The report contains no extracted PDF text or private submissions.

## 4. Important plugin content

There is **no Elementor content**. Classic/Gutenberg HTML plus shortcodes are the main transformation inputs. Found: 13 TablePress tables, 8 NextGEN galleries/314 images/1 album, 96 published Download Manager packages, 52 Filebase records, 61 published Link Library CPT records plus legacy link tables, 26 Connections organizations, 2 Contact Form 7 definitions, 6 Content Views definitions, and 21 enabled Future actions on published posts. See `plugin-inventory.md`, shortcode/table maps, gallery item/album relationships and expiry inventories. Security logs, user credentials, MFA secrets, form submissions/mail templates and backup/cache data were excluded from reports.

## 5. What maps with high confidence

Ordinary news records -> Article with original dates, context categories/tags and SourceReference; ordinary editorial pages -> Page; verified original images -> Media; NextGEN -> Gallery/ordered GalleryItem; Download Manager/Filebase -> secure Document with reviewed deduplication; public link URL/name -> ExternalResource; menus -> NavigationItem/PublicRoute relationships. Source identity and URL/hash relationships can be automated. Publication, PDF accessibility, alt/copyright gaps and ambiguous private/orphan context must still be reviewed before publishing.

No real records or redirects have been created. `mapping.csv` is a proposal ledger, not an importer.

## 6. Transformation work

Sanitize basic paragraphs/lists/links into safe Markdown/text blocks; lift headings, images, galleries and downloads into explicit controlled blocks. Replace `[pdf-embedder]` with secure Document components, `[wpdm_category]`/tree and category listings with catalogs, and gallery/table/form shortcodes with structured records or reviewed alternatives. Do not retain WordPress/Elementor/plugin wrappers, inline styles, scripts, iframes or visual-builder JSON. A separate dry-run converter with per-block diagnostics and manual fallbacks is needed before import.

TablePress is structured public data: administration -> Person/Department, service A–Z -> Service/contact references, clubs/trades -> Organization, council -> CouncilTerm/CouncilMember, calendar -> Event. It has **{len(events)} rows after the calendar header, {findings['event_rows_with_full_date_candidate']} with full-date candidates**; headings/date ranges are not automatic Event records. LifeSituation, Location, PublicNotice, Organization type, official notice dates and committees need editorial mapping, not title-only guesses. External portal/map services become managed links rather than automatic third-party embeds.

## 7. Manual review

26 nonblank council entries and 26 portrait references were found; 4 separator rows are excluded from member counts. Review names/roles/term dates and any actual committee relationships, select originals and apply focal crops. Do not copy administration-table photos into employees. TablePress service/directory rows need duplicate/department/organization reconciliation. Old homepage intro/obsolete service tables and inactive Connections data need retention decisions. Future actions must retain the original Europe/Berlin meaning and current enabled/newStatus semantics; historical jobs must not become current expiry rules. Contact routing recipients are configured privately, outside reports/imported public content.

`manual-review.csv` and `decisions.csv` list concrete decisions. `{len(orphans)}` seed-only page/post candidates and unreferenced files are not proven orphans. External links were inventoried without third-party requests; availability of external targets remains unverified.

## 8. Missing, broken and backup discrepancies

Crawl status counts: {', '.join(f"{n} HTTP {status}" for status,n in crawl['statuses'].items())}. {len(broken)} observed incoming edges point at live 404/410 targets; seeded/noncanonical 404s are separate from reachable-page broken links. The 61 missing gallery originals are a **filesystem backup gap**, not a live outage. The dynamic thumbnail attachment needs manual resolution. Two SQL public Encyclopedia records are absent from WXR. Inactive-prefix content and plugin helper drafts must not be confused with current public records. All differences are explicit, with no inferred accessibility or deletion decisions.

## 9. URL/redirect risks

Keep indexed editorial slugs, category paths, old host/scheme aliases and one-hop trailing-slash redirects. Root query URLs such as `?wpdmdl=ID`, `?p=ID`, `?page_id=ID`, old Filebase query URLs and direct `/wp-content/uploads/...` / `/wp-content/gallery/...` paths need deterministic legacy-ID/path -> published file/content delivery. The current Laravel Redirect model is **path-only**, rejects `?`, and cannot directly distinguish query downloads at `/`; an adapter/allowlisted compatibility route is required before cutover. Never expose a protected/private source merely because it exists in a backup. Preserve public file identity even after secure storage moves originals outside `public/`.

No redirects were implemented during this phase. Proposed canonical changes require review against menu links, archive/pagination and direct file backlinks; WXR GUIDs are not canonical authority.

## 10. Current CMS gaps and the approved budget correction

Implemented and tested: **multiple budget packages per year**, with free-text Thema / Art rather than uniqueness by year or Herausgeber. A main plan, school-association plan, Nachtrag and correction can coexist. Each package has its own ordered originals, combined file, public URL, revisions/proposals and immutable proof. Package-specific download routes include the package ID; old year-only routes only serve a uniquely identifiable public package and return 404 if ambiguous. Historical proofs were not rewritten or given invented topic labels.

Remaining gaps: no generic controlled accessible table block; no root-query legacy download/ID resolver; no reviewed direct-media/file compatibility layer; no equivalent dynamic query/view-builder or nested album representation (use controlled catalogs/ordered gallery blocks); old GIF/DOC/ZIP formats exceed the secure upload allowlist; RSS/feed continuation is not implemented. Existing contact routing can represent public contact tasks, but exact old field/privacy workflow needs manual configuration. Committee memberships and legal/publication semantics cannot be inferred solely from old HTML.

## 11. Fix or decide before importing

1. **Budget PDFs:** 25 components in order: Gemeinde (10), Grundschulverband (7), Mittelschulverband (8), all 2026. The main 232-page Gemeinde, 94-page Grundschulverband and 102-page Mittelschulverband PDFs are rejected by the current FPDI parser. The other 22 pass source parsing (not a full merge/accessibility certification). Obtain compatible exports or a licensed/local parser solution suitable for goneo; no paid dependency/service was purchased. See `budget-pdf-preflight.csv` and `budget-components.csv`.
2. Decide structured conversions versus a bounded accessible table component for remaining statistics/information tables.
3. Design and test legacy query/download/direct-file delivery without bypassing publication or access checks.
4. Recover missing original gallery bytes, reconcile duplicate content and choose which private/draft/historic files remain unexposed.
5. Review expiry, notice archive semantics, unknown alt/copyright/accessibility and exact contact workflow. Agree on safe conversions for incompatible file formats.

**Readiness:** ready to design and build an importer with a strict dry-run/manual-review boundary; **not ready to run a complete production import** until the blocking PDF, URL and table decisions are resolved. This phase stops here. No migration execution, deployment or push occurred.

## Verification and report index

The CMS correction passed four read-only CMS responsive/accessibility checks and 462 PHP tests (2,924 assertions), Pint, PHPStan and Composer audit; build and 5 browser tests passed at 390/768/1024/1440px. Read-only tooling has 7 offline parser/safety tests. Input hashes were rechecked unchanged; raw sources/cache are ignored and untracked. See `README.md` for report scopes, commands and crawl limitations.
'''
    (OUT/'summary.md').write_text(summary)
    readme='''# Inventory reports and read-only tooling

Read `summary.md` first, then `decisions.csv` and `plugin-inventory.md`. Machine-readable files are UTF-8 CSV/JSON; dates and source IDs retain source meaning. Rows are candidates, never an instruction to publish/delete. Reports intentionally omit raw content bodies, private form data, internal contact recipients, credentials/hashes and arbitrary option values.

## Reproduce locally

```sh
python3 scripts/migration/inventory.py
python3 scripts/migration/enrich.py
python3 scripts/migration/crawl.py
python3 scripts/migration/report.py
python3 -m unittest discover -s tests/Inventory -v
```

Python standard library suffices for SQL/WXR/ZIP/crawl/report. Budget PDF preflight additionally uses local `pdfinfo` and the project's existing Docker/PHP FPDI vendor package. It never bootstraps Laravel or connects to its DB. Missing PDF runtimes are recorded as untested, not supported. Inventory and enrich write only sanitized reports plus raw cache below ignored `migration-source/analysis-cache`. SQL is parsed, never executed. Nested ZIPs are copied into the ignored cache and read directly; no arbitrary ZIP paths are extracted. Every original source gets an unchanged-byte hash check.

Download Manager links may be in `data-downloadurl`, not ordinary hrefs; the public `refresh` cache-buster is removed only when a numeric `wpdmdl` is present, without discarding authentication/password parameters. Stable root download URLs are also seeded from published package IDs. The crawler resumes private cached state, retries recorded network/client errors, observes robots.txt and globally limits GET start times to one per 0.6 seconds with two workers. Requests never use login/cookies, POST/form submissions, wp-admin, wp-login, XML-RPC or REST mutation endpoints. Query strings are allowlisted; recipient/token/password/nonce URLs are excluded. External URLs are recorded, not fetched. It includes anchors, images/srcsets, iframes, sitemaps, styles/scripts/feeds and local CSS assets; it does not execute JavaScript or exhaust arbitrary search/filter combinations. File requests sample headers/body; HTTP 206 is a normal Range response. A finite graph and no pending queue are not proof that every historically indexed URL has been found. Add access/search-console backlink exports later for further orphan/URL evidence, without inferring their contents now.

## Report scopes

- `content.csv`, `counts.json`, `tables.csv`: active-prefix published/attachment candidates, all post type/status totals, schemas/counts only for operational/private tables.
- `mapping.csv`, `manual-review.csv`, `decisions.csv`: proposed domain conversion and concrete unresolved decisions.
- `urls.csv`, `crawl-counts.json`, `redirect-candidates.csv`, `observed-redirects.csv`, `source-live-crosscheck.csv`, `uncrawled-url-candidates.csv`: request/canonical/redirect evidence and explicit public/unknown candidates. Redirect proposals are not executed.
- `internal-links.csv`, `broken-links.csv`, `orphan-candidates.csv`, `external-links.csv`, `live-external-links.csv`: link relationships, actual incoming failures and unverified external destinations.
- `documents.csv`, `media.csv`, `filesystem.csv`, `duplicates.csv`, `missing-files.csv`, `filebase.csv`, `managed-downloads.csv`: individual source files, physical hashes, attachment dimensions/scaled originals, duplicate groups and missing-backup/live evidence. Thumbnail/orphan flags are candidates; only original/reference relationships establish provenance. Do not expose all backed-up files.
- `galleries.csv`, `gallery-items.csv`, `albums.csv`, `council-portrait-candidates.csv`: ordered gallery/album/council relationships; blank council separator rows are not members.
- `tablepress.csv`, `table-row-candidates.csv`, `shortcodes.csv`, `html-conversion.csv`, `event-candidates.csv`, `organization-candidates.csv`: structured/plugin conversion evidence. Row counts are not guaranteed domain entity counts.
- `taxonomies.csv`, `content-taxonomies.csv`, `menus.csv`, `link-library.csv`: category/menu/link assignments and legacy-ID deduplication candidates.
- `scheduled.csv`, `future-actions.csv`, `forms.csv`, `legacy-calendar.csv`, `postmeta-keys.csv`: public scheduling/form type/schema evidence; no recipient or arbitrary meta values.
- `budget-components.csv`, `budget-pdf-preflight.csv`: 25 year/topic components and actual local parser checks. Compressed-object presence alone is not failure; the parser result is authoritative for this preflight. No merged files or public accessibility status were imported.
- `discrepancies.csv`, `postmeta-discrepancies.csv`, `source-provenance.csv`, `findings.json`: cross-source differences, unchanged original-source fingerprints and high-level computed counts.

Raw source/HTML/state/PDF files remain sensitive and gitignored. Re-running stages replaces report projections; run them in the listed order. Keep backup sources immutable. Report generation is idempotent (avoid appending duplicate workflow decisions).
'''
    (OUT/'README.md').write_text(readme)
    print(json.dumps(findings,indent=2))
if __name__=='__main__':main()
