<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Locations\Pages;

use App\Filament\App\Resources\Locations\LocationResource;
use App\Filament\App\Support\ServiceCall;
use App\Models\Location;
use App\Services\Locations\LocationService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateLocation extends CreateRecord
{
    protected static string $resource = LocationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return ServiceCall::run(fn () => DB::transaction(function () use ($data): Location {
            $location = app(LocationService::class)->create($data);
            LocationResource::saveTheme($location, $data);

            return $location;
        }));
    }
}
