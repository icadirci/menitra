<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Enums\BillingProfileType;
use App\Filament\App\Support\ServiceCall;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\BillingProfileService;
use App\Tenancy\TenantContext;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Livewire\Attributes\Url;

/**
 * Invoice details used for payments (iyzico buyer / billing address) and
 * the receipts. Owner-only (TenantPolicy::manageSettings), checked on mount,
 * on every Livewire request and again in BillingProfileService::save().
 */
class ManageBillingProfile extends Page
{
    protected static ?string $slug = 'subscription/billing-details';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** "checkout": back to the payment page after saving. */
    #[Url]
    public ?string $return = null;

    public static function canAccess(): bool
    {
        $context = app(TenantContext::class);
        $user = auth()->user();

        return $context->check()
            && $user instanceof User
            && $user->can('manageSettings', $context->tenant());
    }

    public function getTitle(): string
    {
        return __('billing.profile.title');
    }

    public function mount(): void
    {
        $profile = app(BillingProfileService::class)->current();
        $user = auth()->user();

        $this->form->fill($profile !== null ? [
            ...$profile->only(['name', 'tax_office', 'email', 'phone', 'address', 'city', 'district', 'postal_code']),
            'type' => $profile->type->value,
            'tax_id' => $profile->tax_id,
        ] : [
            'type' => BillingProfileType::Individual->value,
            'name' => $user instanceof User ? $user->name : null,
            'email' => $user instanceof User ? $user->email : null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('billing.profile.sections.identity'))
                    ->description(__('billing.profile.sections.identity_help'))
                    ->columns(['default' => 1, 'md' => 2])
                    ->columnSpanFull()
                    ->schema([
                        Radio::make('type')
                            ->label(__('billing.profile.fields.type'))
                            ->options(collect(BillingProfileType::cases())->mapWithKeys(fn (BillingProfileType $type): array => [$type->value => $type->label()])->all())
                            ->inline()
                            ->required()
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('name')
                            ->label(fn (Get $get): string => $get('type') === BillingProfileType::Corporate->value
                                ? __('billing.profile.fields.company_name')
                                : __('billing.profile.fields.name'))
                            ->required()
                            ->maxLength(160),
                        TextInput::make('tax_id')
                            ->label(fn (Get $get): string => $get('type') === BillingProfileType::Corporate->value
                                ? __('billing.profile.fields.vkn')
                                : __('billing.profile.fields.tckn'))
                            ->required()
                            ->maxLength(11)
                            ->inputMode('numeric'),
                        TextInput::make('tax_office')
                            ->label(__('billing.profile.fields.tax_office'))
                            ->visible(fn (Get $get): bool => $get('type') === BillingProfileType::Corporate->value)
                            ->required(fn (Get $get): bool => $get('type') === BillingProfileType::Corporate->value)
                            ->maxLength(120),
                    ]),
                Section::make(__('billing.profile.sections.contact'))
                    ->columns(['default' => 1, 'md' => 2])
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('email')
                            ->label(__('billing.profile.fields.email'))
                            ->helperText(__('billing.profile.fields.email_help'))
                            ->email()
                            ->required()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label(__('billing.profile.fields.phone'))
                            ->tel()
                            ->placeholder('05xx xxx xx xx')
                            ->maxLength(32),
                        Textarea::make('address')
                            ->label(__('billing.profile.fields.address'))
                            ->required()
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull(),
                        TextInput::make('district')
                            ->label(__('billing.profile.fields.district'))
                            ->maxLength(80),
                        TextInput::make('city')
                            ->label(__('billing.profile.fields.city'))
                            ->required()
                            ->maxLength(80),
                        TextInput::make('postal_code')
                            ->label(__('billing.profile.fields.postal_code'))
                            ->maxLength(5),
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
                        Action::make('save')
                            ->label(__('panels.app.actions.save'))
                            ->submit('save'),
                        Action::make('back')
                            ->label(__('billing.actions.back_to_subscription'))
                            ->color('gray')
                            ->url(fn (): string => ViewSubscription::getUrl()),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();
        $user = auth()->user();

        ServiceCall::run(fn () => app(BillingProfileService::class)->save($this->tenant(), $user, $data));

        Notification::make()->success()->title(__('billing.profile.saved'))->send();

        if ($this->return === 'checkout') {
            $this->redirect(Checkout::getUrl());
        }
    }

    private function tenant(): Tenant
    {
        return app(TenantContext::class)->tenant();
    }
}
