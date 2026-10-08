<?php

use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\EnsureAdminSession;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\RequireVotingSession;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);

        $middleware->web(append: [
            CheckMaintenanceMode::class,
        ]);

        $middleware->alias([
            'permission' => RequirePermission::class,
            'admin.session' => EnsureAdminSession::class,
            'voting.session' => RequireVotingSession::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('admin', 'admin/*')
            ? route('admin.login')
            : route('voter.entry'));

        $middleware->redirectUsersTo(fn (Request $request) => $request->is('admin', 'admin/*')
            ? route('admin.dashboard')
            : route('voter.elections'));

        // The ballot token cookie is encrypted like every other cookie (default behaviour).
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Never show SQL to users (spec §44); log it with a reference instead.
        $exceptions->render(function (QueryException $e, Request $request) {
            if (config('app.debug')) {
                return null;
            }
            $reference = strtoupper(bin2hex(random_bytes(4)));
            Log::error('Database error', ['reference' => $reference, 'message' => $e->getMessage()]);

            return response()->view('errors.500', ['reference' => $reference], 500);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your session has ended. Please sign in again.'], 401);
            }

            return null;
        });

        $exceptions->dontFlash(['password', 'current_password', 'password_confirmation', 'confirm_password', 'otp', 'code', 'mfa_code']);
    })->create();
