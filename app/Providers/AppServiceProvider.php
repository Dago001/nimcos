<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Announcements\AnnouncementFeed;
use App\Services\Settings\SettingsService;
use App\Support\Permissions;
use App\Support\ServiceNumber;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);
    }

    public function boot(): void
    {
        $this->guardProductionConfiguration();

        // Silently dropped (non-fillable) attributes are programming errors: surface them in development.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        if ($proxies = config('nimcos.trusted_proxies')) {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        // Route keys are UUIDs; anything else is a 404 before it reaches the database.
        foreach (['election', 'electionVoter', 'electionPosition', 'candidate', 'voter', 'import', 'user', 'role', 'alert', 'position', 'announcement'] as $param) {
            Route::pattern($param, '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}');
        }

        // Permission-based authorisation (spec §24): one gate per permission.
        foreach (array_keys(Permissions::catalogue()) as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }

        Password::defaults(fn () => Password::min((int) config('nimcos.admin.password_min_length', 12))
            ->letters()->mixedCase()->numbers()->symbols());

        View::composer('partials.announcements', function ($view) {
            $feed = app(AnnouncementFeed::class);
            $view->with(['tickerItems' => $feed->ticker(), 'popupItems' => $feed->popups()]);
        });

        Paginator::defaultView('partials.pagination');
        Paginator::defaultSimpleView('partials.pagination');

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        // Service Number entry: per IP, and per Service Number regardless of IP.
        RateLimiter::for('voter-access', function (Request $request) {
            $sn = ServiceNumber::normalise((string) $request->input('service_number'));

            return [
                Limit::perMinute(10)->by('ip:'.$request->ip()),
                Limit::perHour(60)->by('ip-h:'.$request->ip()),
                Limit::perMinute(5)->by('sn:'.$sn),
            ];
        });

        RateLimiter::for('otp-verify', fn (Request $request) => [
            Limit::perMinute(10)->by('otp-ip:'.$request->ip()),
            Limit::perMinute(6)->by('otp-session:'.$request->session()->getId()),
        ]);

        RateLimiter::for('ballot', fn (Request $request) => Limit::perMinute(30)->by('ballot:'.$request->session()->getId()));

        RateLimiter::for('admin-login', function (Request $request) {
            return [
                Limit::perMinute(10)->by('admin-ip:'.$request->ip()),
                Limit::perMinute(5)->by('admin-email:'.mb_strtolower((string) $request->input('email'))),
            ];
        });

        RateLimiter::for('admin', fn (Request $request) => Limit::perMinute(240)->by('admin:'.($request->user()?->getKey() ?? $request->ip())));

        RateLimiter::for('public', fn (Request $request) => Limit::perMinute(30)->by('public:'.$request->ip()));
    }

    /** Refuse to boot with configurations that would compromise a real election. */
    private function guardProductionConfiguration(): void
    {
        if (! $this->app->isProduction()) {
            return;
        }
        if (config('nimcos.demo_mode')) {
            throw new RuntimeException('NIMCOS_DEMO_MODE must be false in production.');
        }
        if (config('app.debug')) {
            throw new RuntimeException('APP_DEBUG must be false in production.');
        }
        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            Log::critical('MAIL_MAILER is "'.config('mail.default').'" in production: voters and administrators will not receive emails.');
        }
    }
}
