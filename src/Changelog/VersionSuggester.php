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
        // Un tag que no es semver se trata como "no hay tag": partirlo con
        // explode() sugiere un número hacia atrás —`v1.4` propone 1.5.0, que es
        // más chico que 1.4.7— y quien acepta con Enter baja la versión.
        if ($currentTag === null || preg_match('/^v?(\d+)\.(\d+)\.(\d+)$/', $currentTag, $parts) !== 1) {
            return '1.0.0';
        }

        [$major, $minor, $patch] = [(int) $parts[1], (int) $parts[2], (int) $parts[3]];

        foreach ($commits as $commit) {
            if (preg_match('/^feat(\(.+\))?!?:/', $commit) === 1) {
                return $major.'.'.($minor + 1).'.0';
            }
        }

        return $major.'.'.$minor.'.'.($patch + 1);
    }
}
