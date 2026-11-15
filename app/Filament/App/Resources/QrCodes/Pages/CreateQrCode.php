<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\QrCodes\Pages;

use App\Filament\App\Resources\QrCodes\QrCodeResource;
use App\Filament\App\Support\ServiceCall;
use App\Services\QrCodes\QrCodeService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateQrCode extends CreateRecord
{
    protected static string $resource = QrCodeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $location = QrCodeResource::usableLocation($data['location_id'] ?? null);
        abort_if($location === null, 403);

        return ServiceCall::run(fn () => app(QrCodeService::class)->create($location, $data));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
