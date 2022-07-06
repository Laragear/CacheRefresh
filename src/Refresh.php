<?php

namespace Laragear\CacheRefresh;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;

/**
 * @template TValue  Value being refreshed.
 */
class Refresh
{
    /**
     * Create a new refresh operation instance.
     *
     * @param  \Illuminate\Contracts\Cache\Repository  $repository
     * @param  string  $key
     * @param  \DateTimeInterface|\DateInterval|int|null  $ttl
     * @param  string  $name
     * @param  string  $owner
     * @param  \Closure(mixed, \Laragear\CacheRefresh\Expire): TValue|null  $callback
     * @param  int  $wait
     * @param  int  $seconds
     */
    public function __construct(
        protected Repository $repository,
        protected string $key,
        protected DateTimeInterface|DateInterval|int|null $ttl = null,
        protected string $name = '',
        protected string $owner = '',
        protected ?Closure $callback = null,
        protected int $wait = 10,
        protected int $seconds = 0,
    ) {
        $this->name = $this->key.':refresh';
    }

    /**
     * Changes cache lock configuration.
     *
     * @param  string  $name
     * @param  int|null  $seconds
     * @param  string|null  $owner
     * @return $this
     */
    public function lock(string $name, int $seconds = null, string $owner = null): static
    {
        [$this->name, $this->seconds, $this->owner] = [$name, $seconds ?? $this->seconds, $owner ?? $this->owner];

        return $this;
    }

    /**
     * Sets the seconds to wait to acquire the lock.
     *
     * @param  int  $seconds
     * @return $this
     */
    public function waitFor(int $seconds): static
    {
        $this->wait = $seconds;

        return $this;
    }

    /**
     * Retrieves and refreshes the item from the cache through a callback.
     *
     * @param  callable(mixed, \Laragear\CacheRefresh\Expire): (TValue|null)  $callback
     * @param  \DateTimeInterface|\DateInterval|int|null  $ttl
     * @return TValue|mixed
     */
    public function put(callable $callback, DateTimeInterface|DateInterval|int|null $ttl = null): mixed
    {
        [$this->callback, $this->ttl] = [$callback, $ttl ?? $this->ttl];

        $store = $this->repository->getStore();

        if ($store instanceof LockProvider) {
            return $store
                ->lock($this->name, $this->seconds, $this->owner)
                ->block($this->wait, function (): mixed {
                    return $this->refresh();
                });
        }

        return $this->refresh();
    }

    /**
     * Executes the refresh operation.
     *
     * @return TValue
     */
    protected function refresh(): mixed
    {
        $expire = $this->expireObject();

        $item = $this->repository->get($this->key);

        $exists = $item !== null;

        return tap(($this->callback)($item, $expire), function (mixed $result) use ($expire, $exists): void {
            // We will call the cache store only on two cases: when this callback returns
            // something to be put, and when there is something to forget in the cache.
            // This way we can save a cache call when there is nothing to manipulate.
            if (($exists && $expire->at === 0) || $result !== null) {
                $this->repository->put($this->key, $result, $expire->at);
            }
        });
    }

    /**
     * Creates a simple object to manage the item lifetime.
     *
     * @return \Laragear\CacheRefresh\Expire
     */
    protected function expireObject(): Expire
    {
        return new Expire($this->ttl);
    }
}
