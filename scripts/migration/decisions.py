#!/usr/bin/env python3
"""Explain every demo page/navigation entry without copying demo facts."""
import csv, json, re
from pathlib import Path

ROOT=Path(__file__).resolve().parents[2]
REPORT=ROOT/'docs/migration/local'
manifest=json.loads((ROOT/'migration-source/prepared/manifest.json').read_text())
records={r['key']:r for r in manifest['records']}
source=(ROOT/'database/seeders/DevelopmentDemoSeeder.php').read_text()
rows=[]

def destination(key):return records[key]['path']
def add(scope,label,status,key=None,reason=''):
    rows.append({'scope':scope,'item':label,'decision':status,'destination':destination(key) if key else '', 'reason':reason})

pagekeys={
 '/buergerservice':'wp:10','/buergerservice/a-z':'wp:222','/aktuelles':'wp:8',
 '/veranstaltungen':'wp:4','/bekanntmachungen':'wp:670','/dokumente':'wp:7085',
 '/verzeichnisse':'structure:verzeichnisse','/vereine':'wp:249',
 '/rathaus-und-politik':'structure:rathaus','/leben':'structure:leben',
 '/bauen-und-wirtschaft':'structure:bauen','/ortsrecht':'wp:227',
 '/barrierefreiheit':'wp:9637','/datenschutz':'wp:3658','/impressum':'wp:24',
 '/leben/kinderbetreuung':'wp:2316', '/rathaus-und-politik/grusswort':'wp:92',
 '/leben/freizeit-am-see':'wp:255',
}
region=source.split('private function pages(): void',1)[1].split('private function blocks(',1)[0]
pages=re.findall(r"'(/[^']+)' => \['([^']+)'",region)
pages+= [(path,title) for title,path in re.findall(r"'\w+' => \['([^']+)',[^\n]+?'(/[^']+)'",region)]
pages += [('/rathaus-und-politik/grusswort','Grußwort des Ersten Bürgermeisters'),('/leben/freizeit-am-see','Freizeit am See')]
for path,title in pages:
    key=pagekeys[path]
    status='merged' if path in {'/leben/kinderbetreuung','/leben/freizeit-am-see'} else 'kept' if key.startswith('structure:') else 'replaced with real content'
    reason='Retain redesigned catalogue/landing architecture; verified source content replaces all synthetic facts.'
    if status=='merged':reason='Merge into the verified children/youth or bathing-water page; former demo path redirects there. Fictional facilities/rules/contacts are removed.'
    if path in {'/impressum','/datenschutz','/barrierefreiheit'}:reason='Preserve the published legacy text as source information; legal applicability to the Laravel website requires editorial review.'
    add('page',title,status,key,reason)
add('page','Entwurf: Seite mit Qualitätsproblemen','removed',reason='Synthetic quality/error fixture has no municipal public content counterpart; demo seeder remains available.')

targets={
 'page./buergerservice':'wp:10','page./buergerservice/a-z':'wp:222','page./dokumente':'wp:7085',
 'page./verzeichnisse':'structure:verzeichnisse','page./aktuelles':'wp:8','page./veranstaltungen':'wp:4',
 'page./bekanntmachungen':'wp:670','page./vereine':'wp:249','page.rathaus':'structure:rathaus',
 'page.leben':'structure:leben','page.bauen':'structure:bauen','page.ortsrecht':'wp:227',
 'page.barrierefreiheit':'wp:9637','page.datenschutz':'wp:3658','page.impressum':'wp:24',
 'page.see':'wp:255','page.kinderbetreuung':'wp:2316','council.current':'wp:212',
 'life.umzug':'wp:300','life.geburt':'wp:296','life.bauen':'structure:bauen',
 'dep.buergerbuero':'wp:20','dep.bauamt':'wp:20','loc.rathaus':'wp:20',
 'loc.wertstoffhof':'wp:302','gallery.ort':'wp:547','svc.bauantrag':'table:2029:13',
 'svc.gewerbe':'table:2029:49','notice.bplan':'wp:1999','svc.personalausweis':'wp:310',
 'svc.anmeldung':'wp:300','svc.hundesteuer':'table:2029:63','svc.sperrmuell':'table:2029:118',
 'svc.fuehrungszeugnis':'wp:1987',
}
nav=source.split('private function navigation(): void',1)[1].split('private function redirects(): void',1)[0]
groups=[('main',nav.split('$main = [',1)[1].split('foreach ($main',1)[0]),
        ('service',nav.split("foreach ([['Personalausweis",1)[1].split('as $index',1)[0]),
        ('footer',nav.split('$footer = [',1)[1].split('foreach ($footer',1)[0])]
