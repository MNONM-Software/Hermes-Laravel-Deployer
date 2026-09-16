<?php

namespace Mnonm\HermesDeployer\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mnonm\HermesDeployer\Models\DeployOperation;
use Mnonm\HermesDeployer\Tests\TestCase;

class DeployOperationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_a_run_operation(): void
    {
        DeployOperation::create([
            'operation' => '2026_09_03_090000_backfill_saldo',
            'ran_at' => now(),
            'app_version' => '1.5.0',
        ]);

        $this->assertDatabaseHas('deploy_operations', [
            'operation' => '2026_09_03_090000_backfill_saldo',
            'app_version' => '1.5.0',
        ]);
    }

    public function test_it_rejects_the_same_operation_twice(): void
    {
        DeployOperation::create(['operation' => 'dup', 'ran_at' => now()]);

        $this->expectException(QueryException::class);

        DeployOperation::create(['operation' => 'dup', 'ran_at' => now()]);
    }

    public function test_it_does_not_keep_timestamps(): void
    {
        $this->assertFalse((new DeployOperation)->usesTimestamps());
    }
}
