<?php

namespace App\Filament\Resources\StockOpnameResource\Pages;

use App\Actions\Opname\FinalizeOpnameAction;
use App\Actions\Opname\SaveOpnameCountAction;
use App\Enums\StockOpnameStatus;
use App\Exceptions\ApiProblemException;
use App\Filament\Resources\StockOpnameResource;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockOpnameDetail;
use App\Support\AuditContext;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CountStockOpname extends ViewRecord
{
    protected static string $resource = StockOpnameResource::class;

    protected static string $view = 'filament.resources.stock-opname-resource.pages.count-stock-opname';

    protected static bool $shouldRegisterNavigation = false;

    public ?int $currentDetailId = null;

    public string $searchQuery = '';

    public array $searchResults = [];

    public string $qtyFisik = '';

    public string $note = '';

    public bool $showReview = false;

    public bool $showFinalizeConfirmation = false;

    public ?array $completionSummary = null;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->authorizeAccess();
        $this->refreshRecord();

        if ($this->record->status === StockOpnameStatus::Completed) {
            $this->showReview = true;
            $this->completionSummary = $this->buildCompletionSummary();
        } else {
            $this->selectNextUncounted();
        }
    }

    public function getTitle(): string
    {
        return "Stock Opname #{$this->record->getKey()}";
    }

    public function hydrate(): void
    {
        parent::hydrate();
        $this->refreshRecord();
    }

    public function updatedSearchQuery(): void
    {
        $search = trim($this->searchQuery);
        if (mb_strlen($search) < 2) {
            $this->searchResults = [];

            return;
        }

        $this->searchResults = $this->record->details()
            ->with('item')
            ->whereHas('item', fn ($query) => $query->withTrashed()->where(fn ($itemQuery) => $itemQuery
                ->where('nama', 'like', "%{$search}%")
                ->orWhere('kode', 'like', "%{$search}%")
                ->orWhere('barcode', 'like', "%{$search}%")))
            ->limit(10)
            ->get()
            ->map(fn (StockOpnameDetail $detail): array => $this->detailSummary($detail))
            ->all();
    }

    public function handleBarcode(string $barcode): void
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return;
        }

        $detail = $this->record->details()
            ->whereHas('item', fn ($query) => $query->withTrashed()->where('barcode', $barcode))
            ->first();
        if ($detail === null) {
            Notification::make()->title('Barcode tidak termasuk scope opname')->danger()->send();

            return;
        }

        $this->selectDetail((int) $detail->getKey());
    }

    public function selectDetail(int $detailId): void
    {
        $detail = $this->record->details()->with('item')->whereKey($detailId)->firstOrFail();
        $this->currentDetailId = (int) $detail->getKey();
        $this->qtyFisik = $detail->qty_fisik === null ? '' : (string) $detail->qty_fisik;
        $this->note = $detail->note ?? '';
        $this->searchQuery = '';
        $this->searchResults = [];
        $this->showReview = false;
    }

    public function saveAndNext(): void
    {
        Gate::authorize('update', $this->record);
        if ($this->currentDetailId === null) {
            throw ValidationException::withMessages(['qtyFisik' => ['Pilih item terlebih dahulu.']]);
        }
        $data = $this->validate([
            'qtyFisik' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $detail = $this->record->details()->whereKey($this->currentDetailId)->firstOrFail();
        try {
            app(SaveOpnameCountAction::class)->execute((int) $this->record->getKey(), [[
                'item_id' => $detail->item_id,
                'qty_fisik' => (int) $data['qtyFisik'],
                'note' => $data['note'] ?: null,
            ]], auth()->user(), AuditContext::fromRequest(request()));
        } catch (ApiProblemException $exception) {
            Notification::make()->title('Hitungan tidak dapat disimpan')->body($exception->getMessage())->danger()->send();
            $this->refreshRecord();

            return;
        }

        Notification::make()->title('Hitungan disimpan')->success()->send();
        $this->refreshRecord();
        $this->selectNextUncounted();
    }

    public function openReview(): void
    {
        $this->showReview = true;
        $this->currentDetailId = null;
    }

    public function openFinalizeConfirmation(): void
    {
        if (! $this->isComplete()) {
            Notification::make()->title('Masih ada item yang belum dihitung')->danger()->send();

            return;
        }
        $this->showFinalizeConfirmation = true;
    }

    public function finalize(): void
    {
        Gate::authorize('update', $this->record);
        try {
            $result = app(FinalizeOpnameAction::class)->execute(
                (int) $this->record->getKey(), auth()->user(), AuditContext::fromRequest(request()),
            );
        } catch (ApiProblemException $exception) {
            Notification::make()->title('Opname tidak dapat difinalisasi')->body($exception->getMessage())->danger()->send();
            $this->showFinalizeConfirmation = false;
            $this->refreshRecord();

            return;
        }
        $this->record = $result['opname'];
        $this->completionSummary = $result['summary'];
        $this->showFinalizeConfirmation = false;
        $this->showReview = true;
        Notification::make()->title('Stock opname selesai')->success()->send();
        $this->refreshRecord();
    }

    public function isComplete(): bool
    {
        return $this->record->details()->exists()
            && ! $this->record->details()->whereNull('counted_at')->exists();
    }

    public function currentDetail(): ?StockOpnameDetail
    {
        return $this->currentDetailId === null
            ? null
            : $this->record->details()->with('item')->whereKey($this->currentDetailId)->first();
    }

    public function reviewDetails()
    {
        return $this->record->details()->with('item')->orderBy('item_id')->get();
    }

    public function selectNextUncounted(): void
    {
        $next = $this->record->details()->whereNull('counted_at')->orderBy('item_id')->first();
        if ($next === null) {
            $this->openReview();

            return;
        }
        $this->selectDetail((int) $next->getKey());
    }

    private function refreshRecord(): void
    {
        $this->record = StockOpname::whereKey($this->record->getKey())
            ->with(['rack', 'creator'])
            ->withCount([
                'details',
                'details as counted_details_count' => fn ($query) => $query->whereNotNull('counted_at'),
            ])
            ->firstOrFail();
    }

    private function detailSummary(StockOpnameDetail $detail): array
    {
        return [
            'id' => $detail->getKey(),
            'nama' => $detail->item?->nama,
            'kode' => $detail->item?->kode,
            'barcode' => $detail->item?->barcode,
            'counted' => $detail->counted_at !== null,
        ];
    }

    private function buildCompletionSummary(): array
    {
        $movements = StockMovement::where('reference_type', StockOpname::class)
            ->where('reference_id', $this->record->getKey())
            ->get();

        return [
            'item_count' => $this->record->details_count,
            'adjusted_lines' => $movements->count(),
            'unchanged_lines' => $this->record->details_count - $movements->count(),
            'total_units_in' => $movements->where('direction', 'in')->sum('qty'),
            'total_units_out' => $movements->where('direction', 'out')->sum('qty'),
        ];
    }
}
