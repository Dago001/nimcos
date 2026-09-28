<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditResult;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\Reauthenticator;
use App\Services\Notifications\NotificationService;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly Reauthenticator $reauth,
        private readonly NotificationService $notifications,
    ) {}

    public function index(): View
    {
        return view('admin.users.index', ['users' => User::query()->with('roles')->orderBy('name')->paginate(30)]);
    }

    public function create(): View
    {
        return view('admin.users.form', ['user' => new User, 'roles' => Role::query()->orderBy('label')->get(), 'assigned' => []]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:191', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:20'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['uuid', 'exists:roles,id'],
            'confirm_password' => ['required', 'string'],
            'confirm_mfa' => ['nullable', 'string'],
        ]);
        $this->reauth->confirm($request->user(), $data['confirm_password'], $data['confirm_mfa'] ?? null, 'create_admin');

        $temporary = $this->temporaryPassword();
        $user = DB::transaction(function () use ($data, $request, $temporary) {
            $user = new User(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null]);
            $user->password = $temporary;
            $user->forceFill([
                'status' => UserStatus::ACTIVE,
                'must_change_password' => true,
                'created_by' => $request->user()->getKey(),
            ])->save();
            $user->roles()->sync(collect($data['roles'])->mapWithKeys(fn ($id) => [$id => ['assigned_by' => $request->user()->getKey(), 'assigned_at' => now()]])->all());

            return $user;
        });

        $this->audit->log(AuditAction::USER_CREATED, AuditResult::SUCCESS, $user, [
            'email' => $user->email,
            'roles' => $user->roles()->pluck('name')->all(),
        ]);

        $this->notifications->sendAdminCredentials($user, $temporary, isNewAccount: true);

        return redirect()->route('admin.users.edit', $user)
            ->with('temporary_password', $this->revealOnScreen($temporary))
            ->with('success', "Administrator created. A temporary password has been emailed to {$user->email}; they must change it at first sign-in.");
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', [
            'user' => $user,
            'roles' => Role::query()->orderBy('label')->get(),
            'assigned' => $user->roles()->pluck('roles.id')->all(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['uuid', 'exists:roles,id'],
            'unlock' => ['nullable', 'boolean'],
            'confirm_password' => ['required', 'string'],
            'confirm_mfa' => ['nullable', 'string'],
        ]);
        $actor = $request->user();
        $this->reauth->confirm($actor, $data['confirm_password'], $data['confirm_mfa'] ?? null, 'update_admin');

        $before = $user->roles()->pluck('name')->sort()->values()->all();

        DB::transaction(function () use ($user, $data, $actor, $request) {
            $user->fill(['name' => $data['name'], 'phone' => $data['phone'] ?? null]);
            $user->status = UserStatus::from($data['status']);
            if ($request->boolean('unlock')) {
                $user->forceFill(['locked_until' => null, 'failed_login_count' => 0]);
            }
            $user->save();
            $user->roles()->sync(collect($data['roles'])->mapWithKeys(fn ($id) => [$id => ['assigned_by' => $actor->getKey(), 'assigned_at' => now()]])->all());
            $user->flushPermissionCache();

            // Never leave the system without an active administrator manager.
            $managers = User::query()->where('status', UserStatus::ACTIVE->value)->get()
                ->filter(fn (User $u) => $u->hasPermission(Permissions::MANAGE_ADMINS))->count();
            if ($managers === 0) {
                throw ValidationException::withMessages(['roles' => 'At least one active administrator must keep the "Manage administrators" permission.']);
            }
        });

        $after = $user->roles()->pluck('name')->sort()->values()->all();
        $this->audit->log(AuditAction::USER_UPDATED, AuditResult::SUCCESS, $user, ['status' => $user->status->value, 'unlocked' => $request->boolean('unlock')]);
        if ($before !== $after) {
            $this->audit->log(AuditAction::USER_ROLES_CHANGED, AuditResult::SUCCESS, $user, ['from' => $before, 'to' => $after]);
        }

        return back()->with('success', 'Administrator updated.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['confirm_password' => ['required', 'string'], 'confirm_mfa' => ['nullable', 'string']]);
        $this->reauth->confirm($request->user(), $data['confirm_password'], $data['confirm_mfa'] ?? null, 'reset_admin_password');

        $temporary = $this->temporaryPassword();
        $user->password = $temporary;
        $user->forceFill(['must_change_password' => true, 'locked_until' => null, 'failed_login_count' => 0])->save();
        // End any existing sessions of that user.
        DB::table('sessions')->where('user_id', $user->getKey())->delete();

        $this->audit->log(AuditAction::USER_UPDATED, AuditResult::SUCCESS, $user, ['password_reset' => true]);

        $this->notifications->sendAdminCredentials($user, $temporary, isNewAccount: false);

        return back()->with('temporary_password', $this->revealOnScreen($temporary))
            ->with('success', "Password reset. A temporary password has been emailed to {$user->email}.");
    }

    /** Emailed only; also shown on screen in demo mode, where mail goes to the log. */
    private function revealOnScreen(string $temporary): ?string
    {
        return config('nimcos.demo_mode') ? $temporary : null;
    }

    private function temporaryPassword(): string
    {
        // Guaranteed to satisfy the password policy: upper, lower, digit, symbol.
        return Str::password(10, symbols: false).'A'.random_int(10, 99).'!'.Str::lower(Str::random(2));
    }
}
