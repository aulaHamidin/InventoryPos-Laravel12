<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Stok Barang</title>
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
            color: #111827; /* Gray 900 */
            margin: 0 0 5px 0;
            font-size: 24px;
        }
        .header p {
            color: #6B7280; /* Gray 500 */
            margin: 0;
            font-size: 14px;
        }
        .summary-box {
            background-color: #EEF2FF; /* Indigo 50 */
            border-left: 4px solid #4F46E5;
            padding: 15px;
            margin-bottom: 20px;
        }
        .summary-title {
            font-size: 12px;
            text-transform: uppercase;
            color: #4338CA; /* Indigo 700 */
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
            border-bottom: 1px solid #E5E7EB; /* Gray 200 */
        }
        th {
            background-color: #F9FAFB; /* Gray 50 */
            color: #374151; /* Gray 700 */
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
        .text-center {
            text-align: center;
        }
        .danger {
            color: #DC2626; /* Red 600 */
            font-weight: bold;
        }
        .success {
            color: #059669; /* Emerald 600 */
        }
        .footer {
            margin-top: 30px;
            font-size: 10px;
            color: #9CA3AF;
            text-align: center;
            border-top: 1px solid #E5E7EB;
            padding-top: 10px;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>Laporan Stok Barang</h1>
        <p>Tenant: {{ $tenant->nama_toko }} | Dicetak pada: {{ now()->format('d M Y H:i') }}</p>
    </div>

    <div class="summary-box">
        <table style="border: none; width: 100%;">
            <tr style="background: none;">
                <td style="border: none; padding: 0;">
                    <div class="summary-title">Total Jenis Barang</div>
                    <div class="summary-value">{{ $items->count() }} Item</div>
                </td>
                <td style="border: none; padding: 0;" class="text-right">
                    <div class="summary-title">Total Nilai Aset (Estimasi HPP)</div>
                    <div class="summary-value">Rp {{ number_format($totalAssetValue, 0, ',', '.') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th>Kode</th>
                <th>Nama Barang</th>
                <th>Kategori</th>
                <th class="text-center">Satuan</th>
                <th class="text-right">Harga Jual</th>
                <th class="text-right">Stok Aktual</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
            <tr>
                <td>{{ $item->kode }}</td>
                <td><strong>{{ $item->nama }}</strong></td>
                <td>{{ $item->category?->nama ?? '-' }}</td>
                <td class="text-center">{{ $item->satuan }}</td>
                <td class="text-right">Rp {{ number_format($item->harga_jual, 0, ',', '.') }}</td>
                <td class="text-right">
                    <span class="{{ $item->stok_saat_ini <= $item->stok_minimal ? 'danger' : '' }}">
                        {{ $item->stok_saat_ini }}
                    </span>
                </td>
                <td>
                    @if($item->stok_saat_ini <= $item->stok_minimal)
                        <span class="danger">Stok Rendah</span>
                    @else
                        <span class="success">Aman</span>
                    @endif
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
