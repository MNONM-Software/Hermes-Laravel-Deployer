<?php

namespace Mnonm\HermesDeployer\Tests\Unit;

use Mnonm\HermesDeployer\DeployStep;
use PHPUnit\Framework\TestCase;

class DeployStepTest extends TestCase
{
    public function test_a_migrations_step_keeps_every_path_in_order(): void
    {
        $step = DeployStep::migrations(['/a/2026_01_01_000000_one.php', '/a/2026_01_02_000000_two.php']);

        $this->assertSame('migrations', $step->kind);
        $this->assertSame(
            ['/a/2026_01_01_000000_one.php', '/a/2026_01_02_000000_two.php'],
            $step->paths
        );
        $this->assertNull($step->name);
    }

    public function test_an_operation_step_derives_its_name_from_the_file(): void
    {
        $step = DeployStep::operation('/a/2026_01_03_000000_backfill_saldo.php');

        $this->assertSame('operation', $step->kind);
        $this->assertSame('2026_01_03_000000_backfill_saldo', $step->name);
        $this->assertSame(['/a/2026_01_03_000000_backfill_saldo.php'], $step->paths);
    }
}
