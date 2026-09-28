<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditResult;
use App\Http\Controllers\Controller;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\Reauthenticator;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function edit(): View
    {
        return view('admin.settings.edit', [
            'definitions' => SettingsService::DEFINITIONS,
            'values' => $this->settings->all(),
            'environment' => [
                'Application environment' => app()->environment(),
                'Demo mode' => config('nimcos.demo_mode') ? 'ON (test data only)' : 'Off',
                'Mail driver' => config('mail.default'),
                'Queue connection' => config('queue.default'),
                'Operational timezone' => config('nimcos.display_timezone'),
                'MFA enforced by environment' => config('nimcos.admin.require_mfa') ? 'Yes' : 'No',
            ],
        ]);
    }

    public function update(Request $request, AuditLogger $audit, Reauthenticator $reauth): RedirectResponse
    {
        $data = $request->validate([
            'otp_ttl_minutes' => ['required', 'integer', 'min:2', 'max:15'],
            'otp_max_attempts' => ['required', 'integer', 'min:3', 'max:10'],
            'voting_session_minutes' => ['required', 'integer', 'min:5', 'max:60'],
            'require_admin_mfa' => ['nullable', 'boolean'],
            'support_contact' => ['nullable', 'string', 'max:200'],
            'confirm_password' => ['required', 'string'],
            'confirm_mfa' => ['nullable', 'string'],
        ]);
        $reauth->confirm($request->user(), $data['confirm_password'], $data['confirm_mfa'] ?? null, 'change_settings');

        $data['require_admin_mfa'] = $request->boolean('require_admin_mfa');
        $changed = [];
        foreach (array_keys(SettingsService::DEFINITIONS) as $key) {
            $old = $this->settings->get($key);
            if ($old != ($data[$key] ?? null)) {
                $changed[$key] = ['from' => $old, 'to' => $data[$key] ?? null];
                $this->settings->set($key, $data[$key] ?? null, $request->user());
            }
        }

        if ($changed !== []) {
            $audit->log(AuditAction::SETTINGS_CHANGED, AuditResult::SUCCESS, null, ['changes' => $changed]);
        }

        return back()->with('success', $changed ? 'Settings saved.' : 'No changes to save.');
    }
}
