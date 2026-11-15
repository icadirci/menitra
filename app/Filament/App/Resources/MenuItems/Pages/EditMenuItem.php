<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\MenuItems\Pages;

use App\Filament\App\Forms\TranslatableFields;
use App\Filament\App\Resources\MenuItems\MenuItemResource;
use App\Filament\App\Support\ServiceCall;
use App\Models\MenuItem;
use App\Services\Menus\MenuContentService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditMenuItem extends EditRecord
{
    protected static string $resource = MenuItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(function (MenuItem $record): bool {
                    app(MenuContentService::class)->deleteItem($record);

                    return true;
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var MenuItem $item */
        $item = $this->getRecord();

        $data['translations'] = TranslatableFields::fillFrom($item, MenuItemResource::TRANSLATED);
        $data['allergens'] = $item->allergens()->get()->map(fn ($row) => $row->allergen->value)->all();
        $data['dietary_labels'] = $item->dietaryLabels()->get()->map(fn ($row) => $row->label->value)->all();
        $data['extra_group_ids'] = $item->extraGroups()->pluck('extra_groups.id')->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless(MenuItemResource::canUseCategory($data['menu_category_id'] ?? null), 403);

        /** @var MenuItem $record */
        return ServiceCall::run(fn () => app(MenuContentService::class)->saveItem($data, $record));
    }
}
