<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Enums\FaqPlacement;
use App\Http\Controllers\Controller;
use App\Services\Marketing\MarketingContent;
use Illuminate\Contracts\View\View;

class PricingController extends Controller
{
    public function __invoke(MarketingContent $content): View
    {
        $plans = $content->plans();

        return view('marketing.pricing', [
            'plans' => $plans,
            'comparison' => $content->comparison($plans),
            'yearlySaving' => $content->yearlySaving($plans),
            'faqs' => $content->faqs(FaqPlacement::Pricing),
        ]);
    }
}
