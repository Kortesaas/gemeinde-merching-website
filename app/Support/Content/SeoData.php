<?php

namespace App\Support\Content;

final readonly class SeoData
{
    public function __construct(public ?string $title, public ?string $description, public ?string $canonical, public string $robots, public string $siteName) {}
}
