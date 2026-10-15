<?php

namespace Tests\Feature;

use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    public function test_backup_command_is_registered(): void
    {
        $this->assertArrayHasKey(
            'pmas:backup',
            \Illuminate\Support\Facades\Artisan::all()
        );
    }

    /**
     * The backup shells out to mysqldump, which only applies to MySQL. On any
     * other connection it must fail closed rather than write a bogus file.
     */
    public function test_backup_refuses_non_mysql_connections(): void
    {
        $driver = config('database.connections.' . config('database.default') . '.driver');

        if ($driver === 'mysql') {
            $this->markTestSkipped('Guard under test only applies to non-MySQL connections.');
        }

        $this->artisan('pmas:backup')->assertExitCode(1);
    }
}
