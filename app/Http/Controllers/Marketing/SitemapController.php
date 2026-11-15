<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Support\MarketingUrl;
use Illuminate\Http\Response;

/**
 * /sitemap.xml (central domain): every public marketing page in every
 * platform locale, with hreflang alternates. Tenant menus are not listed.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        return response()
            ->view('marketing.sitemap', ['pages' => array_map(MarketingUrl::alternates(...), MarketingUrl::PAGES)])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
