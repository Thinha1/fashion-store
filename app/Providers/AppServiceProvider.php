<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
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
        // Any Policy/Gate ability whose name matches a permission code (e.g. "products.view")
        // is granted automatically to a user whose role carries that permission (or "*").
        Gate::before(fn (User $user, string $ability): ?bool => $user->hasPermission($ability) ? true : null);

        RateLimiter::for('login', function ($request): Limit {
            $throttleKey = mb_strtolower((string) $request->input('email')).'|'.$request->ip();

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
