<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FaqPlacement;
use App\Models\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faq>
 */
class FaqFactory extends Factory
{
    public function definition(): array
    {
        return [
            'placement' => FaqPlacement::Home,
            'is_published' => true,
            'sort_order' => 0,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(['is_published' => false]);
    }

    /**
     * @param  array<string, array{question: string, answer: string}>  $translations  locale => fields
     */
    public function withTranslations(array $translations): static
    {
        return $this->afterCreating(function (Faq $faq) use ($translations): void {
            foreach ($translations as $locale => $fields) {
                $faq->translations()->create(['locale' => $locale, ...$fields]);
            }
        });
    }
}
