<?php

namespace Tests;

use Illuminate\Cache\Repository;
use Illuminate\Support\ServiceProvider;
use Laragear\CacheRefresh\CacheRefreshServiceProvider;

class ServiceProviderTest extends TestCase
{
    public function test_registers_request_macro(): void
    {
        static::assertTrue(Repository::hasMacro('refresh'));
    }

    public function test_publishes_stub(): void
    {
        static::assertSame(
            [CacheRefreshServiceProvider::STUBS => $this->app->basePath('.stubs/cache-refresh.php')],
            ServiceProvider::pathsToPublish(CacheRefreshServiceProvider::class, 'phpstorm')
        );
    }
}
