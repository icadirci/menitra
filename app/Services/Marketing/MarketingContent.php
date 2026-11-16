<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use App\Enums\BillingInterval;
use App\Enums\FaqPlacement;
use App\Enums\PlanFeature;
use App\Enums\SocialNetwork;
use App\Models\Faq;
use App\Models\Plan;
use App\Models\PlanLimit;
use App\Models\SiteSetting;
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
    /** @var array<string, ContactDetails> locale => details */
    private array $contactDetails = [];

    /**
     * Platform contact details from the /admin site settings. Memoized per
     * locale: the class is a scoped singleton and the footer and the contact
     * page both read it in the same request.
     */
    public function contactDetails(): ContactDetails
    {
        return $this->contactDetails[app()->getLocale()] ??= $this->loadContactDetails();
    }

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
            ->with(['prices', 'limits'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Plan $plan): ?PricingPlan => $this->toPricingPlan($plan))
            ->filter()
            ->values();
    }

    /**
     * Comparison table rows of the given plans: PlanFeature cases in enum
     * order, split into count limits and boolean features. A feature is listed
     * only when at least one plan includes it (a row of dashes compares nothing).
     *
     * @param  Collection<int, PricingPlan>  $plans
     * @return array{limits: list<PlanFeature>, features: list<PlanFeature>}
     */
    public function comparison(Collection $plans): array
    {
        $rows = ['limits' => [], 'features' => []];

        foreach (PlanFeature::cases() as $feature) {
            if ($plans->contains(fn (PricingPlan $plan): bool => $plan->includes($feature))) {
                $rows[$feature->isBoolean() ? 'features' : 'limits'][] = $feature;
            }
        }

        return $rows;
    }

    /**
     * Largest yearly saving among the plans and whether every plan with a
     * yearly price saves the same, for the billing toggle badge.
     *
     * @param  Collection<int, PricingPlan>  $plans
     * @return array{percent: int, uniform: bool}|null
     */
    public function yearlySaving(Collection $plans): ?array
    {
        $yearly = $plans->filter(fn (PricingPlan $plan): bool => $plan->yearly !== null);
        $savings = $yearly->map(fn (PricingPlan $plan): int => $plan->yearlySavingPercent ?? 0);

        if ($savings->max() < 1) {
            return null;
        }

        return ['percent' => $savings->max(), 'uniform' => $savings->unique()->count() === 1];
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
            limits: $plan->limits
                ->mapWithKeys(fn (PlanLimit $limit): array => [$limit->feature->value => $limit->limit_value])
                ->all(),
            trialDays: $plan->trialDays(),
            includedLocations: $plan->included_locations,
            extraLocationMonthly: $monthly->extraLocationMoney(),
            extraLocationYearly: $yearly?->extraLocationMoney(),
        );
    }

    private function loadContactDetails(): ContactDetails
    {
        $settings = SiteSetting::query()
            ->whereKey(SiteSetting::SINGLETON_ID)
            ->withTranslations($this->locales())
            ->first();

        if ($settings === null) {
            return ContactDetails::empty();
        }

        // Per field: current locale, then the platform default locale.
        $translation = $settings->translate();
        $fallback = $settings->translate(config('locales.default'));
        $socials = [];

        foreach (SocialNetwork::cases() as $network) {
            if (($url = $settings->socialUrl($network)) !== null) {
                $socials[] = ['network' => $network, 'url' => $url];
            }
        }

        return new ContactDetails(
            email: $settings->contact_email,
            phone: $settings->phone,
            whatsapp: $settings->phone_has_whatsapp,
            address: $translation?->address ?? $fallback?->address,
            supportHours: $translation?->support_hours ?? $fallback?->support_hours,
            socials: $socials,
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
