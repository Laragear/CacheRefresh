<?php

namespace Laragear\CacheRefresh;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Cache\LockProvider as LockContract;
use Illuminate\Contracts\Cache\Repository as CacheContract;

use function tap;

/**
 * @template TValue The value being refreshed
 */
class Refresh
{
    /**
     * Create a new refresh operation instance.
     *
     * @param  (\Closure(TValue|mixed|null, \Laragear\CacheRefresh\Expire):TValue)|null  $callback
     */
    public function __construct(
        protected CacheContract $repository,
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
     * @param  \Closure(TValue|null|mixed, \Laragear\CacheRefresh\Expire): TValue  $callback
     * @return TValue|mixed
     */
    public function put(Closure $callback, DateTimeInterface|DateInterval|int|null $ttl = null): mixed
    {
        [$this->callback, $this->ttl] = [$callback, $ttl ?? $this->ttl];

        $store = $this->repository->getStore();

        if ($store instanceof LockContract) {
            return $store
                ->lock($this->name, $this->seconds, $this->owner)
                ->block($this->wait, Closure::fromCallable([$this, 'refresh']));
        }

        return $this->refresh();
    }

    /**
     * Executes the refresh operation.
     *
     * @return TValue|mixed|null
     */
    protected function refresh(): mixed
    {
        $expire = new Expire($this->ttl);

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
}
