"""Offline safety/parser regression checks; no production data or network."""
import importlib.util
import tempfile
import unittest
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
def module(name):
    spec=importlib.util.spec_from_file_location(name,ROOT/'scripts/migration'/f'{name}.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m);return m
import sys
sys.path.insert(0,str(ROOT/'scripts/migration'))
inventory=module('inventory');crawl=module('crawl');enrich=module('enrich')
class InventoryTests(unittest.TestCase):
    def test_sql_comments_first_insert_and_private_allowlist(self):
        sql="""CREATE TABLE `wp_posts` (`ID` int,\n `post_title` text);\n-- first insertion follows a comment\nINSERT INTO `wp_posts` (`ID`, `post_title`) VALUES (1,'Hello; it\\'s public');\nCREATE TABLE `wp_users` (`ID` int,\n `user_pass` text);\nINSERT INTO `wp_users` VALUES (1,'NEVER_REPORT_THIS');\nCREATE TABLE `wp_options` (`option_id` int,\n `option_name` text,\n `option_value` text);\nINSERT INTO `wp_options` VALUES (1,'home','https://example.test'),(2,'smtp_password','NEVER_REPORT_THIS');\n"""
        with tempfile.TemporaryDirectory() as d:
            p=Path(d)/'dump.sql';p.write_text(sql);r=inventory.read_sql(p)
        self.assertEqual(r['data']['wp_posts'][0]['post_title'],"Hello; it's public")
        self.assertEqual(r['counts']['wp_users'],1)
        self.assertNotIn('wp_users',r['data'])
        self.assertEqual(len(r['data']['wp_options']),1)
    def test_values_preserve_whitespace_and_literal_null(self):
        self.assertEqual(list(inventory.values("(1,'  text  ','NULL',NULL,'a,b(c)'),(2,'a\\nb','x',0,'a\\\\b');")),[['1','  text  ','NULL',None,'a,b(c)'],['2','a\nb','x','0','a\\b']])
    def test_serialized_reader_is_non_executable_and_utf8_safe(self):
        self.assertEqual(enrich.decode('a:1:{i:0;s:6:"Grüß";}'),{0:'Grüß'})
        self.assertIsNone(enrich.decode('O:8:"Exploit!":0:{}'))
        self.assertEqual(enrich.relative('/old/private/host/wp-content/uploads/a.pdf'),'wp-content/uploads/a.pdf')
        self.assertEqual(crawl.normalize('/Bürger Infos.pdf'),'https://www.gemeinde-merching.de/B%C3%BCrger%20Infos.pdf')
    def test_incomplete_dump_fails(self):
        with self.assertRaises(ValueError):list(inventory.values("(1,'truncated"))
    def test_url_filters_private_or_external_endpoints(self):
        for u in ['https://external.test/','/wp-admin/','/wp-login.php','/?recipient=internal@example.test','/?token=secret','/?s=search','/?action=delete','https://user:password@www.gemeinde-merching.de/']:
            self.assertIsNone(crawl.normalize(u),u)
        self.assertEqual(crawl.normalize('/?wpdmdl=42#file'),'https://www.gemeinde-merching.de/?wpdmdl=42')
        self.assertEqual(crawl.normalize('/download/plan/?wpdmdl=42&refresh=opaque'),'https://www.gemeinde-merching.de/download/plan/?wpdmdl=42')
    def test_html_only_reads_navigation(self):
        p=crawl.PageParser();p.feed('<title>Public &amp; title</title><form action="/submit"><input value="private"></form><a href="/page/">Page</a><img src="/a.jpg" srcset="/b.jpg 2x"><link rel="canonical" href="/page">')
        self.assertEqual(p.links,['/page/']);self.assertEqual(p.images,['/a.jpg','/b.jpg']);self.assertEqual(p.forms,1);self.assertEqual(p.canonical,'/page');self.assertNotIn('private',str(p.__dict__))
    def test_safe_report_urls_exclude_credentials_and_recipients(self):
        self.assertEqual(inventory.safe_url('https://x.test/?email=internal@example.test'),'')
        self.assertEqual(inventory.safe_url('https://u:p@x.test/'),'')
        self.assertEqual(inventory.safe_url('mailto:internal@example.test'),'')
if __name__=='__main__':unittest.main()
