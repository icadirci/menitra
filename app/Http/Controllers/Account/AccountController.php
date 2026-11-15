<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\Account\AccountOverview;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * GET /account: minimal, read-only account page (not a dashboard). The tenant
 * is set by ResolveAccountTenant from the user's verified membership.
 */
class AccountController extends Controller
{
    public function __invoke(Request $request, AccountOverview $overview): Response
    {
        return response()
            ->view('account.show', ['account' => $overview->for($request->user())])
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex');
    }
}
