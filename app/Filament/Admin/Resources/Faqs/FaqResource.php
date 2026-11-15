<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Faqs;

use App\Enums\FaqPlacement;
use App\Filament\Admin\Forms\PlatformTranslatableFields;
use App\Filament\Admin\Navigation\AdminNavigationGroup;
use App\Filament\Admin\Resources\Faqs\Pages\CreateFaq;
use App\Filament\Admin\Resources\Faqs\Pages\EditFaq;
use App\Filament\Admin\Resources\Faqs\Pages\ListFaqs;
use App\Models\Faq;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Marketing FAQ entries (home page, pricing page). Authorization: FaqPolicy.
 */
class FaqResource extends Resource
{
    protected static ?string $model = Faq::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Website;

    protected static ?int $navigationSort = 10;

    public static function getModelLabel(): string
    {
        return __('panels.admin.faqs.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panels.admin.faqs.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Select::make('placement')
                        ->label(__('panels.admin.faqs.fields.placement'))
                        ->options(FaqPlacement::class)
                        ->default(FaqPlacement::Home)
                        ->required(),
                    Toggle::make('is_published')
                        ->label(__('panels.admin.fields.is_published'))
                        ->default(true)
                        ->inline(false),
                ]),
            PlatformTranslatableFields::make(fn (string $locale, bool $isDefault): array => [
                TextInput::make('question')
                    ->label(__('panels.admin.faqs.fields.question'))
                    ->required($isDefault)
                    ->maxLength(255),
                Textarea::make('answer')
                    ->label(__('panels.admin.faqs.fields.answer'))
                    ->required($isDefault)
                    ->rows(5)
                    ->maxLength(5000),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('translations'))
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('question')
                    ->label(__('panels.admin.faqs.fields.question'))
                    ->state(fn (Faq $record): ?string => $record->translate()?->question)
                    ->wrap(),
                TextColumn::make('placement')
                    ->label(__('panels.admin.faqs.fields.placement'))
                    ->badge(),
                IconColumn::make('is_published')
                    ->label(__('panels.admin.fields.is_published'))
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('placement')
                    ->label(__('panels.admin.faqs.fields.placement'))
                    ->options(FaqPlacement::class),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFaqs::route('/'),
            'create' => CreateFaq::route('/create'),
            'edit' => EditFaq::route('/{record}/edit'),
        ];
    }
}
