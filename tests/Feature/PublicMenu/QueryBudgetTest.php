<?php

declare(strict_types=1);

namespace Tests\Feature\PublicMenu;

use App\Enums\ExtraSelectionType;
use App\Models\ExtraGroup;
use App\Models\Location;
use App\Models\MenuCategory;
use App\Models\Tenant;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsMenus;
use Tests\TestCase;

/**
 * Phase 8 performance pass: a cold (uncached) menu render runs a constant
 * number of queries whatever the menu size (no N+1); warm renders run none;
 * the showcase resolves demo businesses from the resolution cache.
 */
class QueryBudgetTest extends TestCase
{
    use BuildsMenus;
    use RefreshDatabase;

    private function addItems(Tenant $tenant, MenuCategory $category, ExtraGroup $extras, int $count, string $prefix): void
    {
        foreach (range(1, $count) as $i) {
            $item = $this->item($category, "{$prefix} {$i}", null, ['is_popular' => $i === 1, 'is_featured' => $i === 1], "{$prefix} EN {$i}");

            $this->inTenant($tenant, function () use ($item, $extras, $i): void {
                foreach ([['Küçük', 9000, true], ['Büyük', 12000, false]] as $position => [$name, $price, $default]) {
                    $variant = $item->variants()->create(['price' => $price, 'is_default' => $default, 'is_available' => true, 'sort_order' => $position]);
                    $variant->translations()->create(['locale' => 'tr', 'name' => $name]);
                }

                $item->allergens()->create(['allergen' => 'milk']);
                $item->dietaryLabels()->create(['label' => $i % 2 === 0 ? 'vegan' : 'vegetarian']);
                $item->extraGroups()->attach($extras->getKey(), ['sort_order' => 0]);
            });
        }
    }

    private function coldQueries(string $path): int
    {
        Cache::flush();
        $count = 0;
        DB::listen(function () use (&$count): void {
            $count++;
        });

        $this->get($path)->assertOk();
        $queries = $count;
        // The listener stays registered; park its counter so later calls in
        // the same test don't depend on it.
        $count = PHP_INT_MIN;

        return $queries;
    }

    public function test_cold_menu_queries_do_not_grow_with_the_menu(): void
    {
        $tenant = $this->subscribedTenant('butce', ['max_items' => 500]);
        $location = $this->location($tenant, 'merkez', true);
        $menu = $this->menu($tenant, 'menu', [$location]);
        $first = $this->category($menu, 'Kahveler');
        $second = $this->category($menu, 'Tatlılar');
        $extras = $this->inTenant($tenant, function (): ExtraGroup {
            $group = ExtraGroup::query()->create(['selection_type' => ExtraSelectionType::Single, 'min_select' => 0, 'max_select' => 1, 'is_required' => false]);
            $group->translations()->create(['locale' => 'tr', 'name' => 'Şeker']);
            $extra = $group->extras()->create(['price_delta' => 0, 'is_available' => true]);
            $extra->translations()->create(['locale' => 'tr', 'name' => 'Sade']);

            return $group;
        });

        $this->addItems($tenant, $first, $extras, 2, 'Kahve');
        $small = $this->coldQueries('/m/butce/merkez/menu');

        $this->addItems($tenant, $first, $extras, 10, 'Latte');
        $this->addItems($tenant, $second, $extras, 10, 'Kek');
        $large = $this->coldQueries('/m/butce/merkez/menu');

        $this->assertSame($small, $large, "Cold render grew from {$small} to {$large} queries with 10x the items (N+1).");
        // 2026-11-15: 38 (resolution 7, location/menus 13, content tree 13, a
        // few repeated tenant/translation loads). Cold renders are rare: the
        // snapshot stays cached for 24h and warm requests run no query.
        $this->assertLessThanOrEqual(40, $large, "Cold menu render query budget exceeded ({$large}).");
    }

    public function test_warm_menu_and_location_pages_run_no_query(): void
    {
        $this->seed(DemoSeeder::class);

        foreach (['/m/kahve-konagi/moda/menu', '/m/kahve-konagi/moda', '/m/kahve-konagi', '/m/petal-bakehouse/merkez/menu'] as $path) {
            $this->get($path)->assertOk(); // warm the caches

            $count = 0;
            DB::listen(function () use (&$count): void {
                $count++;
            });
            $this->get($path)->assertOk();
            $this->assertSame(0, $count, "{$path} ran {$count} queries with a warm cache.");
            $count = PHP_INT_MIN;
        }
    }

    public function test_warm_showcase_does_not_query_tenants(): void
    {
        $this->seed(DemoSeeder::class);
        $this->get('/temalar')->assertOk();

        $tenantQueries = [];
        DB::listen(function ($query) use (&$tenantQueries): void {
            if (preg_match('/\bfrom "(tenants|subscriptions|tenant_translations)"/', $query->sql) === 1) {
                $tenantQueries[] = $query->sql;
            }
        });

        $this->get('/temalar')->assertOk();
        $this->assertSame([], $tenantQueries);
    }
}
