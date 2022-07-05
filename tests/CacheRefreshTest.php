<?php

namespace Tests;

use Closure;
use Illuminate\Cache\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Cache;
use Laragear\CacheRefresh\Refresh;
use Mockery;

class CacheRefreshTest extends TestCase
{
    protected function getRepository(): Repository
    {
        $dispatcher = new Dispatcher(Mockery::mock(Container::class));
        $repository = new Repository(Mockery::mock(Store::class));

        $repository->setEventDispatcher($dispatcher);

        return $repository;
    }

    protected function getRepositoryWithLockProvider(): Repository
    {
        $dispatcher = new Dispatcher(Mockery::mock(Container::class));
        $repository = new Repository(Mockery::mock(Store::class, LockProvider::class));

        $repository->setEventDispatcher($dispatcher);

        return $repository;
    }

    protected function getMockedLock($block = 10): Mockery\MockInterface
    {
        $lock = Mockery::mock(Lock::class);

        $lock->expects('block')
            ->with($block, Mockery::type(Closure::class))
            ->andReturnUsing(function ($lock, $callback) {
                return $callback();
            });

        return $lock;
    }

    public function test_repository_has_macro(): void
    {
        static::assertInstanceOf(Refresh::class, Cache::refresh('foo'));
        static::assertInstanceOf(Refresh::class, cache()->refresh('foo'));
    }

    public function test_refresh_uses_no_lock_provider(): void
    {
        $repo = $this->getRepository();

        /** @var \Mockery\MockInterface $store */
        $store = $repo->getStore();

        $store->expects('lock')->never();
        $store->expects('block')->never();
        $store->expects('get')->with('foo')->andReturn('quz');
        $store->expects('forever')->with('foo', 'quz');

        $result = $repo->refresh('foo', function ($item) {
            return $item ?? 'new';
        });

        static::assertSame('quz', $result);
    }

    public function test_refresh_puts_non_existing_item_forever_by_default(): void
    {
        $repo = $this->getRepositoryWithLockProvider();

        /** @var \Mockery\MockInterface $store */
        $store = $repo->getStore();

        $store->expects('lock')->with('foo:refresh', 0, null)->andReturn($this->getMockedLock());
        $store->expects('get')->with('foo')->andReturn(null);
        $store->expects('forever')->with('foo', 'new');

        $result = $repo->refresh('foo', function ($item) {
            return $item ?? 'new';
        });

        static::assertSame('new', $result);
    }

    public function test_refresh_uses_custom_ttl(): void
    {
        $repo = $this->getRepositoryWithLockProvider();

        /** @var \Mockery\MockInterface $store */
        $store = $repo->getStore();

        $store->expects('lock')->with('foo:refresh', 0, null)->andReturn($this->getMockedLock());
        $store->expects('get')->with('foo')->andReturn(null);
        $store->expects('put')->with('foo', 'new', 90);

        $repo->refresh('foo', function ($item, $expire) {
            static::assertSame(90, $expire->at);

            return $item ?? 'new';
        }, 90);
    }

    public function test_refresh_uses_computed_ttl(): void
    {
        $repo = $this->getRepositoryWithLockProvider();

        /** @var \Mockery\MockInterface $store */
        $store = $repo->getStore();

        $store->expects('lock')->with('foo:refresh', 0, null)->andReturn($this->getMockedLock());
        $store->expects('get')->with('foo')->andReturn(null);
        $store->expects('put')->with('foo', 'new', 60);

        $repo->refresh('foo', function ($item, $expire) {
            $expire->at(60);

            return $item ?? 'new';
        }, 90);
    }

    public function test_refresh_uses_computed_never_ttl(): void
    {
        $repo = $this->getRepositoryWithLockProvider();

        /** @var \Mockery\MockInterface $store */
        $store = $repo->getStore();

        $store->expects('lock')->with('foo:refresh', 0, null)->andReturn($this->getMockedLock());
        $store->expects('get')->with('foo')->andReturn(null);
        $store->expects('forever')->with('foo', 'new');

        $repo->refresh('foo')->put(function ($item, $expire) {
            $expire->never();

            return $item ?? 'new';
        });
    }

