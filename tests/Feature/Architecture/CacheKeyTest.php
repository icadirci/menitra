<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use App\Services\Menu\MenuCacheKeys;
use Illuminate\Support\Facades\File;
use ReflectionMethod;
use Tests\TestCase;

/**
 * docs/architecture/caching.md § Tests: every MenuCacheKeys method that takes
 * tenant data produces a key containing the tenant id; every key is prefixed
 * with `qrmenu:`; keys are built nowhere else.
 */
class CacheKeyTest extends TestCase
{
    public function test_every_key_is_prefixed(): void
    {
        foreach ($this->keys(tenantId: 4242) as $method => $key) {
            $this->assertStringStartsWith('qrmenu:', $key, $method);
        }
    }

    public function test_every_key_built_from_tenant_data_contains_the_tenant_id(): void
    {
        foreach ($this->keys(tenantId: 4242) as $method => $key) {
            if ((new ReflectionMethod(MenuCacheKeys::class, $method))->getParameters()[0]->getName() !== 'tenantId') {
                continue;
            }

            $this->assertStringContainsString('tenant:4242:', $key, $method);
        }
    }

    public function test_keys_of_two_tenants_never_collide(): void
    {
        $a = $this->keys(tenantId: 1);
        $b = $this->keys(tenantId: 11);

        foreach ($a as $method => $key) {
            if ((new ReflectionMethod(MenuCacheKeys::class, $method))->getParameters()[0]->getName() === 'tenantId') {
                $this->assertNotSame($key, $b[$method], $method);
            }
        }
    }

    public function test_branch_keys_contain_the_location_id(): void
    {
        foreach ($this->keys(tenantId: 7, locationId: 31337) as $method => $key) {
            $parameters = array_map(fn ($p) => $p->getName(), (new ReflectionMethod(MenuCacheKeys::class, $method))->getParameters());

            if (in_array('locationId', $parameters, true)) {
                $this->assertStringContainsString('location:31337:', $key, $method);
            }
        }
    }

    public function test_cache_keys_are_built_only_by_menu_cache_keys(): void
    {
        $offenders = [];

        foreach (File::allFiles(app_path()) as $file) {
            if ($file->getFilename() === 'MenuCacheKeys.php') {
                continue;
            }

            if (preg_match("/['\"]qrmenu:/", $file->getContents()) === 1) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $offenders);
    }

    /**
     * Every public static key builder called with sample arguments.
     *
     * @return array<string, string>
     */
    private function keys(int $tenantId, int $locationId = 2): array
    {
        $samples = [
            'tenantId' => $tenantId, 'locationId' => $locationId, 'menuId' => 3, 'locale' => 'tr',
            'tenantVersion' => 5, 'locationVersion' => 6, 'host' => 'acme.restano.app', 'slug' => 'acme', 'code' => 'ABCDEFGHJK',
        ];
        $keys = [];

        foreach ((new \ReflectionClass(MenuCacheKeys::class))->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_STATIC) as $method) {
            $arguments = array_map(function ($parameter) use ($samples, $method) {
                $this->assertArrayHasKey($parameter->getName(), $samples, "Add a sample for {$method->getName()}(\${$parameter->getName()}).");

                return $samples[$parameter->getName()];
            }, $method->getParameters());

            $keys[$method->getName()] = $method->invoke(null, ...$arguments);
        }

        $this->assertNotEmpty($keys);

        return $keys;
    }
}
