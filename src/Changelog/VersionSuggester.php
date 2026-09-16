<?php

namespace Mnonm\HermesDeployer\Changelog;

final class VersionSuggester
{
    /**
     * Propone el número leyendo los commits convencionales. El MAYOR nunca sale
     * de acá: "esto es un cambio grande" es un juicio, y lo pone una persona
     * pasando el número a mano.
     *
     * @param  list<string>  $commits
     */
    public function suggest(?string $currentTag, array $commits): string
    {
        if ($currentTag === null) {
            return '1.0.0';
        }

        [$major, $minor, $patch] = array_map(
            'intval',
            explode('.', ltrim($currentTag, 'v'))
        );

        foreach ($commits as $commit) {
            if (preg_match('/^feat(\(.+\))?!?:/', $commit) === 1) {
                return $major.'.'.($minor + 1).'.0';
            }
        }

        return $major.'.'.$minor.'.'.($patch + 1);
    }
}
