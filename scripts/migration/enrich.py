#!/usr/bin/env python3
"""Project sanitized plugin-specific inventory from private, read-only evidence."""
import base64,collections,csv,hashlib,html,json,re,subprocess,zipfile
from pathlib import Path
from urllib.parse import urlparse,unquote
from inventory import plain,safe_url,write_csv,content_urls

class Serialized:
    """Non-executable PHP serialization reader; objects deliberately unsupported."""
    def __init__(self,s):self.data=s.encode('utf-8');self.i=0
    def until(self,delimiter):
        j=self.data.index(delimiter,self.i);v=self.data[self.i:j];self.i=j+len(delimiter);return v
    def read(self):
        kind=self.data[self.i:self.i+1];self.i+=2
        if kind==b'N':return None
        if kind in (b'i',b'b',b'd'):
            v=self.until(b';').decode();return int(v) if kind!=b'd' else float(v)
        if kind==b's':
            n=int(self.until(b':'));assert self.data[self.i:self.i+1]==b'"';self.i+=1;v=self.data[self.i:self.i+n];self.i+=n;assert self.data[self.i:self.i+2]==b'";';self.i+=2;return v.decode('utf-8',errors='replace')
        if kind==b'a':
            n=int(self.until(b':'));assert self.data[self.i:self.i+1]==b'{';self.i+=1;d={}
            for _ in range(n):k=self.read();v=self.read();d[k]=v
            assert self.data[self.i:self.i+1]==b'}';self.i+=1;return d
        raise ValueError('Object/reference serialization intentionally unsupported')
def decode(s):
    try:return json.loads(s)
    except (ValueError,TypeError):
        try:return Serialized(s).read()
        except (ValueError,AssertionError,IndexError,AttributeError):return None

def relative(v):
    v=html.unescape(unquote(v or '')).replace('\\','/')
    if 'wp-content/' in v:return 'wp-content/'+v.split('wp-content/',1)[1]
    return ''

