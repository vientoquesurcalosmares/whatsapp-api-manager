<?php

namespace ScriptDevelop\WhatsappManager\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use ScriptDevelop\WhatsappManager\WhatsappManagerServiceProvider;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [WhatsappManagerServiceProvider::class];
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    // tests/TestCase.php
    protected function setUp(): void
    {
        parent::setUp();
        $this->beginDatabaseTransaction();
    }

    protected function beginDatabaseTransaction()
    {
        $database = $this->app->make('db');
        $database->connection()->beginTransaction();
        $this->beforeApplicationDestroyed(function () use ($database) {
            $database->connection()->rollBack();
        });
    }

}