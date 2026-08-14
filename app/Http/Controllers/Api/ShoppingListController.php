<?php

namespace App\Http\Controllers\Api;

use App\Actions\Shopping\GenerateShoppingListAction;
use App\Actions\Shopping\ReceiveShoppingListAction;
use App\Actions\Shopping\SubmitShoppingListAction;
use App\Http\Controllers\Controller;
use App\Models\ShoppingList;
use App\Support\AuditContext;
use App\Support\OwnershipGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShoppingListController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ShoppingList::class);

        return $this->success(ShoppingList::with(['items.item', 'items.supplier'])->latest()->paginate(20));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $list = OwnershipGuard::validate(ShoppingList::class, $id);
        $this->authorize('view', $list);

        return $this->success($list->load(['items.item', 'items.supplier']));
    }

    public function generate(Request $request, GenerateShoppingListAction $action): JsonResponse
    {
        $this->authorize('create', ShoppingList::class);
        $list = $action->execute($request->user(), AuditContext::fromRequest($request));

        return $this->success($list, $list ? 'Daftar belanja dibuat.' : 'Tidak ada item stok rendah.', $list ? 201 : 200);
    }

    public function submit(Request $request, int $id, SubmitShoppingListAction $action): JsonResponse
    {
        $list = OwnershipGuard::validate(ShoppingList::class, $id);
        $this->authorize('update', $list);
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.shopping_list_item_id' => ['required', 'integer'],
            'items.*.is_checked' => ['required', 'boolean'],
            'items.*.supplier_id' => ['nullable', 'integer'],
            'items.*.qty_dibeli' => ['nullable', 'integer', 'min:1'],
        ]);

        return $this->success(
            $action->execute($id, $data['items'], $request->user(), AuditContext::fromRequest($request)),
            'Daftar belanja ditandai purchased.',
        );
    }

    public function receive(Request $request, int $id, ReceiveShoppingListAction $action): JsonResponse
    {
        $list = OwnershipGuard::validate(ShoppingList::class, $id);
        $this->authorize('update', $list);
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.shopping_list_item_id' => ['required', 'integer'],
            'items.*.qty_received' => ['required', 'integer', 'min:1'],
            'items.*.harga_satuan' => ['required', 'decimal:0,2', 'min:0'],
        ]);

        return $this->success(
            $action->execute($id, $data['items'], $request->user(), AuditContext::fromRequest($request)),
            'Penerimaan selesai.',
        );
    }
}
