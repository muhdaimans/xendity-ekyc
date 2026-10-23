<?php

declare(strict_types=1);

namespace XendityEkyc\XendityEkyc\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Attributes\WithMigration;
use Orchestra\Testbench\TestCase as Orchestra;
use XendityEkyc\XendityEkyc\XendityEkycServiceProvider;

#[WithMigration]
abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            XendityEkycServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('xendity-ekyc.api_key', 'ApiKey0123456789abcdefghijklmnop');
        $app['config']->set('xendity-ekyc.package_name', 'Xendity');
        $app['config']->set('xendity-ekyc.security_key', 'Xendity883929');
        $app['config']->set('xendity-ekyc.cipher_key', 'abcdefghijklmnop');
        $app['config']->set('xendity-ekyc.response_url', 'https://merchant.test/ekyc/done');
        $app['config']->set('xendity-ekyc.http.retry_sleep', 0);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
