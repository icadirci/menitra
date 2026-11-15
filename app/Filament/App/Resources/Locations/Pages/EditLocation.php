<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Locations\Pages;

use App\Filament\App\Forms\TranslatableFields;
use App\Filament\App\Resources\Locations\LocationResource;
use App\Filament\App\Support\ServiceCall;
use App\Models\Location;
use App\Services\Locations\LocationService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditLocation extends EditRecord
{
    protected static string $resource = LocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            LocationResource::makeDefaultAction(),
            LocationResource::deleteAction(),
            LocationResource::restoreAction(),
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

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Location $record */
        return ServiceCall::run(fn () => app(LocationService::class)->update($record, $data));
    }
}
