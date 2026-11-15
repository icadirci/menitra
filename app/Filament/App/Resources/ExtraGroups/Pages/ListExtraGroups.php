<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\ExtraGroups\Pages;

use App\Filament\App\Resources\ExtraGroups\ExtraGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExtraGroups extends ListRecords
{
    protected static string $resource = ExtraGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
