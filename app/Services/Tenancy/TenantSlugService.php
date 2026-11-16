<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenant;
use App\Models\TenantSlugRedirect;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The only writer of a tenant's slug after registration (launch plan Faz 8).
 *
 * - Same rules as registration: format, length, reserved list, unique among
 *   tenants (deleted ones included) and among former slugs of other tenants.
 * - The old slug is kept in tenant_slug_redirects: public menu URLs with it
 *   answer 301 to the new slug and no other tenant can take it. Going back to
 *   one of the tenant's own former slugs removes that redirect row.
 * - Resolution cache entries of the old and new slug are cleared after commit
 *   by MenuCacheObserver (Tenant saved). QR codes (/q/{code}) are unaffected.
 */
final class TenantSlugService
{
    /**
     * @return array<int, mixed>
     */
    public static function rules(?Tenant $ignore = null): array
    {
        $formerSlugs = Rule::unique('tenant_slug_redirects', 'slug');

        if ($ignore !== null) {
            $formerSlugs->where(fn ($query) => $query->where('tenant_id', '!=', $ignore->getKey()));
        }

        return [
            'required',
            'string',
            'min:3',
            'max:40',
            'regex:/^[a-z0-9](-?[a-z0-9])*$/',
            Rule::notIn(config('tenancy.reserved_slugs')),
            Rule::unique('tenants', 'slug')->ignore($ignore?->getKey()),
            $formerSlugs,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'slug.regex' => __('auth.register.slug_format'),
            'slug.not_in' => __('auth.register.slug_reserved'),
            'slug.unique' => __('admin.tenants.errors.slug_taken'),
        ];
    }

    /**
     * @return string The previous slug
     *
     * @throws ValidationException
     */
    public function change(Tenant $tenant, string $slug): string
    {
        $slug = Str::lower(trim($slug));

        return DB::transaction(function () use ($tenant, $slug): string {
            $locked = Tenant::withTrashed()->whereKey($tenant->getKey())->lockForUpdate()->firstOrFail();
            $old = (string) $locked->slug;

            if ($slug === $old) {
                throw ValidationException::withMessages(['slug' => __('admin.tenants.errors.slug_unchanged')]);
            }

            Validator::make(['slug' => $slug], ['slug' => self::rules($locked)], self::messages(), [
                'slug' => __('auth.fields.slug'),
            ])->validate();

            TenantSlugRedirect::query()->where('tenant_id', $locked->getKey())->where('slug', $slug)->delete();

            if (! TenantSlugRedirect::query()->where('slug', $old)->exists()) {
                $redirect = new TenantSlugRedirect;
                $redirect->forceFill(['tenant_id' => $locked->getKey(), 'slug' => $old])->save();
            }

            $locked->forceFill(['slug' => $slug])->save();
            $tenant->setRawAttributes($locked->getAttributes(), sync: true);

            return $old;
        });
    }
}
