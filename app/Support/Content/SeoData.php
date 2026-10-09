<?php

namespace App\Support\Content;

final readonly class SeoData
{
    /** @param array{url:string,width:int,height:int,type:string,alt:string} $image */
    public function __construct(
        public ?string $title,
        public ?string $description,
        public ?string $canonical,
        public string $robots,
        public string $siteName,
        public array $image,
        public string $type = 'website',
    ) {}
}
