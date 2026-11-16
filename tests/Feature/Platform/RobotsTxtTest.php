<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Tests\TestCase;

class RobotsTxtTest extends TestCase
{
    public function test_non_production_environments_disallow_everything(): void
    {
        $response = $this->get('http://localhost/robots.txt')->assertOk();

        $this->assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));
        $this->assertSame("User-agent: *\nDisallow: /\n", $response->getContent());
        $this->assertSame([], $response->headers->all('set-cookie'));
    }

    public function test_production_allows_crawling_and_lists_the_sitemap(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $content = (string) $this->get('http://localhost/robots.txt')->assertOk()->getContent();

        $this->assertStringContainsString("Disallow:\n", $content);
        $this->assertStringNotContainsString('Disallow: /', $content);
        $this->assertStringContainsString('Sitemap: http://localhost/sitemap.xml', $content);
    }

    public function test_robots_is_served_on_tenant_subdomains_too(): void
    {
        $this->get('http://acme.localhost/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /', false);
    }
}
