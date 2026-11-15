<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Menus\Pages;

use App\Filament\App\Forms\TranslatableFields;
use App\Filament\App\Resources\Menus\MenuResource;
use App\Filament\App\Support\ServiceCall;
use App\Models\Menu;
use App\Services\Menus\MenuService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditMenu extends EditRecord
{
    protected static string $resource = MenuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(function (Menu $record): bool {
                    app(MenuService::class)->delete($record);

                    return true;
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['translations'] = TranslatableFields::fillFrom($this->getRecord(), MenuResource::TRANSLATED);

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Menu $record */
        return ServiceCall::run(fn () => app(MenuService::class)->update($record, $data));
    }
}
