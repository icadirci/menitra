<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Faqs\Pages;

use App\Filament\Admin\Concerns\SavesPlatformTranslations;
use App\Filament\Admin\Resources\Faqs\FaqResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFaq extends EditRecord
{
    use SavesPlatformTranslations;

    protected static string $resource = FaqResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function translationFields(): array
    {
        return ['question', 'answer'];
    }

    protected function requiredTranslationFields(): array
    {
        return ['question', 'answer'];
    }
}
