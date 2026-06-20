<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractDecision;

class ContractPdfRenderer
{
    public function render(Contract $contract, ?ContractDecision $signature): string
    {
        $pages = array_chunk($this->documentLines($contract, $signature), 50);
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $kids = [];
        $objectNumber = 4;

        foreach ($pages as $pageLines) {
            $pageObject = $objectNumber++;
            $contentObject = $objectNumber++;
            $kids[] = "{$pageObject} 0 R";
            $stream = $this->contentStream($pageLines);

            $objects[$pageObject] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents {$contentObject} 0 R >>";
            $objects[$contentObject] = '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream";
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';
        ksort($objects);

        return $this->buildPdf($objects);
    }

    /**
     * @return list<string>
     */
    private function documentLines(Contract $contract, ?ContractDecision $signature): array
    {
        $lines = [
            'Nofa Clean Contract Agreement',
            'Reference: '.$contract->reference,
            'Status: '.$contract->status,
            'Generated: '.now()->toDateTimeString(),
            '',
            'Customer',
            'Name: '.($contract->customer?->name ?? '-'),
            'Email: '.($contract->customer?->email ?? '-'),
            'Phone: '.($contract->customer?->phone ?? '-'),
            '',
            'Service Site',
            'Site: '.($contract->site?->name ?? '-'),
            'City: '.($contract->site?->city ?? '-'),
            'District: '.($contract->site?->district ?? '-'),
            'Address: '.($contract->site?->address ?? '-'),
            '',
            'Agreement Summary',
            'Service: '.($contract->service?->title ?? '-'),
            'Package: '.($contract->servicePackage?->name ?? 'Custom agreement'),
            'Period: '.($contract->starts_on?->toDateString() ?? '-').' to '.($contract->ends_on?->toDateString() ?? '-'),
            'Monthly fee: SAR '.$this->money($contract->monthly_fee_halalas),
            'VAT: '.$contract->vat_rate.'%',
            'Billing cycle: '.$contract->billing_cycle,
            'Workers: '.$contract->agreed_workers,
            'Visits per week: '.($contract->visits_per_week ?? '-'),
            'Hours per visit: '.($contract->hours_per_visit ?? '-'),
            'Materials: '.($contract->included_materials ? 'Included' : 'Not included'),
            'Extra hour rate: SAR '.$this->money((int) $contract->extra_hour_rate_halalas),
            'Notice days: '.$contract->notice_days,
            'Auto renews: '.($contract->auto_renews ? 'Yes' : 'No'),
            '',
            'Payment Plan',
        ];

        foreach ($contract->payment_plan ?? [] as $item) {
            $lines[] = sprintf(
                '%s - day %s - %s%%',
                $item['label'] ?? 'Installment',
                $item['day'] ?? 1,
                $item['percent'] ?? 0,
            );
        }

        $lines[] = '';
        $lines[] = 'Scope';

        foreach ($contract->service_scope ?? [] as $item) {
            $lines = [...$lines, ...$this->wrapped(($item['area'] ?? 'Area').': '.($item['tasks'] ?? '-'))];
        }

        $lines[] = '';
        $lines[] = 'Terms';
        $lines = [...$lines, ...$this->wrapped($contract->terms_and_conditions ?: 'No terms recorded.')];

        if ($contract->special_terms) {
            $lines[] = '';
            $lines[] = 'Special Terms';
            $lines = [...$lines, ...$this->wrapped($contract->special_terms)];
        }

        $lines[] = '';
        $lines[] = 'Customer Acceptance';

        if ($signature) {
            $lines[] = 'Decision: Accepted and signed';
            $lines[] = 'Signer: '.($signature->signer_name ?? '-');
            $lines[] = 'Title: '.($signature->signer_title ?? '-');
            $lines[] = 'Typed signature: '.($signature->signature_text ?? '-');
            $lines[] = 'Accepted at: '.($signature->accepted_at?->toDateTimeString() ?? '-');

            if ($signature->customer_note) {
                $lines = [...$lines, ...$this->wrapped('Customer note: '.$signature->customer_note)];
            }
        } else {
            $lines[] = 'No customer signature has been recorded yet.';
        }

        return array_map(fn (string $line): string => $this->ascii($line), $lines);
    }

    /**
     * @return list<string>
     */
    private function wrapped(string $text): array
    {
        return explode("\n", wordwrap($this->ascii($text), 88, "\n", true));
    }

    private function contentStream(array $lines): string
    {
        $commands = ['BT', '/F1 11 Tf', '50 790 Td'];

        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $commands[] = '0 -15 Td';
            }

            $commands[] = '('.$this->escape($line).') Tj';
        }

        $commands[] = 'ET';

        return implode("\n", $commands);
    }

    /**
     * @param  array<int, string>  $objects
     */
    private function buildPdf(array $objects): string
    {
        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];
        $maxObject = max(array_keys($objects));

        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= "{$number} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".($maxObject + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($number = 1; $number <= $maxObject; $number++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$number] ?? 0);
        }

        return $pdf."trailer\n<< /Size ".($maxObject + 1)." /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }

    private function ascii(string $value): string
    {
        return (string) preg_replace('/[^\x20-\x7E]/', '?', trim(strip_tags($value)));
    }

    private function money(int $halalas): string
    {
        return number_format($halalas / 100, 2);
    }
}
