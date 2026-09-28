<?php

namespace App\Models;

use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable;

    /** Status, lockout and MFA fields are never mass-assignable. */
    protected $fillable = ['name', 'email', 'phone'];

    protected $hidden = ['password', 'remember_token', 'mfa_secret'];

    /** @var array<string, bool>|null */
    private ?array $permissionCache = null;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => UserStatus::class,
            'mfa_secret' => 'encrypted',
            'mfa_confirmed_at' => 'datetime',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'must_change_password' => 'boolean',
            'is_test_data' => 'boolean',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')->withPivot(['assigned_by', 'assigned_at']);
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->permissionCache === null) {
            $names = Permission::query()
                ->join('role_permissions', 'role_permissions.permission_id', '=', 'permissions.id')
                ->join('user_roles', 'user_roles.role_id', '=', 'role_permissions.role_id')
                ->where('user_roles.user_id', $this->getKey())
                ->pluck('permissions.name')
                ->all();
            $this->permissionCache = array_fill_keys($names, true);
        }

        return isset($this->permissionCache[$permission]);
    }

    public function flushPermissionCache(): void
    {
        $this->permissionCache = null;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::ACTIVE;
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function hasMfa(): bool
    {
        return $this->mfa_confirmed_at !== null && $this->mfa_secret !== null;
    }

    public function roleLabels(): string
    {
        return $this->roles->pluck('label')->implode(', ');
    }
}
