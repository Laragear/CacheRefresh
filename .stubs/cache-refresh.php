<?php

namespace Illuminate\Support\Facades
{
    /**
     * @method static \Laragear\CacheRefresh\Refresh|mixed refresh(string $key, \Closure|null $refresh = null, \Closure|\DateTimeInterface|\DateInterval|int|null $ttl = null)
     */
    class Cache
    {
        //
    }
}

namespace Illuminate\Cache
{
    class Repository
    {
        /**
         * Refreshes a key value from the cache, optionally with a new expiration time.
         *
         * Refreshing is not an atomic operation on cache stores that do not support locking.
         *
         * @param  string  $key
         * @param  \Closure<mixed|null,\Laragear\CacheRefresh\Expire>|null  $callback
         * @param  \DateTimeInterface|\DateInterval|int|null  $ttl
         * @return \Laragear\CacheRefresh\Refresh|mixed
         */
        public function refresh(
            string $key,
            \Closure $callback = null,
            \DateTimeInterface|\DateInterval|int|null $ttl = null
        ): mixed {
            //
        }
    }
}
