#!/usr/bin/env python3
"""Read-only WordPress inventory. Raw data stays below the gitignored source root.

Never executes SQL, imports Laravel records, or emits credential/option values.
"""
import argparse, collections, csv, hashlib, html, json, re, sys, zipfile
from pathlib import Path
from urllib.parse import urlparse, unquote
import xml.etree.ElementTree as ET

CONTENT_TABLES = {'posts','postmeta','terms','term_taxonomy','term_relationships','termmeta',
 'ngg_album','ngg_gallery','ngg_pictures','links','links_extrainfo','linkcategorymeta',
 'wpfb_cats','wpfb_files','wpfb_files_id3','connections','connections_address',
 'connections_phone','connections_link','connections_terms','connections_term_taxonomy',
 'connections_term_relationships','connections_meta','totalsoft_cal_1','totalsoft_cal_2',
 'totalsoft_cal_3','totalsoft_cal_4','totalsoft_cal_events','totalsoft_cal_events_p2',
 'totalsoft_cal_events_p3','totalsoft_cal_ids','totalsoft_cal_part','totalsoft_cal_types',
 'ppfuture_actions_args','ppfuture_workflow_scheduled_steps'}

def statements(path):
    """SQL statement lexer: quote-aware, handles mysqldump multiline INSERTs."""
    buf=[]; quoted=None; escaped=False
    with path.open(encoding='utf-8',errors='replace') as handle:
        for line in handle:
            if not buf and (line.lstrip().startswith('--') or not line.strip()): continue
            for c in line:
                buf.append(c)
                if escaped: escaped=False; continue
                if quoted:
                    if c=='\\': escaped=True
                    elif c==quoted: quoted=None
                elif c in "'\"`": quoted=c
                elif c==';':
                    yield ''.join(buf).strip(); buf=[]
    if ''.join(buf).strip(): raise ValueError('Unterminated SQL statement')

def values(source):
    """Yield SQL INSERT tuples; strings may contain commas/parentheses/escapes."""
    row=None; token=[]; quote=False; escape=False; string=False
    escapes={'n':'\n','r':'\r','t':'\t','0':'\0','b':'\b','Z':'\x1a'}
    for c in source:
        if escape: token.append(escapes.get(c,c)); escape=False; continue
        if quote:
            if c=='\\': escape=True
            elif c=="'": quote=False
            else: token.append(c)
            continue
        if c=="'":
            quote=True; string=True
            if not ''.join(token).strip():token=[]
        elif c=='(' and row is None: row=[]; token=[]; string=False
        elif row is not None and c in ',)':
            v=''.join(token) if string else ''.join(token).strip(); row.append(None if v=='NULL' and not string else v); token=[]; string=False
            if c==')': yield row; row=None
        elif row is not None: token.append(c)
    if quote or row is not None: raise ValueError('Incomplete SQL tuple')

def read_sql(path):
    schemas={}; data=collections.defaultdict(list); counts=collections.Counter(); prefixes=set()
    for stmt in statements(path):
        m=re.search(r'CREATE TABLE(?: IF NOT EXISTS)? `([^`]+)`\s*\((.*)',stmt,re.S)
        if m:
            table,body=m.groups(); schemas[table]=re.findall(r'^\s*`([^`]+)`\s+',body,re.M); continue
        m=re.search(r'\bINSERT(?: IGNORE)? INTO `([^`]+)`\s*(\([^;]*?\))?\s*VALUES\s*(.*);$',stmt,re.S)
        if not m: continue
        table,coltext,source=m.groups(); cols=re.findall(r'`([^`]+)`',coltext) if coltext else schemas.get(table,[])
        suffix=next((s for s in sorted(CONTENT_TABLES,key=len,reverse=True) if table.endswith(s)),None)
        # Options are parsed only for a strict public-settings allowlist.
        if table.endswith('options'): suffix='options'
        if suffix: prefixes.add(table[:-len(suffix)])
        for row in values(source):
            counts[table]+=1
            if suffix:
                if len(row)!=len(cols): raise ValueError(f'Column mismatch in {table}')
                item=dict(zip(cols,row))
                if suffix=='options' and item.get('option_name') not in {'siteurl','home','blogname','permalink_structure','active_plugins','template','stylesheet','show_on_front','page_on_front','page_for_posts','tablepress_tables','redirector','widget_text','widget_block','timezone_string','gmt_offset'}: continue
                data[table].append(item)
    return {'schemas':schemas,'counts':dict(counts),'data':dict(data),'prefixes':sorted(prefixes)}

