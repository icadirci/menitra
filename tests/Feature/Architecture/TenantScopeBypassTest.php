<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use App\Tenancy\TenantAgnostic;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\TestCase;

/**
 * withoutTenantScope() may appear only in the documented places
 * (docs/architecture/tenancy.md § Allowed scope bypasses). The TenantScope
 * can't be removed any other way (withoutGlobalScope(s)) either.
 */
class TenantScopeBypassTest extends TestCase
{
    /**
     * Files allowed to call withoutTenantScope(), with the documented reason.
     */
    private const array ALLOWED = [
        'app/Models/Concerns/BelongsToTenant.php' => 'defines it',
        'app/Tenancy/ResolvedTenantFactory.php' => 'tenant resolver: the tenant is not known yet',
        'app/Services/Menu/QrCodeResolver.php' => 'the QR code identifies the tenant',
        'app/Services/Team/InvitationTokenResolver.php' => 'the invitation token identifies the tenant',
        'app/Console/Commands/ProcessSubscriptionLifecycle.php' => 'TenantAgnostic command',
    ];

    /**
     * Directories allowed to call withoutTenantScope(): the admin area
     * (platform management, read-only cross-tenant queries; see the admin
     * bypass rules in tenancy.md).
     */
    private const array ALLOWED_PREFIXES = [
        'app/Filament/Admin/Resources/Tenants/' => 'admin: tenant management',
        'app/Services/Admin/' => 'admin: platform management services',
    ];

    public function test_without_tenant_scope_is_used_only_in_documented_places(): void
    {
        $offenders = [];

        foreach (File::allFiles(app_path()) as $file) {
            $relative = 'app/'.str_replace('\\', '/', $file->getRelativePathname());

            if (str_contains($this->code($file->getContents()), 'withoutTenantScope(') && ! $this->isAllowed($relative)) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame([], $offenders, 'Undocumented tenant scope bypass in: '.implode(', ', $offenders));
    }

    public function test_commands_and_jobs_that_bypass_the_scope_are_tenant_agnostic(): void
    {
        foreach (array_keys(self::ALLOWED) as $path) {
            if (! Str::startsWith($path, ['app/Console/', 'app/Jobs/'])) {
                continue;
            }

            $class = 'App\\'.str_replace(['app/', '/', '.php'], ['', '\\', ''], $path);
            $this->assertTrue((new ReflectionClass($class))->implementsInterface(TenantAgnostic::class), "{$class} must implement TenantAgnostic.");
        }
    }

    public function test_the_tenant_scope_is_never_removed_through_without_global_scope(): void
    {
        $offenders = [];

        foreach (File::allFiles(app_path()) as $file) {
            $relative = 'app/'.str_replace('\\', '/', $file->getRelativePathname());

            if ($relative === 'app/Models/Concerns/BelongsToTenant.php') {
                continue;
            }

            $contents = $this->code($file->getContents());

            // withoutGlobalScopes() without arguments would drop TenantScope too.
            if (str_contains($contents, 'TenantScope::class') && str_contains($contents, 'withoutGlobalScope')
                || preg_match('/withoutGlobalScopes\(\s*\)/', $contents) === 1) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame([], $offenders, 'Tenant scope removed in: '.implode(', ', $offenders));
    }

    private function isAllowed(string $relative): bool
    {
        return array_key_exists($relative, self::ALLOWED)
            || Str::startsWith($relative, array_keys(self::ALLOWED_PREFIXES));
    }

    /**
     * PHP source without comments (docblocks may mention the method).
     */
    private function code(string $source): string
    {
        $code = '';

        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $code .= is_array($token) ? $token[1] : $token;
        }

        return $code;
    }
}
