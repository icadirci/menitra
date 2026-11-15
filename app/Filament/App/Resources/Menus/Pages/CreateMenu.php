<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Menus\Pages;

use App\Filament\App\Resources\Menus\MenuResource;
use App\Filament\App\Support\ServiceCall;
use App\Services\Menus\MenuService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMenu extends CreateRecord
{
    protected static string $resource = MenuResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return ServiceCall::run(fn () => app(MenuService::class)->create($data));
    }
}
