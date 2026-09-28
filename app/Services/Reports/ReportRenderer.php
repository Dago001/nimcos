<?php

namespace App\Services\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportRenderer
{
    public function render(array $report, string $format, string $filename): Response
    {
        return match ($format) {
            'csv' => $this->csv($report, $filename.'.csv'),
            'xlsx' => $this->xlsx($report, $filename.'.xlsx'),
            'pdf' => $this->pdf($report, $filename.'.pdf'),
            default => abort(404),
        };
    }

    /**
     * Neutralise spreadsheet formula injection (CSV/Excel "DDE" attacks): a cell that
     * begins with = + - @ or a control character is prefixed with an apostrophe.
     */
    public static function safeCell(mixed $value): string|int|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        $value = (string) $value;
        if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value) && ! is_numeric($value)) {
            return "'".$value;
        }

        return $value;
    }

    private function csv(array $report, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel detects encoding
            fputcsv($out, [$report['title'].' — '.$report['subtitle']], ',', '"', '');
            fputcsv($out, ['Generated', display_time(now(), 'j M Y H:i').' WAT'], ',', '"', '');
            foreach ($report['summary'] as $k => $v) {
                fputcsv($out, [$k, self::safeCell($v)], ',', '"', '');
            }
            fputcsv($out, [], ',', '"', '');
            fputcsv($out, $report['headings'], ',', '"', '');
            foreach ($report['rows'] as $row) {
                fputcsv($out, array_map([self::class, 'safeCell'], $row), ',', '"', '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function xlsx(array $report, string $filename): StreamedResponse
    {
        $book = new Spreadsheet;
        $book->getProperties()->setCreator('NIMCOS E-VOTING')->setTitle($report['title']);
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Report');

        $r = 1;
        $sheet->setCellValueExplicit("A{$r}", $report['title'].' — '.$report['subtitle'], DataType::TYPE_STRING);
        $sheet->getStyle("A{$r}")->getFont()->setBold(true)->setSize(13);
        $r++;
        $sheet->setCellValueExplicit("A{$r}", 'Generated '.display_time(now(), 'j M Y H:i').' WAT', DataType::TYPE_STRING);
        $r++;
        foreach ($report['summary'] as $k => $v) {
            $sheet->setCellValueExplicit("A{$r}", (string) $k, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("B{$r}", (string) $v, DataType::TYPE_STRING);
            $r++;
        }
        $r++;
        foreach (array_values($report['headings']) as $i => $heading) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValueExplicit("{$col}{$r}", $heading, DataType::TYPE_STRING);
            $sheet->getStyle("{$col}{$r}")->getFont()->setBold(true);
        }
        foreach ($report['rows'] as $row) {
            $r++;
            foreach (array_values($row) as $i => $value) {
                $col = Coordinate::stringFromColumnIndex($i + 1);
                // Explicit types: text is never interpreted as a formula.
                if (is_int($value) || is_float($value)) {
                    $sheet->setCellValueExplicit("{$col}{$r}", $value, DataType::TYPE_NUMERIC);
                } else {
                    $sheet->setCellValueExplicit("{$col}{$r}", (string) $value, DataType::TYPE_STRING);
                }
            }
        }
        foreach (range(1, count($report['headings'])) as $i) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function pdf(array $report, string $filename): Response
    {
        $report['rows'] = is_array($report['rows']) ? $report['rows'] : iterator_to_array($report['rows'], false);
        $logo = public_path('images/nimcos-seal.jpg');

        return Pdf::loadView('reports.pdf', [
            'report' => $report,
            'logo' => is_file($logo) ? 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($logo)) : null,
        ])->setPaper('a4', count($report['headings']) > 5 ? 'landscape' : 'portrait')->download($filename);
    }
}
