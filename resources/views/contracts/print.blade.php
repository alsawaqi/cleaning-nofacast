@php
    $money = fn (int $halalas): string => number_format($halalas / 100, 2);
    $scope = collect($contract->service_scope ?? []);
    $paymentPlan = collect($contract->payment_plan ?? []);
@endphp
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>{{ $contract->reference }}</title>
        <style>
            * { box-sizing: border-box; }
            body { margin: 0; background: #eef2f7; color: #111827; font-family: Arial, sans-serif; }
            .page { width: 210mm; min-height: 297mm; margin: 0 auto; background: #fff; padding: 22mm; }
            .actions { display: flex; justify-content: flex-end; gap: 10px; padding: 18px; }
            .btn { border: 1px solid #cbd5e1; border-radius: 8px; color: #0f172a; font-weight: 700; padding: 10px 14px; text-decoration: none; }
            .btn-primary { background: #1040b6; border-color: #1040b6; color: #fff; }
            .header { display: flex; justify-content: space-between; gap: 24px; border-bottom: 3px solid #1040b6; padding-bottom: 20px; }
            .brand { color: #1040b6; font-size: 28px; font-weight: 800; letter-spacing: 0; }
            .subtitle { margin-top: 6px; color: #64748b; font-size: 13px; font-weight: 700; text-transform: uppercase; }
            .doc-title { margin: 26px 0 10px; font-size: 24px; font-weight: 800; }
            .muted { color: #64748b; font-size: 13px; line-height: 1.6; }
            .badge { display: inline-block; border-radius: 999px; background: #eff6ff; color: #1040b6; font-size: 12px; font-weight: 800; padding: 7px 10px; text-transform: uppercase; }
            .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
            .card { border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; }
            .label { color: #64748b; font-size: 11px; font-weight: 800; text-transform: uppercase; }
            .value { margin-top: 7px; color: #111827; font-size: 14px; font-weight: 700; line-height: 1.5; }
            .section { margin-top: 24px; break-inside: avoid; }
            h2 { margin: 0 0 12px; color: #0f172a; font-size: 17px; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border-bottom: 1px solid #e2e8f0; padding: 10px; text-align: left; vertical-align: top; }
            th { background: #f8fafc; color: #475569; font-size: 12px; text-transform: uppercase; }
            td { font-size: 13px; line-height: 1.55; }
            .terms { border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; color: #334155; font-size: 13px; line-height: 1.75; white-space: pre-line; }
            .signature { border: 1px solid #bfdbfe; border-radius: 8px; background: #eff6ff; padding: 18px; }
            .signature-text { margin-top: 14px; color: #0f172a; font-family: Georgia, serif; font-size: 30px; }
            .footer { margin-top: 34px; border-top: 1px solid #e2e8f0; padding-top: 14px; color: #64748b; font-size: 11px; line-height: 1.6; }
            @media print {
                body { background: #fff; }
                .actions { display: none; }
                .page { width: auto; min-height: auto; padding: 16mm; }
            }
        </style>
    </head>
    <body>
        <div class="actions">
            <button class="btn" type="button" onclick="window.print()">Print</button>
            <a class="btn btn-primary" href="{{ $downloadUrl }}">Download PDF</a>
        </div>

        <main class="page">
            <header class="header">
                <div>
                    <div class="brand">Nofa Clean</div>
                    <div class="subtitle">Cleaning and facility services agreement</div>
                </div>
                <div>
                    <div class="badge">{{ $contract->status }}</div>
                    <p class="muted">
                        Reference: <strong>{{ $contract->reference }}</strong><br>
                        Generated: {{ now()->toDateTimeString() }}
                    </p>
                </div>
            </header>

            <h1 class="doc-title">Service Contract</h1>
            <p class="muted">
                This document summarizes the agreed service scope, commercial terms, and customer acceptance signature for the contract below.
            </p>

            <section class="section grid">
                <div class="card">
                    <div class="label">Customer</div>
                    <div class="value">{{ $contract->customer?->name ?? '-' }}</div>
                    <div class="muted">{{ $contract->customer?->email ?? '-' }} / {{ $contract->customer?->phone ?? '-' }}</div>
                </div>
                <div class="card">
                    <div class="label">Service site</div>
                    <div class="value">{{ $contract->site?->name ?? '-' }}</div>
                    <div class="muted">{{ $contract->site?->district ?? '-' }}, {{ $contract->site?->city ?? '-' }}<br>{{ $contract->site?->address ?? '-' }}</div>
                </div>
            </section>

            <section class="section">
                <h2>Agreement Summary</h2>
                <div class="grid">
                    <div class="card">
                        <div class="label">Service</div>
                        <div class="value">{{ $contract->service?->title ?? '-' }}</div>
                    </div>
                    <div class="card">
                        <div class="label">Package</div>
                        <div class="value">{{ $contract->servicePackage?->name ?? 'Custom agreement' }}</div>
                    </div>
                    <div class="card">
                        <div class="label">Period</div>
                        <div class="value">{{ $contract->starts_on?->toDateString() ?? '-' }} - {{ $contract->ends_on?->toDateString() ?? '-' }}</div>
                    </div>
                    <div class="card">
                        <div class="label">Monthly fee</div>
                        <div class="value">SAR {{ $money($contract->monthly_fee_halalas) }}</div>
                    </div>
                    <div class="card">
                        <div class="label">Workload</div>
                        <div class="value">{{ $contract->agreed_workers }} worker(s), {{ $contract->visits_per_week ?? '-' }} visit(s) weekly, {{ $contract->hours_per_visit ?? '-' }} hour(s) per visit</div>
                    </div>
                    <div class="card">
                        <div class="label">Billing</div>
                        <div class="value">{{ $contract->billing_cycle }} / VAT {{ $contract->vat_rate }}%</div>
                    </div>
                </div>
            </section>

            <section class="section">
                <h2>Payment Plan</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Installment</th>
                            <th>Due day</th>
                            <th>Percent</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($paymentPlan as $installment)
                            <tr>
                                <td>{{ $installment['label'] ?? 'Installment' }}</td>
                                <td>{{ $installment['day'] ?? 1 }}</td>
                                <td>{{ $installment['percent'] ?? 0 }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3">No payment plan recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </section>

            <section class="section">
                <h2>Scope Of Work</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Area</th>
                            <th>Tasks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($scope as $item)
                            <tr>
                                <td>{{ $item['area'] ?? '-' }}</td>
                                <td>{{ $item['tasks'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2">No scope items recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </section>

            <section class="section">
                <h2>Terms</h2>
                <div class="terms">{{ $contract->terms_and_conditions ?: 'No terms recorded.' }}</div>
                @if ($contract->special_terms)
                    <div class="terms" style="margin-top: 12px;">{{ $contract->special_terms }}</div>
                @endif
            </section>

            <section class="section">
                <h2>Customer Acceptance</h2>
                <div class="signature">
                    @if ($signature)
                        <div class="label">Accepted and signed</div>
                        <div class="signature-text">{{ $signature->signature_text }}</div>
                        <p class="muted">
                            Signer: <strong>{{ $signature->signer_name }}</strong><br>
                            Title: {{ $signature->signer_title ?? '-' }}<br>
                            Accepted at: {{ $signature->accepted_at?->toDateTimeString() ?? '-' }}
                        </p>
                        @if ($signature->customer_note)
                            <p class="muted">Customer note: {{ $signature->customer_note }}</p>
                        @endif
                    @else
                        <div class="label">Not signed yet</div>
                        <p class="muted">No customer signature has been recorded for this contract.</p>
                    @endif
                </div>
            </section>

            <footer class="footer">
                Nofa Clean contract document. The signed acceptance block reflects the latest accepted customer decision recorded in the portal.
            </footer>
        </main>
    </body>
</html>
