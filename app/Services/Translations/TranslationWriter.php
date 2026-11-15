<?php

declare(strict_types=1);

namespace App\Services\Translations;

use App\Exceptions\CrossTenantWriteException;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasTranslations;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Writes `{entity}_translations` rows submitted by the panel
 * (state shape: [locale => [field => value]]).
 *
 * - Only the tenant's enabled locales are accepted; any other key is rejected.
 * - The default locale must be submitted with all required fields.
 * - A non-default locale whose fields are all empty is removed (the user
 *   cleared it); a partially filled one must still have the required fields.
 * - Rows of locales that aren't enabled — or simply weren't submitted — are
 *   never touched, so disabling a language never loses its translations.
 * - Only the translation model's fillable fields are written.
 */
final class TranslationWriter
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  Model  $model  a model using HasTranslations, owned by the current tenant
     * @param  array<string, mixed>  $input
     * @param  list<string>  $required
     *
     * @throws ValidationException
     */
    public function sync(Model $model, array $input, array $required = ['name']): void
    {
        $this->assertTranslatable($model);

        $tenant = $this->context->tenant();
        $this->assertOwnedByCurrentTenant($model, $tenant);

        $this->write($model, $input, $tenant->enabledLocales(), $tenant->default_locale, $required);
    }

    /**
     * Same rules for platform-level records (plans, marketing FAQ and
     * testimonials): the locale set is config('locales.supported') and the
     * default is config('locales.default'). Tenant-owned models are refused.
     *
     * @param  array<string, mixed>  $input
     * @param  list<string>  $required
     *
     * @throws ValidationException
     */
    public function syncPlatform(Model $model, array $input, array $required = ['name']): void
    {
        $this->assertTranslatable($model);

        if ($model instanceof Tenant || in_array(BelongsToTenant::class, class_uses_recursive($model), true)) {
            throw new InvalidArgumentException($model::class.' is tenant-owned; use sync().');
        }

        $this->write($model, $input, array_keys(config('locales.supported')), config('locales.default'), $required);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<string>  $enabled
     * @param  list<string>  $required
     *
     * @throws ValidationException
     */
    private function write(Model $model, array $input, array $enabled, string $default, array $required): void
    {
        $fields = array_values(array_diff($model->translations()->getRelated()->getFillable(), ['locale']));

        $rows = $this->normalise($input, $enabled, $default, $fields, $required);

        DB::transaction(function () use ($model, $rows): void {
            foreach ($rows as $locale => $values) {
                if ($values === null) {
                    $model->translations()->where('locale', $locale)->delete();

                    continue;
                }

                $model->translations()->updateOrCreate(['locale' => $locale], $values);
            }
        });

        $model->unsetRelation('translations');
    }

    private function assertTranslatable(Model $model): void
    {
        if (! in_array(HasTranslations::class, class_uses_recursive($model), true)) {
            throw new InvalidArgumentException($model::class.' does not use HasTranslations.');
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<string>  $enabled
     * @param  list<string>  $fields
     * @param  list<string>  $required
     * @return array<string, array<string, string|null>|null> locale => values (null = remove)
     *
     * @throws ValidationException
     */
    private function normalise(array $input, array $enabled, string $default, array $fields, array $required): array
    {
        $errors = [];
        $rows = [];

        foreach ($input as $locale => $values) {
            if (! is_string($locale) || ! in_array($locale, $enabled, true) || ! is_array($values)) {
                $errors["translations.{$locale}"] = __('validation.in', ['attribute' => (string) $locale]);

                continue;
            }

            $clean = [];
            foreach ($fields as $field) {
                $value = $values[$field] ?? null;
                $value = is_string($value) ? trim($value) : $value;
                $clean[$field] = ($value === '' || $value === null) ? null : (string) $value;
            }

            if ($locale !== $default && array_filter($clean, fn (?string $value): bool => $value !== null) === []) {
                $rows[$locale] = null;

                continue;
            }

            foreach ($required as $field) {
                if (($clean[$field] ?? null) === null) {
                    $errors["translations.{$locale}.{$field}"] = __('validation.required', ['attribute' => $field]);
                }
            }

            $rows[$locale] = $clean;
        }

        if (! array_key_exists($default, $rows)) {
            foreach ($required as $field) {
                $errors["translations.{$default}.{$field}"] ??= __('validation.required', ['attribute' => $field]);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $rows;
    }

    private function assertOwnedByCurrentTenant(Model $model, Tenant $tenant): void
    {
        $owner = $model instanceof Tenant ? $model->getKey() : $model->getAttribute('tenant_id');

        if ($owner === null || (int) $owner !== (int) $tenant->getKey()) {
            throw new CrossTenantWriteException('Translations can only be written for records of the current tenant.');
        }
    }
}