def plain(s): return re.sub(r'\s+',' ',html.unescape(re.sub('<[^>]+>',' ',s or ''))).strip()
def safe_url(s):
    try:
        p=urlparse(html.unescape(s or '').strip())
        if p.scheme not in ('http','https') or p.username or p.password:return ''
        # Never emit recipient addresses/tokens/private endpoint parameters.
        if re.search(r'(?:email|recipient|token|key|nonce|password|auth|secret|session)=',p.query,re.I):return ''
        return p._replace(fragment='').geturl()
    except ValueError:return ''
def content_urls(s):return sorted({u for v in re.findall(r'https?://[^\s<>"\'\\]+',s or '') if (u:=safe_url(v.rstrip('.,);]')))})
def write_csv(out,name,rows,fields=None):
    rows=list(rows); fields=fields or list(dict.fromkeys(k for r in rows for k in r))
    with (out/name).open('w',encoding='utf-8',newline='') as f:
        w=csv.DictWriter(f,fieldnames=fields or ['note'],lineterminator="\n");w.writeheader();w.writerows(rows)
def digest(path):
    h=hashlib.sha256()
    with path.open('rb') as f:
        for block in iter(lambda:f.read(1024*1024),b''):h.update(block)
    return h.hexdigest()

def main():
    a=argparse.ArgumentParser();a.add_argument('--source',type=Path,default=Path('migration-source'));a.add_argument('--out',type=Path,default=Path('docs/migration/inventory'));args=a.parse_args()
    src=args.source;out=args.out;out.mkdir(parents=True,exist_ok=True);cache=src/'analysis-cache';cache.mkdir(exist_ok=True)
    archives=cache/'archives';archives.mkdir(exist_ok=True)
    provenance=[]
    for p in sorted(src.glob('*.sql'))+sorted(src.glob('*.xml'))+sorted((src/'wpvivid-full').glob('*.zip'))+sorted(src.glob('*backup_db/*.sql')):
        provenance.append({'source':str(p.relative_to(src)),'bytes':p.stat().st_size,'sha256':digest(p)})
    for p in sorted((src/'wpvivid-full').glob('*.zip')):
        with zipfile.ZipFile(p) as z:
            for info in z.infolist():
                if info.filename.endswith('.zip'):
                    target=archives/Path(info.filename).name
                    if not target.exists():target.write_bytes(z.read(info))
    fs={}; plugins={};active_prefix=None
    for p in sorted(archives.glob('*.zip')):
        with zipfile.ZipFile(p) as z:
            for i in z.infolist():
                name=i.filename.lstrip('/');name=re.sub(r'^.*?(?=wp-content/|wp-config.php)', '',name)
                if not name.startswith(('wp-content/','wp-config.php')): name='wp-content/'+name
                if i.is_dir():continue
                if name.endswith('wp-config.php'):
                    text=z.read(i).decode(errors='replace'); m=re.search(r'\$table_prefix\s*=\s*[\'"]([^\'"]+)',text)
                    if m:active_prefix=m[1]
                # Only public content paths become tracked reports; all other bytes stay private.
                if name.startswith('wp-content/plugins/'):
                    part=name.split('/')[2]
                    if name.count('/')==3 and name.endswith('.php'):
                        text=z.read(i).decode(errors='replace')[:8192]
                        m=re.search(r'^\s*\*?\s*Plugin Name:\s*(.+)',text,re.M)
                        if m:plugins[part]={'name':m[1].strip(),'version':(re.search(r'^\s*\*?\s*Version:\s*(.+)',text,re.M) or [None,'unknown'])[1].strip()}
                if not name.startswith('wp-content/'):continue
                if not re.search(r'\.(?:pdf|jpe?g|png|gif|webp|avif|svg|tiff?|bmp|docx?|xlsx?|odt|ods|pptx?|zip|mp[34]|mov|wav|ogg)$',name,re.I):continue
                if '/plugins/' in name or '/themes/' in name or re.search(r'/(?:cache|backup[^/]*|wpvivid[^/]*|wflogs)/',name,re.I):continue
                h=hashlib.sha256();
                with z.open(i) as f:
                    for block in iter(lambda:f.read(1024*1024),b''):h.update(block)
                fs[name]={'path':name,'bytes':i.file_size,'sha256':h.hexdigest(),'archive':p.name,'extension':Path(name).suffix.lower()}
    prod=read_sql(src/'69246m55851_1.sql');secondary=read_sql(next(src.glob('*backup_db/*.sql')))
    prefix_evidence='wp-config.php' if active_prefix else 'secondary DB single prefix; cross-check WXR IDs/titles; wp-config.php absent from supplied archives'
    if not active_prefix:
        candidates=[p for p in secondary['prefixes'] if p+'posts' in secondary['schemas']]
        if len(candidates)!=1:raise ValueError('Ambiguous active prefix; inspect privately')
        active_prefix=candidates[0]
    # Sensitive parsed data is cached privately for follow-up analysis, never in Git.
    (cache/'parsed.json').write_text(json.dumps({'production':prod,'secondary':secondary,'files':fs,'plugins':plugins,'prefix':active_prefix},ensure_ascii=False))
    data=prod['data'];prefix=active_prefix
    def table(s):return data.get(prefix+s,[])
    posts={r['ID']:r for r in table('posts')};meta=collections.defaultdict(dict)
    for r in table('postmeta'):meta[r['post_id']][r['meta_key']]=r['meta_value'] or ''
    wxr={};ns={'wp':'http://wordpress.org/export/1.2/','content':'http://purl.org/rss/1.0/modules/content/'}
    for item in ET.parse(next(src.glob('*.xml'))).findall('./channel/item'):
        id=item.findtext('wp:post_id',namespaces=ns)
        wxr[id]={'type':item.findtext('wp:post_type',namespaces=ns),'status':item.findtext('wp:status',namespaces=ns),'link':item.findtext('link'),'title':item.findtext('title'),'content':item.findtext('content:encoded',namespaces=ns) or ''}
    publictypes={'post','page','wpdmpro','attachment','link_library_links','tablepress_table','ngg_gallery','ngg_pictures','ngg_album','encyclopedia'}
    public={id:r for id,r in posts.items() if (r['post_status']=='publish' or r['post_type']=='attachment' and r['post_status']=='inherit') and r['post_type'] in publictypes}
    known={id:safe_url(w['link']) for id,w in wxr.items() if w['status']=='publish'}
    def url(r):
        id=r['ID'];w=known.get(id)
        if w:return w
        if r['post_type']=='page':
            slugs=[r['post_name']];parent=r.get('post_parent');seen={id}
            while parent in posts and parent not in seen:
                seen.add(parent);slugs.insert(0,posts[parent]['post_name']);parent=posts[parent].get('post_parent')
            return 'https://www.gemeinde-merching.de/'+'/'.join(slugs)+'/'
        if r['post_type']=='post':return 'https://www.gemeinde-merching.de/'+r['post_name']+'/'
        return safe_url(r.get('guid',''))
    content=[];review=[];mapping=[];seeds={'https://www.gemeinde-merching.de/','https://www.gemeinde-merching.de/robots.txt','https://www.gemeinde-merching.de/wp-sitemap.xml','https://www.gemeinde-merching.de/sitemap_index.xml'};postlinks={};meta_counts=collections.Counter()
    for id,r in public.items():
        typ=r['post_type'];m=meta[id];s=r['post_content'] or '';u=url(r);elementor=m.get('_elementor_data','');shortcodes=sorted(set(re.findall(r'\[([a-zA-Z][\w-]*)\b',s)))
        widgets=[]
        if elementor:
            try:
                def walk(x):
                    if isinstance(x,dict):
                        if x.get('widgetType'):widgets.append(x['widgetType'])
                        for v in x.values():walk(v)
                    elif isinstance(x,list):
                        for v in x:walk(v)
                walk(json.loads(elementor))
            except ValueError:review.append({'source_id':id,'type':'elementor','issue':'Malformed Elementor JSON; inspect privately'})
        target={'post':'Article','page':'Page','wpdmpro':'Document','attachment':'Media / Document','link_library_links':'ExternalResource','tablepress_table':'ContentBlock table','ngg_gallery':'Gallery','ngg_pictures':'Media','ngg_album':'Gallery collection'}.get(typ,'manual review')
        confidence='high' if typ in ('post','wpdmpro','ngg_gallery','ngg_pictures') else 'review'
        title=plain(r['post_title'])
        if typ=='page' and re.search('haushalt',title,re.I):target='Haushaltsplan catalog + yearly BudgetPlan';confidence='review'
        elif typ=='page' and re.search('gemeinderat',title,re.I):target='CouncilTerm / CouncilMember / Committee';confidence='review'
        elif typ=='page' and re.search('verein|gewerbe|gastronom',title,re.I):target='Organization catalog';confidence='review'
        elif typ=='page' and re.search('lebenslag',title,re.I):target='LifeSituation';confidence='review'
        content.append({'source_id':id,'type':typ,'status':r['post_status'],'title':title,'slug':r['post_name'],'parent_id':r.get('post_parent'),'created':r.get('post_date'),'modified':r.get('post_modified'),'url':u,'wxr_present':id in wxr,'elementor':bool(elementor),'elementor_widgets':'|'.join(sorted(set(widgets))),'shortcodes':'|'.join(shortcodes),'html_chars':len(s)})
        mapping.append({'source':'post:'+id,'source_type':typ,'title':title,'proposed_model':target,'confidence':confidence,'transformation':'Elementor widget conversion' if elementor else ('Shortcode resolution' if shortcodes else 'Sanitize HTML into controlled blocks'),'legacy_url':u})
        if confidence=='review' or shortcodes or elementor:review.append({'source_id':id,'type':typ,'issue':f'{target}; verify structured fields/relationships; widgets={"|".join(sorted(set(widgets)))}; shortcodes={"|".join(shortcodes)}'})
        urls=content_urls(s+' '+elementor);postlinks[id]=urls;
        if typ=='wpdmpro':seeds.add('https://www.gemeinde-merching.de/?wpdmdl='+id)
        seeds.update(u for u in urls if urlparse(u).hostname in {'www.gemeinde-merching.de','gemeinde-merching.de'});seeds.add(u)
        meta_counts.update(m.keys())
    attachments=[];docs=[];missing=[];refs=set();derivatives=set();doc_sources=[]
    for id,r in public.items():
        if r['post_type']=='attachment':
            rel=meta[id].get('_wp_attached_file','');path=('wp-content/'+unquote(rel).split('wp-content/',1)[1]) if 'wp-content/' in rel else 'wp-content/uploads/'+rel if rel and not rel.startswith(('http://','https://')) else '';file=fs.get(path)
            # Serialized metadata sizes are treated as candidates and checked, not executed.
            for name in re.findall(r's:\d+:"file";s:\d+:"([^"]+)"',meta[id].get('_wp_attachment_metadata','')):
                candidate=str(Path(path).parent/name)
                if candidate in fs:derivatives.add(candidate)
            refs.add(path)
            row={'source_id':id,'title':plain(r['post_title']),'path':path,'source_url':safe_url(rel) if rel.startswith(('http://','https://')) else 'https://www.gemeinde-merching.de/'+path,'mime':r['post_mime_type'],'exists_in_backup':bool(file),'bytes':file['bytes'] if file else '', 'sha256':file['sha256'] if file else '', 'parent_id':r.get('post_parent'),'metadata_present':bool(meta[id].get('_wp_attachment_metadata')),'accessibility':'not verified'}
            attachments.append(row)
            if not file:missing.append({'reference':'attachment:'+id,'path':path,'reason':'Attachment original absent in filesystem backup'})
        if r['post_type']=='wpdmpro':
            raw=meta[id].get('__wpdm_files','');paths=re.findall(r's:\d+:"([^"]+)"',raw)
            for v in paths:
                if v.startswith(('http://','https://')):path=unquote(urlparse(v).path).lstrip('/')
                else:path=('wp-content/'+v.split('wp-content/',1)[1]) if 'wp-content/' in v else 'wp-content/uploads/download-manager-files/'+v.lstrip('/')
                file=fs.get(path);refs.add(path); year=re.findall(r'20\d{2}',plain(r['post_title'])+' '+path)
                row={'source':'wpdmpro:'+id,'title':plain(r['post_title']),'path':path,'source_url':safe_url(v) if v.startswith('http') else 'https://www.gemeinde-merching.de/'+path,'public_package_url':url(r),'exists_in_backup':bool(file),'bytes':file['bytes'] if file else '', 'sha256':file['sha256'] if file else '', 'year_candidates':'|'.join(sorted(set(year))),'budget_candidate':bool(re.search('haushalt',plain(r['post_title'])+' '+path,re.I)),'accessibility':'not verified'}
                docs.append(row);doc_sources.append(row);seeds.add(row['source_url'])
                if not file:missing.append({'reference':row['source'],'path':path,'reason':'Download Manager source absent in backup'})
    galleries=[]
    for r in table('ngg_gallery'):
        gid=r.get('gid');pictures=[p for p in table('ngg_pictures') if p.get('galleryid')==gid]
        galleries.append({'gallery_id':gid,'title':plain(r.get('title')),'path':r.get('path'),'page_id':r.get('pageid'),'pictures':len(pictures),'preview_id':r.get('previewpic')})
        for p in pictures:
            path=(r.get('path','').rstrip('/')+'/'+p.get('filename','')).lstrip('/');refs.add(path);f=fs.get(path)
            attachments.append({'source_id':'ngg:'+p.get('pid',''),'title':plain(p.get('alttext')),'path':path,'source_url':safe_url(rel) if rel.startswith(('http://','https://')) else 'https://www.gemeinde-merching.de/'+path,'mime':'image','exists_in_backup':bool(f),'bytes':f['bytes'] if f else '', 'sha256':f['sha256'] if f else '', 'parent_id':'gallery:'+str(gid),'metadata_present':bool(p.get('meta_data')),'accessibility':'not verified'})
            seeds.add('https://www.gemeinde-merching.de/'+path)
            if not f:missing.append({'reference':'ngg:'+str(p.get('pid')),'path':path,'reason':'NextGEN original absent in backup'})
    # File-only PDFs and rendered public HTML links are public evidence, no private plugin files.
    linked_paths={unquote(urlparse(u).path).lstrip('/') for urls in postlinks.values() for u in urls if urlparse(u).hostname in {'www.gemeinde-merching.de','gemeinde-merching.de'}}
    refs.update(linked_paths)
    for path,f in fs.items():
        if f['extension']=='.pdf' and not any(d['path']==path for d in docs):
            docs.append({'source':'filesystem','title':'','path':path,'source_url':'https://www.gemeinde-merching.de/'+path,'public_package_url':'','exists_in_backup':True,'bytes':f['bytes'],'sha256':f['sha256'],'year_candidates':'|'.join(sorted(set(re.findall('20\\d{2}',path)))),'budget_candidate':bool(re.search('haushalt',path,re.I)),'accessibility':'not verified'})
        f['classification']='original/referenced' if path in refs else 'generated thumbnail' if path in derivatives or re.search(r'-\d+x\d+\.[^.]+$',path) or '/thumbs/' in path else 'unreferenced candidate (not proven orphan)'
    hashes=collections.defaultdict(list)
    for p,f in fs.items():hashes[f['sha256']].append(p)
    duplicates=[{'sha256':h,'paths':'|'.join(ps),'count':len(ps)} for h,ps in hashes.items() if len(ps)>1]
    tables=[{'table':t,'rows':prod['counts'].get(t,0),'secondary_rows':secondary['counts'].get(t,''),'columns':'|'.join(cols),'scope':'public/editorial candidate' if t.startswith(prefix) and (t[len(prefix):] in CONTENT_TABLES) else 'operational/private or inactive prefix; not exported'} for t,cols in prod['schemas'].items()]
    tablepress=[{'source_id':id,'title':plain(r['post_title']),'rows':len(json.loads(r['post_content'])) if (r['post_content'] or '').startswith('[') else '', 'bytes':len(r['post_content'] or ''),'shortcode_id':meta[id].get('_tablepress_table_id',''),'mapping':'controlled table block; preserve headers/links; check responsive layout'} for id,r in public.items() if r['post_type']=='tablepress_table']
    ext=[]
    for id,urls in postlinks.items():
        for u in urls:
            if urlparse(u).hostname not in {'www.gemeinde-merching.de','gemeinde-merching.de'}:ext.append({'source_id':id,'url':u,'checked_live':False})
    for r in table('links'):
        u=safe_url(r.get('link_url'))
        if u:ext.append({'source_id':'link:'+r.get('link_id',''),'url':u,'checked_live':False})
    schedules=[]
    for id,m in meta.items():
        keys=[k for k in m if re.search('expir|future|schedule',k,re.I)]
        if keys and id in public:schedules.append({'source_id':id,'title':plain(posts[id]['post_title']),'metadata_keys':'|'.join(keys),'mapping':'manual review expiry/timezone/action; private raw values excluded'})
    menus=[]
    for id,r in posts.items():
        if r['post_type']!='nav_menu_item' or r['post_status']!='publish':continue
        m=meta[id];menus.append({'source_id':id,'label':plain(r['post_title']),'object_type':m.get('_menu_item_object'),'object_id':m.get('_menu_item_object_id'),'parent_id':m.get('_menu_item_menu_item_parent'),'order':r.get('menu_order'),'custom_url':safe_url(m.get('_menu_item_url'))})
        if safe_url(m.get('_menu_item_url')):seeds.add(safe_url(m['_menu_item_url']))
    taxonomy=[];terms={r['term_id']:r for r in table('terms')}
    for r in table('term_taxonomy'):
        t=terms.get(r['term_id'],{});taxonomy.append({'term_id':r['term_id'],'taxonomy':r['taxonomy'],'name':plain(t.get('name')),'slug':t.get('slug'),'parent_id':r.get('parent'),'assigned_count':r.get('count')})
    discrepancies=[]
    secposts={r['ID']:r for r in secondary['data'].get(prefix+'posts',[])}
    for id,r in posts.items():
        if id not in public:continue
        w=wxr.get(id);sr=secposts.get(id)
        if not w:discrepancies.append({'source_id':id,'comparison':'SQL/WXR','issue':'Published record missing in WXR'})
        elif w['content']!=(r['post_content'] or ''):
            formatting=w['content'].replace('\r\n','\n')==(r['post_content'] or '').replace('\r\n','\n')
            discrepancies.append({'source_id':id,'comparison':'SQL/WXR','issue':'CRLF/LF serialization difference only' if formatting else 'Post content differs beyond line-ending normalization'})
        if not sr:discrepancies.append({'source_id':id,'comparison':'SQL/secondary DB','issue':'Published record missing in secondary'})
        elif any(sr.get(k)!=r.get(k) for k in ('post_title','post_content','post_status','post_modified')):discrepancies.append({'source_id':id,'comparison':'SQL/secondary DB','issue':'Title/content/status/modified differs'})
    for id,w in wxr.items():
        if w['status']=='publish' and id not in posts:discrepancies.append({'source_id':id,'comparison':'WXR/SQL','issue':'Published WXR ID absent from active SQL prefix'})
    reports={'content.csv':content,'mapping.csv':mapping,'manual-review.csv':review,'documents.csv':docs,'media.csv':attachments,'galleries.csv':galleries,'tables.csv':tables,'tablepress.csv':tablepress,'external-links.csv':ext,'missing-files.csv':missing,'filesystem.csv':list(fs.values()),'duplicates.csv':duplicates,'scheduled.csv':schedules,'menus.csv':menus,'taxonomies.csv':taxonomy,'discrepancies.csv':discrepancies,'source-provenance.csv':provenance,'postmeta-keys.csv':[{'key':k,'published_records':n} for k,n in meta_counts.most_common()]}
    for name,rows in reports.items():write_csv(out,name,rows)
    seeds={u for u in seeds if u and urlparse(u).hostname in {'www.gemeinde-merching.de','gemeinde-merching.de'}}
    (cache/'crawl-seeds.json').write_text(json.dumps(sorted(seeds),ensure_ascii=False))
    counts={'active_prefix':prefix,'prefix_evidence':prefix_evidence,'prefixes_in_dump':[p for p in prod['prefixes'] if p+'posts' in prod['schemas']],'post_types_statuses':dict(collections.Counter(r['post_type']+':'+r['post_status'] for r in posts.values())),'wxr_types':dict(collections.Counter(w['type'] for w in wxr.values())),'public_content_types':dict(collections.Counter(r['type'] for r in content)),'filesystem_extensions':dict(collections.Counter(f['extension'] for f in fs.values())),'filesystem_classification':dict(collections.Counter(f['classification'] for f in fs.values())),'attachments':len(attachments),'documents':len(docs),'galleries':len(galleries),'duplicates':len(duplicates),'missing_files':len(missing),'discrepancies':len(discrepancies),'seeds':len(seeds),'plugins':plugins,'public_settings':{r['option_name']:r['option_value'] for r in table('options') if r['option_name'] in {'siteurl','home','blogname','permalink_structure','template','stylesheet','show_on_front','page_on_front','page_for_posts','timezone_string','gmt_offset'}},'active_plugin_paths':re.findall(r's:\d+:"([^"]+)"',next((r['option_value'] for r in table('options') if r['option_name']=='active_plugins'),''))}
    (out/'counts.json').write_text(json.dumps(counts,ensure_ascii=False,indent=2)+'\n')
    print(json.dumps({k:v for k,v in counts.items() if k not in {'plugins','public_settings','active_plugin_paths'}},ensure_ascii=False,indent=2))
if __name__=='__main__':main()
