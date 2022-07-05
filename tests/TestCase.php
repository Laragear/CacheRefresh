<?php

namespace Tests;

use Laragear\CacheRefresh\CacheRefreshServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [CacheRefreshServiceProvider::class];
    }
}
