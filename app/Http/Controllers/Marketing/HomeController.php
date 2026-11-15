<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Enums\FaqPlacement;
use App\Http\Controllers\Controller;
use App\Services\Marketing\MarketingContent;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(MarketingContent $content): View
    {
        return view('marketing.home', [
            'plans' => $content->plans(),
            'faqs' => $content->faqs(FaqPlacement::Home),
            'testimonials' => $content->testimonials(),
        ]);
    }
}
