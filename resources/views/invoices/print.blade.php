<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>{{ $invoice->number }}</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; color: #111827; }
            .header { display: flex; justify-content: space-between; border-bottom: 2px solid #0f766e; padding-bottom: 18px; }
            .brand { font-size: 26px; font-weight: 700; color: #0f766e; }
            .muted { color: #6b7280; font-size: 13px; }
            table { width: 100%; border-collapse: collapse; margin-top: 28px; }
            th, td { border-bottom: 1px solid #e5e7eb; padding: 12px; text-align: left; }
            th { background: #f8fafc; }
            .total { font-weight: 700; font-size: 18px; }
            .qr { margin-top: 28px; word-break: break-all; font-size: 11px; color: #475569; }
        </style>
    </head>
    <body>
        <div class="header">
            <div>
                <div class="brand">Nofacast Clean</div>
                <div class="muted">Saudi VAT invoice / فاتورة ضريبية</div>
            </div>
            <div>
                <strong>{{ $invoice->number }}</strong><br>
                <span class="muted">Issue: {{ $invoice->issue_date->toDateString() }}</span><br>
                <span class="muted">Due: {{ $invoice->due_date->toDateString() }}</span>
            </div>
        </div>

        <p><strong>Customer:</strong> {{ $invoice->customer->name }}</p>
        <p><strong>Contract:</strong> {{ $invoice->contract?->reference ?? 'One-time service' }}</p>

        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Cleaning and facility services</td>
                    <td>SAR {{ number_format($invoice->net_total_halalas / 100, 2) }}</td>
                </tr>
                <tr>
                    <td>VAT {{ $invoice->vat_rate }}%</td>
                    <td>SAR {{ number_format($invoice->vat_total_halalas / 100, 2) }}</td>
                </tr>
                <tr class="total">
                    <td>Total</td>
                    <td>SAR {{ number_format($invoice->gross_total_halalas / 100, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="qr">
            <strong>ZATCA QR payload:</strong> {{ $invoice->zatca_qr }}
        </div>
    </body>
</html>
