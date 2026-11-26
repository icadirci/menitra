<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Navigation\AdminNavigationGroup;
use App\Filament\App\Forms\MoneyInput;
use App\Models\ShopSetting;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Shop switch, shipping fees and delivery time. Super admins only
 * (ShopSettingPolicy), checked in canAccess() and again in save().
 */
class ManageShopSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Shop;

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'shop-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('update', ShopSetting::class);
    }

    public static function getNavigationLabel(): string
    {
        return __('shop.admin.settings.title');
    }

    public function getTitle(): string
    {
        return __('shop.admin.settings.title');
    }

    public function mount(): void
    {
        $settings = ShopSetting::current();

        $this->form->fill($settings->only($settings->getFillable()));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make()
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('is_enabled')
                            ->label(__('shop.admin.settings.is_enabled'))
                            ->helperText(__('shop.admin.settings.is_enabled_help'))
                            ->columnSpanFull(),
                        MoneyInput::make('shipping_amount')
                            ->label(__('shop.admin.settings.shipping_amount'))
                            ->helperText(__('shop.admin.settings.vat_help'))
                            ->required(),
                        MoneyInput::make('free_shipping_from')
                            ->label(__('shop.admin.settings.free_shipping_from'))
                            ->helperText(__('shop.admin.settings.free_shipping_from_help')),
                        TextInput::make('min_delivery_days')
                            ->label(__('shop.admin.settings.min_delivery_days'))
                            ->integer()->minValue(1)->maxValue(90),
                        TextInput::make('max_delivery_days')
                            ->label(__('shop.admin.settings.max_delivery_days'))
                            ->integer()->minValue(1)->maxValue(90)->gte('min_delivery_days'),
                        TextInput::make('notification_email')
                            ->label(__('shop.admin.settings.notification_email'))
                            ->helperText(__('shop.admin.settings.notification_email_help'))
                            ->email()
                            ->maxLength(255),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label(__('panels.admin.site_settings.save'))->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        ShopSetting::current()->fill($this->form->getState())->save();

        Notification::make()->success()->title(__('admin.notifications.saved'))->send();
    }
}
