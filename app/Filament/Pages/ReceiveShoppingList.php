<?php

namespace App\Filament\Pages;

use App\Actions\Shopping\ReceiveShoppingListAction;
use App\Enums\ShoppingListStatus;
use App\Enums\SubscriptionCapability;
use App\Enums\UserRole;
use App\Filament\Resources\ShoppingListResource;
use App\Models\ShoppingList;
use App\Services\ImpersonationContext;
use App\Support\AuditContext;
use App\Support\OwnershipGuard;
use App\Support\SubscriptionCapabilityService;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ReceiveShoppingList extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.receive-shopping-list';

    protected static bool $shouldRegisterNavigation = false;

    public ShoppingList $shoppingList;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->role === UserRole::Owner
            && auth()->user()?->is_active === true
            && ! ImpersonationContext::active()
            && app(SubscriptionCapabilityService::class)->allows(auth()->user(), SubscriptionCapability::Operate);
    }

    public function mount(): void
    {
        $listId = (int) request()->query('list');
        $this->shoppingList = OwnershipGuard::validate(ShoppingList::class, $listId);

        abort_unless($this->shoppingList->status === ShoppingListStatus::Purchased, 404);

        $items = $this->shoppingList->items()
            ->where('is_checked', true)->with('item')->orderBy('id')->get()
            ->map(fn ($row) => [
                'shopping_list_item_id' => $row->id,
                'item_name' => $row->item?->nama,
                'qty_dibeli' => $row->qty_dibeli,
                'qty_received' => $row->qty_dibeli,
                'harga_satuan' => $row->item?->harga_beli ?? '0.00',
            ])->all();

        $this->form->fill(['items' => $items]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Repeater::make('items')->schema([
                Hidden::make('shopping_list_item_id'),
                TextInput::make('item_name')->disabled(),
                TextInput::make('qty_dibeli')->disabled()->label('Dibeli'),
                TextInput::make('qty_received')->numeric()->minValue(1)->required()->label('Diterima aktual'),
                TextInput::make('harga_satuan')->numeric()->minValue(0)->required()->prefix('Rp'),
            ])->columns(4)->addable(false)->deletable(false)->reorderable(false),
        ])->statePath('data');
    }

    public function save(): mixed
    {
        app(ReceiveShoppingListAction::class)->execute(
            (int) $this->shoppingList->getKey(), $this->form->getState()['items'],
            auth()->user(), AuditContext::fromRequest(request()),
        );

        Notification::make()->title('Penerimaan selesai dan stok diperbarui')->success()->send();

        return redirect(ShoppingListResource::getUrl('index'));
    }
}
