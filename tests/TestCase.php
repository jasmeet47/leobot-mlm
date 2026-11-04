<?php

namespace Tests;

use App\Support\DisposableDatabase;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUpTraits()
    {
        DisposableDatabase::assertSafe();

        return parent::setUpTraits();
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->app) && in_array(DatabaseTruncation::class, class_uses_recursive(static::class), true)) {
                $this->truncateDatabaseTables();
            }
        } finally {
            parent::tearDown();
        }
    }
}
