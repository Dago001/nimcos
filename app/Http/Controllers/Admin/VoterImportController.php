<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ImportStatus;
use App\Http\Controllers\Controller;
use App\Models\Voter;
use App\Models\VoterImport;
use App\Services\Reports\ReportRenderer;
use App\Services\Voters\VoterFileParser;
use App\Services\Voters\VoterImportService;
use App\Support\PhpIniSize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VoterImportController extends Controller
{
    public function __construct(private readonly VoterImportService $imports) {}

    public function index(): View
    {
        return view('admin.imports.index', [
            'imports' => VoterImport::query()->with(['uploader', 'confirmer'])->latest()->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.imports.create', [
            'columns' => array_keys(VoterFileParser::COLUMNS),
            'required' => VoterFileParser::REQUIRED_COLUMNS,
            // PHP rejects an oversized upload before Laravel's own validation ever
            // runs, with no useful error — warn here rather than let an admin hit
            // a confusing "file failed to upload" on a real register.
            'phpUploadLimitKb' => PhpIniSize::effectiveUploadLimitKb(),
        ]);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'wb');
            fputcsv($out, array_keys(VoterFileParser::COLUMNS), ',', '"', '');
            fputcsv($out, ['12345', 'NMC-01234', 'ADEYEMI', 'Tunde', 'Olusegun', 'MALE', '1985-06-15', 'ACI', 'LAGOS COMMAND', 'MMIA', '08031234567', 'officer@example.com', 'ACTIVE'], ',', '"', '');
            fclose($out);
        }, 'nimcos-voter-register-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function store(Request $request): RedirectResponse
    {
        $maxKb = (int) config('nimcos.uploads.import_max_kb');
        $request->validate([
            'file' => ['required', 'file', "max:{$maxKb}", 'extensions:csv,txt,xlsx,xls',
                'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip,application/octet-stream'],
            'update_existing' => ['nullable', 'boolean'],
        ], [
            'file.extensions' => 'Upload a CSV or Excel (.xlsx) file.',
            'file.mimetypes' => 'The file content does not look like a CSV or Excel workbook.',
        ]);

        $import = $this->imports->preview($request->file('file'), $request->user(), $request->boolean('update_existing', true));

        return redirect()->route('admin.imports.show', $import);
    }

    public function show(VoterImport $import): View
    {
        if ($import->status === ImportStatus::PROCESSING) {
            $user = $import->confirmer ?: auth()->user();
            if ($user) {
                try {
                    $this->imports->process($import, $user);
                    $import->refresh();
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        return view('admin.imports.show', [
            'import' => $import->load(['uploader', 'confirmer']),
            'rowErrors' => $import->errors()->limit(200)->get(),
            'errorTotal' => $import->errors()->count(),
            'sample' => $import->status === ImportStatus::PREVIEWED ? $this->imports->sampleRows($import) : [],
        ]);
    }

    public function confirm(Request $request, VoterImport $import): RedirectResponse
    {
        $request->validate(['acknowledge' => ['accepted']], ['acknowledge.accepted' => 'Confirm that you have reviewed the preview.']);
        $this->imports->confirm($import, $request->user());

        try {
            $this->imports->process($import, $request->user());
            $import->refresh();
        } catch (\Throwable $e) {
            report($e);
        }

        $msg = $import->status === ImportStatus::COMPLETED
            ? 'Import completed successfully! '.number_format($import->imported_count).' voter(s) onboarded.'
            : 'Import confirmed and is being processed.';

        return redirect()->route('admin.imports.show', $import)->with('success', $msg);
    }

    public function cancel(VoterImport $import): RedirectResponse
    {
        $this->imports->cancel($import);

        return redirect()->route('admin.imports.index')->with('success', 'Import cancelled. No records were changed.');
    }

    public function errors(VoterImport $import): StreamedResponse
    {
        return response()->streamDownload(function () use ($import) {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['ROW', 'ERROR TYPE', 'SERVICE NUMBER', 'PROBLEMS', ...array_keys(VoterFileParser::COLUMNS)], ',', '"', '');
            foreach ($import->errors()->lazy(500) as $error) {
                $raw = $error->raw;
                $cells = [$error->row_number, $error->error_type, $error->service_number, implode(' ', $error->messages)];
                foreach (VoterFileParser::COLUMNS as $field) {
                    $cells[] = $raw[$field] ?? '';
                }
                fputcsv($out, array_map([ReportRenderer::class, 'safeCell'], $cells), ',', '"', '');
            }
            fclose($out);
        }, 'import-errors-'.substr($import->getKey(), 0, 8).'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function destroy(Request $request, VoterImport $import, VoterFileParser $parser): RedirectResponse
    {
        $deletedVotersCount = 0;

        DB::transaction(function () use ($import, $parser, &$deletedVotersCount) {
            $sns = [];

            // If the original file is still in storage, parse all service numbers from it
            if ($import->stored_path && Storage::disk('local')->exists($import->stored_path)) {
                try {
                    $absolute = Storage::disk('local')->path($import->stored_path);
                    $parsed = $parser->parse($absolute, pathinfo($import->stored_path, PATHINFO_EXTENSION));
                    $sns = array_column($parsed['valid'] ?? [], 'service_number');
                } catch (\Throwable) {
                    // Ignore parsing error and fallback
                }
            }

            // Also include any service numbers captured in error records
            $errorSns = $import->errors()->whereNotNull('service_number')->pluck('service_number')->toArray();
            $allSns = array_values(array_unique(array_filter(array_merge($sns, $errorSns))));

            $voterQuery = Voter::query();
            if (! empty($allSns)) {
                $voterQuery->whereIn('service_number', $allSns);
            } elseif ($import->imported_count > 0) {
                // If file is not on disk, check if this was the only completed import
                if (VoterImport::query()->where('status', ImportStatus::COMPLETED->value)->count() <= 1) {
                    $voterQuery->whereNotNull('id');
                } else {
                    $voterQuery->where(function ($q) use ($import) {
                        $q->where('created_by', $import->uploaded_by)
                            ->orWhere('created_by', $import->confirmed_by);
                    });
                }
            } else {
                $voterQuery->whereRaw('1 = 0');
            }

            $voterIds = $voterQuery->pluck('id');

            if ($voterIds->isNotEmpty()) {
                // Remove election voter records and voting sessions to avoid FK constraint violations
                $evIds = DB::table('election_voters')->whereIn('voter_id', $voterIds)->pluck('id');
                if ($evIds->isNotEmpty()) {
                    DB::table('voting_sessions')->whereIn('election_voter_id', $evIds)->delete();
                    DB::table('election_voters')->whereIn('id', $evIds)->delete();
                }

                $deletedVotersCount = Voter::query()->whereIn('id', $voterIds)->delete();
            }

            // Delete import errors
            $import->errors()->delete();

            // Delete stored file if exists
            if ($import->stored_path && Storage::disk('local')->exists($import->stored_path)) {
                Storage::disk('local')->delete($import->stored_path);
            }

            // Delete the import record
            $import->delete();
        });

        $msg = 'Import record deleted.';
        if ($deletedVotersCount > 0) {
            $msg .= ' '.number_format($deletedVotersCount).' onboarded voter(s) removed from the register.';
        }

        return redirect()->route('admin.imports.index')->with('success', $msg);
    }
}