def main():
    src=Path('migration-source');cache=src/'analysis-cache';out=Path('docs/migration/inventory');x=json.loads((cache/'parsed.json').read_text());prefix=x['prefix'];d=x['production']['data'];fs=x['files'];posts={r['ID']:r for r in d[prefix+'posts']};meta=collections.defaultdict(dict)
    for r in d[prefix+'postmeta']:meta[r['post_id']][r['meta_key']]=r['meta_value'] or ''
    table=lambda s:d.get(prefix+s,[])
    opts={r['option_name']:r['option_value'] for r in table('options')};maps=decode(opts.get('tablepress_tables','')) or {};tablemap=maps.get('table_post',{}) if isinstance(maps,dict) else {}
    # TablePress options may be JSON with table IDs -> post IDs.
    if isinstance(tablemap,dict): ids={str(v):str(k) for k,v in tablemap.items()}
    else:ids={}
    allshortcodes=[];widgettypes=collections.Counter();htmlmetrics=[];terms={r['term_id']:r for r in table('terms')};tt={r['term_taxonomy_id']:r for r in table('term_taxonomy')};assignments=collections.defaultdict(list)
    for r in table('term_relationships'):
        term=tt.get(r['term_taxonomy_id'],{});label=terms.get(term.get('term_id'),{})
        assignments[r['object_id']].append((term.get('taxonomy',''),label.get('name',''),label.get('slug','')))
    for id,r in posts.items():
        if r['post_status']!='publish':continue
        text=r['post_content'] or '';shortcodes=re.findall(r'\[([a-zA-Z][\w-]*)\b([^\]]*)\]',text)
        for name,args in shortcodes:
            # Arguments can contain recipient addresses: only known content IDs/slugs are emitted.
            selected={}
            for match in re.finditer(r"""(id|catid|category|template|album_ids|gallery_ids|table|ids|src|display)\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s/\]]+))""",args):
                value=next(v for v in match.groups()[1:] if v is not None)
                if not re.search('@|token|secret',value,re.I):selected[match[1]]=value
            allshortcodes.append({'source_id':id,'title':plain(r['post_title']),'shortcode':name,'content_arguments':json.dumps(selected,ensure_ascii=False),'mapping':{'table':'resolve TablePress; prefer structured records','pdf-embedder':'Document block / download, not iframe','wpdm_category':'category-filtered Document catalog','connections':'Organization directory','ngg':'Gallery / ordered Media','contact-form-7':'existing contact workflow; recipients configured separately','link-library':'ExternalResource catalog','catlist':'Article catalog','html-sitemap':'new sitemap/index'}.get(name,'manual review')})
        if r['post_type'] in {'post','page','encyclopedia'}:
            htmlmetrics.append({'source_id':id,'title':plain(r['post_title']),'paragraphs':len(re.findall('<p(?:\\s|>)',text,re.I)),'headings':len(re.findall('<h[1-6](?:\\s|>)',text,re.I)),'images':len(re.findall('<img(?:\\s|>)',text,re.I)),'tables':len(re.findall('<table(?:\\s|>)',text,re.I)),'iframes':len(re.findall('<iframe(?:\\s|>)',text,re.I)),'scripts':len(re.findall('<script(?:\\s|>)',text,re.I)),'gutenberg_comments':len(re.findall('<!-- wp:',text)),'inline_style_attributes':len(re.findall('style=',text,re.I)),'elementor':bool(meta[id].get('_elementor_data'))})
    tables=[];table_rows=[];council=[];directory=[];eventrows=[];seedadd=set();review=[]
    for id,r in posts.items():
        if r['post_type']!='tablepress_table' or r['post_status']!='publish':continue
        cells=decode(r['post_content']) or [];shortid=ids.get(id,'');uses=[s['source_id'] for s in allshortcodes if s['shortcode']=='table' and json.loads(s['content_arguments']).get('id')==shortid] if shortid else []
        tables.append({'source_id':id,'shortcode_id':shortid,'title':plain(r['post_title']),'rows':len(cells),'columns':max(map(len,cells)) if cells else 0,'headers':'|'.join(plain(v) for v in cells[0]) if cells else '', 'used_on_posts':'|'.join(uses),'mapping':('CouncilMember / CouncilTerm' if id=='4582' else 'Event rows' if id=='8634' else 'Person / Department' if id=='2025' else 'Organization directory' if id in {'2027','2028'} else 'Service + Person/Department' if id=='2029' else 'manual review; controlled table block currently unavailable')})
        for idx,row in enumerate(cells):
            label=plain(row[0]) if row else ''
            label=re.sub(r'[A-Za-z0-9_.+%-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}','[email omitted]',label)
            table_rows.append({'source_table':id,'shortcode_id':shortid,'row':idx,'source_title':plain(r['post_title']),'label':label[:500],'columns':len(row),'links':sum(len(content_urls(v)) for v in row),'nonempty_cells':sum(bool(plain(v)) for v in row),'mapped_by_table':tables[-1]['mapping'],'review':'Header/separator/layout rows must be distinguished; raw HTML/contact addresses intentionally excluded'})
            for cell in row:
                for u in content_urls(cell):
                    if urlparse(u).hostname in {'www.gemeinde-merching.de','gemeinde-merching.de'}:seedadd.add(u)
            if id=='4582' and idx:
                urls=content_urls(row[0]);paths=[relative(u) for u in urls if relative(u)];name=' '.join(plain(v) for v in row[1:3]);
                council.append({'source_table':id,'row':idx,'member_name':name,'row_kind':'member' if name.strip() else 'separator','group':plain(row[3]) if len(row)>3 else '', 'function':plain(row[4]) if len(row)>4 else '', 'portrait_urls':'|'.join(urls),'portrait_paths':'|'.join(paths),'exists_in_backup':all(p in fs for p in paths) if paths else False,'review':'verify membership dates/term/row semantics; crop from original, not thumbnail'})
            if id=='8634' and idx:
                dates=re.findall(r'\b\d{1,2}\.\d{1,2}\.(?:\d{2}|\d{4})\b',plain(row[0]));eventrows.append({'source_table':id,'row':idx,'date_label':plain(row[0]),'time_label':plain(row[1]) if len(row)>1 else '', 'title':plain(row[3]) if len(row)>3 else '', 'date_parse_candidate':bool(dates),'review':'confirm month/year heading, date ranges, timezone and organizer/location; separator rows are not events'})
    for r in table('connections'):
        if r['visibility']!='public' or r['status']!='approved':continue
        id=r['id'];directory.append({'source':'connections:'+id,'name':plain(r['organization']),'entry_type':r['entry_type'],'public_address_rows':sum(v.get('entry_id')==id and v.get('visibility')=='public' for v in table('connections_address')),'public_phone_rows':sum(v.get('entry_id')==id and v.get('visibility')=='public' for v in table('connections_phone')),'taxonomy_ids':'|'.join(v.get('term_taxonomy_id','') for v in table('connections_term_relationships') if v.get('entry_id')==id),'mapping':'Organization; cross-check against TablePress trade/club rows before deduplication'})
    budgets=[]
    for id,r in posts.items():
        title=plain(r['post_title']);m=re.match(r'^(Gde|GSV|MSV)\s*-\s*HH\s*(\d+)\s*(.*?)\s*(20\d{2})$',title)
        if r['post_type']!='wpdmpro' or r['post_status']!='publish' or not m:continue
        issuer,order,label,year=m.groups();files=decode(meta[id].get('__wpdm_files','')) or {};vals=list(files.values()) if isinstance(files,dict) else files if isinstance(files,list) else []
        for v in vals:
            path=relative(v) or 'wp-content/uploads/download-manager-files/'+str(v).lstrip('/');f=fs.get(path)
            budgets.append({'source_id':id,'issuer':{'Gde':'Gemeinde Merching','GSV':'Grundschulverband','MSV':'Mittelschulverband'}[issuer],'issuer_code':issuer,'year':year,'order':int(order),'title':title,'component_label':label,'path':path,'exists_in_backup':bool(f),'bytes':f['bytes'] if f else '', 'sha256':f['sha256'] if f else '', 'mapping':'Independent BudgetPlan by reviewed topic/year; 25 sources across 3 packages; main PDFs need compatible export','accessibility':'not verified'})
    budgets.sort(key=lambda r:(r['year'],r['issuer_code'],r['order']));
    # Inspect candidate budget PDFs using local pdfinfo only; no file modifications.
    pdfdir=cache/'pdf-preflight';pdfdir.mkdir(exist_ok=True);pdfs=[]
    for row in budgets:
        f=fs.get(row['path']);info={'source_id':row['source_id'],'path':row['path'],'pages':'','tagged':'unknown','encrypted':'unknown','pdf_version':'','compressed_object_streams':'unknown','fpdi_parse':'not tested'}
        if f:
            local=pdfdir/(f['sha256']+'.pdf')
            if not local.exists():
                with zipfile.ZipFile(cache/'archives'/f['archive']) as z:
                    name=next((n for n in z.namelist() if relative(n)==row['path'] or 'wp-content/'+n.lstrip('/')==row['path']),None)
                    if name:local.write_bytes(z.read(name))
            if local.exists():
                raw=local.read_bytes();info['compressed_object_streams']=bool(re.search(rb'/Type\s*/ObjStm\b',raw));
                try:
                    p=subprocess.run(['pdfinfo',str(local)],capture_output=True,text=True,timeout=20)
                    if p.returncode==0:
                        fields=dict(re.findall(r'^([^:\n]+):\s*(.*)$',p.stdout,re.M));info.update(pages=fields.get('Pages',''),tagged=fields.get('Tagged','unknown'),encrypted=fields.get('Encrypted','unknown'),pdf_version=fields.get('PDF version',''))
                    else:info['fpdi_parse']='pdfinfo read failed; inspect privately'
                except (OSError,subprocess.TimeoutExpired):info['fpdi_parse']='pdfinfo unavailable'
                # Pure PHP existing vendor parser; no bootstrap or DB connection.
                code="require 'vendor/autoload.php'; try {$p=new \\setasign\\Fpdi\\Fpdi(); echo $p->setSourceFile($argv[1]);} catch (\\Throwable $e) {fwrite(STDERR, get_class($e)); exit(1);}"
                try:
                    p=subprocess.run(['docker','compose','exec','-T','app','php','-r',code,str(local)],capture_output=True,text=True,timeout=25)
                    info['fpdi_parse']='supported' if p.returncode==0 else 'unsupported / parser rejected'
                except (OSError,subprocess.TimeoutExpired):info['fpdi_parse']='not tested (runtime unavailable)'
        pdfs.append(info)
    schedules=[]
    for r in table('ppfuture_actions_args'):
        id=r.get('post_id');p=posts.get(id,{})
        if p.get('post_status')!='publish':continue
        args=decode(r.get('args')) or {};action=args.get('expireType') or args.get('action') if isinstance(args,dict) else ''
        schedules.append({'source_id':id,'title':plain(p.get('post_title')),'enabled':r.get('enabled'),'scheduled_date':r.get('scheduled_date'),'created_at':r.get('created_at'),'action':str(action or ''),'new_status':args.get('newStatus','') if isinstance(args,dict) else '', 'review':'Verify enabled flag + current metadata + WordPress timezone; never apply historical jobs as current expiry'})
    forms=[]
    for id,r in posts.items():
        if r['post_type']=='wpcf7_contact_form':
            form=meta[id].get('_form','');types=collections.Counter(re.findall(r'\[([\w-]+)\*?\s',form));forms.append({'source_id':id,'status':r['post_status'],'title':plain(r['post_title']),'field_types':json.dumps(types),'mapping':'ContactRoute / existing contact form; compare public fields and validation; recipients/mail templates intentionally not exported'})
    albums=[]
    for r in table('ngg_album'):
        order=decode(r.get('sortorder'))
        if order is None:
            try:order=json.loads(base64.b64decode(r.get('sortorder') or '',validate=True))
            except (ValueError,TypeError):order={}
        vals=list(order.values()) if isinstance(order,dict) else order if isinstance(order,list) else []
        albums.append({'album_id':r['id'],'name':plain(r['name']),'gallery_order':'|'.join(map(str,vals)),'page_id':r.get('pageid'),'mapping':'Page with ordered Gallery blocks; nested albums need explicit review'})
    filebase=[]
    for r in table('wpfb_files'):
        p='wp-content/uploads/filebase/'+r['file_path'].lstrip('/');f=fs.get(p)
        filebase.append({'source_id':r['file_id'],'title':plain(r['file_display_name']),'original_filename':r['file_name_original'],'path':p,'source_url':'https://www.gemeinde-merching.de/'+p,'exists_in_backup':bool(f),'bytes':f['bytes'] if f else '', 'sha256':f['sha256'] if f else '', 'category_id':r.get('file_category'),'offline':r.get('file_offline'),'review':'Legacy WP-Filebase: match Download Manager copies and live URLs before creating duplicate Documents'})
    links=[]
    for r in table('links'):
        if r.get('link_visible')!='Y':continue
        u=safe_url(r.get('link_url'))
        if u:links.append({'source':'links:'+r['link_id'],'name':plain(r['link_name']),'url':u,'matching_cpt_ids':'|'.join(id for id,m in meta.items() if m.get('link_url')==u or m.get('_link_library_link_url')==u),'mapping':'ExternalResource; deduplicate CPT and wp_links representation'})
    media_rows=list(csv.DictReader((out/'media.csv').open()))
    for row in media_rows:
        id=row['source_id'];m=meta.get(id,{})
        if id.startswith('ngg:'):continue
        info=decode(m.get('_wp_attachment_metadata','')) or {}
        if not isinstance(info,dict):info={}
        original=info.get('original_image','')
        original_path=str(Path(row['path']).parent/original) if row['path'] and original else ''
        row.update(width=info.get('width',''),height=info.get('height',''),unscaled_original_path=original_path,unscaled_original_exists=original_path in fs if original_path else '',alt_present=bool(m.get('_wp_attachment_image_alt')),linked_parent_status=posts.get(row.get('parent_id'),{}).get('post_status',''),public_exposure='candidate only; verify public references/parent before publishing')
    write_csv(out,'media.csv',media_rows)
    # Relationships and public descriptions, without internal contact addresses or submitter data.
    gallery_items=[]
    galleries={r['gid']:r for r in table('ngg_gallery')}
    for r in sorted(table('ngg_pictures'),key=lambda r:(int(r['galleryid']),int(r.get('sortorder') or 0),int(r['pid']))):
        g=galleries.get(r['galleryid'],{});path=(g.get('path','').rstrip('/')+'/'+r.get('filename','')).lstrip('/')
        gallery_items.append({'gallery_id':r['galleryid'],'picture_id':r['pid'],'sort_order':r.get('sortorder'),'path':path,'exists_in_backup':path in fs,'exclude_flag':r.get('exclude'),'alt_present':bool(plain(r.get('alttext'))),'description_present':bool(plain(r.get('description'))),'linked_post_id':r.get('post_id')})
    categories=[]
    for id,values in assignments.items():
        r=posts.get(id,{})
        if r.get('post_status')!='publish':continue
        for taxonomy,name,slug in values:categories.append({'source_id':id,'type':r.get('post_type'),'taxonomy':taxonomy,'name':name,'slug':slug,'proposed_mapping':'context Category / article Tag; old event taxonomies require review' if taxonomy not in {'nav_menu','wp_theme'} else 'Navigation / operational style'})
    plugin_configs=[]
    for suffix in ['totalsoft_cal_1','totalsoft_cal_2','totalsoft_cal_3','totalsoft_cal_4','totalsoft_cal_events','totalsoft_cal_events_p2','totalsoft_cal_events_p3']:
        plugin_configs.append({'table':prefix+suffix,'rows':len(table(suffix)),'mapping':'calendar configuration only; no events found' if 'events' not in suffix else 'event table empty'})
    for name,rows in {'gallery-items.csv':gallery_items,'content-taxonomies.csv':categories,'legacy-calendar.csv':plugin_configs}.items():write_csv(out,name,rows)
    for name,rows in {'shortcodes.csv':allshortcodes,'html-conversion.csv':htmlmetrics,'tablepress.csv':tables,'table-row-candidates.csv':table_rows,'council-portrait-candidates.csv':council,'organization-candidates.csv':directory,'event-candidates.csv':eventrows,'budget-components.csv':budgets,'budget-pdf-preflight.csv':pdfs,'future-actions.csv':schedules,'forms.csv':forms,'albums.csv':albums,'filebase.csv':filebase,'link-library.csv':links}.items():write_csv(out,name,rows)
    seeds=set(json.loads((cache/'crawl-seeds.json').read_text()));seeds.update(seedadd);seeds.update(r['source_url'] for r in filebase if r['offline']=='0');
    for r in table('links'):
        u=safe_url(r.get('link_url'))
        if u and urlparse(u).hostname in {'www.gemeinde-merching.de','gemeinde-merching.de'}:seeds.add(u)
    (cache/'crawl-seeds.json').write_text(json.dumps(sorted(seeds),ensure_ascii=False))
    stats={'tablepress_shortcode_map':tablemap,'council_rows':sum(bool(r['member_name'].strip()) for r in council),'council_separator_rows':sum(not r['member_name'].strip() for r in council),'council_photo_rows':sum(bool(r['portrait_paths']) for r in council),'event_candidate_rows':len(eventrows),'connections_public':len(directory),'forms':len(forms),'budgets':dict(collections.Counter(r['issuer_code']+':'+r['year'] for r in budgets)),'budget_bytes':dict((k,sum(int(r['bytes'] or 0) for r in budgets if r['issuer_code']+':'+r['year']==k)) for k in {r['issuer_code']+':'+r['year'] for r in budgets}),'budget_pdf_parser':dict(collections.Counter(r['fpdi_parse'] for r in pdfs)),'future_enabled_published':sum(r['enabled']=='1' for r in schedules),'sql_prefix_summary':{p:{'posts':len(d.get(p+'posts',[])),'latest_modified':max((r['post_modified'] for r in d.get(p+'posts',[])),default='')} for p in ['wp_','wpd754ae']}}
    (out/'plugin-counts.json').write_text(json.dumps(stats,ensure_ascii=False,indent=2)+'\n');print(json.dumps(stats,ensure_ascii=False,indent=2))
if __name__=='__main__':main()
