#!/usr/bin/env python3
"""Resumable, read-only public crawl. Two workers, globally paced GET requests.

Never submits forms, authenticates, crawls admin/REST endpoints or contacts outsiders.
HTML and raw responses remain gitignored. CSVs contain public URL evidence only.
"""
import argparse, collections, csv, hashlib, html, json, re, threading, time
from concurrent.futures import ThreadPoolExecutor, wait, FIRST_COMPLETED
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import urljoin,urlparse,urlunparse,parse_qsl,urlencode,unquote,quote
from urllib.request import Request,build_opener,HTTPRedirectHandler
from urllib.error import HTTPError,URLError
from urllib.robotparser import RobotFileParser

HOSTS={'www.gemeinde-merching.de','gemeinde-merching.de'}
ALLOWED_QUERY={'p','page_id','cat','paged','page','attachment_id','wpdmdl','wpdmpro','download','download_id','file','lang','nggpage','show','album','gallery','pid','ai1ec','month','year','action','wpdmact','id','wpdmpackage','post_type','wpfilebase_thumbnail','fid','name','wpfb_dl','ver','feed'}

def normalize(url,base='https://www.gemeinde-merching.de/'):
    try:
        p=urlparse(urljoin(base,html.unescape(url).strip()))
        if p.scheme not in {'http','https'} or p.hostname not in HOSTS or p.username or p.password or p.port not in (None,80,443):return None
        path=quote(p.path or '/',safe="/:@!$&'()*+,;=-._~%")
        if re.search(r'/(?:wp-admin|wp-login\.php|wp-json|xmlrpc\.php|wp-cron\.php)(?:/|$)|/trackback(?:/|$)',path,re.I):return None
        query=parse_qsl(p.query,keep_blank_values=True)
        # Download Manager's public refresh parameter is a transient cache-buster, not content identity.
        if any(k=='wpdmdl' and v.isdigit() for k,v in query):query=[(k,v) for k,v in query if k!='refresh']
        if any(k not in ALLOWED_QUERY for k,v in query):return None
        if any(k=='post_type' and v not in {'wpdmpro','encyclopedia','attachment'} for k,v in query):return None
        if any(k=='action' and v not in {'download','downloadfile'} for k,v in query):return None
        return urlunparse((p.scheme,p.netloc.lower(),path,'',urlencode(sorted(query)),''))
    except ValueError:return None

class PageParser(HTMLParser):
    def __init__(self):super().__init__(convert_charrefs=True);self.links=[];self.images=[];self.title=[];self.canonical='';self.in_title=False;self.forms=0;self.iframes=[];self.redirect='';self.assets=[]
    def handle_starttag(self,tag,attrs):
        a=dict(attrs)
        if tag=='title':self.in_title=True
        if tag=='script' and a.get('src'):self.assets.append(a['src'])
        if tag=='link' and a.get('href') and (set(a.get('rel','').lower().split()) & {'stylesheet','preload','alternate'}):self.assets.append(a['href'])
        if tag=='a' and a.get('href'):self.links.append(a['href'])
        if a.get('data-downloadurl'):self.links.append(a['data-downloadurl'])
        if tag=='img':
            for key in ('src','data-src','data-lazy-src'):
                if a.get(key):self.images.append(a[key])
            for key in ('srcset','data-srcset'):
                for part in a.get(key,'').split(','):
                    if part.strip():self.images.append(part.strip().split()[0])
        if tag=='link' and 'canonical' in a.get('rel','').lower():self.canonical=a.get('href','')
        if tag=='form':self.forms+=1
        if tag=='iframe' and a.get('src'):self.iframes.append(a['src'])
        if tag=='meta' and a.get('http-equiv','').lower()=='refresh':self.redirect=a.get('content','')
    def handle_endtag(self,tag):
        if tag=='title':self.in_title=False
    def handle_data(self,s):
        if self.in_title:self.title.append(s)

