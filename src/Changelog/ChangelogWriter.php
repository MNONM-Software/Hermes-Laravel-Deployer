<?php

namespace Mnonm\HermesDeployer\Changelog;

final class ChangelogWriter
{
    /** @param  list<string>  $commits */
    public function entry(string $version, string $date, array $commits): string
    {
        $lines = ["## v{$version} — {$date}", '', '### Qué salió', ''];

        foreach ($commits as $commit) {
            $lines[] = "- {$commit}";
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    /** Mete la entrada nueva arriba de todo, debajo del encabezado del archivo. */
    public function prepend(string $existing, string $entry): string
    {
        $lines = explode("\n", $existing);
        $insertAt = 0;

        foreach ($lines as $index => $line) {
            if (str_starts_with($line, '## ')) {
                $insertAt = $index;
                break;
            }

            $insertAt = $index + 1;
        }

        array_splice($lines, $insertAt, 0, explode("\n", rtrim($entry)."\n"));

        return implode("\n", $lines);
    }
}
