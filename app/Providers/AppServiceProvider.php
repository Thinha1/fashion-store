<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
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

        // The storefront header renders a 3-level mega-menu (top category ->
        // garment type -> a handful of specific styles). A garment type with
        // no further styles (e.g. "Balo") falls back to listing its own
        // products directly in the view.
        View::composer('layouts.app', function ($view): void {
            $view->with('megaMenu', Category::query()
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->with(['children' => function ($query) {
                    $query->where('is_active', true)
                        ->orderBy('sort_order')
                        ->with([
                            'children' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order'),
                            'products' => fn ($query) => $query->where('status', 'active')->latest('id')->limit(5),
                        ]);
                }])
                ->get());
        });
    }
}
