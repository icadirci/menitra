<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\ExtraGroups\Pages;

use App\Filament\App\Forms\TranslatableFields;
use App\Filament\App\Resources\ExtraGroups\ExtraGroupResource;
use App\Filament\App\Support\ServiceCall;
use App\Models\ExtraGroup;
use App\Services\Menus\ExtraGroupService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditExtraGroup extends EditRecord
{
    protected static string $resource = ExtraGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(function (ExtraGroup $record): bool {
                    app(ExtraGroupService::class)->deleteGroup($record);

                    return true;
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['translations'] = TranslatableFields::fillFrom($this->getRecord(), ['name']);

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var ExtraGroup $record */
        return ServiceCall::run(fn () => app(ExtraGroupService::class)->saveGroup($data, $record));
    }
}
