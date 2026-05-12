<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: sans-serif;
            font-size: 13px;
            color: #1f2937;
            line-height: 1.5;
        }

        .container {
            padding: 40px 48px;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
        }

        .brand {
            font-size: 24px;
            font-weight: 700;
            color: #6366f1;
        }

        .invoice-meta {
            text-align: right;
        }

        .invoice-number {
            font-size: 20px;
            font-weight: 600;
            color: #111827;
        }

        .invoice-status {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 4px;
        }

        .status-draft {
            background: #f3f4f6;
            color: #6b7280;
        }

        .status-sent {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .status-paid {
            background: #f0fdf4;
            color: #166534;
        }

        .status-overdue {
            background: #fef2f2;
            color: #991b1b;
        }

        /* Parties */
        .parties {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
        }

        .party-label {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #9ca3af;
            margin-bottom: 8px;
        }

        .party-name {
            font-size: 15px;
            font-weight: 600;
            color: #111827;
        }

        .party-detail {
            font-size: 12px;
            color: #6b7280;
            margin-top: 2px;
        }

        /* Dates */
        .dates {
            display: flex;
            gap: 32px;
            margin-bottom: 32px;
            padding: 16px 20px;
            background: #f9fafb;
            border-radius: 8px;
        }

        .date-item {}

        .date-label {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #9ca3af;
        }

        .date-value {
            font-size: 14px;
            font-weight: 500;
            color: #111827;
            margin-top: 2px;
        }

        /* Line items table */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }

        thead th {
            padding: 10px 12px;
            text-align: left;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #9ca3af;
            border-bottom: 2px solid #e5e7eb;
        }

        thead th:last-child {
            text-align: right;
        }

        tbody td {
            padding: 14px 12px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 13px;
            color: #374151;
            vertical-align: top;
        }

        tbody td:last-child {
            text-align: right;
            font-weight: 500;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        /* Totals */
        .totals {
            width: 240px;
            margin-left: auto;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 13px;
            color: #6b7280;
        }

        .total-row.subtotal {
            border-top: 1px solid #e5e7eb;
            padding-top: 12px;
        }

        .total-row.grand {
            border-top: 2px solid #111827;
            padding-top: 12px;
            margin-top: 6px;
            font-size: 16px;
            font-weight: 700;
            color: #111827;
        }

        .total-label {}

        .total-value {
            font-weight: 500;
        }

        /* Notes */
        .notes-section {
            margin-top: 40px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
        }

        .notes-label {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #9ca3af;
            margin-bottom: 8px;
        }

        .notes-text {
            font-size: 12px;
            color: #6b7280;
            line-height: 1.6;
        }

        /* Footer */
        .footer {
            margin-top: 48px;
            text-align: center;
            font-size: 11px;
            color: #d1d5db;
        }
    </style>
</head>

<body>
    <div class="container">

        {{-- Header --}}
        <div class="header">
            <div class="brand">FreelanceFlow</div>
            <div class="invoice-meta">
                <div class="invoice-number">{{ $invoice->number }}</div>
                <div class="invoice-status status-{{ $invoice->status }}">
                    {{ $invoice->status_label }}
                </div>
            </div>
        </div>

        {{-- Parties --}}
        <div class="parties">
            <div>
                <div class="party-label">From</div>
                <div class="party-name">{{ config('app.name') }}</div>
                <div class="party-detail">hello@freelanceflow.test</div>
            </div>
            <div style="text-align: right">
                <div class="party-label">Bill To</div>
                <div class="party-name">{{ $invoice->client->name }}</div>
                @if ($invoice->client->company)
                    <div class="party-detail">{{ $invoice->client->company }}</div>
                @endif
                <div class="party-detail">{{ $invoice->client->email }}</div>
            </div>
        </div>

        {{-- Dates --}}
        <div class="dates">
            <div class="date-item">
                <div class="date-label">Invoice Date</div>
                <div class="date-value">
                    {{ $invoice->issued_at ? $invoice->issued_at->format('M d, Y') : '—' }}
                </div>
            </div>
            <div class="date-item">
                <div class="date-label">Due Date</div>
                <div class="date-value">
                    {{ $invoice->due_at ? $invoice->due_at->format('M d, Y') : '—' }}
                </div>
            </div>
            @if ($invoice->project)
                <div class="date-item">
                    <div class="date-label">Project</div>
                    <div class="date-value">{{ $invoice->project->name }}</div>
                </div>
            @endif
        </div>

        {{-- Line Items --}}
        <table>
            <thead>
                <tr>
                    <th style="width: 50%">Description</th>
                    <th style="width: 15%; text-align: right">Qty</th>
                    <th style="width: 20%; text-align: right">Rate</th>
                    <th style="width: 15%; text-align: right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->line_items as $item)
                    <tr>
                        <td>{{ $item['description'] }}</td>
                        <td style="text-align: right">{{ $item['quantity'] }}</td>
                        <td style="text-align: right">₹{{ number_format($item['rate'], 2) }}</td>
                        <td>₹{{ number_format($item['quantity'] * $item['rate'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Totals --}}
        <div class="totals">
            <div class="total-row subtotal">
                <span class="total-label">Subtotal</span>
                <span class="total-value">{{ $invoice->formatted_subtotal }}</span>
            </div>
            @if ($invoice->tax_rate > 0)
                <div class="total-row">
                    <span class="total-label">GST ({{ $invoice->tax_rate }}%)</span>
                    <span class="total-value">{{ $invoice->formatted_tax_amount }}</span>
                </div>
            @endif
            <div class="total-row grand">
                <span class="total-label">Total Due</span>
                <span class="total-value">{{ $invoice->formatted_total }}</span>
            </div>
        </div>

        {{-- Notes --}}
        @if ($invoice->notes)
            <div class="notes-section">
                <div class="notes-label">Notes</div>
                <div class="notes-text">{{ $invoice->notes }}</div>
            </div>
        @endif

        {{-- Footer --}}
        <div class="footer">
            Thank you for your business · FreelanceFlow · {{ config('app.url') }}
        </div>

    </div>
</body>

</html>