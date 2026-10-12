<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        Gate::define('access-admin', fn (User $user): bool => $user->is_admin);

        RateLimiter::for('ai-assist', fn (Request $request): Limit => Limit::perMinute(config('services.openai.requests_per_minute'))
            ->by($request->user()?->id ?: $request->ip())
            ->response(fn (Request $request, array $headers): RedirectResponse => back()
                ->withInput($request->except(['_token', '_method', 'enhance']))
                ->withErrors(['ai' => "Too many AI requests. Try again in {$headers['Retry-After']} seconds."])
                ->withHeaders($headers)));
    }
}
