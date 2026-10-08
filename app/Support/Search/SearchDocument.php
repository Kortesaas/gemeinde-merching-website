<?php

namespace App\Support\Search;

/**
 * Normalised searchable text of one record. A later phase stores these in a
 * MySQL table with a FULLTEXT index (no external search server).
 */
final readonly class SearchDocument
{
    /**
     * @param  list<string>  $keywords  aliases, categories, responsibilities …
     */
    public function __construct(
        public string $title,
        public string $summary = '',
        public array $keywords = [],
        public string $body = '',
    ) {}

    public function text(): string
    {
        return trim(implode("\n", array_filter([$this->title, $this->summary, implode(' ', $this->keywords), $this->body])));
    }
}
