<?php

namespace Laragear\CacheRefresh;

use Closure;
use DateInterval;
use DateTimeInterface as DateTime;
use Illuminate\Cache\Repository;
use Illuminate\Support\ServiceProvider;

class CacheRefreshServiceProvider extends ServiceProvider
{
    public const STUBS = __DIR__.'/../.stubs/cache-refresh.php';

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot(): void
    {
        Repository::macro(
            'refresh',
            function (string $key, Closure $callback = null, DateTime|DateInterval|int|null $ttl = null): mixed {
                /** @var \Illuminate\Cache\Repository $this */
                $operation = new Refresh($this, $key, $ttl);

                return $callback ? $operation->put($callback, $ttl) : $operation;
            }
        );

        if ($this->app->runningInConsole()) {
            $this->publishes([static::STUBS => $this->app->basePath('.stubs/cache-refresh.php')], 'phpstorm');
        }
    }
}
