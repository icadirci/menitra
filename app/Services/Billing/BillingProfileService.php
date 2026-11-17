<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\BillingProfileType;
use App\Exceptions\CrossTenantWriteException;
use App\Models\Tenant;
use App\Models\TenantBillingProfile;
use App\Models\User;
use App\Rules\TurkishTaxId;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The tenant's invoice details (owner-only, TenantPolicy::manageSettings).
 * Validated here again, whatever the panel form did.
 */
class BillingProfileService
{
    public function __construct(private readonly TenantContext $context) {}

    public function current(): ?TenantBillingProfile
    {
        return TenantBillingProfile::query()->first();
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws AuthorizationException|ValidationException
     */
    public function save(Tenant $tenant, User $user, array $input): TenantBillingProfile
    {
        $this->ensureCurrentTenant($tenant);

        if (! $user->can('manageSettings', $tenant)) {
            throw new AuthorizationException;
        }

        $input = self::normalize($input);
        $data = Validator::make($input, self::rules(BillingProfileType::tryFrom((string) ($input['type'] ?? ''))), [], self::attributes())->validate();

        if ($data['type'] === BillingProfileType::Individual->value) {
            $data['tax_office'] = null;
        }

        $profile = $this->current() ?? new TenantBillingProfile;
        $profile->fill($data)->save();

        return $profile;
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(?BillingProfileType $type): array
    {
        return [
            'type' => ['required', Rule::enum(BillingProfileType::class)],
            'name' => ['required', 'string', 'min:3', 'max:160'],
            'tax_office' => [Rule::requiredIf($type === BillingProfileType::Corporate), 'nullable', 'string', 'max:120'],
            'tax_id' => ['required', 'string', new TurkishTaxId($type)],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^\+[1-9]\d{9,14}$/'],
            'address' => ['required', 'string', 'min:10', 'max:500'],
            'city' => ['required', 'string', 'max:80'],
            'district' => ['nullable', 'string', 'max:80'],
            'postal_code' => ['nullable', 'string', 'regex:/^\d{5}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return collect(['type', 'name', 'tax_office', 'tax_id', 'email', 'phone', 'address', 'city', 'district', 'postal_code'])
            ->mapWithKeys(fn (string $field): array => [$field => __("billing.profile.fields.{$field}")])
            ->all();
    }

    /**
     * Trims strings, strips separators from the tax id and normalises Turkish
     * mobile numbers to E.164 (05xx… / 5xx… → +905xx…).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        foreach ($input as $key => $value) {
            if (is_string($value)) {
                $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
                $input[$key] = $value === '' ? null : $value;
            }
        }

        if (is_string($input['tax_id'] ?? null)) {
            $input['tax_id'] = preg_replace('/[\s.-]/', '', $input['tax_id']);
        }

        if (is_string($input['phone'] ?? null)) {
            $digits = preg_replace('/[^\d+]/', '', $input['phone']) ?? '';
            $input['phone'] = match (true) {
                preg_match('/^0?(5\d{9})$/', $digits, $m) === 1 => '+90'.$m[1],
                preg_match('/^90(5\d{9})$/', $digits, $m) === 1 => '+90'.$m[1],
                default => $digits,
            };
        }

        if (is_string($input['email'] ?? null)) {
            $input['email'] = mb_strtolower($input['email']);
        }

        return $input;
    }

    private function ensureCurrentTenant(Tenant $tenant): void
    {
        if (! $this->context->check() || $this->context->id() !== (int) $tenant->getKey()) {
            throw new CrossTenantWriteException('Billing details can only be changed for the tenant in the current TenantContext.');
        }
    }
}
