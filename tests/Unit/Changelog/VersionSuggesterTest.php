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

    public function test_it_never_raises_the_major_number_on_its_own(): void
    {
        // "esto es un cambio grande" es un juicio, no sale de los commits.
        $this->assertSame('1.5.0', (new VersionSuggester)->suggest('v1.4.2', [
            'feat!: rework completo del circuito',
            'BREAKING CHANGE: todo distinto',
        ]));
    }
}
