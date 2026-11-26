<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BankTransfers\Pages;

use App\Filament\Admin\Resources\BankTransfers\BankTransferResource;
use Filament\Resources\Pages\ListRecords;

class ListBankTransfers extends ListRecords
{
    protected static string $resource = BankTransferResource::class;
}
