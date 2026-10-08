<?php

namespace App\Http\Middleware;

use App\Services\Settings\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    public function __construct(private readonly SettingsService $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $isMaintenance = (bool) $this->settings->get('system_under_maintenance');
        } catch (\Throwable) {
            $isMaintenance = false;
        }

        if (! $isMaintenance) {
            return $next($request);
        }

        // 1. Exclude admin routes so administrators can manage the system
        if ($request->is('admin', 'admin/*')) {
            return $next($request);
        }

        // 2. Exclude application health-check endpoint
        if ($request->is('up')) {
            return $next($request);
        }

        // 3. Exclude static assets
        if ($request->is('assets/*', 'images/*', 'favicon.*')) {
            return $next($request);
        }

        // 4. Authenticated administrators (guard: web) can preview or access the site
        if ($request->user('web') !== null) {
            return $next($request);
        }

        // 5. JSON responses (AJAX / API requests)
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'The voting portal is currently undergoing scheduled maintenance. Please try again later.',
            ], 503);
        }

        $customMessage = (string) ($this->settings->get('maintenance_message') ?? '');

        return response()->view('maintenance', [
            'message' => $customMessage,
            'supportContact' => $this->settings->get('support_contact') ?: $this->settings->get('contact_email'),
            'phone' => $this->settings->get('contact_phone'),
        ], 503, [
            'Retry-After' => '300',
            'Cache-Control' => 'no-cache, private, no-store, must-revalidate',
        ]);
    }
}
