<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Testimonials;

use App\Filament\Admin\Forms\PlatformTranslatableFields;
use App\Filament\Admin\Navigation\AdminNavigationGroup;
use App\Filament\Admin\Resources\Testimonials\Pages\CreateTestimonial;
use App\Filament\Admin\Resources\Testimonials\Pages\EditTestimonial;
use App\Filament\Admin\Resources\Testimonials\Pages\ListTestimonials;
use App\Models\Testimonial;
use BackedEnum;
use Filament\Actions\DeleteAction;
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
 * Customer testimonials on the marketing home page. Authorization: TestimonialPolicy.
 */
class TestimonialResource extends Resource
{
    protected static ?string $model = Testimonial::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Website;

    protected static ?int $navigationSort = 20;

    public static function getModelLabel(): string
    {
        return __('panels.admin.testimonials.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panels.admin.testimonials.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('author_name')
                        ->label(__('panels.admin.testimonials.fields.author_name'))
                        ->required()
                        ->maxLength(120),
                    Toggle::make('is_published')
                        ->label(__('panels.admin.fields.is_published'))
                        ->default(true)
                        ->inline(false),
                ]),
            PlatformTranslatableFields::make(fn (string $locale, bool $isDefault): array => [
                Textarea::make('quote')
                    ->label(__('panels.admin.testimonials.fields.quote'))
                    ->required($isDefault)
                    ->rows(4)
                    ->maxLength(600),
                TextInput::make('author_role')
                    ->label(__('panels.admin.testimonials.fields.author_role'))
                    ->helperText(__('panels.admin.testimonials.fields.author_role_help'))
                    ->maxLength(160),
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
                TextColumn::make('author_name')
                    ->label(__('panels.admin.testimonials.fields.author_name'))
                    ->description(fn (Testimonial $record): ?string => $record->translate()?->author_role)
                    ->searchable(),
                TextColumn::make('quote')
                    ->label(__('panels.admin.testimonials.fields.quote'))
                    ->state(fn (Testimonial $record): ?string => $record->translate()?->quote)
                    ->limit(90)
                    ->wrap(),
                IconColumn::make('is_published')
                    ->label(__('panels.admin.fields.is_published'))
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTestimonials::route('/'),
            'create' => CreateTestimonial::route('/create'),
            'edit' => EditTestimonial::route('/{record}/edit'),
        ];
    }
}
