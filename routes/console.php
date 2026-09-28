<?php

use App\Enums\AlertSeverity;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditChainVerifier;
use App\Services\Audit\AuditLogger;
use App\Services\Elections\ElectionLifecycle;
use App\Services\Security\SecurityAlertService;
use App\Services\Voting\VotingSessionService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/*
| Election clock: auto-open and auto-close elections, expire idle ballot sessions.
| Voting also refuses ballots after ends_at independently of this job.
*/
Artisan::command('nimcos:tick', function (ElectionLifecycle $lifecycle, VotingSessionService $sessions) {
    $result = $lifecycle->tick();
    $expired = $sessions->expireStale();
    if ($result['opened'] || $result['closed'] || $expired) {
        $this->info("Opened {$result['opened']}, closed {$result['closed']}, expired {$expired} session(s).");
    }
})->purpose('Open/close elections on schedule and expire idle voting sessions');

Artisan::command('audit:verify', function (AuditChainVerifier $verifier, SecurityAlertService $alerts) {
    $result = $verifier->verify();
    if ($result['ok']) {
        $this->info("Audit chain intact ({$result['checked']} entries).");

        return 0;
    }
    $alerts->raise(SecurityAlertService::AUDIT_CHAIN_BROKEN, AlertSeverity::CRITICAL,
        "Audit log integrity check failed at entry #{$result['broken_at']}: {$result['reason']}", null, null, $result, 'audit-chain');
    $this->error("Audit chain BROKEN at entry #{$result['broken_at']}: {$result['reason']}");

    return 1;
})->purpose('Verify the tamper-evident audit log hash chain');

Artisan::command('nimcos:create-admin {email} {name} {--role=SUPER_ADMIN}', function (AuditLogger $audit) {
    $email = mb_strtolower(trim($this->argument('email')));
    $role = Role::query()->where('name', $this->option('role'))->first();
    if (! $role) {
        $this->error('Unknown role. Run the seeders first (php artisan db:seed --class=RolesAndPermissionsSeeder).');

        return 1;
    }
    if (User::query()->where('email', $email)->exists()) {
        $this->error('An administrator with that email already exists.');

        return 1;
    }

    $password = $this->secret('Password (min 12 chars, upper/lower/number/symbol)');
    $validator = Validator::make(['password' => $password], ['password' => ['required', Password::defaults()]]);
    if ($validator->fails()) {
        $this->error(implode(' ', $validator->errors()->all()));

        return 1;
    }

    $user = DB::transaction(function () use ($email, $password, $role) {
        $user = new User(['name' => $this->argument('name'), 'email' => $email]);
        $user->password = $password;
        $user->forceFill(['status' => UserStatus::ACTIVE, 'password_changed_at' => now()])->save();
        $user->roles()->attach($role->getKey(), ['assigned_at' => now()]);

        return $user;
    });

    $audit->log(AuditAction::USER_CREATED, entity: $user, metadata: ['email' => $email, 'roles' => [$role->name], 'via' => 'console'],
        actor: ['type' => 'SYSTEM', 'id' => null, 'label' => 'console']);
    $this->info("Administrator {$email} created with role {$role->label}.");

    return 0;
})->purpose('Create an administrator account from the server console');

Artisan::command('nimcos:prune', function () {
    // Operational data only. Ballots, votes, audit logs and election records are never pruned.
    $otps = DB::table('otp_verifications')->where('created_at', '<', now()->subDays(30))->delete();
    $this->info("Pruned {$otps} OTP record(s) older than 30 days.");
})->purpose('Remove expired operational data (old OTP records)');

Schedule::command('nimcos:tick')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('audit:verify')->hourly()->withoutOverlapping();
Schedule::command('nimcos:prune')->dailyAt('02:30');
Schedule::command('queue:prune-failed --hours=168')->daily();
