<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\Tenancy\TenantRegistrationService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Validates public registration input and delegates to
 * TenantRegistrationService. Only the listed fields are read, so extra input
 * (e.g. platform_role, tenant_id) is ignored.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(private readonly TenantRegistrationService $registration) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        $input['slug'] = Str::lower(trim((string) ($input['slug'] ?? '')))
            ?: Str::slug((string) ($input['business_name'] ?? ''));

        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
            'business_name' => ['required', 'string', 'max:120'],
            'slug' => [
                'required',
                'string',
                'min:3',
                'max:40',
                'regex:/^[a-z0-9](-?[a-z0-9])*$/',
                Rule::notIn(config('tenancy.reserved_slugs')),
                Rule::unique('tenants', 'slug'),
            ],
            // A hint only: TenantRegistrationService falls back to the default plan.
            'plan' => ['nullable', 'string', 'max:64'],
        ], [
            'slug.regex' => __('auth.register.slug_format'),
            'slug.not_in' => __('auth.register.slug_reserved'),
        ], [
            'name' => __('auth.fields.name'),
            'email' => __('auth.fields.email'),
            'password' => __('auth.fields.password'),
            'business_name' => __('auth.fields.business_name'),
            'slug' => __('auth.fields.slug'),
        ])->validate();

        return $this->registration->register(
            name: $data['name'],
            email: Str::lower($data['email']),
            password: $data['password'],
            businessName: $data['business_name'],
            slug: $data['slug'],
            // New businesses always start in the platform default (tr); they can
            // enable and switch locales later. Independent of the UI language.
            locale: config('locales.default'),
            planCode: $data['plan'] ?? null,
        );
    }
}
