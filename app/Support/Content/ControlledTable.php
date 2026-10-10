<?php

namespace App\Support\Content;

use InvalidArgumentException;

/** Bounded tab-separated cells, with a column header and safe Markdown. */
final class ControlledTable
{
    /**
     * Recognise repeated directory records, keeping every original cell in order.
     * Ordinary comparison tables (including three-column facts) stay tables.
     *
     * @param  list<list<string>>  $rows
     * @return list<list<list<string>>>|null
     */
    public static function recordGroups(array $rows): ?array
    {
        if (($rows[0] ?? null) !== ['Merkmal', 'Angabe'] || count($rows) < 2 || trim($rows[1][0], " *:\t") !== 'Name') {
            return null;
        }
        $groups = [];
        foreach (array_slice($rows, 1) as $row) {
            if (trim($row[0], " *:\t") === 'Name') {
                $groups[] = [];
            }
            $groups[array_key_last($groups)][] = $row;
        }

        return count($groups) > 1 ? $groups : null;
    }

    /** @return list<list<string>> */
    public static function rows(string $text): array
    {
        $rows = array_map(fn ($row) => explode("\t", $row), explode("\n", str_replace("\r", '', trim($text, "\n\r"))));
        $width = count($rows[0]);
        if (count($rows) > 200 || $width > 12 || ! array_filter($rows[0], fn ($cell) => trim($cell) !== '')) {
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
