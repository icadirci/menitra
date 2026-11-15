<?php

declare(strict_types=1);

namespace Tests\Fixtures\Livewire;

use App\Enums\MediaType;
use App\Filament\App\Forms\TenantImageUpload;
use App\Models\Location;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use RuntimeException;

/**
 * Test-only form that saves a location cover the way panel pages will:
 * validation, storage and the model update inside one transaction.
 */
class ImageUploadFixture extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public Location $location;

    public bool $failAfterStoring = false;

    public function mount(Location $location): void
    {
        $this->location = $location;
        $this->form->fill(['cover_path' => $location->cover_path]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([TenantImageUpload::make('cover_path', MediaType::Location)])
            ->statePath('data');
    }

    public function save(): void
    {
        DB::transaction(function (): void {
            $state = $this->form->getState();
            $this->location->forceFill(['cover_path' => $state['cover_path']])->save();

            if ($this->failAfterStoring) {
                throw new RuntimeException('Simulated failure after the upload was stored.');
            }
        });
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}
