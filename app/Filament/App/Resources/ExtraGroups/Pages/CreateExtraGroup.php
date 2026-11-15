<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\ExtraGroups\Pages;

use App\Filament\App\Resources\ExtraGroups\ExtraGroupResource;
use App\Filament\App\Support\ServiceCall;
use App\Services\Menus\ExtraGroupService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateExtraGroup extends CreateRecord
{
    protected static string $resource = ExtraGroupResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return ServiceCall::run(fn () => app(ExtraGroupService::class)->saveGroup($data));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
