<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Enums\MediaType;
use App\Filament\App\Forms\TenantImageUpload;
use App\Filament\App\Forms\TranslatableFields;
use App\Filament\App\Navigation\AppNavigationGroup;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Translations\TranslationWriter;
use App\Tenancy\TenantContext;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
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
 * Business profile: name, tagline and description per enabled locale
 * (tenant_translations), logo and cover image. The slug and currency are
 * shown read-only. Owner-only (TenantPolicy::manageSettings), checked on
 * mount, on every Livewire request and again in save().
 */
class EditBusinessProfile extends Page
{
    protected static string|UnitEnum|null $navigationGroup = AppNavigationGroup::Business;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'business-profile';

    /** @var list<string> */
    private const TRANSLATED = ['name', 'tagline', 'description'];

    /** @var list<string> */
    private const MEDIA = ['logo_path', 'cover_path'];

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $context = app(TenantContext::class);
        $user = auth()->user();

        return $context->check()
            && $user instanceof User
            && $user->can('manageSettings', $context->tenant());
    }

    public static function getNavigationLabel(): string
    {
        return __('panels.app.profile.navigation');
    }

    public function getTitle(): string
    {
        return __('panels.app.profile.title');
    }

    public function mount(): void
    {
        $tenant = $this->tenant();

        $this->form->fill([
            ...$tenant->only(self::MEDIA),
            'translations' => TranslatableFields::fillFrom($tenant, self::TRANSLATED),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('panels.app.profile.sections.identity'))
                    ->description(__('panels.app.profile.sections.identity_help'))
                    ->columnSpanFull()
                    ->schema([
                        TranslatableFields::make(fn (string $locale, bool $isDefault): array => [
                            TextInput::make('name')
                                ->label(__('panels.app.profile.fields.name'))
                                ->required($isDefault)
                                ->maxLength(120),
                            TextInput::make('tagline')
                                ->label(__('panels.app.profile.fields.tagline'))
                                ->maxLength(160),
                            Textarea::make('description')
                                ->label(__('panels.app.profile.fields.description'))
                                ->rows(4)
                                ->maxLength(2000),
                        ]),
                    ]),
                Section::make(__('panels.app.profile.sections.images'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TenantImageUpload::make('logo_path', MediaType::Logo)
                            ->label(__('panels.app.profile.fields.logo'))
                            ->helperText(__('panels.app.profile.fields.logo_help')),
                        TenantImageUpload::make('cover_path', MediaType::Cover)
                            ->label(__('panels.app.profile.fields.cover'))
                            ->helperText(__('panels.app.profile.fields.cover_help')),
                    ]),
                Section::make(__('panels.app.profile.sections.address'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('slug')
                            ->label(__('panels.app.profile.fields.slug'))
                            ->state(fn (): string => $this->tenant()->slug)
                            ->helperText(__('panels.app.profile.fields.slug_help'))
                            ->copyable(),
                        TextEntry::make('currency')
                            ->label(__('panels.app.profile.fields.currency'))
                            ->state(fn (): string => $this->tenant()->currency),
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
                            ->submit('save')
                            ->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    /**
     * Uploads are stored while the form state is read, so the whole save runs
     * in one transaction: a failure removes new files (MediaCleaner) and
     * replaced files are deleted only after commit (HasTenantMedia).
     */
    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        DB::transaction(function (): void {
            $data = $this->form->getState();
            $tenant = $this->tenant();

            $tenant->forceFill(Arr::only($data, self::MEDIA))->save();
            app(TranslationWriter::class)->sync($tenant, $data['translations'] ?? [], ['name']);
        });

        Notification::make()->success()->title(__('panels.app.profile.saved'))->send();
    }

    private function tenant(): Tenant
    {
        return app(TenantContext::class)->tenant();
    }
}
