<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Plans;

use App\Enums\BillingInterval;
use App\Enums\PlanFeature;
use App\Filament\Admin\Forms\PlatformTranslatableFields;
use App\Filament\Admin\Navigation\AdminNavigationGroup;
use App\Filament\Admin\Resources\Plans\Pages\CreatePlan;
use App\Filament\Admin\Resources\Plans\Pages\EditPlan;
use App\Filament\Admin\Resources\Plans\Pages\ListPlans;
use App\Filament\App\Forms\MoneyInput;
use App\Models\Plan;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Plans, prices and limits (super admins only, PlanPolicy). Saved through
 * App\Services\Subscription\PlanService. Plans are deactivated, never deleted.
 */
class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Subscriptions;

    protected static ?int $navigationSort = 10;

    public static function getModelLabel(): string
    {
        return __('panels.admin.plans.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panels.admin.plans.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('panels.admin.plans.sections.general'))
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('code')
                        ->label(__('panels.admin.plans.fields.code'))
                        ->helperText(__('panels.admin.plans.fields.code_help'))
                        ->required()
                        ->maxLength(64)
                        ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                        ->unique(ignoreRecord: true)
                        ->disabledOn('edit')
                        ->dehydrated(fn (string $operation): bool => $operation === 'create'),
                    TextInput::make('trial_days')
                        ->label(__('panels.admin.plans.fields.trial_days'))
                        ->helperText(__('panels.admin.plans.fields.trial_days_help'))
                        ->integer()
                        ->minValue(0)
                        ->maxValue(365),
                    TextInput::make('sort_order')
                        ->label(__('panels.admin.plans.fields.sort_order'))
                        ->integer()
                        ->default(0),
                    Toggle::make('is_active')
                        ->label(__('panels.admin.plans.fields.is_active'))
                        ->helperText(__('panels.admin.plans.fields.is_active_help'))
                        ->default(true),
                    Toggle::make('is_public')
                        ->label(__('panels.admin.plans.fields.is_public'))
                        ->helperText(__('panels.admin.plans.fields.is_public_help'))
                        ->default(true),
                    Toggle::make('is_featured')
                        ->label(__('panels.admin.plans.fields.is_featured'))
                        ->helperText(__('panels.admin.plans.fields.is_featured_help')),
                ]),
            PlatformTranslatableFields::make(fn (string $locale, bool $isDefault): array => [
                TextInput::make('name')
                    ->label(__('panels.admin.plans.fields.name'))
                    ->required($isDefault)
                    ->maxLength(100),
                Textarea::make('description')
                    ->label(__('panels.admin.plans.fields.description'))
                    ->helperText(__('panels.admin.plans.fields.description_help'))
                    ->rows(2)
                    ->maxLength(300),
            ]),
            Section::make(__('panels.admin.plans.sections.prices'))
                ->description(__('panels.admin.plans.sections.prices_help'))
                ->columns(2)
                ->columnSpanFull()
                ->statePath('prices')
                ->schema(array_map(
                    fn (BillingInterval $interval): MoneyInput => MoneyInput::make($interval->value)->label($interval->label()),
                    BillingInterval::cases(),
                )),
            Section::make(__('panels.admin.plans.sections.limits'))
                ->description(__('panels.admin.plans.sections.limits_help'))
                ->columns(3)
                ->columnSpanFull()
                ->statePath('limits')
                ->schema(array_map(
                    fn (PlanFeature $feature): TextInput|Toggle => $feature->isBoolean()
                        ? Toggle::make($feature->value)->label($feature->label())->inline(false)
                        : TextInput::make($feature->value)
                            ->label($feature->label())
                            ->integer()
                            ->minValue(0)
                            ->placeholder(__('plans.unlimited')),
                    PlanFeature::cases(),
                )),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['translations', 'prices']))
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label(__('panels.admin.plans.fields.name'))
                    ->state(fn (Plan $record): ?string => $record->translate()?->name)
                    ->description(fn (Plan $record): string => $record->code),
                TextColumn::make('monthly')
                    ->label(BillingInterval::Monthly->label())
                    ->state(fn (Plan $record): ?string => $record->priceFor(BillingInterval::Monthly)?->money()->format()),
                TextColumn::make('yearly')
                    ->label(BillingInterval::Yearly->label())
                    ->state(fn (Plan $record): ?string => $record->priceFor(BillingInterval::Yearly)?->money()->format()),
                IconColumn::make('is_featured')
                    ->label(__('panels.admin.plans.fields.is_featured'))
                    ->boolean(),
                IconColumn::make('is_public')
                    ->label(__('panels.admin.plans.fields.is_public'))
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label(__('panels.admin.plans.fields.is_active'))
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlans::route('/'),
            'create' => CreatePlan::route('/create'),
            'edit' => EditPlan::route('/{record}/edit'),
        ];
    }
}
