#!/usr/bin/env python3
"""Build an allowlisted, public-only migration manifest; never execute WordPress SQL.

Original sources and private parsed caches are read-only. Only referenced public
files are extracted, by exact archive member name, into the ignored import area.
"""
import collections, csv, hashlib, html, json, re, zipfile
from datetime import datetime, timezone
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import urlparse, urljoin, unquote, parse_qs
from inventory import digest, plain, safe_url
from enrich import decode, relative

ROOT = Path(__file__).resolve().parents[2]
SOURCE = ROOT / 'migration-source'
OUT = SOURCE / 'prepared'
HOSTS = {'gemeinde-merching.de', 'www.gemeinde-merching.de'}

class Node:
    def __init__(self, tag='', attrs=None): self.tag=tag; self.attrs=attrs or {}; self.children=[]
class Tree(HTMLParser):
    def __init__(self, text):
        super().__init__(convert_charrefs=True); self.root=Node(); self.stack=[self.root]; self.feed(text)
    def handle_starttag(self, tag, attrs):
        n=Node(tag,dict(attrs)); self.stack[-1].children.append(n)
        if tag not in {'img','br','hr','input','meta','link','source','embed','wbr'}: self.stack.append(n)
    def handle_startendtag(self,tag,attrs):
        self.handle_starttag(tag,attrs)
        if self.stack[-1].tag==tag:self.stack.pop()
    def handle_endtag(self,tag):
        for i in range(len(self.stack)-1,0,-1):
            if self.stack[i].tag==tag:self.stack=self.stack[:i];break
    def handle_data(self,data):self.stack[-1].children.append(data)

def nodes(n,tag):
    if isinstance(n,str):return
    if n.tag==tag:yield n
    for c in n.children:yield from nodes(c,tag)

def text(n):
    if isinstance(n,str):return n
    if n.tag in {'script','style','iframe','form','input','button'}:return ''
    if n.tag=='br':return '\n'
    return ''.join(text(c) for c in n.children)

def slug(s):
    import unicodedata
    s=s.lower().replace('ä','ae').replace('ö','oe').replace('ü','ue').replace('ß','ss')
    s=unicodedata.normalize('NFKD',s).encode('ascii','ignore').decode()
    return re.sub('[^a-z0-9]+','-',s).strip('-')[:120] or 'eintrag'

def clean(s):
    return re.sub(r'[ \t]+',' ',html.unescape(s or '')).strip()

