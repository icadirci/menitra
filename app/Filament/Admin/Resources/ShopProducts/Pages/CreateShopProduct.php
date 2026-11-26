<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ShopProducts\Pages;

use App\Filament\Admin\Concerns\SavesPlatformTranslations;
use App\Filament\Admin\Resources\ShopProducts\ShopProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateShopProduct extends CreateRecord
{
    use SavesPlatformTranslations;

    protected static string $resource = ShopProductResource::class;

    protected function translationFields(): array
    {
        return ['name', 'description'];
    }

    protected function requiredTranslationFields(): array
    {
        return ['name'];
    }
}
