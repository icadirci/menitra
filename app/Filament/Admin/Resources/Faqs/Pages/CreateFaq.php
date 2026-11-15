<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Faqs\Pages;

use App\Filament\Admin\Concerns\SavesPlatformTranslations;
use App\Filament\Admin\Resources\Faqs\FaqResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFaq extends CreateRecord
{
    use SavesPlatformTranslations;

    protected static string $resource = FaqResource::class;

    protected function translationFields(): array
    {
        return ['question', 'answer'];
    }

    protected function requiredTranslationFields(): array
    {
        return ['question', 'answer'];
    }
}
