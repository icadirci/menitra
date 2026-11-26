<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ShopOrders\Pages;

use App\Filament\Admin\Resources\ShopOrders\ShopOrderResource;
use Filament\Resources\Pages\ListRecords;

class ListShopOrders extends ListRecords
{
    protected static string $resource = ShopOrderResource::class;
}
