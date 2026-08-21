<?php

namespace App\Filament\Pages;

use App\Actions\Analytics\UpdateTenantAnalyticsSettingsAction;
use App\Enums\UserRole;
use App\Services\TenantContext;
use App\Support\AuditContext;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class AnalyticsSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Pengaturan';

    protected static ?string $navigationLabel = 'Pengaturan Analytics';

    protected static ?string $title = 'Pengaturan Analytics';

    protected static ?int $navigationSort = 90;

    protected static string $view = 'filament.pages.analytics-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->role === UserRole::Owner
            && auth()->user()?->is_active === true;
    }

    public function mount(): void
    {
        $this->form->fill([
            'dead_stock_days' => (int) TenantContext::get()->dead_stock_days,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('dead_stock_days')
                ->label('Batas Dead Stock (hari)')
                ->helperText('Isi 0 untuk menonaktifkan klasifikasi dead stock. Perubahan akan memicu refresh analytics seluruh tenant.')
                ->integer()
                ->minValue(0)
                ->required(),
        ])->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $tenant = app(UpdateTenantAnalyticsSettingsAction::class)->execute(
            (int) $state['dead_stock_days'],
            auth()->user(),
            AuditContext::fromRequest(request()),
        );
        TenantContext::set($tenant);
        $this->form->fill(['dead_stock_days' => (int) $tenant->dead_stock_days]);

        Notification::make()
            ->title('Pengaturan analytics tersimpan')
            ->body('Refresh analytics tenant telah dimasukkan ke antrean.')
            ->success()
            ->send();
    }
}
