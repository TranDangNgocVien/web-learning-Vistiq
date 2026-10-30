<?php

namespace Tests\Concerns;

use VistiqCore\Services\Shared\CompactDatabase;

/** Assertions target the public storage contract, whether rows are normalized or embedded. */
trait AssertsCompactStorage
{
    protected function assertDatabaseHas($table, array $data = [], $connection = null)
    {
        if (is_string($table) && CompactDatabase::embedded($table)) {
            $this->assertTrue(CompactDatabase::table($table)->where($data)->exists(), 'Missing record in '.$table.': '.json_encode($data));

            return $this;
        }

        return parent::assertDatabaseHas($table, $data, $connection);
    }

    protected function assertDatabaseMissing($table, array $data = [], $connection = null)
    {
        if (is_string($table) && CompactDatabase::embedded($table)) {
            $this->assertFalse(CompactDatabase::table($table)->where($data)->exists(), 'Unexpected record in '.$table);

            return $this;
        }

        return parent::assertDatabaseMissing($table, $data, $connection);
    }

    protected function assertDatabaseCount($table, int $count, $connection = null)
    {
        if (is_string($table) && CompactDatabase::embedded($table)) {
            $this->assertSame($count, CompactDatabase::table($table)->count(), 'Unexpected count in '.$table);

            return $this;
        }

        return parent::assertDatabaseCount($table, $count, $connection);
    }
}
