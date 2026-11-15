<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Testimonial>
 */
class TestimonialFactory extends Factory
{
    public function definition(): array
    {
        return [
            'author_name' => fake()->name(),
            'is_published' => true,
            'sort_order' => 0,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(['is_published' => false]);
    }

    /**
     * @param  array<string, array{quote: string, author_role?: string|null}>  $translations  locale => fields
     */
    public function withTranslations(array $translations): static
    {
        return $this->afterCreating(function (Testimonial $testimonial) use ($translations): void {
            foreach ($translations as $locale => $fields) {
                $testimonial->translations()->create(['locale' => $locale, ...$fields]);
            }
        });
    }
}
