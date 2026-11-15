<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use App\Enums\BillingInterval;
use App\Enums\FaqPlacement;
use App\Models\Faq;
use App\Models\Plan;
use App\Models\Testimonial;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Read side of the marketing site: platform content managed in /admin,
 * resolved for the current locale (fallback: platform default locale).
 * Only published / public records and allow-listed fields leave this class.
 */
final class MarketingContent
{
    /**
     * Active, public plans that have an active monthly price in the platform
     * currency, ordered for display.
     *
     * @return Collection<int, PricingPlan>
     */
    public function plans(): Collection
    {
        return Plan::query()
            ->active()
            ->where('is_public', true)
            ->withTranslations($this->locales())
            ->with('prices')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Plan $plan): ?PricingPlan => $this->toPricingPlan($plan))
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, array{question: string, answer: string}>
     */
    public function faqs(FaqPlacement $placement): Collection
    {
        return Faq::query()
            ->published()
            ->where('placement', $placement)
            ->withTranslations($this->locales())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Faq $faq): ?array => ($t = $faq->translate()) ? ['question' => $t->question, 'answer' => $t->answer] : null)
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, array{quote: string, name: string, role: ?string, initials: string}>
     */
    public function testimonials(int $limit = 6): Collection
    {
        return Testimonial::query()
            ->published()
            ->withTranslations($this->locales())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (Testimonial $testimonial): ?array => ($t = $testimonial->translate()) ? [
                'quote' => $t->quote,
                'name' => $testimonial->author_name,
                'role' => $t->author_role,
                'initials' => $testimonial->initials(),
            ] : null)
            ->filter()
            ->values();
    }

    private function toPricingPlan(Plan $plan): ?PricingPlan
    {
        $monthly = $plan->priceFor(BillingInterval::Monthly);
        $translation = $plan->translate();

        if ($monthly === null || $translation === null) {
            return null;
        }

        $yearly = $plan->priceFor(BillingInterval::Yearly);
        $yearlyPerMonth = null;
        $saving = null;

        if ($yearly !== null) {
            $yearlyPerMonth = Money::fromMinor(intdiv($yearly->amount, 12), $yearly->currency);
            $fullYear = $monthly->amount * 12;

            if ($fullYear > 0 && $yearly->amount < $fullYear) {
                $saving = (int) floor((($fullYear - $yearly->amount) * 100) / $fullYear);
            }
        }

        return new PricingPlan(
            code: $plan->code,
            name: $translation->name,
            description: $translation->description,
            featured: $plan->is_featured,
            monthly: $monthly->money(),
            yearly: $yearly?->money(),
            yearlyPerMonth: $yearlyPerMonth,
            yearlySavingPercent: $saving ?: null,
        );
    }

    /**
     * @return list<string>
     */
    private function locales(): array
    {
        return array_values(array_unique([app()->getLocale(), config('locales.default')]));
    }
}
