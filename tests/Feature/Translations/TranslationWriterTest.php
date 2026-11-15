<?php

declare(strict_types=1);

namespace Tests\Feature\Translations;

use App\Exceptions\CrossTenantWriteException;
use App\Models\Location;
use App\Models\Menu;
use App\Models\Tenant;
use App\Services\Translations\TranslationWriter;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Fixtures\Livewire\TranslatableFormFixture;
use Tests\TestCase;

class TranslationWriterTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        config(['locales.supported' => ['tr' => 'Türkçe', 'en' => 'English', 'de' => 'Deutsch']]);

        $this->tenant = Tenant::factory()->create();
        DB::table('tenant_locales')->insert(['tenant_id' => $this->tenant->id, 'locale' => 'en', 'sort_order' => 1]);
        $this->location = Location::factory()->for($this->tenant)->create(); // factory adds a "tr" name

        $this->app->make(TenantContext::class)->set($this->tenant);
    }

    private function writer(): TranslationWriter
    {
        return $this->app->make(TranslationWriter::class);
    }

    /**
     * @return array<string, string|null>
     */
    private function translations(Location $location): array
    {
        return $location->translations()->pluck('name', 'locale')->all();
    }

    public function test_it_creates_and_updates_rows_for_enabled_locales(): void
    {
        $this->writer()->sync($this->location, [
            'tr' => ['name' => '  Merkez Şube ', 'description' => ''],
            'en' => ['name' => 'Main branch', 'description' => 'By the sea'],
        ]);

        $this->assertEquals(['tr' => 'Merkez Şube', 'en' => 'Main branch'], $this->translations($this->location));
        $this->assertNull($this->location->translations()->where('locale', 'tr')->value('description'));
        $this->assertSame('By the sea', $this->location->translations()->where('locale', 'en')->value('description'));
    }

    public function test_the_default_locale_is_required(): void
    {
        foreach ([['en' => ['name' => 'Main']], ['tr' => ['name' => ' '], 'en' => ['name' => 'Main']]] as $input) {
            try {
                $this->writer()->sync($this->location, $input);
                $this->fail('Missing default name accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('translations.tr.name', $exception->errors());
            }
        }
    }

    public function test_a_partially_filled_other_locale_needs_its_required_fields(): void
    {
        $this->expectException(ValidationException::class);

        $this->writer()->sync($this->location, [
            'tr' => ['name' => 'Merkez'],
            'en' => ['name' => '', 'description' => 'Only a description'],
        ]);
    }

    public function test_clearing_an_other_locale_removes_its_row(): void
    {
        $this->location->translations()->create(['locale' => 'en', 'name' => 'Main']);

        $this->writer()->sync($this->location, ['tr' => ['name' => 'Merkez'], 'en' => ['name' => '', 'description' => null]]);

        $this->assertSame(['tr' => 'Merkez'], $this->translations($this->location));
    }

    public function test_locales_that_are_not_enabled_are_rejected_and_their_rows_untouched(): void
    {
        $this->location->translations()->create(['locale' => 'de', 'name' => 'Hauptfiliale']);

        try {
            $this->writer()->sync($this->location, ['tr' => ['name' => 'Merkez'], 'de' => ['name' => 'Geändert']]);
            $this->fail('A disabled locale was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('translations.de', $exception->errors());
        }

        // A valid write without "de" leaves the stored "de" row alone.
        $this->writer()->sync($this->location, ['tr' => ['name' => 'Merkez']]);

        $this->assertSame('Hauptfiliale', $this->location->translations()->where('locale', 'de')->value('name'));
    }

    public function test_only_fillable_translation_fields_are_written(): void
    {
        $this->writer()->sync($this->location, ['tr' => ['name' => 'Merkez', 'location_id' => 999_999, 'id' => 1]]);

        $this->assertSame(['tr' => 'Merkez'], $this->translations($this->location));
    }

    public function test_it_refuses_records_of_another_tenant(): void
    {
        $context = $this->app->make(TenantContext::class);
        $context->clear();
        $foreign = Location::factory()->create();
        $context->set($this->tenant);

        $this->expectException(CrossTenantWriteException::class);
        $this->writer()->sync($foreign, ['tr' => ['name' => 'Ele geçirildi']]);
    }

    public function test_it_works_for_the_tenant_itself_and_other_entities(): void
    {
        $menu = Menu::create(['slug' => 'aksam', 'type' => 'dinner']);

        $this->writer()->sync($menu, ['tr' => ['name' => 'Akşam'], 'en' => ['name' => 'Dinner']]);
        $this->writer()->sync($this->tenant, ['tr' => ['name' => 'Kafe', 'tagline' => 'Taze kahve']]);

        $this->assertSame('Dinner', $menu->translate('en')->name);
        $this->assertSame('Taze kahve', $this->tenant->translations()->where('locale', 'tr')->value('tagline'));
    }

    public function test_the_form_has_one_tab_per_enabled_locale_and_requires_only_the_default(): void
    {
        Livewire::test(TranslatableFormFixture::class, ['location' => $this->location])
            ->assertFormFieldExists('translations.tr.name')
            ->assertFormFieldExists('translations.en.name')
            ->assertFormFieldDoesNotExist('translations.de.name')
            ->fillForm(['translations.tr.name' => '', 'translations.en.name' => ''])
            ->call('save')
            ->assertHasFormErrors(['translations.tr.name' => 'required'])
            ->assertHasNoFormErrors(['translations.en.name']);
    }

    public function test_the_form_is_filled_from_and_saved_through_the_writer(): void
    {
        $this->location->translations()->where('locale', 'tr')->update(['name' => 'Eski ad']);

        Livewire::test(TranslatableFormFixture::class, ['location' => $this->location])
            ->assertFormSet(['translations.tr.name' => 'Eski ad', 'translations.en.name' => null])
            ->fillForm(['translations.tr.name' => 'Yeni ad', 'translations.en.name' => 'New name'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals(['tr' => 'Yeni ad', 'en' => 'New name'], $this->translations($this->location));
    }
}
