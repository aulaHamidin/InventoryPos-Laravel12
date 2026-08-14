<?php

namespace App\Jobs;

use App\Enums\PosTransactionStatus;
use App\Models\Item;
use App\Models\PosTransaction;
use App\Models\ReportExport;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Notifications\ReportExportReady;
use App\Services\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

class GenerateReportExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $exportId, public readonly int $tenantId)
    {
        $this->onQueue('exports');
    }

    public function handle(): void
    {
        $tenant = Tenant::findOrFail($this->tenantId);

        TenantContext::run($tenant, function (): void {
            $export = ReportExport::whereKey($this->exportId)->firstOrFail();
            $export->update(['status' => 'processing', 'progress' => 10, 'error' => null]);

            [$title, $headings, $rows] = $this->dataset($export);
            $export->update(['progress' => 65]);

            $extension = $export->format;
            $fileName = "{$export->report_type}-{$export->getKey()}-".now()->format('YmdHis').".{$extension}";
            $path = "report-exports/{$export->tenant_id}/{$fileName}";

            if ($extension === 'pdf') {
                $content = Pdf::loadView('reports.queued-export', compact('title', 'headings', 'rows'))->output();
            } else {
                $spreadsheet = new Spreadsheet;
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->fromArray([$headings], null, 'A1');
                if ($rows !== []) {
                    $sheet->fromArray($rows, null, 'A2');
                }
                $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->getFont()->setBold(true);
                foreach (range('A', $sheet->getHighestColumn()) as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }

                $temporary = tempnam(sys_get_temp_dir(), 'inventori-q-export-');
                (new Xlsx($spreadsheet))->save($temporary);
                $content = file_get_contents($temporary);
                unlink($temporary);
            }

            Storage::disk('local')->put($path, $content);
            $export->update([
                'status' => 'completed',
                'progress' => 100,
                'path' => $path,
                'file_name' => $fileName,
                'completed_at' => now(),
            ]);
            $export->user->notify(new ReportExportReady($export));
        });
    }

    public function failed(Throwable $exception): void
    {
        $tenant = Tenant::find($this->tenantId);
        if ($tenant === null) {
            return;
        }

        TenantContext::run($tenant, fn () => ReportExport::whereKey($this->exportId)->update([
            'status' => 'failed',
            'error' => mb_substr($exception->getMessage(), 0, 1000),
        ]));
    }

    private function dataset(ReportExport $export): array
    {
        $filters = $export->filters ?? [];

        if ($export->report_type === 'stock') {
            $query = Item::with('category')->orderBy('nama')
                ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
                ->when($filters['low_stock'] ?? false, fn ($q) => $q->whereColumn('stok_saat_ini', '<=', 'stok_minimal'));
            $rows = $query->get()->map(fn (Item $item) => [
                $item->kode, $item->nama, $item->category?->nama, $item->satuan,
                $item->stok_saat_ini, $item->stok_minimal, $item->average_cost, $item->harga_jual,
            ])->all();

            return ['Laporan Stok', ['Kode', 'Nama', 'Kategori', 'Satuan', 'Stok', 'Stok Minimal', 'Biaya Rata-rata', 'Harga Jual'], $rows];
        }

        if ($export->report_type === 'movement') {
            $query = StockMovement::with('item')->orderByDesc('created_at')
                ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
                ->when($filters['item_id'] ?? null, fn ($q, $id) => $q->where('item_id', $id))
                ->when($filters['movement_type'] ?? null, fn ($q, $type) => $q->where('movement_type', $type));
            $rows = $query->get()->map(fn (StockMovement $movement) => [
                $movement->created_at->format('Y-m-d H:i:s'), $movement->item?->kode,
                $movement->item?->nama, $movement->movement_type, $movement->direction,
                $movement->qty, $movement->harga_satuan, $movement->note,
            ])->all();

            return ['Laporan Pergerakan Stok', ['Tanggal', 'Kode', 'Item', 'Tipe', 'Arah', 'Qty', 'Harga Satuan', 'Catatan'], $rows];
        }

        $query = PosTransaction::with('cashier')->orderByDesc('created_at')
            ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status));
        $rows = $query->get()->map(fn (PosTransaction $transaction) => [
            $transaction->invoice_number, $transaction->created_at->format('Y-m-d H:i:s'),
            $transaction->cashier?->name, $transaction->subtotal_amount,
            $transaction->discount_amount, $transaction->total_amount,
            $transaction->status instanceof PosTransactionStatus ? $transaction->status->value : $transaction->status,
        ])->all();

        return ['Laporan POS', ['Invoice', 'Tanggal', 'Kasir', 'Bruto', 'Diskon', 'Net', 'Status'], $rows];
    }
}