class SafeRedirect(HTTPRedirectHandler):
    def __init__(self):super().__init__();self.chain=[]
    def redirect_request(self,req,fp,code,msg,headers,newurl):
        self.chain.append({'from':req.full_url,'status':code,'to':newurl})
        if normalize(newurl) is None:return None
        return super().redirect_request(req,fp,code,msg,headers,newurl)

class Rate:
    def __init__(self,delay):self.delay=delay;self.lock=threading.Lock();self.next=0
    def acquire(self):
        with self.lock:
            now=time.monotonic();pause=max(0,self.next-now);self.next=max(now,self.next)+self.delay
        if pause:time.sleep(pause)

def main():
    a=argparse.ArgumentParser();a.add_argument('--source',type=Path,default=Path('migration-source'));a.add_argument('--out',type=Path,default=Path('docs/migration/inventory'));a.add_argument('--delay',type=float,default=.6);a.add_argument('--max-urls',type=int,default=10000);args=a.parse_args()
    if args.delay<.5:raise ValueError('Use at least half a second between requests')
    cache=args.source/'analysis-cache';responses=cache/'crawl';responses.mkdir(parents=True,exist_ok=True);args.out.mkdir(parents=True,exist_ok=True)
    statepath=cache/'crawl-state.json';state=json.loads(statepath.read_text()) if statepath.exists() else {};rate=Rate(args.delay)
    opener=build_opener();robot=RobotFileParser();robots_status='unknown'
    rate.acquire()
    try:
        with opener.open(Request('https://www.gemeinde-merching.de/robots.txt',headers={'User-Agent':'MerchingMigrationInventory/1.0 (read-only public site audit)'}),timeout=20) as r:
            robot.parse(r.read(512*1024).decode(errors='replace').splitlines());robots_status=str(r.status)
    except HTTPError as e:
        robots_status=str(e.code);robot.parse([] if e.code in (404,410) else ['User-agent: *','Disallow: /'])
    except (URLError,TimeoutError):raise RuntimeError('Cannot determine robots rules; retry later')
    seeds=json.loads((cache/'crawl-seeds.json').read_text()) if (cache/'crawl-seeds.json').exists() else ['https://www.gemeinde-merching.de/']
    queue=collections.deque(sorted({u for s in seeds if (u:=normalize(s))}));queued=set(queue);blocked=set();edges=set();external=set()
    # Retry transient/client encoding failures with the current safe URL normalization.
    for u in [u for u,r in state.items() if r['status']==0]:
        del state[u]
        if u not in queued:queue.append(u);queued.add(u)
    for r in state.values():
        for u in r.get('discovered',[]):
            if u not in queued:queue.append(u);queued.add(u)
        edges.update(tuple(x) for x in r.get('edges',[]));external.update(tuple(x) for x in r.get('external',[]))
    def fetch(u):
        rate.acquire();redirect=SafeRedirect();client=build_opener(redirect)
        req=Request(normalize(u),headers={'User-Agent':'MerchingMigrationInventory/1.0 (read-only public site audit)','Accept':'text/html,application/pdf,application/xml,image/*;q=0.8,*/*;q=0.5','Range':'bytes=0-2097151'})
        row={'url':u,'status':0,'final_url':u,'canonical':'','title':'','type':'unknown','content_type':'','content_length':'','redirect_chain':[],'discovered':[],'edges':[],'external':[],'images':[],'downloads':[],'forms':0,'error':'','checked_at':time.strftime('%Y-%m-%dT%H:%M:%SZ',time.gmtime())}
        try:
            try:r=client.open(req,timeout=25)
            except HTTPError as e:r=e
            with r:
                row.update(status=r.status,final_url=r.geturl(),content_type=r.headers.get('Content-Type',''),content_length=r.headers.get('Content-Length',''),redirect_chain=redirect.chain)
                textual='text/html' in row['content_type'] or 'xml' in row['content_type'] or 'text/css' in row['content_type']
                body=r.read(2097153 if textual else 1024)
                if len(body)>2097152:row['error']='HTML/XML larger than 2 MiB; truncated'
                if textual:(responses/(hashlib.sha256(u.encode()).hexdigest()+'.html')).write_bytes(body)
                text=body.decode('utf-8',errors='replace')
                if 'text/html' in row['content_type']:
                    row['type']='page';parser=PageParser();parser.feed(text);row.update(title=re.sub(r'\s+',' ',' '.join(parser.title)).strip(),canonical=urljoin(row['final_url'],parser.canonical) if parser.canonical else '',forms=parser.forms)
                    for kind,items in [('link',parser.links),('image',parser.images),('iframe',parser.iframes),('asset',parser.assets)]:
                        for v in items:
                            absolute=urljoin(row['final_url'],v);p=urlparse(absolute)
                            if p.scheme not in {'https','http'}:continue
                            n=normalize(absolute)
                            if n:
                                row['discovered'].append(n);row['edges'].append([u,n,kind])
                                if kind=='image':row['images'].append(n)
                                if re.search(r'\.(pdf|docx?|xlsx?|zip)(?:\?|$)',n,re.I) or 'wpdmdl=' in n:row['downloads'].append(n)
                            elif p.hostname not in HOSTS and not p.username and not re.search(r'(token|email|recipient|nonce|secret|key)=',p.query,re.I):row['external'].append([u,p._replace(fragment='').geturl(),kind])
                elif 'xml' in row['content_type']:
                    row['type']='sitemap/xml'
                    for v in re.findall(r'<loc>\s*(.*?)\s*</loc>',text):
                        if (n:=normalize(v)):row['discovered'].append(n);row['edges'].append([u,n,'sitemap'])
                elif 'text/css' in row['content_type']:
                    row['type']='stylesheet'
                    for v in re.findall(r'url\(\s*[\'"]?([^\'")]+)',text):
                        if (n:=normalize(v,row['final_url'])):row['discovered'].append(n);row['edges'].append([u,n,'css-asset'])
                elif 'pdf' in row['content_type'] or body.startswith(b'%PDF-'):row['type']='PDF'
                elif row['content_type'].startswith('image/'):row['type']='image'
                else:row['type']='file'
        except (URLError,TimeoutError,OSError,ValueError) as e:row['error']=type(e).__name__
        row['discovered']=sorted(set(row['discovered']));return row
    for u,row in state.items():
        if row.get('type')!='page' or row['status']>=400:continue
        local=responses/(hashlib.sha256(u.encode()).hexdigest()+'.html')
        if not local.exists():continue
        parser=PageParser();parser.feed(local.read_text(errors='replace'))
        for kind,value in [('asset',v) for v in parser.assets]+[('link',v) for v in parser.links]:
            n=normalize(value,row['final_url'])
            if n:
                edge=(u,n,kind);edges.add(edge)
                if list(edge) not in row.get('edges',[]):row['edges'].append(list(edge))
                if n not in row['discovered']:row['discovered'].append(n)
                if n not in queued:queue.append(n);queued.add(n)
            else:
                absolute=urljoin(row['final_url'],value);external_url=urlparse(absolute)
                if external_url.scheme in {'http','https'} and external_url.hostname not in HOSTS and not external_url.username and not re.search(r'(token|email|recipient|nonce|secret|key)=',external_url.query,re.I):
                    edge=(u,external_url._replace(fragment='').geturl(),kind);external.add(edge)
                    if list(edge) not in row.get('external',[]):row['external'].append(list(edge))
    futures={};completed=0
    with ThreadPoolExecutor(max_workers=2) as pool:
        while queue or futures:
            while queue and len(futures)<2 and len(state)+len(futures)<args.max_urls:
                u=queue.popleft()
                if u in state:continue
                if not robot.can_fetch('MerchingMigrationInventory',u):blocked.add(u);continue
                futures[pool.submit(fetch,u)]=u
            if not futures:break
            done,_=wait(futures,return_when=FIRST_COMPLETED)
            for f in done:
                u=futures.pop(f);r=f.result();state[u]=r;completed+=1
                edges.update(tuple(x) for x in r['edges']);external.update(tuple(x) for x in r['external'])
                for n in r['discovered']:
                    if n not in queued:queue.append(n);queued.add(n)
                if completed%20==0:
                    tmp=statepath.with_suffix('.tmp');tmp.write_text(json.dumps(state,ensure_ascii=False));tmp.replace(statepath)
                    print(json.dumps({'crawled':len(state),'pending':len(queue),'blocked':len(blocked)}),flush=True)
                if r['status'] in (429,503):time.sleep(10)
    statepath.write_text(json.dumps(state,ensure_ascii=False));
    def csvout(name,rows,fields):
        with (args.out/name).open('w',encoding='utf-8',newline='') as f:
            w=csv.DictWriter(f,fieldnames=fields,lineterminator="\n");w.writeheader();w.writerows(rows)
    rows=[];redirects=[]
    for u,r in sorted(state.items()):
        classification='manual review';reason='Public route mapping requires content ownership check'
        canonical=r['canonical'] or r['final_url']
        if r['status'] in (404,410):classification='broken/obsolete';reason='Live endpoint missing; verify backlinks before removing'
        elif r['status']>=400 or not r['status']:reason='Fetch error/restriction; do not infer obsolete'
        elif r['type']=='sitemap/xml' and ('/feed' in urlparse(u).path or 'feed=' in urlparse(u).query):classification='manual review';reason='Public RSS/Atom feed continuation not implemented in the new CMS'
        elif r['redirect_chain']:classification='duplicate/alias';reason='Existing live redirect; preserve full chain destination'
        elif r['type'] in ('PDF','image','file'):classification='preserve exactly';reason='Direct asset URLs may have external backlinks'
        elif canonical!=u:classification='duplicate/alias';reason='Live canonical differs'
        elif urlparse(u).path not in ('','/') and urlparse(u).path.endswith('/'):
            classification='migrate to another canonical URL + 301';reason='Slashless new convention; proposed same path without trailing slash'
        else:classification='preserve exactly';reason='Preserve public path unless domain mapping requires a reviewed change'
        target=(canonical.rstrip('/') if urlparse(canonical).path not in ('','/') else canonical) if classification=='migrate to another canonical URL + 301' else canonical
        rows.append({k:r[k] for k in ('url','status','final_url','canonical','title','type','content_type','content_length','forms','error','checked_at')}|{'classification':classification,'proposed_target':target,'reason':reason,'images':len(r['images']),'downloads':len(r['downloads']),'internal_links':len(r['discovered'])})
        if classification in ('duplicate/alias','migrate to another canonical URL + 301'):redirects.append({'url':u,'proposed_target':target,'classification':classification,'status':r['status'],'reason':reason,'implemented':False})
    fields=['url','status','final_url','canonical','title','type','content_type','content_length','forms','error','checked_at','classification','proposed_target','reason','images','downloads','internal_links']
    csvout('urls.csv',rows,fields);csvout('redirect-candidates.csv',redirects,['url','proposed_target','classification','status','reason','implemented']);csvout('internal-links.csv',[dict(zip(['source_url','target_url','kind'],e)) for e in sorted(edges)],['source_url','target_url','kind']);csvout('live-external-links.csv',[dict(zip(['source_url','target_url','kind'],e)) for e in sorted(external)],['source_url','target_url','kind']);csvout('robots-blocked.csv',[{'url':u,'reason':'robots.txt disallows automated crawl'} for u in sorted(blocked)],['url','reason'])
    counts={'crawled':len(state),'types':dict(collections.Counter(r['type'] for r in state.values())),'statuses':dict(collections.Counter(str(r['status']) for r in state.values())),'classifications':dict(collections.Counter(r['classification'] for r in rows)),'robots_status':robots_status,'robots_blocked':len(blocked),'pending':len(queue),'limit':args.max_urls,'delay_seconds':args.delay,'workers':2,'finished_at':time.strftime('%Y-%m-%dT%H:%M:%SZ',time.gmtime())};(args.out/'crawl-counts.json').write_text(json.dumps(counts,indent=2)+'\n');print(json.dumps(counts,indent=2))
if __name__=='__main__':main()
