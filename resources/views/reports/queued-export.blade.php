<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; }
        h1 { color: #4338ca; font-size: 18px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 5px; text-align: left; }
        th { background: #4f46e5; color: #fff; }
        tr:nth-child(even) { background: #f9fafb; }
    </style>
</head>
<body>
<h1>{{ $title }}</h1>
<p>Dibuat {{ now()->format('Y-m-d H:i') }}</p>
@if (!empty($summaries))
    <h2>Ringkasan operasional per metode</h2>
    <table style="margin-bottom: 16px">
        <thead><tr><th>Metode</th><th>Jumlah Payment</th><th>Total Payment</th><th>Refund Tercatat</th><th>Net Operasional</th></tr></thead>
        <tbody>
        @foreach ($summaries as $summary)
            <tr>@foreach ($summary as $value)<td>{{ $value }}</td>@endforeach</tr>
        @endforeach
        </tbody>
    </table>
@endif
<table>
    <thead><tr>@foreach ($headings as $heading)<th>{{ $heading }}</th>@endforeach</tr></thead>
    <tbody>
    @forelse ($rows as $row)
        <tr>@foreach ($row as $value)<td>{{ $value }}</td>@endforeach</tr>
    @empty
        <tr><td colspan="{{ count($headings) }}">Tidak ada data.</td></tr>
    @endforelse
    </tbody>
</table>
</body>
</html>
