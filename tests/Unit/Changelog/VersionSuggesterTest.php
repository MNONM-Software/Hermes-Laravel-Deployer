<?php

namespace Mnonm\HermesDeployer\Tests\Unit\Changelog;

use Mnonm\HermesDeployer\Changelog\VersionSuggester;
use PHPUnit\Framework\TestCase;

class VersionSuggesterTest extends TestCase
{
    public function test_a_feat_raises_the_middle_number(): void
    {
        $this->assertSame('1.5.0', (new VersionSuggester)->suggest('v1.4.2', [
            'fix(remitos): fecha invertida',
            'feat(stock): reubicacion de lotes',
        ]));
    }

    public function test_only_fixes_raise_the_last_number(): void
    {
        $this->assertSame('1.4.3', (new VersionSuggester)->suggest('v1.4.2', [
            'fix(remitos): fecha invertida',
            'docs: aclarar el readme',
        ]));
    }

    public function test_without_a_previous_tag_it_starts_at_one(): void
    {
        $this->assertSame('1.0.0', (new VersionSuggester)->suggest(null, [
            'feat(stock): lo primero',
        ]));
    }

    public function test_a_tag_that_is_not_semver_is_treated_as_no_tag(): void
    {
        // Antes tiraba warnings de índice indefinido y proponía 0.1.0.
        $this->assertSame('1.0.0', (new VersionSuggester)->suggest('sprint-3', [
            'feat(stock): lo primero',
        ]));
    }

    public function test_a_tag_with_two_numbers_does_not_suggest_a_lower_version(): void
    {
        // `v1.4` proponía 1.5.0, que puede ser más chico que lo que ya está.
        $this->assertSame('1.0.0', (new VersionSuggester)->suggest('v1.4', [
            'fix(remitos): fecha invertida',
        ]));
    }

    public function test_it_never_raises_the_major_number_on_its_own(): void
    {
        // "esto es un cambio grande" es un juicio, no sale de los commits.
        $this->assertSame('1.5.0', (new VersionSuggester)->suggest('v1.4.2', [
            'feat!: rework completo del circuito',
            'BREAKING CHANGE: todo distinto',
        ]));
    }
}
