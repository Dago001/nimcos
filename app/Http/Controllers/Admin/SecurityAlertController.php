<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use App\Enums\AuditResult;
use App\Http\Controllers\Controller;
use App\Models\SecurityAlert;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SecurityAlertController extends Controller
{
    public function index(Request $request): View
    {
        $status = AlertStatus::tryFrom((string) $request->query('status', 'OPEN'));
        $query = SecurityAlert::query()->with('reviewer')->orderByDesc('last_seen_at');
        if ($status) {
            $query->where('status', $status->value);
        }
        if ($severity = AlertSeverity::tryFrom((string) $request->query('severity'))) {
            $query->where('severity', $severity->value);
        }

        return view('admin.alerts.index', [
            'alerts' => $query->paginate(30)->withQueryString(),
            'status' => $status,
            'filters' => $request->query(),
        ]);
    }

    public function review(Request $request, SecurityAlert $alert, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([AlertStatus::REVIEWED->value, AlertStatus::DISMISSED->value])],
            'review_notes' => ['required', 'string', 'max:2000'],
        ]);
        $alert->forceFill([
            'status' => $data['status'],
            'review_notes' => $data['review_notes'],
            'reviewed_by' => $request->user()->getKey(),
            'reviewed_at' => now(),
        ])->save();

        $audit->log(AuditAction::SECURITY_ALERT_REVIEWED, AuditResult::SUCCESS, $alert, ['status' => $data['status']]);

        return back()->with('success', 'Alert marked as '.strtolower($data['status']).'.');
    }
}
