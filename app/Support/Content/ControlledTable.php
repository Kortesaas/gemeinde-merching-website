<?php

namespace App\Support\Content;

use InvalidArgumentException;

/** Bounded tab-separated cells, with a column header and safe Markdown. */
final class ControlledTable
{
    /** @return list<list<string>> */
    public static function rows(string $text): array
    {
        $rows = array_map(fn ($row) => explode("\t", $row), explode("\n", str_replace("\r", '', trim($text, "\n\r"))));
        $width = count($rows[0]);
        if (count($rows) > 200 || $width > 12 || $width < 1 || ! array_filter($rows[0], fn ($cell) => trim($cell) !== '')) {
            throw new InvalidArgumentException('Bitte 1–200 Zeilen mit 1–12 Spalten und einer Kopfzeile eingeben.');
        }
        foreach ($rows as $row) {
            if (count($row) !== $width) {
                throw new InvalidArgumentException('Jede Tabellenzeile muss gleich viele durch Tabulatoren getrennte Zellen enthalten.');
            }
        }

        return $rows;
    }
}
