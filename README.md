# Cache Refresh

[![Latest Version on Packagist](https://img.shields.io/packagist/v/laragear/cache-refresh.svg)](https://packagist.org/packages/laragear/cache-refresh)
[![Latest stable test run](https://github.com/Laragear/CacheRefresh/workflows/Tests/badge.svg)](https://github.com/Laragear/CacheRefresh/actions)
[![Codecov coverage](https://codecov.io/gh/Laragear/CacheRefresh/branch/1.x/graph/badge.svg?token=OUUWluNbr6)](https://codecov.io/gh/Laragear/CacheRefresh)
[![Maintainability](https://api.codeclimate.com/v1/badges/6fb0cc168f26b3f245bc/maintainability)](https://codeclimate.com/github/Laragear/CacheRefresh/maintainability)
[![Sonarcloud Status](https://sonarcloud.io/api/project_badges/measure?project=Laragear_CacheRefresh&metric=alert_status)](https://sonarcloud.io/dashboard?id=Laragear_CacheRefresh)
[![Laravel Octane Compatibility](https://img.shields.io/badge/Laravel%20Octane-Compatible-success?style=flat&logo=laravel)](https://laravel.com/docs/9.x/octane#introduction)

Refresh items in your cache without data races.

```php
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use App\Models\Message;

public function send(Message $message)
{
    Cache::refresh(
        $message->to, 
        fn ($messages) => Collection::wrap($messages)->push($message)
    );
}
```

## Keep this package free

[![](.assets/patreon.png)](https://patreon.com/packagesforlaravel)[![](.assets/ko-fi.png)](https://ko-fi.com/DarkGhostHunter)[![](.assets/buymeacoffee.png)](https://www.buymeacoffee.com/darkghosthunter)[![](.assets/paypal.png)](https://www.paypal.com/paypalme/darkghosthunter)

Your support allows me to keep this package free, up-to-date and maintainable. Alternatively, you
can **[spread the word!](http://twitter.com/share?text=I%20am%20using%20this%20cool%20PHP%20package&url=https://github.com%2FLaragear%2FCacheRefresh&hashtags=PHP,Laravel)**

## Requirements

* Laravel 9.x, or later
* PHP 8.0 or later
* Cache Driver with Lock support (*).

> (*) You can still use Cache Refresh without a driver that supports locking, but bear in mind, **refreshing won't be atomic**.

## Installation

You can install the package via Composer:

```bash
composer require laragear/cache-refresh
```

## Usage

Cache Refresh will retrieve a key value from your cache store that you can edit using a callback. This callback is free to change the value and return it to be persisted.

When the cached value doesn't exist, like when is first called, you will receive `null`, so remember to _un-null_ the value when is first called.

```php
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use App\Models\Message;

public function send(Message $message)
{
    // Add the incoming message to a list of messages, refreshing the overall list.
    $messages = Cache::refresh(
        $message->to,
        function (?Collection $messages) {
            return Collection::wrap($messages)->push($message);
        },
        60 * 5
    );
    
    return 'Messages has been queued';
}
```

### Custom Expiration time

The callback also receives an `Expire` instance, which will allow you to change the expiration time of the key inside the callback. 

```php
use Illuminate\Support\Facades\Cache;
use Laragear\CacheRefresh\Expire;
use App\Models\Mission;

Cache::refresh('mission', function ($mission, Expire $expire) {
    $mission ??= new Mission();
    
    if ($mission->ongoing()) {
        // Set a new expiration time.
        $expire->at(today()->endOfDay());
    }
    
    if ($mission->completed()) {
        // Expire the value immediately.
        $expire->now();
    }
    
    if ($mission->isVeryDifficult()) {
        // Put it forever.
        $expire->never();
    }

    return $mission;
}, 60 * 5);
```

### Custom Lock configuration

You can omit a callback to manage the lock time and the waiting time using `lock()` and `waitFor()`, respectively, and issue the callback using `put()`.

```php
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use App\Models\Message;

Cache::refresh('mission')->lock(60)->waitFor(10)->put(fn ($value) => ..., 60 * 5);
```

## PhpStorm stubs

For users of PhpStorm, there is a stub file to aid in macro autocompletion for this package. You can publish them using the `phpstorm` tag:

```shell
php artisan vendor:publish --provider="Laragear\CacheRefresh\CacheRefreshServiceProvider" --tag="phpstorm"
```

The file gets published into the `.stubs` folder of your project. You should point your [PhpStorm to these stubs](https://www.jetbrains.com/help/phpstorm/php.html#advanced-settings-area).

## Laravel Octane compatibility

* There are no singletons using a stale application instance.
* There are no singletons using a stale config instance.
* There are no singletons using a stale request instance.

There should be no problems using this package with Laravel Octane.

## Security

If you discover any security related issues, please email darkghosthunter@gmail.com instead of using the issue tracker.

# License

This specific package version is licensed under the terms of the [MIT License](LICENSE.md), at time of publishing.

[Laravel](https://laravel.com) is a Trademark of [Taylor Otwell](https://github.com/TaylorOtwell/). Copyright © 2011-2022 Laravel LLC.
