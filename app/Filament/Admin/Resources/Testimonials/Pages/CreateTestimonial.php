<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Testimonials\Pages;

use App\Filament\Admin\Concerns\SavesPlatformTranslations;
use App\Filament\Admin\Resources\Testimonials\TestimonialResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTestimonial extends CreateRecord
{
    use SavesPlatformTranslations;

    protected static string $resource = TestimonialResource::class;

    protected function translationFields(): array
    {
        return ['quote', 'author_role'];
    }

    protected function requiredTranslationFields(): array
    {
        return ['quote'];
    }
}
