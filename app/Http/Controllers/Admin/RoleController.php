<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditResult;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\Reauthenticator;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => Role::query()->with('permissions')->withCount('users')->orderBy('label')->get(),
            'permissions' => Permission::query()->orderBy('group')->orderBy('label')->get()->groupBy('group'),
        ]);
    }

    public function update(Request $request, Role $role, AuditLogger $audit, Reauthenticator $reauth): RedirectResponse
    {
        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['uuid', 'exists:permissions,id'],
            'confirm_password' => ['required', 'string'],
            'confirm_mfa' => ['nullable', 'string'],
        ]);
        $reauth->confirm($request->user(), $data['confirm_password'], $data['confirm_mfa'] ?? null, 'change_role_permissions');

        $before = $role->permissions()->pluck('name')->sort()->values()->all();

        DB::transaction(function () use ($role, $data) {
            $role->permissions()->sync($data['permissions'] ?? []);
            $managers = User::query()->where('status', UserStatus::ACTIVE->value)->get()
                ->filter(fn (User $u) => $u->hasPermission(Permissions::MANAGE_ADMINS))->count();
            if ($managers === 0) {
                throw ValidationException::withMessages(['permissions' => 'This change would leave no active administrator able to manage administrators.']);
            }
        });

        $after = $role->permissions()->pluck('name')->sort()->values()->all();
        $audit->log(AuditAction::ROLE_PERMISSIONS_CHANGED, AuditResult::SUCCESS, $role, [
            'role' => $role->name,
            'added' => array_values(array_diff($after, $before)),
            'removed' => array_values(array_diff($before, $after)),
        ]);

        return back()->with('success', "Permissions for {$role->label} updated.");
    }
}
