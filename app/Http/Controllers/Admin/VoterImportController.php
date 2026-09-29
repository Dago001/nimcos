<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ImportStatus;
use App\Http\Controllers\Controller;
use App\Models\VoterImport;
use App\Services\Reports\ReportRenderer;
use App\Services\Voters\VoterFileParser;
use App\Services\Voters\VoterImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        return view('admin.imports.create', ['columns' => array_keys(VoterFileParser::COLUMNS), 'required' => VoterFileParser::REQUIRED_COLUMNS]);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'wb');
            fputcsv($out, array_keys(VoterFileParser::COLUMNS), ',', '"', '');
            fputcsv($out, ['12345', 'ADEYEMI', 'Tunde', 'Olusegun', 'ACI', 'LAGOS COMMAND', 'MMIA', '08031234567', 'officer@example.com', 'ACTIVE'], ',', '"', '');
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

        return redirect()->route('admin.imports.show', $import)->with('success', 'Import confirmed and is being processed. This page shows the final totals when it completes.');
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
}
