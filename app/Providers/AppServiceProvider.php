<?php

namespace App\Providers;

use App\Models\User;
use App\Services\AiAssist\AiRateLimit;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        URL::forceHttps($this->app->isProduction());

        Gate::define('access-admin', fn (User $user): bool => $user->is_admin);

        RateLimiter::for('post-autosave', fn (Request $request): Limit => Limit::perMinute(30)
            ->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('post-preview', fn (Request $request): Limit => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('ai-assist', fn (Request $request): Limit => Limit::perMinute(config('services.openai.requests_per_minute'))
            ->by($request->user()?->id ?: $request->ip())
            ->response(function (Request $request, array $headers): RedirectResponse|JsonResponse {
                if ($request->user() !== null) {
                    app(AiRateLimit::class)->blockFor($request->user(), (int) $headers['Retry-After']);
                }

                return $request->expectsJson()
                ? response()->json([
                    'message' => "Too many AI requests. Try again in {$headers['Retry-After']} seconds.",
                    'retry_after' => (int) $headers['Retry-After'],
                ], 429, $headers)
                : back()
                    ->withInput($request->except(['_token', '_method', 'enhance']))
                    ->withErrors(['ai' => "Too many AI requests. Try again in {$headers['Retry-After']} seconds."])
                    ->withHeaders($headers);
            }));
    }
}
