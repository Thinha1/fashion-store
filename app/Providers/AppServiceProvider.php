<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\User;
use App\Services\Ai\AiProviderContract;
use App\Services\Ai\OpenAiCompatibleProvider;
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
        // The product-draft chat widget's AI backend: a self-hosted,
        // OpenAI-compatible chat completions endpoint. Bound behind the
        // interface (not just `new`d in the controller) so tests can swap it
        // for a fake, and so a second provider can be added later without
        // touching the controller, widget, or prompt/parsing logic.
        $this->app->bind(AiProviderContract::class, OpenAiCompatibleProvider::class);
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

        RateLimiter::for('supplier-tax-lookup', fn ($request): Limit => Limit::perMinute(
            max(1, (int) config('services.vietqr.business_requests_per_minute'))
        )->by((string) $request->user()->id));

        RateLimiter::for('product-ai-assist', fn ($request): Limit => Limit::perMinute(
            max(1, (int) config('services.ai.requests_per_minute'))
        )->by((string) $request->user()->id));

        // Guest-callable (see ProductAssistController), so — unlike every
        // other limiter above — this can't key by an authenticated user id
        // alone; falls back to IP for anonymous shoppers.
        RateLimiter::for('shopping-assist', fn ($request): Limit => Limit::perMinute(
            max(1, (int) config('services.ai.shopping_assist_requests_per_minute'))
        )->by($request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip()));

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
