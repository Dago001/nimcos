<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AlertSeverity;
use App\Enums\AuditResult;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Audit\AuditChainVerifier;
use App\Services\Security\SecurityAlertService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = AuditLog::query()->orderByDesc('id');

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from = (string) $request->query('from'))) {
            $query->where('created_at', '>=', to_utc_from_display($from.' 00:00'));
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to = (string) $request->query('to'))) {
            $query->where('created_at', '<', to_utc_from_display($to.' 00:00')->addDay());
        }
        if ($actor = trim((string) $request->query('actor'))) {
            $query->where('actor_label', 'ilike', '%'.str_replace(['%', '_'], ['\%', '\_'], $actor).'%');
        }
        if ($action = trim((string) $request->query('action'))) {
            $query->where('action', 'like', str_replace(['%', '_'], ['\%', '\_'], $action).'%');
        }
        if ($entity = trim((string) $request->query('entity'))) {
            $query->where('entity_type', $entity);
        }
        if ($result = AuditResult::tryFrom((string) $request->query('result'))) {
            $query->where('result', $result->value);
        }
        if ($ip = trim((string) $request->query('ip'))) {
            $query->where('ip', $ip);
        }

        return view('admin.audit.index', [
            'logs' => $query->simplePaginate(50)->withQueryString(),
            'filters' => $request->query(),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'entities' => AuditLog::query()->whereNotNull('entity_type')->distinct()->orderBy('entity_type')->pluck('entity_type'),
        ]);
    }

    public function verifyChain(AuditChainVerifier $verifier, SecurityAlertService $alerts): RedirectResponse
    {
        $result = $verifier->verify();
        if ($result['ok']) {
            return back()->with('success', "Audit chain intact: {$result['checked']} entries verified.");
        }

        $alerts->raise(SecurityAlertService::AUDIT_CHAIN_BROKEN, AlertSeverity::CRITICAL,
            "Audit log integrity check failed at entry #{$result['broken_at']}: {$result['reason']}", null, null, $result, 'audit-chain');

        return back()->with('error', "Audit chain BROKEN at entry #{$result['broken_at']}: {$result['reason']}");
    }
}
