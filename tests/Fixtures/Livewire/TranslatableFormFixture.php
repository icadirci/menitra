<?php

declare(strict_types=1);

namespace Tests\Fixtures\Livewire;

use App\Filament\App\Forms\TranslatableFields;
use App\Models\Location;
use App\Services\Translations\TranslationWriter;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;

/**
 * Test-only form that uses TranslatableFields the way panel pages will.
 */
class TranslatableFormFixture extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public Location $location;

    public function mount(Location $location): void
    {
        $this->location = $location;
        $this->form->fill(['translations' => TranslatableFields::fillFrom($location)]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TranslatableFields::make(fn (string $locale, bool $isDefault): array => [
                    TextInput::make('name')->required($isDefault)->maxLength(255),
                    Textarea::make('description'),
                ]),
            ])
            ->statePath('data');
    }

    public function save(TranslationWriter $writer): void
    {
        $writer->sync($this->location, $this->form->getState()['translations']);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}
