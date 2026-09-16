<?php

namespace Mnonm\HermesDeployer\Tests\Unit\Changelog;

use Mnonm\HermesDeployer\Changelog\ChangelogWriter;
use PHPUnit\Framework\TestCase;

class ChangelogWriterTest extends TestCase
{
    public function test_it_renders_the_entry_with_the_version_the_date_and_the_commits(): void
    {
        $entry = (new ChangelogWriter)->entry('1.5.0', '2026-09-16', [
            'feat(stock): reubicacion de lotes',
            'fix(remitos): fecha invertida',
        ]);

        $this->assertStringContainsString('## v1.5.0 — 2026-09-16', $entry);
        $this->assertStringContainsString('- feat(stock): reubicacion de lotes', $entry);
        $this->assertStringContainsString('- fix(remitos): fecha invertida', $entry);
    }

    public function test_it_puts_the_new_entry_above_the_old_ones(): void
    {
        $existing = "# Changelog\n\n## v1.4.0 — 2026-09-01\n\n- lo viejo\n";

        $result = (new ChangelogWriter)->prepend($existing, "## v1.5.0 — 2026-09-16\n\n- lo nuevo\n");

        $this->assertStringStartsWith("# Changelog\n", $result);
        $this->assertLessThan(
            strpos($result, 'v1.4.0'),
            strpos($result, 'v1.5.0')
        );
    }
}
