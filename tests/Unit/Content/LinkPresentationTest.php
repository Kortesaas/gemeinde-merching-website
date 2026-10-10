<?php

namespace Tests\Unit\Content;

use App\Support\Content\NewTabLinks;
use App\Support\Content\PublicFormat;
use PHPUnit\Framework\TestCase;

class LinkPresentationTest extends TestCase
{
    public function test_external_and_document_links_keep_other_attributes_and_gain_safe_new_tab_targets(): void
    {
        $html = '<a href="https://example.org/?a=1&amp;b=2" rel="nofollow" aria-describedby="existing" target="_self">Extern</a>'
            .'<a href="/download/4/form.pdf">PDF</a><a href="/satzung-original">Original</a>'
            .'<a href="http://localhost:8089/haushaltsplaene/2026/gesamt.pdf">Haushalt</a>';
        $result = NewTabLinks::apply($html, 'http://localhost:8089', ['/satzung-original']);
        $this->assertSame(4, substr_count($result, 'target="_blank"'));
        $this->assertStringContainsString('rel="nofollow noopener noreferrer"', $result);
        $this->assertStringContainsString('aria-describedby="existing new-tab-description"', $result);
        $this->assertStringContainsString('href="https://example.org/?a=1&amp;b=2"', $result);
        $this->assertSame($result, NewTabLinks::apply($result, 'http://localhost:8089', ['/satzung-original']));
    }

    public function test_internal_pages_anchors_phone_email_and_assets_keep_their_original_behavior(): void
    {
        $html = '<a href="/formulare">Formulare</a><a href="http://localhost:8089/kontakt">Kontakt</a>'
            .'<a href="#termin-2">Termin</a><a href="tel:0823374410">Telefon</a>'
            .'<a href="mailto:rathaus@example.org">E-Mail</a><a href="/medien/1/bild.jpg">Bild</a>';
        $this->assertSame($html, NewTabLinks::apply($html, 'http://localhost:8089', []));
    }

    public function test_phone_alternatives_break_into_individual_correct_dial_links_without_changing_labels(): void
    {
        $this->assertSame([
            ['label' => '08233/7441-14', 'href' => 'tel:08233744114'],
            ['label' => 'bzw. -20', 'href' => 'tel:08233744120'],
        ], PublicFormat::phoneLinks('08233/7441-14 bzw. -20'));
        $this->assertSame([
            ['label' => '08233/7441-24', 'href' => 'tel:08233744124'],
            ['label' => '08233/736358', 'href' => 'tel:08233736358'],
        ], PublicFormat::phoneLinks('08233/7441-24 08233/736358'));
    }
}
