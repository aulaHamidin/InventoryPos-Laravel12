<?php

namespace App\Http\Controllers\Api;

use App\Actions\Inventory\DeleteItemSupplierAction;
use App\Actions\Inventory\SetPreferredSupplierAction;
use App\Actions\Inventory\UpsertItemSupplierAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemSupplier;
use App\Support\AuditContext;
use App\Support\OwnershipGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemSupplierController extends Controller
{
    public function index(Request $request, int $item): JsonResponse
    {
        $model = OwnershipGuard::validate(Item::class, $item);
        $this->authorize('view', $model);

        $links = $model->itemSupplierLinks()->with('supplier')->orderByDesc('is_preferred')->get();
        if ($request->user()->role === UserRole::Staff) {
            return $this->success($links->map(fn (ItemSupplier $link): array => [
                'supplier_sku' => $link->supplier_sku,
                'lead_time_days' => $link->lead_time_days,
                'is_preferred' => $link->is_preferred,
                'supplier' => $link->supplier ? [
                    'id' => $link->supplier->id,
                    'nama' => $link->supplier->nama,
                    'kontak' => $link->supplier->kontak,
                    'alamat' => $link->supplier->alamat,
                ] : null,
            ])->values());
        }

        return $this->success($links);
    }

    public function store(Request $request, int $item, UpsertItemSupplierAction $action): JsonResponse
    {
        $model = OwnershipGuard::validate(Item::class, $item);
        $this->authorize('update', $model);
        $data = $this->validated($request, true);

        return $this->success(
            $action->execute($item, (int) $data['supplier_id'], $data, $request->user(), AuditContext::fromRequest($request)),
            'Supplier item tersimpan.',
            201,
        );
    }

    public function update(Request $request, int $link, UpsertItemSupplierAction $action): JsonResponse
    {
        $model = OwnershipGuard::validate(ItemSupplier::class, $link);
        $this->authorize('update', $model);
        $data = $this->validated($request, false);

        return $this->success(
            $action->execute($model->item_id, $model->supplier_id, $data, $request->user(), AuditContext::fromRequest($request)),
            'Supplier item diperbarui.',
        );
    }

    public function preferred(Request $request, int $link, SetPreferredSupplierAction $action): JsonResponse
    {
        $model = OwnershipGuard::validate(ItemSupplier::class, $link);
        $this->authorize('update', $model);

        return $this->success(
            $action->execute($link, $request->user(), AuditContext::fromRequest($request)),
            'Preferred supplier diperbarui.',
        );
    }

    public function destroy(Request $request, int $link, DeleteItemSupplierAction $action): JsonResponse
    {
        $model = OwnershipGuard::validate(ItemSupplier::class, $link);
        $this->authorize('update', $model);
        $action->execute($link, $request->user(), AuditContext::fromRequest($request));

        return $this->success(null, 'Supplier item dihapus.');
    }

    private function validated(Request $request, bool $supplierRequired): array
    {
        return $request->validate([
            'supplier_id' => [$supplierRequired ? 'required' : 'sometimes', 'integer'],
            'supplier_sku' => ['nullable', 'string', 'max:100'],
            'harga_beli_terakhir' => ['nullable', 'decimal:0,2', 'min:0'],
            'lead_time_days' => ['nullable', 'integer', 'min:0'],
            'is_preferred' => ['sometimes', 'boolean'],
        ]);
    }
}
