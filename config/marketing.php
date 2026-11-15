<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Theme showcase
    |--------------------------------------------------------------------------
    |
    | Theme key (App\Enums\MenuTheme) => slug of the demo business whose live
    | public menu shows that theme (DemoSeeder). Only slugs, never menu data:
    | the showcase links to a demo only while it resolves and is online.
    |
    */

    'showcase' => [
        'classic' => 'kahve-konagi',
        'minimal' => 'noon',
        'bistro' => 'maison-ardoise',
        'cafe' => 'kiln-coffee',
        'grid' => 'petal-bakehouse',
    ],

    /*
    |--------------------------------------------------------------------------
    | Legal pages
    |--------------------------------------------------------------------------
    |
    | The privacy notice and terms are generic drafts written during the
    | unattended run (2026-11-15). While this is true the pages show a visible
    | "draft — legal review pending" notice. Set it to false only after the
    | texts in lang/{locale}/legal.php have been reviewed by a lawyer.
    |
    */

    'legal_review_pending' => (bool) env('MARKETING_LEGAL_REVIEW_PENDING', true),

];
