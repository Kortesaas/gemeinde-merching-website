#!/usr/bin/env python3
"""Read-only local acceptance ledger against every inventory and crawl item.

No external requests are made by validation. Every source URL gets a destination
or a concrete review row. Responses are checked without following redirect chains.
"""
import csv, json, hashlib, collections
from pathlib import Path
from urllib.parse import urlparse, unquote, parse_qs, urlencode, quote
from urllib.request import Request, build_opener, HTTPRedirectHandler
from urllib.error import HTTPError
from concurrent.futures import ThreadPoolExecutor

ROOT=Path(__file__).resolve().parents[2]
REPORT=ROOT/'docs/migration/local'
BASE='http://localhost:8089'
class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self,*args):return None

def request(path):
    try:
        with build_opener(NoRedirect()).open(Request(BASE+quote(path, safe='/%?&=+-_.'), method='HEAD'),timeout=60) as r:return r.status,r.headers.get('Location','')
    except HTTPError as e:return e.code,e.headers.get('Location','')
    except Exception as e:return 0,type(e).__name__

def normalized(u):
    p=urlparse(u);path=unquote(p.path).rstrip('/') or '/';q=parse_qs(p.query)
    ids=[k for k in ['wpdmdl','p','page_id','attachment_id','download_id','wpfb_dl'] if k in q]
    if ids:
        k=ids[0];v=q[k][0]
        if v.isdigit():path+='?'+k+'='+str(int(v))
    return path

def write(name,rows):
    with (REPORT/name).open('w',newline='') as f:
        w=csv.DictWriter(f,fieldnames=list(rows[0]),lineterminator='\n');w.writeheader();w.writerows(rows)

def main():
    mappings=list(csv.DictReader((REPORT/'url-mappings.csv').open()));destinations={r['old_url']:r['destination'] for r in mappings}
    unique=sorted(set(destinations.values()))
    with ThreadPoolExecutor(max_workers=4) as pool:checked=dict(zip(unique,pool.map(request,unique)))
    mapchecks=[]
    with ThreadPoolExecutor(max_workers=4) as pool:responses=list(pool.map(request,[r['old_url'] for r in mappings]))
    for r,(status,location) in zip(mappings,responses):
        destination_status=checked.get(r['destination'],(0,''))[0]
        path=urlparse(location).path or '/'
        mapchecks.append({'old_url':r['old_url'],'destination':r['destination'],'status':status,'location':location,'destination_status':destination_status,'result':'ok' if status in {200,301} and destination_status==200 and (status==200 or unquote(path)==r['destination']) else 'needs review'})
    write('redirect-validation.csv',mapchecks)
    crawl=list(csv.DictReader((ROOT/'docs/migration/inventory/urls.csv').open()));coverage=[]
    state=json.loads((ROOT/'migration-source/analysis-cache/crawl-state.json').read_text())
    inbound=collections.defaultdict(set)
    for url,response in state.items():
        if response.get('type')=='page' and response['status']<400:
            for source,target,kind in response.get('edges',[]):
                if kind in {'link','image'}:inbound[normalized(target)].add(url)
    native={'/robots.txt','/sitemap.xml'}
    for row in crawl:
        url=row.get('url') or row.get('requested_url');path=normalized(url);destination=destinations.get(path)
        if path=='/':destination='/'
        typ=row.get('type','');status=int(row.get('status') or 0)
        if destination:result='mapped';reason='Public destination checked locally'
        elif status>=400:result='review';reason='Existing public site HTTP '+str(status)+'; not silently treated as missing migrated content'
        elif path in native and request(path)[0]==200:destination=path;result='mapped';reason='Native Laravel sitemap/robots replaces WordPress output'
        elif any(x in url for x in ['/themes/','/plugins/','/wp-includes/','/astra-local-fonts/','/wpcf7_captcha/','.js','.css','.woff','.ttf']):result='intentionally skipped';reason='WordPress theme/plugin/font/CAPTCHA presentation runtime is replaced'
        elif typ=='sitemap/xml' and any(x in url for x in ['/feed','sitemap','.xml']):result='intentionally skipped';reason='WordPress feed/XML output replaced by native search, archives and sitemap; source entries are audited individually'
        elif '?ver=' in url:result='intentionally skipped';reason='Versioned WordPress runtime asset'
        elif typ=='page':
            # A slashless canonical path can be retained without needing a legacy entry.
            candidate=path.split('?',1)[0]
            if candidate in unique:destination=candidate;result='mapped';reason='Preserved canonical content path'
            else:result='review';reason='Public HTML alias/archive/plugin output needs editorial URL decision'
        else:
            result='review'
            if path in inbound:reason='Linked from current public HTML; original source identity unresolved (see incoming_public_pages)'
            elif '/filebase/' in path:reason='Historical Filebase URL is reachable but has no current public HTML link; applicability/publication relationship requires review before import'
            elif '/gallery/' in path:reason='NextGEN image has no imported public gallery relationship; exclusions are retained (see preparation-review.csv)'
            else:reason='Seeded attachment/file URL is reachable but unlinked from current public HTML; backup availability alone is insufficient to publish it'
        coverage.append({'source_url':url,'source_type':typ,'source_status':status,'destination':destination or '', 'status':result,'reason':reason,'incoming_public_pages':' | '.join(sorted(inbound.get(path,[])))})
    write('crawl-coverage.csv',coverage)
    manifest=json.loads((ROOT/'migration-source/prepared/manifest.json').read_text());keys={r['key'] for r in manifest['records']+manifest['assets']}
    keys.update(k for r in manifest['assets']+manifest['records'] for k in r.get('source_keys',[]))
    content=[]
    for row in csv.DictReader((ROOT/'docs/migration/inventory/content.csv').open()):
        key='wp:'+row['source_id'];typ=row['type']
        if key in keys:state='imported';reason='SourceReference tracked'
        elif typ=='tablepress_table':state='converted or intentionally skipped';reason='See tablepress.csv: structured rows / controlled table / unused helper'
        elif typ=='nav_menu_item':state='merged';reason='Replaced by real content in retained new navigation architecture'
        elif typ=='attachment':state='intentionally skipped';reason='Only verified public referenced originals are imported; unreferenced/private-parent backup attachments are not auto-published'
        elif typ=='link_library_links':state='merged or review';reason='Local document/URL identity preserved, external resources imported; see preparation-review.csv'
        else:state='review';reason='No public target identity; see import-result.json'
        content.append({'source_id':row['source_id'],'type':typ,'title':row['title'],'status':state,'reason':reason})
    write('source-content-coverage.csv',content)
    summary={'destinations_checked':len(unique),'destination_failures':[p for p,(s,l) in checked.items() if s!=200],'legacy_checks':len(mapchecks),'legacy_failures':sum(r['result']!='ok' for r in mapchecks),'crawl_coverage':dict(collections.Counter(r['status'] for r in coverage)),'source_content_coverage':dict(collections.Counter(r['status'] for r in content))}
    (REPORT/'validation-result.json').write_text(json.dumps(summary,indent=2)+'\n');print(json.dumps(summary,indent=2))
    if summary['destination_failures'] or summary['legacy_failures']:raise SystemExit(1)

if __name__=='__main__':main()
