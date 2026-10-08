<?php

namespace Tests\Feature\Public;

use Tests\TestCase;

class IndexingTest extends TestCase
{
    public function test_non_production_is_never_indexable(): void
    {
        config(['site.public_indexing' => true]);

        $this->get('/')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        $this->get('/robots.txt')->assertOk()->assertSee("Disallow: /\n", false);
    }

    public function test_production_without_flag_is_not_indexable(): void
    {
        $this->app['env'] = 'production';
        config(['site.public_indexing' => false]);

        $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/robots.txt')->assertSee("Disallow: /\n", false);
    }

    public function test_production_with_explicit_flag_is_indexable(): void
    {
        $this->app['env'] = 'production';
        config(['site.public_indexing' => true]);

        $response = $this->get('/');
        $response->assertHeaderMissing('X-Robots-Tag')
            ->assertSee('<meta name="robots" content="index, follow">', false);

        $this->get('/robots.txt')->assertSee("Disallow:\n", false)->assertDontSee('Disallow: /', false);
    }

    public function test_backend_is_never_indexable_even_when_public_indexing_is_enabled(): void
    {
        $this->app['env'] = 'production';
        config(['site.public_indexing' => true]);

        $this->get($this->adminUrl('login'))
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }
}
