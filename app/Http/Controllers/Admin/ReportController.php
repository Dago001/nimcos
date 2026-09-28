<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditResult;
use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditLogger;
use App\Services\Reports\ReportRenderer;
use App\Services\Reports\ReportService;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function index(): View
    {
        return view('admin.reports.index', [
            'types' => ReportService::TYPES,
            'elections' => Election::query()->orderByDesc('starts_at')->get(),
        ]);
    }

    public function download(Request $request, ReportService $reports, ReportRenderer $renderer, AuditLogger $audit): Response
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(ReportService::TYPES))],
            'format' => ['required', Rule::in(['csv', 'xlsx', 'pdf'])],
            'election' => ['nullable', 'uuid', 'exists:elections,id'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        // Per-report permissions on top of generate_reports.
        $user = $request->user();
        $needed = match ($data['type']) {
            'audit' => Permissions::VIEW_AUDIT_LOGS,
            'results' => Permissions::VIEW_RESULTS,
            default => Permissions::GENERATE_REPORTS,
        };
        abort_unless($user->hasPermission($needed), 403);

        $election = isset($data['election']) ? Election::query()->find($data['election']) : null;
        $report = $reports->build($data['type'], $election, $data);

        $audit->log(AuditAction::REPORT_GENERATED, AuditResult::SUCCESS, $election, [
            'type' => $data['type'],
            'format' => $data['format'],
        ]);

        $filename = Str::slug('nimcos '.$data['type'].' '.($election?->code ?? '').' '.display_time(now(), 'Y-m-d Hi'));

        return $renderer->render($report, $data['format'], $filename);
    }
}
