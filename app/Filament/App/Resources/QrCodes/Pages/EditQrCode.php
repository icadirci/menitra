<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\QrCodes\Pages;

use App\Filament\App\Resources\QrCodes\QrCodeResource;
use App\Filament\App\Support\ServiceCall;
use App\Models\QrCode;
use App\Services\QrCodes\QrCodeService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditQrCode extends EditRecord
{
    protected static string $resource = QrCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            QrCodeResource::downloadAction('svg'),
            QrCodeResource::downloadAction('png'),
            QrCodeResource::downloadOnBackgroundAction(),
            DeleteAction::make()
                ->modalDescription(__('panels.app.qr_codes.confirm.delete'))
                ->using(function (QrCode $record): bool {
                    app(QrCodeService::class)->delete($record);

                    return true;
                }),
        ];
    }

    /**
     * The target branch is re-authorized: a manager can't move a code to a
     * branch that isn't assigned to them.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $location = QrCodeResource::usableLocation($data['location_id'] ?? null);
        abort_if($location === null, 403);

        /** @var QrCode $record */
        return ServiceCall::run(fn () => app(QrCodeService::class)->update($record, $location, $data));
    }
}
