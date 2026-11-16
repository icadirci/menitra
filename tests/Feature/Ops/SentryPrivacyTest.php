<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Exceptions\MenuUnavailableException;
use App\Models\Tenant;
use App\Support\Monitoring\SentryEventScrubber;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Sentry\Event;
use Sentry\Options;
use Tests\TestCase;

/**
 * Sentry never receives personal data (launch plan Faz 5, security.md):
 * explicit data collection settings, token paths redacted, no tracing.
 */
class SentryPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_dsn_is_configured_in_tests(): void
    {
        $this->assertEmpty(config('sentry.dsn'));
    }

    public function test_the_sdk_options_collect_no_personal_data(): void
    {
        $config = config('sentry');
        unset($config['breadcrumbs'], $config['tracing'], $config['crons']);
        $options = new Options($config);
        $collection = $options->getDataCollection();

        $this->assertNotNull($collection);
        $this->assertFalse($options->shouldSendDefaultPii());
        $this->assertFalse($collection->shouldCollectUserInfo());
        $this->assertSame([], $collection->getHttpBodies());
        $this->assertSame('off', $collection->getStackFrameVariables()->getMode());
        $this->assertFalse($collection->shouldCollectDatabaseQueryData());
        $this->assertFalse($collection->shouldCollectQueues());
        $this->assertSame('off', $collection->getCookies()->getMode());
        $this->assertSame('off', $collection->getUrlQueryParams()->getMode());
        $this->assertSame('allowList', $collection->getHttpHeaders()['request']->getMode());
        $this->assertNull($options->getTracesSampleRate());
        $this->assertNull($options->getProfilesSampleRate());
        $this->assertFalse(config('sentry.breadcrumbs.sql_bindings'));
    }

    public function test_token_paths_are_redacted(): void
    {
        $cases = [
            'https://restano.app/reset-password/abcDEF123' => 'https://restano.app/reset-password/[redacted]',
            'https://restano.app/email/verify/42/5f2b?expires=1&signature=x' => 'https://restano.app/email/verify/[redacted]?expires=1&signature=x',
            'https://restano.app/invitations/tok3n' => 'https://restano.app/invitations/[redacted]',
            'https://restano.app/app/kahve/menus' => 'https://restano.app/app/kahve/menus',
        ];

        foreach ($cases as $url => $expected) {
            $event = Event::createEvent();
            $event->setRequest(['url' => $url, 'method' => 'GET']);

            $this->assertSame($expected, SentryEventScrubber::beforeSend($event)?->getRequest()['url']);
        }
    }

    public function test_events_are_tagged_with_the_current_tenant_id_only(): void
    {
        $event = SentryEventScrubber::beforeSend(Event::createEvent());
        $this->assertArrayNotHasKey('tenant_id', $event->getTags());

        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);

        $event = SentryEventScrubber::beforeSend(Event::createEvent());
        $this->assertSame((string) $tenant->id, $event->getTags()['tenant_id']);
        $this->assertNull($event->getUser());
    }

    public function test_offline_menus_are_not_reported(): void
    {
        $handler = app(ExceptionHandler::class);

        $this->assertFalse($handler->shouldReport(new MenuUnavailableException('Kahve')));
        $this->assertTrue($handler->shouldReport(new RuntimeException('boom')));
    }
}
