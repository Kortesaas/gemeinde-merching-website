<?php

namespace App\Admin\Fields;

/**
 * Temporary safe rich-text field (Markdown, rendered by SafeMarkdown).
 * Raw HTML is never executed. Replaced by the controlled block editor later.
 */
class Markdown extends Textarea
{
    public int $max = 100000;

    public int $rows = 14;

    public ?string $hint = 'Formatierung: Leerzeile = neuer Absatz, „## Überschrift“, „- Aufzählung“, „**fett**“, „[Linktext](https://…)“. HTML wird nicht übernommen.';
}
