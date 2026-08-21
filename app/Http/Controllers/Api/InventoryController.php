<?php

namespace App\Http\Controllers\Api;

use App\Actions\Analytics\ApplySmartThresholdAction;
use App\Actions\Inventory\AdjustStockAction;
use App\Actions\Inventory\StockInAction;
use App\Actions\Inventory\StockOutAction;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Item;
use App\Models\StockMovement;
use App\Support\AuditContext;
use App\Support\OwnershipGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Item::class);
        $query = Item::query()->with(['category', 'rack']);

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(fn ($q) => $q->where('nama', 'like', "%{$search}%")
                ->orWhere('kode', 'like', "%{$search}%")
                ->orWhere('barcode', 'like', "%{$search}%"));
        }
        if ($request->filled('category_id')) {
            OwnershipGuard::validate(Category::class, (int) $request->integer('category_id'));
            $query->where('category_id', $request->integer('category_id'));
        }

        return $this->success($query->paginate(min(100, max(1, $request->integer('per_page', 15)))));
    }

    public function scan(Request $request, string $barcode): JsonResponse
    {
        $this->authorize('viewAny', Item::class);

        return $this->success(Item::where('barcode', $barcode)->where('is_active', true)->firstOrFail());
    }

    public function applySmartThreshold(
        Request $request,
        int $id,
        ApplySmartThresholdAction $action,
    ): JsonResponse {
        $item = OwnershipGuard::validate(Item::class, $id);
        $this->authorize('update', $item);

        $unexpected = array_values(array_diff(
            array_keys($request->all()),
            ['threshold_mode', 'lead_time_days', 'safety_stock_days'],
        ));
        if ($unexpected !== []) {
            throw ValidationException::withMessages([
                'request' => ['Field tidak diizinkan: '.implode(', ', $unexpected).'.'],
            ]);
        }

        $data = $request->validate([
            'threshold_mode' => ['required', 'in:auto_velocity'],
            'lead_time_days' => ['required', 'integer', 'min:0'],
            'safety_stock_days' => ['required', 'integer', 'min:0'],
        ]);

        $result = $action->execute(
            $id,
            (int) $data['lead_time_days'],
            (int) $data['safety_stock_days'],
            $request->user(),
            AuditContext::fromRequest($request),
        );

        return $this->success(
            $result->toApiArray($id, 'auto_velocity'),
            'Smart Threshold berhasil diterapkan.',
        );
    }

    public function stockIn(Request $request, StockInAction $action): JsonResponse
    {
        $this->authorize('create', StockMovement::class);
        $data = $request->validate([
            'item_id' => ['required', 'integer'], 'qty' => ['required', 'integer', 'min:1'],
            'harga_satuan' => ['required', 'decimal:0,2', 'min:0'], 'supplier_id' => ['nullable', 'integer'],
            'reference_type' => ['nullable', 'string', 'max:100'], 'reference_id' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $movement = $action->execute(
            (int) $data['item_id'], (int) $data['qty'], $data['harga_satuan'], $request->user(),
            isset($data['supplier_id']) ? (int) $data['supplier_id'] : null,
            $data['reference_type'] ?? null, $data['reference_id'] ?? null, $data['note'] ?? null,
            AuditContext::fromRequest($request),
        );

        return $this->success($movement, 'Stok masuk berhasil dicatat.', 201);
    }

    public function stockOut(Request $request, StockOutAction $action): JsonResponse
    {
        $this->authorize('create', StockMovement::class);
        $data = $request->validate([
            'item_id' => ['required', 'integer'], 'qty' => ['required', 'integer', 'min:1'],
            'movement_type' => ['nullable', 'string'], 'reference_type' => ['nullable', 'string', 'max:100'],
            'reference_id' => ['nullable', 'integer'], 'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $movement = $action->execute(
            (int) $data['item_id'], (int) $data['qty'], $request->user(),
            $data['movement_type'] ?? 'stock_out', $data['reference_type'] ?? null,
            $data['reference_id'] ?? null, $data['note'] ?? null, AuditContext::fromRequest($request),
        );

        return $this->success($movement, 'Stok keluar berhasil dicatat.', 201);
    }

    public function adjustStock(Request $request, AdjustStockAction $action): JsonResponse
    {
        $this->authorize('create', StockMovement::class);
        $data = $request->validate([
            'item_id' => ['required', 'integer'], 'qty' => ['required', 'integer', 'min:1'],
            'direction' => ['required', 'in:in,out'], 'note' => ['required', 'string', 'max:1000'],
        ]);

        return $this->success(
            $action->execute((int) $data['item_id'], (int) $data['qty'], $data['direction'], $data['note'], $request->user(), AuditContext::fromRequest($request)),
            'Penyesuaian stok berhasil dicatat.',
            201,
        );
    }
}
