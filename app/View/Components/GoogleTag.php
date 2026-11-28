<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Support\GoogleTag as Tag;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\View\Component;

/**
 * <x-google-tag />: the Google Ads tag config and the cookie consent banner.
 * Placed only on the marketing pages, /register and /email/verify. Renders
 * nothing while no tag id is configured. The tag itself is loaded by
 * resources/js/lib/google-tag.js after consent (no inline script).
 */
final class GoogleTag extends Component
{
    public function __construct(private readonly Tag $tag, private readonly Request $request) {}

    public function shouldRender(): bool
    {
        return $this->tag->enabled();
    }

    public function render(): View
    {
        $this->tag->markRendered($this->request);

        return view('components.google-tag', [
            'tagId' => $this->tag->id(),
            'signupConversion' => $this->request->hasSession() && $this->tag->pullSignupConversion($this->request->session())
                ? $this->tag->signupConversion()
                : null,
        ]);
    }
}
