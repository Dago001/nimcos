<?php

namespace App\Http\Middleware;

use App\Enums\AlertSeverity;
use App\Models\User;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditLogger;
use App\Services\Security\SecurityAlertService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('permission:manage_voters') or 'permission:view_results|generate_reports' (any of).
 * Denials are audited and repeated denials raise an alert.
 */
class RequirePermission
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SecurityAlertService $alerts,
    ) {}

    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        $user = $request->user('web');
        $required = explode('|', $permissions);

        if ($user instanceof User) {
            foreach ($required as $permission) {
                if ($user->hasPermission($permission)) {
                    return $next($request);
                }
            }
        }

        $this->audit->denied(AuditAction::ACCESS_DENIED, null, [
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'required' => $required,
        ]);

        if ($user instanceof User && $request->isMethod('POST')) {
            $this->alerts->raise(
                SecurityAlertService::UNAUTHORIZED_ADMIN,
                AlertSeverity::MEDIUM,
                'An administrator attempted an action outside their permissions.',
                $request->ip(),
                $user->email,
                ['route' => $request->route()?->getName(), 'required' => $required],
                'admin:'.$user->getKey(),
            );
        }

        abort(403, 'You do not have permission to perform this action.');
    }
}
