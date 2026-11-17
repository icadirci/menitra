<?php

declare(strict_types=1);

return [

    /*
    | Platform (menu content) locales (code => native name). Tenants enable a
    | subset for their menus; adding a language needs a config entry and
    | lang/{code}/menu.php + errors.php (the strings public menu pages use),
    | no schema change.
    */
    'supported' => [
        'tr' => 'Türkçe',
        'en' => 'English',
        'ar' => 'العربية',
    ],

    /*
    | Interface locales: languages with a complete UI translation (marketing
    | site, auth pages, mails, panels, platform content). A subset of
    | 'supported'; each gets its own marketing route group (tr at /, en at /en).
    */
    'interface' => ['tr', 'en'],

    /*
    | Right-to-left locales: menu pages render with dir="rtl", panel
    | translation tabs with right-to-left inputs.
    */
    'rtl' => ['ar'],

    'default' => 'tr',

];
