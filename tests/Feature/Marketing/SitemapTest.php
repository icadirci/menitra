<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Support\MarketingUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use SimpleXMLElement;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_lists_every_marketing_page_in_every_locale_with_alternates(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk();

        $this->assertStringStartsWith('application/xml', (string) $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $response->getContent());

        $xml = new SimpleXMLElement($response->getContent());
        $xml->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xml->registerXPathNamespace('x', 'http://www.w3.org/1999/xhtml');
        $locs = array_map('strval', $xml->xpath('//s:url/s:loc'));

        foreach (MarketingUrl::PAGES as $page) {
            foreach (MarketingUrl::alternates($page) as $url) {
                $this->assertContains($url, $locs);
            }
        }

        $this->assertCount(count(MarketingUrl::PAGES) * count(config('locales.supported')), $locs);
        $this->assertContains(url('/en/themes'), array_map('strval', $xml->xpath('//s:url[s:loc="'.url('/temalar').'"]/x:link[@hreflang="en"]/@href')));
        $this->assertCount(count(MarketingUrl::PAGES) * count(config('locales.supported')), $xml->xpath('//x:link[@hreflang="x-default"]'));
    }

    public function test_every_marketing_get_route_is_in_the_page_list(): void
    {
        $pages = [];

        foreach (Route::getRoutes() as $route) {
            if (preg_match('/^marketing\.[a-z]{2}\.([a-z_]+)$/', (string) $route->getName(), $match) === 1 && in_array('GET', $route->methods(), true)) {
                $pages[] = $match[1];
            }
        }

        $this->assertEqualsCanonicalizing(MarketingUrl::PAGES, array_values(array_unique($pages)));
    }

    public function test_sitemap_is_central_domain_only(): void
    {
        $this->get('http://yaman.'.config('tenancy.central_domain').'/sitemap.xml')->assertNotFound();
    }
}
