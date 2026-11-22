<?php

declare(strict_types=1);

/*
| HTTP error pages (resources/views/errors). Never mention a business: the
| same texts are shown on tenant menu hosts.
*/

return [
    'code' => 'Error :code',
    'home' => 'Back to the home page',
    'platform_home' => 'Discover Menitra',

    '403' => [
        'title' => 'You don’t have access to this page',
        'body' => 'You are not allowed to view this page. Make sure you are signed in with the right account.',
    ],
    '404' => [
        'title' => 'Page not found',
        'body' => 'The page you are looking for may have been moved, removed or never existed.',
    ],
    '419' => [
        'title' => 'Your session has expired',
        'body' => 'For your security this page has expired. Please refresh the page and try again.',
    ],
    '429' => [
        'title' => 'Too many requests',
        'body' => 'You sent too many requests in a short time. Please wait a moment and try again.',
    ],
    '500' => [
        'title' => 'Something went wrong',
        'body' => 'An unexpected error occurred. Please try again shortly.',
    ],
    '503' => [
        'title' => 'We’ll be right back',
        'body' => 'Menitra is undergoing brief maintenance. We’ll be back in a few minutes.',
    ],
    'client' => [
        'title' => 'The request could not be processed',
        'body' => 'Your request could not be processed. Please check the address and try again.',
    ],
    'server' => [
        'title' => 'Something went wrong',
        'body' => 'An unexpected error occurred. Please try again shortly.',
    ],
];
