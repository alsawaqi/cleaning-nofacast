<?php

namespace App\Http\Controllers;

use App\Services\Reports\AdminReportBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Response as ResponseFactory;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function index(Request $request, AdminReportBuilder $reports): Response
    {
        [$from, $to] = $this->range($request, $reports);
        $data = $reports->build($from, $to);

        return Inertia::render('Reports/Index', [
            'filters' => [
                'from' => $from,
                'to' => $to,
            ],
            'summary' => $data['summary'],
            'finance' => $data['finance'],
            'operations' => $data['operations'],
            'workerPerformance' => $data['workerPerformance'],
            'contractRevenue' => $data['contractRevenue'],
            'profitability' => $data['profitability'],
            'exportLinks' => [
                'finance' => route('reports.export', ['report' => 'finance', 'from' => $from, 'to' => $to], false),
                'operations' => route('reports.export', ['report' => 'operations', 'from' => $from, 'to' => $to], false),
                'workers' => route('reports.export', ['report' => 'workers', 'from' => $from, 'to' => $to], false),
                'contracts' => route('reports.export', ['report' => 'contracts', 'from' => $from, 'to' => $to], false),
                'profitability' => route('reports.export', ['report' => 'profitability', 'from' => $from, 'to' => $to], false),
            ],
        ]);
    }

    public function export(Request $request, string $report, AdminReportBuilder $reports): StreamedResponse
    {
        abort_unless(in_array($report, ['finance', 'operations', 'workers', 'contracts', 'profitability'], true), HttpResponse::HTTP_NOT_FOUND);

        [$from, $to] = $this->range($request, $reports);
        $rows = $reports->csvRows($report, $from, $to);

        return ResponseFactory::streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');

            foreach ($rows as $row) {
                fwrite($output, $this->csvLine($row).PHP_EOL);
            }

            fclose($output);
        }, "{$report}-report.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function range(Request $request, AdminReportBuilder $reports): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return $reports->normalizeRange($validated['from'] ?? null, $validated['to'] ?? null);
    }

    /**
     * @param  array<int, string>  $row
     */
    private function csvLine(array $row): string
    {
        return collect($row)
            ->map(fn (string $value): string => $this->csvCell($value))
            ->implode(',');
    }

    private function csvCell(string $value): string
    {
        if (! str_contains($value, ',') && ! str_contains($value, '"') && ! str_contains($value, "\n") && ! str_contains($value, "\r")) {
            return $value;
        }

        return '"'.str_replace('"', '""', $value).'"';
    }
}
