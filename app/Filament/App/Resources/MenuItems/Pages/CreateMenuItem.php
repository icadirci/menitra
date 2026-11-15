<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\MenuItems\Pages;

use App\Filament\App\Resources\MenuItems\MenuItemResource;
use App\Filament\App\Support\ServiceCall;
use App\Services\Menus\MenuContentService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMenuItem extends CreateRecord
{
    protected static string $resource = MenuItemResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        abort_unless(MenuItemResource::canUseCategory($data['menu_category_id'] ?? null), 403);

        return ServiceCall::run(fn () => app(MenuContentService::class)->saveItem($data));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
