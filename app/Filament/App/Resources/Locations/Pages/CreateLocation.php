<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Locations\Pages;

use App\Filament\App\Resources\Locations\LocationResource;
use App\Filament\App\Support\ServiceCall;
use App\Services\Locations\LocationService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateLocation extends CreateRecord
{
    protected static string $resource = LocationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return ServiceCall::run(fn () => app(LocationService::class)->create($data));
    }
}