# Restore the initial utility pair removed by the anchor split above.
groups[1]=('service',"[['Personalausweis"+groups[1][1])
for menu,body in groups:
    for label,key,resource in re.findall(r"\['([^']+)',\s*(?:'([^']+)'|null)(?:,\s*'([^']+)')?",body):
        target=targets.get(key)
        if resource=='portal':target='wp:2847'
        if target:
            add('navigation:'+menu,label,'merged' if key.startswith(('life.','dep.','loc.','notice.')) else 'replaced with real content',target,'Navigation purpose retained; real municipal destination replaces the synthetic target. Duplicate utility links are consolidated in the new navigation.')
        else:add('navigation:'+menu,label,'kept but empty pending real content',reason='The functionality remains in the CMS; no fictional public destination is carried over.')

# Every demo life-situation and service scaffold is reconciled too.
life=source.split('private function lifeSituations(): void',1)[1].split('private function articles(): void',1)[0]
life_targets={'umzug':'wp:300','geburt':'wp:296','heiraten':'wp:296','bauen':'structure:bauen','hund':'table:2029:63','todesfall':'wp:296','gewerbe':'table:2029:49'}
for key,title in re.findall(r"'(\w+)' => \['([^']+)'",life):
    add('life-situation',title,'merged',life_targets[key],'Verified services/pages cover this subject. LifeSituation model and workflow remain available; no synthetic guide text or relationships are imported.')
service=source.split('private function services(): void',1)[1].split('private function lifeSituations(): void',1)[0]
for key,title in re.findall(r"'(\w+)' => \['title' => '([^']+)'",service):
    target=targets.get('svc.'+key)
    if not target:
        normalized=title.lower()
        target=next((r['key'] for r in manifest['records'] if r['type']=='service' and r['attributes']['title'].lower()==normalized),None)
    add('service',title,'replaced with real content' if target else 'merged',target or 'wp:222','Use verified source service/contact records; demo fees, deadlines, FAQs, online links and relationships are removed. The A–Z directory remains the fallback when a precise equivalent is uncertain.')
for label,status,reason in [
 ('Homepage and search','kept','Real media, greeting, articles, events, contacts and rebuilt index replace demo facts; display controls/feedback/archive functionality remains.'),
 ('Council and committee workflows','replaced with real content','One real 2026–2032 term, 26 portraits/members and three named committees; unverified committee memberships remain empty.'),
 ('Budget workflows','replaced with real content','Three real topics with 25 ordered originals; source-only publication snapshots retain the workflow despite FPDI limitations.'),
 ('Synthetic alerts and announcements','removed','No invented amounts, dates, facilities, people or public safety alerts are imported.'),
 ('Historical council terms / additional budget topics','kept but empty pending real content','Schema and workflows remain available; no synthetic past term/package is published.'),
 ('Revision/proposal/role architecture','kept','Normal roles, revision, proposal, publication, routing, upload and search services remain; no old users or operational history imported.'),
 ('Public contact and feedback','kept','Recipient comes only from an explicitly public municipal contact address. Local delivery goes to Mailpit.'),
 ]:add('section',label,status,reason=reason)
with (REPORT/'structure-decisions.csv').open('w',newline='') as handle:
    writer=csv.DictWriter(handle,fieldnames=list(rows[0]),lineterminator='\n');writer.writeheader();writer.writerows(rows)
print(json.dumps({'decision_rows':len(rows),'demo_pages':sum(r['scope']=='page' for r in rows),'navigation_items':sum(r['scope'].startswith('navigation:') for r in rows)},indent=2))
