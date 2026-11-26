<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Forms\PlatformTranslatableFields;
use App\Filament\Admin\Navigation\AdminNavigationGroup;
use App\Models\PaymentSetting;
use App\Models\User;
use App\Rules\Iban;
use App\Services\Translations\TranslationWriter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * Payment methods (card through iyzico, bank transfer) and the bank account
 * with the transfer instructions shown to owners. Super admins only
 * (PaymentSettingPolicy), checked in canAccess() and again in save(), where
 * the rules are re-checked on the server.
 */
class ManagePaymentSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Subscriptions;

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'payment-settings';

    /** @var list<string> */
    private const TRANSLATED = ['bank_transfer_instructions'];

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('update', PaymentSetting::class);
    }

    public static function getNavigationLabel(): string
    {
        return __('billing.settings.title');
    }

    public function getTitle(): string
    {
        return __('billing.settings.title');
    }

    public function getSubheading(): ?string
    {
        return __('billing.settings.subheading');
    }

    public function mount(): void
    {
        $settings = PaymentSetting::current();

        $this->form->fill([
            ...$settings->only($settings->getFillable()),
            'bank_iban' => $settings->formattedIban(),
            'translations' => PlatformTranslatableFields::fillFrom($settings, self::TRANSLATED),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('billing.settings.sections.methods'))
                    ->description(__('billing.settings.sections.methods_help'))
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('card_enabled')
                            ->label(__('billing.settings.fields.card_enabled'))
                            ->helperText(__('billing.settings.fields.card_enabled_help')),
                        Toggle::make('bank_transfer_enabled')
                            ->label(__('billing.settings.fields.bank_transfer_enabled'))
                            ->helperText(__('billing.settings.fields.bank_transfer_enabled_help'))
                            ->live(),
                    ]),
                Section::make(__('billing.settings.sections.bank'))
                    ->description(__('billing.settings.sections.bank_help'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('bank_account_holder')
                            ->label(__('billing.settings.fields.bank_account_holder'))
                            ->required(fn (Get $get): bool => (bool) $get('bank_transfer_enabled'))
                            ->maxLength(160),
                        TextInput::make('bank_name')
                            ->label(__('billing.settings.fields.bank_name'))
                            ->maxLength(120),
                        TextInput::make('bank_iban')
                            ->label(__('billing.settings.fields.bank_iban'))
                            ->placeholder('TR00 0000 0000 0000 0000 0000 00')
                            ->required(fn (Get $get): bool => (bool) $get('bank_transfer_enabled'))
                            ->rule(new Iban)
                            ->maxLength(40),
                        TextInput::make('notification_email')
                            ->label(__('billing.settings.fields.notification_email'))
                            ->helperText(__('billing.settings.fields.notification_email_help'))
                            ->email()
                            ->rule('email:rfc')
                            ->maxLength(255),
                        PlatformTranslatableFields::make(fn (string $locale, bool $isDefault): array => [
                            Textarea::make('bank_transfer_instructions')
                                ->label(__('billing.settings.fields.instructions'))
                                ->helperText(__('billing.settings.fields.instructions_help', ['placeholders' => implode(', ', PaymentSetting::PLACEHOLDERS)]))
                                ->placeholder(__('billing.settings.fields.instructions_placeholder'))
                                ->rows(5)
                                ->maxLength(2000),
                        ]),
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
                            ->label(__('panels.admin.site_settings.save'))
                            ->submit('save')
                            ->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();
        $data['bank_iban'] = filled($data['bank_iban'] ?? null) ? Iban::normalize((string) $data['bank_iban']) : null;

        // Re-checked here: the form's conditional rules are only a convenience.
        if ($data['bank_iban'] !== null && ! Iban::isValid($data['bank_iban'])) {
            throw ValidationException::withMessages(['data.bank_iban' => __('billing.validation.iban')]);
        }

        if (($data['bank_transfer_enabled'] ?? false) && ($data['bank_iban'] === null || blank($data['bank_account_holder'] ?? null))) {
            throw ValidationException::withMessages(['data.bank_iban' => __('billing.settings.errors.bank_account_required')]);
        }

        DB::transaction(function () use ($data): void {
            $settings = PaymentSetting::persisted();
            $settings->fill(Arr::only($data, $settings->getFillable()))->save();
            app(TranslationWriter::class)->syncPlatform($settings, $data['translations'] ?? [], required: []);
        });

        $this->form->fill([...$this->data, 'bank_iban' => PaymentSetting::current()->formattedIban()]);

        Notification::make()->success()->title(__('admin.notifications.saved'))->send();
    }
}
