<?php

use App\Providers\AppServiceProvider;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Support\Facades\URL;

/**
 * Evaluate a config file as it would load with the given environment variables set (null unsets one).
 *
 * @param  array<string, ?string>  $variables
 * @return array<string, mixed>
 */
function configFileWith(string $file, array $variables): array
{
    $previous = [];

    foreach ($variables as $name => $value) {
        $previous[$name] = [$_SERVER[$name] ?? null, $_ENV[$name] ?? null, getenv($name)];

        if ($value === null) {
            unset($_SERVER[$name], $_ENV[$name]);
            putenv($name);
        } else {
            $_SERVER[$name] = $_ENV[$name] = $value;
            putenv("{$name}={$value}");
        }
    }

    try {
        return require config_path($file);
    } finally {
        foreach ($previous as $name => [$server, $env, $put]) {
            if ($server === null) {
                unset($_SERVER[$name]);
            } else {
                $_SERVER[$name] = $server;
            }

            if ($env === null) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $env;
            }

            putenv($put === false ? $name : "{$name}={$put}");
        }
    }
}

afterEach(function () {
    URL::forceHttps(false);
    $this->app['env'] = 'testing';
});

test('production builds every URL with https', function () {
    config(['app.url' => 'http://blog.example.test']);
    URL::useOrigin('http://blog.example.test');
    $this->app['env'] = 'production';

    (new AppServiceProvider($this->app))->boot();

    expect(url('/'))->toStartWith('https://')
        ->and(route('home'))->toStartWith('https://');
});

test('outside production URLs keep the configured scheme', function () {
    URL::useOrigin('http://blog.example.test');

    (new AppServiceProvider($this->app))->boot();

    expect(url('/'))->toStartWith('http://');
});

test('session cookies are secure by default in production only', function () {
    expect(configFileWith('session.php', ['APP_ENV' => 'production', 'SESSION_SECURE_COOKIE' => null])['secure'])->toBeTrue()
        ->and(configFileWith('session.php', ['APP_ENV' => 'local', 'SESSION_SECURE_COOKIE' => null])['secure'])->toBeFalse()
        ->and(configFileWith('session.php', ['APP_ENV' => 'production', 'SESSION_SECURE_COOKIE' => 'false'])['secure'])->toBeFalse();
});

test('logs default to warnings in production, and LOG_LEVEL still overrides it', function () {
    $production = configFileWith('logging.php', ['APP_ENV' => 'production', 'LOG_LEVEL' => null]);
    $local = configFileWith('logging.php', ['APP_ENV' => 'local', 'LOG_LEVEL' => null]);
    $overridden = configFileWith('logging.php', ['APP_ENV' => 'production', 'LOG_LEVEL' => 'error']);

    expect($production['channels']['single']['level'])->toBe('warning')
        ->and($production['channels']['daily']['level'])->toBe('warning')
        ->and($local['channels']['single']['level'])->toBe('debug')
        ->and($overridden['channels']['single']['level'])->toBe('error');
});

test('requests only answer for trusted hosts', function () {
    expect(app(Kernel::class)->hasMiddleware(TrustHosts::class))->toBeTrue();
});
