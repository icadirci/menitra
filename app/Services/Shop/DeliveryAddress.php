<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Models\ShopOrder;
use App\Services\Billing\BillingProfileService;
use App\Support\TurkishProvinces;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validation and prefill of a delivery address. Stored only as the encrypted
 * snapshot of each order.
 */
class DeliveryAddress
{
    /** @var list<string> */
    public const array FIELDS = ['recipient', 'phone', 'address', 'district', 'city', 'postal_code'];

    public function __construct(private readonly BillingProfileService $profiles) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string|null>
     *
     * @throws ValidationException
     */
    public function validate(array $input): array
    {
        $input = BillingProfileService::normalize(Arr::only($input, self::FIELDS));

        return array_merge(array_fill_keys(self::FIELDS, null), Validator::make($input, [
            'recipient' => ['required', 'string', 'min:3', 'max:120'],
            // Required: the courier calls it.
            'phone' => ['required', 'string', 'regex:/^\+[1-9]\d{9,14}$/'],
            'address' => ['required', 'string', 'min:10', 'max:400'],
            'district' => ['required', 'string', 'max:80'],
            'city' => ['required', 'string', Rule::in(TurkishProvinces::ALL)],
            'postal_code' => ['nullable', 'string', 'regex:/^\d{5}$/'],
        ], [
            'phone.regex' => __('shop.errors.phone'),
        ], collect(self::FIELDS)->mapWithKeys(fn (string $field): array => [$field => __("shop.address.{$field}")])->all())->validate());
    }

    /**
     * The last order's address, else the billing address.
     *
     * @return array<string, string|null>
     */
    public function prefill(): array
    {
        $last = ShopOrder::query()->latest('id')->first();

        if ($last !== null) {
            return Arr::only($last->delivery_address ?? [], self::FIELDS);
        }

        return $this->fromBilling();
    }

    /**
     * @return array<string, string|null>
     */
    public function fromBilling(): array
    {
        $profile = $this->profiles->current();

        if ($profile === null) {
            return [];
        }

        return [
            'recipient' => $profile->name,
            'phone' => $profile->phone,
            'address' => $profile->address,
            'district' => $profile->district,
            // Billing cities are free text; only a known province fits the select.
            'city' => in_array($profile->city, TurkishProvinces::ALL, true) ? $profile->city : null,
            'postal_code' => $profile->postal_code,
        ];
    }
}
