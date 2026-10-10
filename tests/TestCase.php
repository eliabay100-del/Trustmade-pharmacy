<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Blade views use @vite; there is no compiled bundle in CI, so stub it.
        $this->withoutVite();
    }
}
