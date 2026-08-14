<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Transaksi POS</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #4F46E5; /* Indigo 600 */
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #111827;
            margin: 0 0 5px 0;
            font-size: 24px;
        }
        .header p {
            color: #6B7280;
            margin: 0;
            font-size: 14px;
        }
        .summary-box {
            background-color: #EEF2FF;
            border-left: 4px solid #4F46E5;
            padding: 15px;
            margin-bottom: 20px;
        }
        .summary-title {
            font-size: 12px;
            text-transform: uppercase;
            color: #4338CA;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .summary-value {
            font-size: 20px;
            font-weight: bold;
            color: #111827;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        th, td {
            padding: 10px 8px;
            text-align: left;
            border-bottom: 1px solid #E5E7EB;
        }
        th {
            background-color: #F9FAFB;
            color: #374151;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 10px;
        }
        tr:nth-child(even) {
            background-color: #F9FAFB;
        }
        .text-right {
            text-align: right;
        }
        .footer {
            margin-top: 30px;
            font-size: 10px;
            color: #9CA3AF;
            text-align: center;
            border-top: 1px solid #E5E7EB;
            padding-top: 10px;
        }
        .badge {
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
        }
        .badge-success { background-color: #D1FAE5; color: #065F46; }
        .badge-danger { background-color: #FEE2E2; color: #991B1B; }
        .badge-warning { background-color: #FEF3C7; color: #92400E; }
    </style>
</head>
<body>

    <div class="header">
        <h1>Laporan Transaksi POS</h1>
        <p>Tenant: {{ $tenant->nama_toko }} | Dicetak pada: {{ now()->format('d M Y H:i') }}</p>
    </div>

    <div class="summary-box">
        <table style="border: none; width: 100%;">
            <tr style="background: none;">
                <td style="border: none; padding: 0;">
                    <div class="summary-title">Total Transaksi</div>
                    <div class="summary-value">{{ $transactions->count() }}</div>
                </td>
                <td style="border: none; padding: 0;" class="text-right">
                    <div class="summary-title">Total Pendapatan (Completed)</div>
                    <div class="summary-value">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th>No. Invoice</th>
                <th>Tanggal</th>
                <th>Kasir</th>
                <th class="text-right">Diskon</th>
                <th class="text-right">Total</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $trx)
            <tr>
                <td><strong>{{ $trx->invoice_number }}</strong></td>
                <td>{{ $trx->created_at->format('d M Y H:i') }}</td>
                <td>{{ $trx->cashier?->name ?? '-' }}</td>
                <td class="text-right">Rp {{ number_format($trx->discount_amount, 0, ',', '.') }}</td>
                <td class="text-right"><strong>Rp {{ number_format($trx->total_amount, 0, ',', '.') }}</strong></td>
                <td>
                    @php
                        $badgeClass = match($trx->status->value) {
                            'completed' => 'badge-success',
                            'failed', 'voided' => 'badge-danger',
                            default => 'badge-warning',
                        };
                    @endphp
                    <span class="badge {{ $badgeClass }}">{{ $trx->status->label() }}</span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Dihasilkan oleh Inventori-Q System &copy; {{ date('Y') }}
    </div>

</body>
</html>
