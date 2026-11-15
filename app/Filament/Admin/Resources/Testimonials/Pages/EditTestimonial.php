<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Testimonials\Pages;

use App\Filament\Admin\Concerns\SavesPlatformTranslations;
use App\Filament\Admin\Resources\Testimonials\TestimonialResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTestimonial extends EditRecord
{
    use SavesPlatformTranslations;

    protected static string $resource = TestimonialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function translationFields(): array
    {
        return ['quote', 'author_role'];
    }

    protected function requiredTranslationFields(): array
    {
        return ['quote'];
    }
}
