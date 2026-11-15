<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\FaqPlacement;
use App\Filament\Admin\Resources\Faqs\Pages\CreateFaq;
use App\Filament\Admin\Resources\Faqs\Pages\EditFaq;
use App\Filament\Admin\Resources\Testimonials\Pages\CreateTestimonial;
use App\Models\Faq;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\Translations\TranslationWriter;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * /admin FAQ and testimonial resources: any platform role, nobody else.
 */
class MarketingContentAdminTest extends TestCase
{
    use RefreshDatabase;

    private function asPlatform(User $user): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel('admin');
    }

    public function test_platform_roles_can_open_the_content_resources(): void
    {
        foreach ([User::factory()->superAdmin()->create(), User::factory()->platformStaff()->create()] as $user) {
            $this->actingAs($user)->get('/admin/faqs')->assertOk()->assertSee(__('panels.admin.faqs.plural'));
            $this->actingAs($user)->get('/admin/testimonials')->assertOk();
            $this->actingAs($user)->get('/admin/faqs/create')->assertOk();
        }
    }

    public function test_business_users_cannot_reach_admin_content(): void
    {
        $owner = TenantMembership::factory()->for(Tenant::factory())->create()->user;

        $this->actingAs($owner)->get('/admin/faqs')->assertForbidden();
        $this->actingAs($owner)->get('/admin/testimonials')->assertForbidden();
    }

    public function test_a_faq_is_created_with_translations(): void
    {
        $this->asPlatform(User::factory()->platformStaff()->create());

        Livewire::test(CreateFaq::class)
            ->fillForm([
                'placement' => FaqPlacement::Pricing->value,
                'is_published' => true,
                'translations' => [
                    'tr' => ['question' => 'Fatura kesiyor musunuz?', 'answer' => 'Evet, her ödeme için.'],
                    'en' => ['question' => 'Do you issue invoices?', 'answer' => 'Yes, for every payment.'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $faq = Faq::query()->with('translations')->sole();
        $this->assertSame(FaqPlacement::Pricing, $faq->placement);
        $this->assertSame('Do you issue invoices?', $faq->translate('en')->question);
        $this->assertSame('Fatura kesiyor musunuz?', $faq->translate('tr')->question);
    }

    public function test_the_default_locale_is_required_and_other_locales_are_optional(): void
    {
        $this->asPlatform(User::factory()->platformStaff()->create());

        Livewire::test(CreateFaq::class)
            ->fillForm([
                'placement' => FaqPlacement::Home->value,
                'translations' => [
                    'tr' => ['question' => '', 'answer' => ''],
                    'en' => ['question' => 'Only English?', 'answer' => 'No.'],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors(['translations.tr.question', 'translations.tr.answer']);

        $this->assertSame(0, Faq::query()->count());

        Livewire::test(CreateFaq::class)
            ->fillForm([
                'placement' => FaqPlacement::Home->value,
                'translations' => [
                    'tr' => ['question' => 'Sadece Türkçe', 'answer' => 'Olur.'],
                    'en' => ['question' => '', 'answer' => ''],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(['tr'], Faq::query()->sole()->translations()->pluck('locale')->all());
    }

    public function test_editing_a_faq_fills_and_updates_translations(): void
    {
        $this->asPlatform(User::factory()->superAdmin()->create());
        $faq = Faq::factory()->withTranslations([
            'tr' => ['question' => 'Eski soru', 'answer' => 'Eski cevap'],
            'en' => ['question' => 'Old question', 'answer' => 'Old answer'],
        ])->create();

        Livewire::test(EditFaq::class, ['record' => $faq->getRouteKey()])
            ->assertSchemaStateSet(['translations.en.question' => 'Old question'])
            ->fillForm(['translations.en.question' => '', 'translations.en.answer' => '', 'translations.tr.question' => 'Yeni soru'])
            ->call('save')
            ->assertHasNoFormErrors();

        $faq->refresh()->load('translations');
        $this->assertSame('Yeni soru', $faq->translate('tr')->question);
        $this->assertNull($faq->translations->firstWhere('locale', 'en'), 'Clearing a non-default locale removes its row.');
    }

    public function test_a_testimonial_is_created(): void
    {
        $this->asPlatform(User::factory()->platformStaff()->create());

        Livewire::test(CreateTestimonial::class)
            ->fillForm([
                'author_name' => 'Elif Aydın',
                'is_published' => true,
                'translations' => [
                    'tr' => ['quote' => 'Artık menü bastırmıyoruz.', 'author_role' => 'Sahibi, Kiln Coffee'],
                    'en' => ['quote' => '', 'author_role' => ''],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $testimonial = Testimonial::query()->sole();
        $this->assertSame('EA', $testimonial->initials());
        $this->assertSame('Sahibi, Kiln Coffee', $testimonial->translate('tr')->author_role);
    }

    public function test_platform_translation_writer_refuses_tenant_owned_models(): void
    {
        $location = Location::factory()->for(Tenant::factory())->create();

        $this->expectException(InvalidArgumentException::class);

        app(TranslationWriter::class)->syncPlatform($location, ['tr' => ['name' => 'X']]);
    }
}
