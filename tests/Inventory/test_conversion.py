import sys
import unittest
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parents[2] / 'scripts/migration'))
from prepare import Migration, Tree, text


class PublicConversionTest(unittest.TestCase):
    def migration(self):
        migration = Migration.__new__(Migration)
        migration.assets = {}
        migration.records = {}
        migration.review = []
        migration.tableids = {}
        return migration

    def test_scripts_and_form_fields_are_not_preserved(self):
        migration = self.migration()
        blocks = migration.convert('<h2>Öffnungszeiten</h2><p>Mittwoch geschlossen</p><script>secret</script><form><input value="private"><p>recipient</p></form>', 'public:1')
        self.assertEqual(blocks, [{'type': 'heading', 'heading': 'Öffnungszeiten', 'heading_level': 2}, {'type': 'text', 'text': 'Mittwoch geschlossen'}])

    def test_unsafe_links_lose_executable_destination(self):
        migration = self.migration()
        self.assertEqual(migration.markdown(Tree('<a href="javascript:evil()">Kontakt</a>').root), 'Kontakt')
        self.assertIn('[Kontakt](mailto:rathaus@example.org)', migration.markdown(Tree('<a href="mailto:rathaus@example.org">Kontakt</a>').root))

    def test_tables_keep_all_cells_links_and_caption(self):
        migration = self.migration()
        blocks = migration.tsv([['Bereich', 'Zuständig'], ['Abfall', '<a href="https://example.org/">Landkreis</a>']], 'Bürgerservice', 'table:1')
        self.assertEqual(blocks[0]['text'], 'Bereich\tZuständig\nAbfall\t[Landkreis](https://example.org/)')
        self.assertEqual(blocks[0]['heading'], 'Bürgerservice')

    def test_long_text_is_split_without_loss(self):
        migration = self.migration()
        value = 'Gemeinde ' * 5000
        blocks = migration.convert('<p>'+value+'</p>', 'public:2')
        self.assertTrue(all(len(b['text']) <= 18000 for b in blocks))
        self.assertEqual(' '.join(b['text'] for b in blocks), value.strip())


if __name__ == '__main__':
    unittest.main()