    public function test_refresh_doesnt_call_cache_if_nothing_to_refresh(): void
    {
        $repo = $this->getRepositoryWithLockProvider();

        /** @var \Mockery\MockInterface $store */
        $store = $repo->getStore();

        $store->expects('lock')->with('foo:refresh', 0, null)->andReturn($this->getMockedLock());
        $store->expects('get')->with('foo')->andReturn(null);
        $store->expects('forget')->never();
        $store->expects('put')->never();
        $store->expects('forever')->never();

        $repo->refresh('foo')->put(function () {
            return null;
        });
    }

    public function test_refresh_forgets_item_if_exists_and_expire_is_now(): void
    {
        $repo = $this->getRepositoryWithLockProvider();

        /** @var \Mockery\MockInterface $store */
        $store = $repo->getStore();

        $store->expects('lock')->with('foo:refresh', 0, null)->andReturn($this->getMockedLock());
        $store->expects('get')->with('foo')->andReturn('foo');
        $store->expects('forget')->with('foo');

        $repo->refresh('foo')->put(function ($item, $expire) {
            $expire->now();

            return null;
        });
    }

    public function test_refresh_doesnt_call_cache_if_nothing_to_forget(): void
    {
        $repo = $this->getRepositoryWithLockProvider();

        /** @var \Mockery\MockInterface $store */
        $store = $repo->getStore();

        $store->expects('lock')->with('foo:refresh', 0, null)->andReturn($this->getMockedLock());
        $store->expects('get')->with('foo')->andReturn(null);
        $store->expects('forget')->never();
        $store->expects('put')->never();
        $store->expects('forever')->never();

        $result = $repo->refresh('foo', function ($item, $expire) {
            $expire->now();

            return null;
        });

        static::assertNull($result);
    }

    public function test_refresh_removes_item_from_cache_store_with_expiration_now(): void
    {
        $repo = $this->getRepositoryWithLockProvider();

        /** @var \Mockery\MockInterface $store */
        $store = $repo->getStore();

        $store->expects('lock')->with('foo:refresh', 0, null)->andReturn($this->getMockedLock());
        $store->expects('get')->with('foo')->andReturn('bar');
        $store->expects('forget')->with('foo')->andReturnTrue();
        $store->expects('put')->never();
        $store->expects('forever')->never();

        $result = $repo->refresh('foo', function ($item, $expire) {
            $expire->now();

            return $item ?? 'bar';
        });

        static::assertSame('bar', $result);
    }

    public function test_refresh_updates_existing_item(): void
    {
        $repo = $this->getRepositoryWithLockProvider();

        /** @var \Mockery\MockInterface $store */
        $store = $repo->getStore();

        $store->expects('lock')->with('foo:refresh', 0, null)->andReturn($this->getMockedLock());
        $store->expects('get')->with('foo')->andReturn('bar');
        $store->expects('forever')->with('foo', 'bar');

        $result = $repo->refresh('foo', function ($item) {
            static::assertSame('bar', $item);

            return $item ?? 'new';
        });

        static::assertSame('bar', $result);
    }

    public function test_refresh_uses_lock_config(): void
    {
        $repo = $this->getRepositoryWithLockProvider();

        /** @var \Mockery\MockInterface $store */
        $store = $repo->getStore();

        $store->expects('lock')->with('test_lock', 20, 'test_owner')->andReturn($this->getMockedLock(30));
        $store->expects('get')->with('foo')->andReturn('bar');
        $store->expects('put')->with('foo', 'bar', 90);

        $result = $repo->refresh('foo')
            ->lock('test_lock', 20, 'test_owner')
            ->waitFor(30)
            ->put(function ($item) {
                return $item ?? 'new';
            }, 90);

        static::assertSame('bar', $result);
    }
}