class Migration:
    def __init__(self):
        p=json.loads((SOURCE/'analysis-cache/parsed.json').read_text()); assert p['prefix']=='wp_'
        self.d=p['production']['data']; self.fs=p['files']; self.posts={r['ID']:r for r in self.d['wp_posts']}
        self.meta=collections.defaultdict(dict)
        for r in self.d['wp_postmeta']:self.meta[r['post_id']][r['meta_key']]=r['meta_value'] or ''
        self.inventory={r['source_id']:r for r in csv.DictReader((ROOT/'docs/migration/inventory/content.csv').open())}
        self.crawl=json.loads((SOURCE/'analysis-cache/crawl-state.json').read_text())
        self.records={}; self.assets={}; self.legacy={}; self.review=[]; self.decisions=[]; self.tables=[]; self.links={}
        self.public={id:r for id,r in self.posts.items() if r['post_status']=='publish' and not r.get('post_password')}
        self.tax={r['term_taxonomy_id']:r for r in self.d['wp_term_taxonomy']}; self.terms={r['term_id']:r for r in self.d['wp_terms']}
        self.assignments=collections.defaultdict(list)
        for r in self.d['wp_term_relationships']:
            t=self.tax.get(r['term_taxonomy_id']);
            if t:self.assignments[r['object_id']].append((t['taxonomy'],t['term_id']))
        self.tableids={r['shortcode_id']:r for r in csv.DictReader((ROOT/'docs/migration/inventory/tablepress.csv').open())}
        self.table_entities={}; self.assetpaths={}; self.archives={}; self.source_counts=collections.Counter()
        self.original_paths={}
        self.attachment_info={}
        for a in csv.DictReader((ROOT/'docs/migration/inventory/media.csv').open()):
            path=a['path'];original=a.get('unscaled_original_path') if a.get('unscaled_original_exists')=='True' else path
            if path and original and original in self.fs:
                self.original_paths[path]=original
                if not a['source_id'].startswith('ngg:') and a['source_id'] in self.posts:
                    post=self.posts[a['source_id']]
                    info={'id':a['source_id'],'title':post['post_title'],'alt':self.meta[a['source_id']].get('_wp_attachment_image_alt'),'date':post['post_date_gmt']}
                    self.attachment_info[original]=info

    def issue(self,source,reason,status='imported but needs review'):
        row={'source':source,'status':status,'reason':reason}
        if row not in self.review:self.review.append(row)

    def add(self,key,kind,attrs,path=None,date=None):
        if key in self.records:return self.records[key]
        r={'key':key,'type':kind,'attributes':attrs,'path':path,'date':date or '2026-10-10 00:00:00','blocks':[],'relations':{},'urls':[]}
        self.records[key]=r;return r

    def original(self,id):return self.inventory.get(id,{}).get('source_url') or self.inventory.get(id,{}).get('url') or 'https://www.gemeinde-merching.de/?p='+id

    def alias(self,url,key,destination=None):
        p=urlparse(html.unescape(url));
        if p.hostname and p.hostname not in HOSTS:return
        if p.scheme and p.scheme not in {'http','https'}:return
        path=unquote(p.path).rstrip('/') or '/'; query=parse_qs(p.query)
        if query:
            ids=[k for k in ['wpdmdl','p','page_id','wpfb_dl','download_id','attachment_id'] if k in query]
            if len(ids)!=1:return
            q=ids[0];v=query[q][0]
            if not v.isdigit():return
            path += '?'+q+'='+str(int(v))
        self.legacy[path]={'url':path,'key':key,'destination':destination}
        record=self.records.get(key) or self.assets.get(key)
        if record and url not in record['urls']:record['urls'].append(url)
        self.links[url]=destination or self.records.get(key,{}).get('path') or ('asset:'+key)
        self.links[p._replace(scheme='https',netloc='www.gemeinde-merching.de',fragment='').geturl()]=self.links[url]

    def file(self,path,title=None,alt=None,key=None,date=None):
        path=unquote(path).lstrip('/')
        requested=path
        candidate=re.sub(r'-\d+x\d+(?=\.[^.]+$)','',path)
        if path in self.original_paths:path=self.original_paths[path]
        elif candidate in self.original_paths:path=self.original_paths[candidate]
        elif candidate in self.fs and candidate!=path:path=candidate
        identity=key
        if path in self.assetpaths:
            if identity and identity!=self.assetpaths[path]:
                self.assets[self.assetpaths[path]].setdefault('source_keys',[]).append(identity)
            if requested!=path:self.alias('https://www.gemeinde-merching.de/'+requested,self.assetpaths[path])
            key=self.assetpaths[path]
            if alt and not self.assets[key]['attributes'].get('alt_text'):self.assets[key]['attributes']['alt_text']=plain(alt)
            return key
        ext=Path(path).suffix.lower()
        if ext not in {'.pdf','.jpg','.jpeg','.png','.webp','.gif','.doc','.docx','.xlsx','.pptx','.odt','.ods','.zip'}:
            self.issue(path,'Unsupported original file format', 'missing source'); return None
        source=SOURCE/'recovery'/path
        if not source.exists():
            f=self.fs.get(path)
            if not f:self.issue(path,'Referenced public original absent', 'missing source');return None
            archive=f['archive'];z=self.archives.get(archive)
            if not z:z=zipfile.ZipFile(SOURCE/'analysis-cache/archives'/archive);self.archives[archive]=z
            member=next((i for i in z.infolist() if i.filename.lstrip('/').endswith(path) or i.filename.lstrip('/')==path.removeprefix('wp-content/')),None)
            if not member:self.issue(path,'Exact archive member not found','missing source');return None
            source=OUT/'files'/path;source.parent.mkdir(parents=True,exist_ok=True)
            if not source.exists():source.write_bytes(z.read(member))
            if digest(source)!=f['sha256']:raise ValueError('Source checksum mismatch')
        info=self.attachment_info.get(path,{})
        title=title or info.get('title')
        alt=alt or info.get('alt')
        date=date or info.get('date')
        key=key or 'file:'+path
        image=ext in {'.jpg','.jpeg','.png','.webp','.gif'}
        attrs={'title':plain(title) or Path(path).stem}
        if image:attrs.update(alt_text=plain(alt) or None,caption=None)
        else:attrs.update(accessibility_status='not_checked')
        self.assets[key]={'key':key,'type':'media' if image else 'document','attributes':attrs,'file':str(source.relative_to(ROOT)),'original_filename':Path(path).name,'sha256':digest(source),'date':date or '2026-10-10 00:00:00','urls':[]}
        if info:self.assets[key].setdefault('source_keys',[]).append('wp:'+info['id'])
        self.assetpaths[path]=key;self.assetpaths[requested]=key;self.alias('https://www.gemeinde-merching.de/'+path,key)
        if requested!=path:self.alias('https://www.gemeinde-merching.de/'+requested,key)
        if image and not alt:self.issue(key,'Original has no verified alternative text; original download remains available, inline placement needs review')
        if ext in {'.doc','.zip','.gif'}:self.issue(key,'Legacy format retained through migration-only inspected storage; GIF delivered as safe PNG')
        return key

    def pathfile(self,url,title=None,alt=None):
        p=urlparse(html.unescape(url))
        if p.hostname and p.hostname not in HOSTS:return None
        path=unquote(p.path).lstrip('/')
        if path.startswith('download/'):
            original='wp-content/uploads/filebase/'+path.removeprefix('download/')
            if original in self.fs:
                key=self.file(original,title,alt)
                if key:self.alias(url,key)
                return key
        if not path.startswith(('wp-content/uploads/','wp-content/gallery/','download/')) or not Path(path).suffix or '/wpcf7_captcha/' in path:return None
        return self.file(path,title,alt)

    def markdown(self,n):
        if isinstance(n,str):return n.replace('\t',' ')
        tag=n.tag
        if tag in {'script','style','iframe','form','img','input','button'}:return ''
        if tag=='br':return '\n'
        s=''.join(self.markdown(c) for c in n.children)
        if tag=='a':
            u=html.unescape(n.attrs.get('href',''))
            if not u:return s
            u=urljoin('https://www.gemeinde-merching.de/',u)
            if not (safe_url(u) or u.startswith(('mailto:','tel:'))):return s
            label=clean(s) or n.attrs.get('title') or u
            return '['+label.replace('[','(').replace(']',')')+']('+u.replace(' ','%20').replace(')','%29')+')'
        if tag in {'strong','b'}:return '**'+s.strip()+'**' if s.strip() else ''
        if tag in {'em','i'}:return '*'+s.strip()+'*' if s.strip() else ''
        if tag=='li':return '\n- '+s.strip()+'\n'
        if tag in {'p','div','blockquote','ul','ol','figure','section'}:return '\n\n'+s.strip()+'\n\n'
        return s

    def tsv(self,table,caption,key,headers=True,omit=None):
        rows=[]
        for row in table:
            vals=[clean(self.markdown(Tree(v).root)).replace('\n',' / ').replace('\t',' ') for i,v in enumerate(row) if i not in (omit or [])]
            rows.append(vals)
        if not rows:return []
        width=max(map(len,rows)); rows=[r+['']*(width-len(r)) for r in rows]
        if not headers:rows.insert(0,['Merkmal','Angabe','Weitere Angaben'][:width])
        if not any(rows[0]):rows[0]=['Spalte '+str(i+1) for i in range(width)]
        result=[];chunk=[rows[0]]
        for row in rows[1:]:
            if len('\n'.join('\t'.join(r) for r in chunk+[row]))>18000:
                result.append({'type':'table','heading':caption[:255],'text':'\n'.join('\t'.join(r) for r in chunk)});chunk=[rows[0]]
            chunk.append(row)
        result.append({'type':'table','heading':caption[:255],'text':'\n'.join('\t'.join(r) for r in chunk)})
        return result

    def convert(self,body,source):
        body=re.sub(r'<!--.*?-->','',body,flags=re.S)
        def shortcode(m):
            name=m.group(1);a=dict(re.findall(r'([\w-]+)=["\']?([^"\'\]\s]+)',m.group(2)))
            return '<migration-ref kind="'+name+'" source="'+html.escape(json.dumps(a),quote=True)+'"></migration-ref>'
        body=re.sub(r'\[([\w-]+)\b([^\]]*)\]',shortcode,body)
        tree=Tree(body).root;blocks=[];buffer=[]
        def flush():
            value=''.join(buffer);buffer.clear();value=re.sub(r'\n[ \t]*\n(?:[ \t]*\n)+','\n\n',value).strip()
            value=re.sub(r'^\s*#{1,6}\s','',value,flags=re.M)
            # Preserve longer legal texts in bounded paragraphs, never truncate them.
            while value:
                cut=len(value) if len(value)<=18000 else max(value.rfind('\n\n',0,18000),value.rfind(' ',0,18000),1)
                blocks.append({'type':'text','text':value[:cut].strip()});value=value[cut:].strip()
        def walk(n):
            if isinstance(n,str):buffer.append(n);return
            if n.tag in {'script','style','form','input','button'}:return
            if n.tag=='iframe':
                flush();u=n.attrs.get('src','')
                if safe_url(u):blocks.append({'type':'text','text':'[Externe Informationen öffnen]('+u+')'})
                self.issue(source,'Embedded third-party content converted to an external link');return
            if re.fullmatch('h[1-6]',n.tag):
                flush();heading=plain(text(n))
                if heading:blocks.append({'type':'heading','heading':heading[:255],'heading_level':2})
                return
            if n.tag=='img':
                flush();u=n.attrs.get('src','')
                if 'nextgen-attach_to_post' in u:
                    for g in self.records.values():
                        if g['type']=='gallery':blocks.append({'type':'text','text':'['+g['attributes']['title']+']('+g['path']+')'})
                    return
                key=self.pathfile(u,n.attrs.get('title'),n.attrs.get('alt'))
                if key:
                    blocks.append({'type':'image','reference':key})
                    if not self.assets[key]['attributes'].get('alt_text'):blocks.append({'type':'text','text':'[Bilddatei öffnen]('+u+')'})
                else:self.issue(source,'Image source could not be imported: '+u,'missing source')
                return
            if n.tag=='table':
                flush();rows=[[ ''.join(self.markdown(c) for c in cell.children) for cell in row.children if not isinstance(cell,str) and cell.tag in {'td','th'}] for row in nodes(n,'tr')]
                rows=[r for r in rows if any(v.strip() for v in r)]
                blocks.extend(self.tsv(rows,'Informationen',source,headers=False));return
            if n.tag=='migration-ref':
                flush();name=n.attrs['kind'];a=json.loads(n.attrs['source'])
                if name=='table':
                    info=self.tableids.get(a.get('id'))
                    if info:
                        id=info['source_id'];table=json.loads(self.posts[id]['post_content'])
                        if id=='2025':blocks.extend({'type':'contact','reference':k} for k in self.table_entities.get(id,[]));blocks.extend(self.tsv(table,info['title'],id,omit=[1]))
                        elif id=='2029':blocks.extend({'type':'services','reference':k} for k in self.table_entities.get(id,[]))
                        elif id=='2028':blocks.extend(self.tsv(table,info['title'],id))
                        elif id=='4582':blocks.extend(self.tsv(table,info['title'],id,omit=[0]))
                        else:blocks.extend(self.tsv(table,info['title'],id,headers=id=='8634'))
                    else:self.issue(source,'Unresolved TablePress ID','ambiguous mapping')
                elif name in {'pdf-embedder','wpdm_package','wpdm_download_link'}:
                    key=self.pathfile(a.get('url','')) if name=='pdf-embedder' else 'wp:'+a.get('id','')
                    if key in self.assets:blocks.append({'type':'downloads','reference':key})
                    elif source=='wp:8567' and 'wp:8132' in self.assets:
                        # Same published topic, not a claim that the unavailable original is identical.
                        blocks.append({'type':'downloads','reference':'wp:8132'})
                        self.issue(source,'Embedded original is unavailable; related verified public notice WPDM 8132 is supplied for the same topic','missing source')
                    else:self.issue(source,'Unresolved embedded document','missing source')
                elif name in {'wpdm_category','wpdm_tree'}:
                    cat=a.get('id','')
                    selected=[id for id in self.public if any(t=='wpdmcategory' and (tid==cat or not cat) for t,tid in self.assignments[id])]
                    for id in sorted(selected,key=lambda id:self.posts[id]['post_title']):
                        key=next((x['key'] for x in self.assets.values() if x['key']=='wp:'+id or 'wp:'+id in x.get('source_keys',[])),None)
                        if key in self.assets:blocks.append({'type':'downloads','reference':key})
                    if not selected:self.issue(source,'Download category requires editorial check: '+cat)
                elif name=='link-library':
                    cat=a.get('categorylistoverride') or ('194' if source=='wp:257' else None)
                    for key,r in self.records.items():
                        if r['type']=='external-resource' and (not cat or ('link_library_category',cat) in self.assignments.get(key.removeprefix('wp:'),[])):
                            blocks.append({'type':'external','reference':key})
                    # Local Link Library downloads are preserved as documents too.
                    for id in self.public:
                        if self.posts[id]['post_type']=='link_library_links':
                            u=self.meta[id].get('link_url','');key=self.assetpaths.get(unquote(urlparse(html.unescape(u)).path).lstrip('/'))
                            if key:blocks.append({'type':'downloads' if self.assets[key]['type']=='document' else 'image','reference':key})
                elif name in {'html-sitemap','catlist','pt_view','connections','ngg','nggallery','ngg_images','ngg_album','contact-form-7'}:
                    self.issue(source,'Dynamic '+name+' replaced by local catalog, navigation or contact; source preserved')
                    if name=='contact-form-7':blocks.append({'type':'text','text':'[Kontakt mit der Gemeinde aufnehmen](/kontakt)'})
                    elif name=='html-sitemap':
                        if source!='wp:26':blocks.append({'type':'text','text':'[Alle Seiten im Inhaltsverzeichnis](/inhaltsverzeichnis)'})
                        else:
                            for r in self.records.values():
                                if r['type']=='page' and r['path'] and r['key']!=source:blocks.append({'type':'text','text':'['+r['attributes']['title']+']('+r['path']+')'})
                    elif name=='catlist':
                        for id in self.public:
                            if self.posts[id]['post_type']=='post' and any(t=='category' and tid==a.get('id') for t,tid in self.assignments[id]):
                                r=self.records['wp:'+id];blocks.append({'type':'text','text':'['+r['attributes']['title']+']('+r['path']+')'})
                elif name.startswith('/') or name=='caption':pass
                else:self.issue(source,'Unconverted shortcode: '+name,'ambiguous mapping')
                return
            if n.tag=='a' and any(True for _ in nodes(n,'img')):
                for c in n.children:walk(c)
                return
            if n.tag=='a' and not any(True for _ in nodes(n,'img')):buffer.append(self.markdown(n));return
            if n.tag in {'strong','b','em','i'}:buffer.append(self.markdown(n));return
            if n.tag=='li':buffer.append('\n- ')
            if n.tag in {'p','div','ul','ol','blockquote','figure'}:buffer.append('\n\n')
            if n.tag=='br':buffer.append('\n')
            for c in n.children:walk(c)
            if n.tag in {'p','div','li','ul','ol','blockquote','figure'}:buffer.append('\n\n')
        walk(tree);flush();return blocks

    def run(self):
        OUT.mkdir(parents=True,exist_ok=True)
        recovery=json.loads((SOURCE/'recovery/manifest.json').read_text());assert len(recovery)==61 and all(r['status']=='recovered' for r in recovery)
        for r in csv.DictReader((ROOT/'docs/migration/inventory/source-provenance.csv').open()):
            if digest(SOURCE/r['source'])!=r['sha256']:raise ValueError('Original migration source changed')
        # Public package files only: explicit published, password-free package relationship.
        for id,p in self.public.items():
            if p['post_type']!='wpdmpro':continue
            m=self.meta[id]
            if m.get('__wpdm_password') or m.get('__wpdm_access') not in ('','a:1:{i:0;s:5:"guest";}','a:1:{i:0;s:5:"all";}'):
                # Live public delivery is required for restricted/unknown package settings.
                row=next((r for r in csv.DictReader((ROOT/'docs/migration/inventory/managed-downloads.csv').open()) if r.get('source_id')==id),{})
                if row.get('live_type')!='PDF' and row.get('response_type')!='PDF':
                    u='https://www.gemeinde-merching.de/?wpdmdl='+id
                    if self.crawl.get(u,{}).get('type')!='PDF':self.issue('wp:'+id,'Package is not verified publicly downloadable','intentionally skipped');continue
            files=decode(m.get('__wpdm_files','')) or []; files=list(files.values()) if isinstance(files,dict) else files
            found=[]
            for f in files:
                path=relative(str(f)) or unquote(urlparse(str(f)).path).lstrip('/')
                if not path:continue
                if not path.startswith('wp-content/'):path='wp-content/uploads/download-manager-files/'+path
                key=self.file(path,p['post_title'],key='wp:'+id,date=p['post_date_gmt']);
                if key:found.append(key)
            if found:
                self.alias('https://www.gemeinde-merching.de/?wpdmdl='+id,found[0]);self.alias(self.original(id),found[0])
                self.alias('https://www.gemeinde-merching.de/download/'+p['post_name']+'/',found[0])
                self.assets[found[0]]['categories']=[tid for t,tid in self.assignments[id] if t=='wpdmcategory']
            else:
                public_links=re.findall(r'href=[\"\'](https?://[^\"\']+)',p['post_content'])
                target=next((u for u in public_links if safe_url(u) and urlparse(u).hostname not in HOSTS),None)
                if target:
                    r=self.add('wp:'+id,'external-resource',{'title':plain(p['post_title'])[:255],'url':html.unescape(target),'type':'online_service','description':plain(p['post_content'])},date=p['post_date_gmt'])
                    self.issue('wp:'+id,'Public package has no file: preserved as verified external online service','imported')
                else:self.issue('wp:'+id,'No public file source','missing source')
        # Original files linked from successful public HTML responses, not backup membership alone.
        for u,r in self.crawl.items():
            if r.get('type')!='page' or r['status']>=400:continue
            for source,target,kind in r.get('edges',[]):
                if kind not in {'link','image'}:continue
                tr=self.crawl.get(target,{})
                # Office ZIP containers can be labelled XML by the inventory classifier.
                if (tr.get('type') in {'PDF','image','file'} or Path(urlparse(target).path).suffix.lower() in {'.doc','.docx','.xlsx','.pptx','.odt','.ods','.zip'}) and tr.get('status') in {200,206}:
                    self.pathfile(target)
        # Public page/article/service structure; canonical URLs selected independently of WP menus.
        canonical={'4':'/veranstaltungen','8':'/aktuelles','10':'/buergerservice','20':'/rathaus-und-politik/verwaltung','24':'/impressum','26':'/inhaltsverzeichnis','51':'/willkommen','56':'/weitere-downloads','92':'/rathaus-und-politik/grusswort','138':'/email-formular','212':'/rathaus-und-politik/gemeinderat','222':'/buergerservice/a-z','224':'/buergerservice/lebenslagen','227':'/ortsrecht','249':'/vereine','257':'/formulare','3658':'/datenschutz','670':'/bekanntmachungen','9222':'/haushaltsplaene','9637':'/barrierefreiheit','7085':'/dokumente','2316':'/leben/kinder-und-jugend','255':'/leben/badeseen'}
        servicepages={'296','300','302','310','810','1987'}
        for id,p in self.public.items():
            typ=p['post_type']
            if typ not in {'page','post','encyclopedia'}:continue
            kind='article' if typ=='post' else 'service' if id in servicepages or typ=='encyclopedia' else 'page'
            if id=='212':kind='wahlperioden'
            path=canonical.get(id) or ('/aktuelles/' if kind=='article' else '/buergerservice/leistungen/' if kind=='service' else '/')+p['post_name']
            attrs={'title':plain(p['post_title'])}
            if kind=='wahlperioden':attrs.update(starts_on='2026-05-01',ends_on='2032-04-30',is_historical=False,description='Gemeinderat 01.05.2026–30.04.2032. Beratende Ausschüsse: Bau- und Umweltausschuss, Ausschuss für Kultur und Sport, Rechnungsprüfungsausschuss. Die nächsten Sitzungstermine finden Sie im Veranstaltungskalender.')
            r=self.add('wp:'+id,kind,attrs,path,p['post_date_gmt']);self.alias(self.original(id),r['key']);self.alias('https://www.gemeinde-merching.de/?'+('page_id' if typ=='page' else 'p')+'='+id,r['key']);self.alias('https://www.gemeinde-merching.de/?p='+id,r['key'])
            if kind=='article':r['categories']=[self.terms[tid]['name'] for t,tid in self.assignments[id] if t=='category'];r['tags']=[self.terms[tid]['name'] for t,tid in self.assignments[id] if t=='post_tag']
        # Gallery exclusions are editorial; never turn an excluded original into a public gallery item.
        for g in self.d['wp_ngg_gallery']:
            if g.get('is_private') not in (None,'','0'):self.issue('ngg-gallery:'+g['gid'],'Private gallery','intentionally skipped');continue
            key='ngg-gallery:'+g['gid'];r=self.add(key,'gallery',{'title':plain(g['title']),'description':plain(g['galdesc'])},'/galerien/'+g['slug'],g.get('date_created'));r['items']=[]
            for pic in sorted((x for x in self.d['wp_ngg_pictures'] if x['galleryid']==g['gid']),key=lambda x:(int(x['sortorder']),int(x['pid']))):
                if pic['exclude']=='1':self.issue('ngg:'+pic['pid'],'NextGEN excluded image','intentionally skipped');continue
                mk=self.file(g['path'].strip('/')+'/'+pic['filename'],pic['alttext'] or pic['filename'],pic['alttext'],key='ngg:'+pic['pid'],date=pic['imagedate'])
                if mk:self.assets[mk]['attributes']['caption']=plain(pic['description']) or None;r['items'].append(mk)
        # Structured employees; photo column intentionally omitted. Public rows only.
        people=[]
        for i,row in enumerate(json.loads(self.posts['2025']['post_content'])[1:],1):
            if not plain(row[2]):continue
            names=[re.sub(r'\s*\(.*?\)\s*','',re.sub(r'^Leiter:\s*','',clean(n))).strip() for n in re.split(r'\s*/\s*|\n',plain(row[2])) if clean(n)]
            emails=re.findall(r'mailto:([^"\s>]+)',row[3]);phone=re.search(r'Tel(?:efon)?\s*\.?\s*:?\s*([^\n<]+)',row[3],re.I)
            for j,name in enumerate(names):
                key=f'table:2025:{i}:{j}'
                existing=next((r for r in self.records.values() if r['type']=='person' and r['attributes']['display_name']==name),None)
                if existing:
                    existing.setdefault('source_keys',[]).append(key)
                    existing['attributes']['responsibilities']+='; '+plain(row[0])
                    existing['attributes']['public_notes']+='\n\n'+plain(row[3])
                    if existing['key'] not in people:people.append(existing['key'])
                    continue
                r=self.add(key,'person',{'display_name':name,'first_name':name.rsplit(' ',1)[0] if ' ' in name else None,'last_name':name.rsplit(' ',1)[-1],'job_title':plain(row[0]),'responsibilities':plain(row[0]),'phone':clean(phone[1]) if phone else None,'email':html.unescape(emails[j]) if j<len(emails) else None,'public_notes':plain(row[3]),'is_active':True,'sort_order':i});people.append(key)
        self.table_entities['2025']=people
        # A–Z row relationships refer to the named office, not inferred individual employees.
        servicekeys=[];departments={}
        for i,row in enumerate(json.loads(self.posts['2029']['post_content'])[1:],1):
            title=plain(row[0]);office=plain(row[1]);phone=plain(row[2])
            if not title:continue
            key=f'table:2029:{i}'
            existing=next((r for r in self.records.values() if r['type']=='service' and r['attributes']['title'].casefold()==title.casefold()),None)
            r=existing or self.add(key,'service',{'title':title,'summary':office or None},'/buergerservice/leistungen/'+slug(title)+'-'+str(i),self.posts['2029']['post_date_gmt'])
            r['blocks'].append({'type':'text','text':self.markdown(Tree(row[0]).root).strip()+'\n\n'+('Zuständige Stelle: '+office+'\n\n' if office else '')+('Telefon: '+phone if phone else '')})
            if office:
                signature=office
                if signature not in departments:
                    dk='table:2029:department:'+str(i);departments[signature]=dk;self.add(dk,'department',{'name':office[:255],'phone':phone[:255] or None,'is_active':True,'sort_order':i},'/verzeichnisse/stellen/'+slug(office)+'-'+str(i))
                dk=departments[signature]
                if phone and phone!=self.records[dk]['attributes']['phone']:
                    self.records[dk]['attributes']['description']=(self.records[dk]['attributes'].get('description') or '')+'\nTelefon für '+title+': '+phone
                r['relations'].setdefault('departments',[]).append(dk)
            servicekeys.append(r['key'])
        self.table_entities['2029']=servicekeys
        # Council rows confirm names, roles, groups, portraits; no inferred committee memberships.
        council=self.records['wp:212'];council['memberships']=[]
        for i,row in enumerate(json.loads(self.posts['4582']['post_content'])[1:],1):
            if not plain(' '.join(row[1:3])):continue
            name=plain(row[2]+' '+row[1]);key='table:4582:'+str(i);portrait=next(nodes(Tree(row[0]).root,'img'),None)
            pk=self.pathfile(portrait.attrs['src'],name,portrait.attrs.get('alt') or name) if portrait else None
            r=self.add(key,'ratsmitglieder',{'title':name,'description':plain(row[4]) or None});r['portrait']=pk
            if pk and not self.assets[pk]['attributes'].get('alt_text'):self.issue(key,'Council portrait alt text unknown; abstract placeholder used pending editorial alternative')
            council['memberships'].append({'key':key,'role':plain(row[4]) or 'Mitglied','grouping':plain(row[3]) or None,'sort_order':i})
        for i,title in enumerate(['Bau- und Umweltauschuss','Ausschuss für Kultur und Sport','Rechnungsprüfungsausschuss']):
            self.add('council-committee:'+str(i),'ausschuesse',{'title':title,'sort_order':i})['term']='wp:212'
        # Parse full, unambiguous calendar dates; preserve uncertain date/time labels verbatim.
        eventkeys=[]
        from zoneinfo import ZoneInfo
        for i,row in enumerate(json.loads(self.posts['8634']['post_content'])[1:],1):
            dates=re.findall(r'\b(\d{1,2}\.\d{1,2}\.\d{4})\b',plain(row[0]))
            if not dates or not plain(row[3]):self.issue('table:8634:'+str(i),'Calendar separator or ambiguous date preserved in calendar table','ambiguous mapping');continue
            try:
                start=datetime.strptime(dates[0],'%d.%m.%Y');end=datetime.strptime(dates[-1],'%d.%m.%Y');times=re.fullmatch(r'(\d{1,2})[:.](\d{2})(?:\s*Uhr)?',plain(row[1]),re.I);unspecified=not bool(times);all_day=False
                if times:start=start.replace(hour=int(times[1]),minute=int(times[2]));end=end.replace(hour=int(times[1]),minute=int(times[2]))
                else:end=end.replace(hour=23,minute=59,second=59)
                key='table:8634:'+str(i);r=self.add(key,'event',{'title':plain(row[3])[:255],'description':self.markdown(Tree(row[3]).root).strip(),'starts_at':start.replace(tzinfo=ZoneInfo('Europe/Berlin')).astimezone(timezone.utc).strftime('%Y-%m-%d %H:%M:%S'),'ends_at':end.replace(tzinfo=ZoneInfo('Europe/Berlin')).astimezone(timezone.utc).strftime('%Y-%m-%d %H:%M:%S') if len(dates)>1 or unspecified else None,'all_day':all_day,'time_is_unspecified':unspecified,'time_text':plain(row[1]) or None,'auto_archive':False,'venue':plain(row[2]) or None,'organizer_name':plain(row[4]) or None,'remarks':plain(row[5]) or None,'schedule_notice':None},'/veranstaltungen/'+slug(plain(row[3]))+'-'+str(i),self.posts['8634']['post_date_gmt']);eventkeys.append(key)
                if unspecified:self.issue(key,'No single clock time; original schedule retained verbatim')
            except ValueError:self.issue('table:8634:'+str(i),'Unparseable calendar date preserved in table','ambiguous mapping')
        self.table_entities['8634']=eventkeys
        # Public Connections approved organizations, selective contact table allowlist.
        orgnames={}
        for o in self.d['wp_connections']:
            if o['visibility']!='public' or o['status']!='approved':continue
            name=plain(o['organization'])
            if not name:self.issue('connections:'+o['id'],'Unnamed entry preserved only where visibly rendered','ambiguous mapping');continue
            key='connections:'+o['id'];attrs={'name':name,'type':'gewerbe','is_active':True,'sort_order':int(o['ordo'] or 0),'description':plain(o['bio']) or None}
            a=next((v for v in self.d['wp_connections_address'] if v['entry_id']==o['id'] and v['visibility']=='public'),None)
            if a:attrs.update(street=a['line_1'],postal_code=a['zipcode'],city=a['city'])
            phone=next((v for v in self.d['wp_connections_phone'] if v['entry_id']==o['id'] and v['visibility']=='public'),None)
            if phone:attrs['phone']=phone['number']
            link=next((v for v in self.d['wp_connections_link'] if v['entry_id']==o['id'] and v.get('visibility')=='public'),None)
            if link and safe_url(link.get('url')):attrs['website']=html.unescape(link['url'])
            self.add(key,'organization',attrs,'/verzeichnisse/gewerbe/'+slug(name));orgnames[name.casefold()]=key
        # Clubs: exact named rows, preserve complete visible row as description.
        for i,row in enumerate(json.loads(self.posts['2028']['post_content'])[1:],1):
            name=plain(row[0]);
            if not name:continue
            key=orgnames.get(name.casefold()) or 'table:2028:'+str(i)
            self.add(key,'organization',{'name':name[:255],'type':'verein','description':self.markdown(Tree(row[1]).root).strip(),'is_active':True,'sort_order':i},'/verzeichnisse/vereine/'+slug(name)+'-'+str(i))
        # Link Library: public URL/title allowlist, never plugin submission/recipient metadata.
        for id,p in self.public.items():
            if p['post_type']!='link_library_links':continue
            u=html.unescape(self.meta[id].get('link_url',''))
            if not safe_url(u):self.issue('wp:'+id,'Unsafe/absent link destination','ambiguous mapping');continue
            purl=urlparse(u)
            if purl.hostname in HOSTS:
                fk=self.pathfile(u,p['post_title'])
                if fk:self.alias('https://www.gemeinde-merching.de/?p='+id,fk)
                else:self.issue('wp:'+id,'Internal link retained in page conversion; specialized resource unnecessary','intentionally skipped')
                continue
            kind='form' if 'formular' in u else 'portal' if 'buergerservice-portal' in u else 'map' if 'bayernatlas' in u else 'information'
            r=self.add('wp:'+id,'external-resource',{'title':plain(p['post_title'])[:255],'url':u,'type':kind},date=p['post_date_gmt'])
        self.add('portal:public','external-resource',{'title':'Bürgerserviceportal Merching','url':'https://www.buergerservice-portal.de/bayern/merching/','type':'portal'})
        if 'wp:8722' in self.records:
            target=next((r for r in self.records.values() if r['type']=='service' and 'straßenbeleuchtung' in r['attributes']['title'].lower()),None)
            if target:
                target['blocks'].extend(self.convert(self.posts['8722']['post_content'],'wp:8722'))
                target['blocks'].append({'type':'external','reference':'wp:8722'})
                self.alias('https://www.gemeinde-merching.de/?wpdmdl=8722',target['key'])
                self.alias('https://www.gemeinde-merching.de/download/'+self.posts['8722']['post_name']+'/',target['key'])
        # Original publication dates and semantic blocks. No old theme, scripts or plugin markup.
        for id,p in self.public.items():
            key='wp:'+id
            if key not in self.records or p['post_type'] not in {'page','post','encyclopedia'}:continue
            r=self.records[key]
            if r['type']=='wahlperioden':continue
            r['blocks']=self.convert(p['post_content'],key)+r['blocks']
            thumb=self.meta[id].get('_thumbnail_id')
            if thumb in self.posts:
                path=self.meta[thumb].get('_wp_attached_file','')
                mk=self.file('wp-content/uploads/'+path,self.posts[thumb]['post_title'],self.meta[thumb].get('_wp_attachment_image_alt'),date=self.posts[thumb]['post_date_gmt']) if path else None
                if mk:r['relations']['media']=[mk]
            # Views are replaced by explicitly selected published articles, not the view runtime.
            views=re.findall(r'"viewId":"([^" ]+)"',p['post_content'])
            if views and id!='51':
                categories={'4631':['Fundsachen'],'390':['Aus dem Rathaus'],'382':['Aus Merching'],'385':['Aus dem Landratsamt und anderen Behörden']}.get(id,[])
                for article in self.records.values():
                    if article['type']=='article' and (not categories or set(categories)&set(article.get('categories',[]))):r['blocks'].append({'type':'text','text':'['+article['attributes']['title']+']('+article['path']+')'})
        # Preserve explicit archive-only Encyclopedia information, flag classification.
        for id,p in self.public.items():
            if p['post_type']=='encyclopedia':self.issue('wp:'+id,'SQL-only Encyclopedia entry preserved as service; classification needs review')
        # Modern catalogues render their structured entries themselves, once.
        # Preserve the calendar's ambiguous source rows on a linked companion page.
        source_calendar=self.add('structure:calendar-source','page',{'title':'Vollständiger veröffentlichter Veranstaltungskalender'},'/veranstaltungen/veroeffentlichter-kalender',self.posts['4']['post_date_gmt'])
        source_calendar['blocks']=self.records['wp:4']['blocks']
        self.records['wp:4']['blocks']=[{'type':'text','text':'[Vollständigen veröffentlichten Veranstaltungskalender ansehen]('+source_calendar['path']+')'}]
        self.records['wp:8']['blocks']=[]
        for key,types in [('wp:222',{'services'}),('wp:7085',{'downloads'}),('wp:9222',{'downloads'})]:
            self.records[key]['blocks']=[b for b in self.records[key]['blocks'] if b['type'] not in types]
        # New intentional landing architecture is retained using real destinations.
        groups=[('rathaus','Rathaus und Politik','/rathaus-und-politik',['20','212','227','92','9222']),('leben','Leben in Merching','/leben',['14','2316','249','251','255','547']),('bauen','Bauen und Wirtschaft','/bauen-und-wirtschaft',['12','1999','4787','415']),('verzeichnisse','Ansprechpersonen und Einrichtungen','/verzeichnisse',['20','249','12','415'])]
        for key,title,path,ids in groups:
            r=self.add('structure:'+key,'page',{'title':title},path)
            r['blocks']=[{'type':'text','text':'['+self.records['wp:'+id]['attributes']['title']+']('+self.records['wp:'+id]['path']+')'} for id in ids]
        # Budget source packages: actual ordered PDFs, never fabricate successful derivatives.
        budgets=collections.defaultdict(list)
        for row in csv.DictReader((ROOT/'docs/migration/inventory/budget-components.csv').open()):budgets[(row['year'],row['issuer'])].append(row)
        for (year,topic),rows in budgets.items():
            key='budget:'+year+':'+topic;r=self.add(key,'budget-plan',{'title':topic+' – Haushaltsplan '+year,'year':int(year),'topic':topic,'description':'Haushaltsplan mit Anlagen. Die Originaldateien stehen in ihrer veröffentlichten Reihenfolge zur Verfügung.','show_components':True,'accessibility_status':'not_checked'},'/haushaltsplaene/'+year+'/'+slug(topic),min(self.posts[x['source_id']]['post_date_gmt'] for x in rows));r['components']=['wp:'+x['source_id'] for x in sorted(rows,key=lambda x:int(x['order']))]
            self.issue(key,'Main PDF unsupported by FPDI; original sources published, combined derivative unavailable','merge issue')
        for id,p in self.public.items():
            if p['post_type']=='wpdmpro' and ('wpdmcategory','209') in self.assignments[id]:
                assetkey=next((a['key'] for a in self.assets.values() if a['key']=='wp:'+id or 'wp:'+id in a.get('source_keys',[])),None)
                if assetkey:
                    key='notice:'+id
                    r=self.add(key,'notice',{'title':plain(p['post_title'])[:255]},'/bekanntmachungen/'+slug(p['post_title'])+'-'+id,p['post_date_gmt'])
                    r['blocks']=[{'type':'downloads','reference':assetkey}]
                    self.issue(key,'Official notice category verified; legal notice/Aushang dates remain unverified')
        # Specialized notice classification requires legal review; preserve articles and all notice downloads.
        for r in self.records.values():
            if r['type']=='article' and 'Öffentliche Bekanntmachungen' in r.get('categories',[]):self.issue(r['key'],'Notice retained as Article; legal publication dates/notice subtype require review','ambiguous mapping')
        # Audit every source item, including unpublished helpers, without copying their bodies.
        for id,p in self.posts.items():
            if p['post_type'] in {'page','post','wpdmpro','encyclopedia'} and id not in self.public:self.issue('wp:'+id,'Unpublished or password-protected source; no import','intentionally skipped')
        for info in self.tableids.values():
            self.tables.append({'source_id':info['source_id'],'title':info['title'],'used_on_posts':info['used_on_posts'],'conversion':'structured records + controlled table' if info['source_id'] in self.table_entities or info['source_id'] in {'2028','4582'} else 'controlled table' if info['used_on_posts'] else 'intentionally skipped: unused/obsolete helper'})
        # Direct attachment identity is only mapped when its file is proven public.
        for id,p in self.posts.items():
            if p['post_type']=='attachment':
                path='wp-content/uploads/'+self.meta[id].get('_wp_attached_file','')
                if path in self.assetpaths:
                    key=self.assetpaths[path]
                    self.assets[key].setdefault('source_keys',[]).append('wp:'+id)
                    self.alias('https://www.gemeinde-merching.de/?attachment_id='+id,key)
        for f in self.d['wp_wpfb_files']:
            path='wp-content/uploads/filebase/'+f['file_path'].lstrip('/')
            candidates=[path,'wp-content/uploads/'+f['file_path'].lstrip('/'),'wp-content/'+f['file_path'].lstrip('/'),f['file_path'].lstrip('/')]
            key=next((self.assetpaths[p] for p in candidates if p in self.assetpaths),None)
            if key:self.alias('https://www.gemeinde-merching.de/?wpfb_dl='+f['file_id'],key)
            else:self.issue('filebase:'+f['file_id'],'Backup-only or no verified current public file relationship','intentionally skipped')
        # The redesigned homepage retains its own Laravel controller and real settings.
        self.alias('https://www.gemeinde-merching.de/','wp:51','/')
        self.alias('https://www.gemeinde-merching.de/?page_id=51','wp:51','/')
        self.alias('https://www.gemeinde-merching.de/?p=51','wp:51','/')
        self.alias('/leben/kinderbetreuung','wp:2316')
        self.alias('/leben/freizeit-am-see','wp:255')
        # Preserve observed plugin query variants and byte-identical public files.
        # A successful seeded GET alone does not make an unlinked backup file public.
        public_hashes={a['sha256']:a['key'] for a in self.assets.values()}
        for url,response in self.crawl.items():
            p=urlparse(url);q=parse_qs(p.query);path=unquote(p.path).lstrip('/')
            identifiers=[k for k in ['wpdmdl','p','page_id','wpfb_dl','download_id','attachment_id'] if k in q]
            if len(identifiers)==1:
                name=identifiers[0];value=q[name][0]
                identity=self.legacy.get('/?'+name+'='+str(int(value))) if value.isdigit() else None
                if identity:self.alias(url,identity['key'],identity['destination'])
                continue
            key=self.assetpaths.get(path)
            if not key and response.get('type') in {'PDF','image','file'}:
                candidate=re.sub(r'-\d+x\d+(?=\.[^.]+$)','',path)
                candidate=re.sub(r'/thumbs/thumbs_', '/',candidate)
                key=self.assetpaths.get(candidate) or self.assetpaths.get(self.original_paths.get(candidate,''))
                if not key and path in self.fs:key=public_hashes.get(self.fs[path]['sha256'])
            if key:self.alias(url,key)
            if '/nggallery/album/' in p.path:
                gallery=next((r for r in self.records.values() if r['type']=='gallery' and r['path'].endswith('/'+p.path.rstrip('/').split('/')[-1])),None)
                if gallery:self.alias(url,gallery['key'])
        # Archives consolidate into the redesigned listings; entries remain searchable.
        for url in self.crawl:
            path=urlparse(url).path
            if path.startswith(('/author/','/category/','/pt_view/')):self.alias(url,'wp:8')
            elif path.startswith('/download-category/'):self.alias(url,'wp:9222' if '/haushaltsplaene' in path else 'wp:7085')
            elif path.rstrip('/')=='/alle-downloads':self.alias(url,'wp:7085')
        # Rewrite local links to canonical destinations (no old-site requests from the new site).
        def rewrite(s):
            for u,destination in sorted(self.links.items(),key=lambda v:len(v[0]),reverse=True):
                if destination and not destination.startswith('asset:'):s=s.replace('('+u+')','('+destination+')')
            return s
        for r in self.records.values():
            for b in r['blocks']:
                if b.get('text'):b['text']=rewrite(b['text'])
            for field in ['description','body']:
                if r['attributes'].get(field):r['attributes'][field]=rewrite(r['attributes'][field])
        self.review=[x for x in self.review if not ('alternative text' in x['reason'] and self.assets.get(x['source'],{}).get('attributes',{}).get('alt_text'))]
        manifest={'version':1,'records':list(self.records.values()),'assets':list(self.assets.values()),'legacy':list(self.legacy.values()),'review':self.review,'tables':self.tables}
        (OUT/'manifest.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2))
        report=ROOT/'docs/migration/local';report.mkdir(exist_ok=True)
        for name,rows in [('preparation-review.csv',self.review),('tablepress.csv',self.tables)]:
            with (report/name).open('w',newline='') as handle:
                w=csv.DictWriter(handle,fieldnames=list(rows[0]),lineterminator='\n');w.writeheader();w.writerows(rows)
        print(json.dumps({'records':dict(collections.Counter(r['type'] for r in self.records.values())),'assets':dict(collections.Counter(r['type'] for r in self.assets.values())),'legacy_mappings':len(self.legacy),'review':len(self.review)},indent=2))

if __name__=='__main__':Migration().run()
