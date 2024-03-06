<?php

namespace Laragear\CacheRefresh;

use DateInterval;
use DateTimeInterface;

class Expire
{
    /**
     * Create a new expire instance.
     */
    public function __construct(public DateTimeInterface|DateInterval|int|null $at)
    {
        //
    }

    /**
     * Expires the cache key at the given time or seconds.
     */
    public function at(DateTimeInterface|DateInterval|int|null $at): void
    {
        $this->at = $at;
    }

    /**
     * Expires the cache immediately, removing it from the cache.
     */
    public function now(): void
    {
        $this->at(0);
    }

    /**
     * Persists the new value forever in the cache.
     */
    public function never(): void
    {
        $this->at(null);
    }
}
