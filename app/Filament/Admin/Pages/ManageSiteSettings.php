<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Enums\SocialNetwork;
use App\Filament\Admin\Forms\PlatformTranslatableFields;
use App\Filament\Admin\Navigation\AdminNavigationGroup;
use App\Models\SiteSetting;
use App\Models\User;
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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * Platform contact details shown in the marketing footer and on the contact
 * page (single site_settings row + translations). Super admins only
 * (SiteSettingPolicy), checked on mount, on every Livewire request
 * (CanAuthorizeAccess) and again in save().
 */
class ManageSiteSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Website;

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'site-settings';

    /** @var list<string> */
    private const TRANSLATED = ['address', 'support_hours'];

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('update', SiteSetting::class);
    }

    public static function getNavigationLabel(): string
    {
        return __('panels.admin.site_settings.navigation');
    }

    public function getTitle(): string
    {
        return __('panels.admin.site_settings.title');
    }

    public function getSubheading(): ?string
    {
        return __('panels.admin.site_settings.subheading');
    }

    public function mount(): void
    {
        $settings = SiteSetting::current();

        $this->form->fill([
            ...$settings->only($settings->getFillable()),
            'translations' => PlatformTranslatableFields::fillFrom($settings, self::TRANSLATED),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('panels.admin.site_settings.sections.contact'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('contact_email')
                            ->label(__('panels.admin.site_settings.fields.contact_email'))
                            ->helperText(__('panels.admin.site_settings.fields.contact_email_help'))
                            ->email()
                            ->rule('email:rfc')
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label(__('panels.admin.site_settings.fields.phone'))
                            ->helperText(__('panels.admin.site_settings.fields.phone_help'))
                            ->tel()
                            ->regex('/^\+?[0-9 ()\-]{7,20}$/')
                            ->maxLength(32),
                        Toggle::make('phone_has_whatsapp')
                            ->label(__('panels.admin.site_settings.fields.phone_has_whatsapp'))
                            ->default(false)
                            ->columnSpanFull(),
                    ]),
                Section::make(__('panels.admin.site_settings.sections.office'))
                    ->columnSpanFull()
                    ->schema([
                        PlatformTranslatableFields::make(fn (string $locale, bool $isDefault): array => [
                            TextInput::make('support_hours')
                                ->label(__('panels.admin.site_settings.fields.support_hours'))
                                ->placeholder(__('panels.admin.site_settings.fields.support_hours_placeholder'))
                                ->maxLength(255),
                            Textarea::make('address')
                                ->label(__('panels.admin.site_settings.fields.address'))
                                ->rows(3)
                                ->maxLength(500),
                        ]),
                    ]),
                Section::make(__('panels.admin.site_settings.sections.social'))
                    ->description(__('panels.admin.site_settings.sections.social_help'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema(array_map(
                        fn (SocialNetwork $network): TextInput => TextInput::make($network->column())
                            ->label($network->label())
                            ->url()
                            ->rule('starts_with:https://')
                            ->placeholder('https://')
                            ->maxLength(255),
                        SocialNetwork::cases(),
                    )),
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

        DB::transaction(function () use ($data): void {
            $settings = SiteSetting::current();
            $settings->update(Arr::only($data, $settings->getFillable()));
            app(TranslationWriter::class)->syncPlatform($settings, $data['translations'] ?? [], required: []);
        });

        Notification::make()->success()->title(__('panels.admin.site_settings.saved'))->send();
    }
}
