<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Locations\Pages;

use App\Filament\App\Forms\TranslatableFields;
use App\Filament\App\Resources\Locations\LocationResource;
use App\Models\Location;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLocation extends ViewRecord
{
    protected static string $resource = LocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Location $location */
        $location = $this->getRecord();

        $data['translations'] = TranslatableFields::fillFrom($location, LocationResource::TRANSLATED);
        $data['opening_hours'] = LocationResource::openingHoursState($location);

        return $data;
    }
}
