<?php

declare(strict_types=1);

return [

    'meta' => [
        'home_title' => 'QR menus for cafés and restaurants',
        'home_description' => 'Turn your menu into a fast, photo-rich page guests open with a single scan. Update prices and sold-out items in seconds.',
        'features_title' => 'Features — QR menus, multi-language menus and themes',
        'features_description' => 'Permanent QR codes, fast phone-first menus, Turkish and English content, themes that fit your place; allergens, add-ons and sold-out status.',
    ],

    'skip_to_content' => 'Skip to content',

    'nav' => [
        'label' => 'Main',
        'features' => 'Features',
        'pricing' => 'Pricing',
        'examples' => 'Menu examples',
        'faq' => 'FAQ',
        'login' => 'Log in',
        'get_started' => 'Get started',
        'open_menu' => 'Open menu',
        'close_menu' => 'Close menu',
    ],

    'language' => [
        'label' => 'Language',
    ],

    'hero' => [
        'badge' => 'Digital menus for cafés & restaurants',
        'title' => 'Your menu, beautifully served at every table.',
        'lead' => 'Restano turns your menu into a fast, photo-rich page guests open with a single scan. Change a price, add a seasonal special or mark something sold out — it’s live before the next order.',
        'cta_primary' => 'Get started',
        'cta_secondary' => 'View pricing',
        'trust' => [
            ':days-day free trial',
            'No credit card needed',
            'No app for guests',
        ],
        'qr_title' => 'Table 7',
        'qr_body' => 'Scan to see our menu',
        'qr_label' => 'Sample QR code',
        'toast_title' => 'Flat white · ₺95',
        'toast_body' => 'Price updated just now',
    ],

    'how' => [
        'eyebrow' => 'How it works',
        'title' => 'From paper menu to QR code in one afternoon.',
        'lead' => 'No designers, no printers, no app downloads. Three steps and your guests are browsing.',
        'steps' => [
            ['title' => 'Add your menu', 'body' => 'Categories, dishes, photos and prices — all from one panel.'],
            ['title' => 'Make it yours', 'body' => 'Choose a theme, then add your logo and colours. It looks like your venue, not like us.'],
            ['title' => 'Print your QR code', 'body' => 'Download a print-ready code for tables, windows and receipts. Guests scan with their camera — no app.'],
        ],
    ],

    'benefits' => [
        'eyebrow' => 'Why Restano',
        'title' => 'Built for the way service actually works.',
        'items' => [
            ['title' => 'Change it in seconds', 'body' => 'New price, new special, sold-out croissants. Update once and every table sees it instantly.'],
            ['title' => 'Guests choose with confidence', 'body' => 'Photos, descriptions and allergen labels answer questions before they reach your staff.'],
            ['title' => 'Every guest, their language', 'body' => 'Offer your menu in Turkish and English. Guests switch with a single tap.'],
            ['title' => 'Unmistakably yours', 'body' => 'Your logo and colours up front. Restano stays quietly in the background.'],
        ],
        'live' => [
            'label' => 'Live menu · sample',
            'synced' => 'Synced',
            'sold_out' => 'Sold out',
            'old_price' => 'Previous price',
            'published' => '2 changes published · visible on every table now',
            'items' => [
                ['name' => 'Turkish coffee', 'note' => 'With lokum & water', 'price' => '₺150', 'old_price' => '₺130', 'dish' => 'turkish', 'bg' => '#E9D9C2'],
                ['name' => 'Pistachio baklava', 'note' => '4 pieces, Gaziantep pistachio', 'price' => null, 'old_price' => null, 'dish' => 'baklava', 'bg' => '#EADFC9'],
                ['name' => 'Brewed tea', 'note' => 'Rize black tea, tulip glass', 'price' => '₺45', 'old_price' => null, 'dish' => 'tea', 'bg' => '#E6D3BD'],
            ],
        ],
    ],

    'features' => [
        'title' => 'Everything a great menu needs. Nothing it doesn’t.',
        'items' => [
            ['icon' => 'qr', 'title' => 'QR code menus', 'body' => 'Codes printed once, never outdated.'],
            ['icon' => 'phone', 'title' => 'Mobile-first', 'body' => 'Designed for thumbs, not shrunk from a desktop site.'],
            ['icon' => 'layers', 'title' => 'Unlimited categories', 'body' => 'Breakfast, coffee, desserts — organised your way.'],
            ['icon' => 'image', 'title' => 'Product photos', 'body' => 'Sharp, fast-loading images for every dish.'],
            ['icon' => 'globe', 'title' => 'Multi-language', 'body' => 'Turkish and English menus from one place.'],
            ['icon' => 'sliders', 'title' => 'Custom appearance', 'body' => 'Themes and colours that match your room.'],
            ['icon' => 'bolt', 'title' => 'Loads in a blink', 'body' => 'Lightweight pages that open fast on café Wi-Fi.'],
            ['icon' => 'sparkle', 'title' => 'Your branding', 'body' => 'Your logo and cover image.'],
        ],
    ],

    // Features page (/en/features). Claims only what the product provides (docs/architecture).
    'features_page' => [
        'eyebrow' => 'Features',
        'title' => 'Everything built around one thing: a menu guests enjoy.',
        'lead' => 'Photos, prices, languages and your brand — in a page that opens quickly on any phone and changes the moment you do.',
        'cta_primary' => 'Start free trial',
        'cta_secondary' => 'See menu examples',

        'qr' => [
            'badge' => 'QR code menus',
            'title' => 'Print one code. Change the menu forever.',
            'body' => 'Your QR code is permanent. Edit dishes, prices and photos as often as you like — the code on the table always opens the latest menu.',
            'points' => [
                'Print-ready downloads in PNG and SVG',
                'A separate code for each branch, or one that opens a specific menu',
                'Printed codes keep working even if your menu address changes',
            ],
            'card_body' => 'Scan with your camera to see today’s menu',
            'card_branch' => 'Kadıköy branch',
            'card_label' => 'Sample QR code card',
            'deep_link' => 'Breakfast menu',
        ],

        'mobile' => [
            'badge' => 'Mobile-friendly · Fast loading',
            'title' => 'Made for one hand and a busy table.',
            'body' => 'Every menu is designed phone-first: big tap targets, categories you can swipe, search within the menu, and pages light enough for patchy café Wi-Fi.',
            'points' => [
                'Opens in the browser — no app, no sign-up',
                'Photos load as guests scroll',
                'Looks right on tablets and desktops too',
            ],
        ],

        'languages' => [
            'badge' => 'Multi-language menus',
            'title' => 'Speak to guests in Turkish and English.',
            'body' => 'Add translations next to each dish. The menu opens in your default language and guests switch to the other with one tap.',
            'points' => [
                'Turkish and English content from one panel',
                'Text without a translation falls back to your default language, so nothing is left blank',
                'Prices, allergens and dietary labels stay the same in every language',
            ],
            // Sample dish in each platform language (illustration; the same in every lang file).
            'demo' => [
                ['locale' => 'tr', 'name' => 'Türk Kahvesi', 'note' => 'Közde pişer, lokum ve su ile.', 'price' => '₺150'],
                ['locale' => 'en', 'name' => 'Turkish coffee', 'note' => 'Brewed over embers, with lokum and water.', 'price' => '₺150'],
            ],
        ],

        'branding' => [
            'badge' => 'Customizable appearance · Your brand',
            'title' => 'A menu that looks like your place, not like software.',
            'body' => 'Start from a theme that suits your venue, then make it yours with your logo, cover photo, colours and fonts.',
            'points' => [
                'Five themes for cafés, bakeries and restaurants',
                'Your brand colours and font pairings chosen for each theme',
                'Use a different theme at any of your branches',
            ],
            'themes_label' => 'Themes',
            'themes_note' => ':count themes · your colours',
            'logo_title' => 'Your logo & cover photo',
            'logo_body' => 'Shown at the top of every menu',
        ],

        'details' => [
            'title' => 'And the details that make service smoother.',
            'items' => [
                ['icon' => 'layers', 'title' => 'Menus and categories', 'body' => 'Separate menus for breakfast, lunch or drinks, with categories in any order you like. Guests switch between them at the top of the menu.', 'tags' => ['Breakfast', 'Coffee', 'Desserts', 'Wine list']],
                ['icon' => 'image', 'title' => 'Product images', 'body' => 'Add photos to dishes and categories. Images load as guests scroll, so the menu opens fast.', 'tags' => ['JPEG', 'PNG', 'WebP']],
                ['icon' => 'tag', 'title' => 'Descriptions & prices', 'body' => 'Ingredients, portion options and add-ons, priced in Turkish lira. Dishes with options show their lowest price as a “from” price.', 'tags' => ['Portions', 'Add-ons', '₺']],
                ['icon' => 'leaf', 'title' => 'Allergens & dietary labels', 'body' => 'Vegan, vegetarian and gluten-free labels and allergen notes that guests can spot at a glance.', 'tags' => ['Vegan', 'Vegetarian', 'Gluten-free']],
                ['icon' => 'clock', 'title' => 'Sold out & serving hours', 'body' => 'Mark a dish sold out indefinitely or until a set time; each branch manages its own status. Give menus serving hours and the one being served right now is highlighted.', 'tags' => ['Sold out', 'Until a set time', 'Per branch']],
                ['icon' => 'sparkle', 'title' => 'Featured & popular', 'body' => 'Highlight today’s specials and bestsellers with badges.', 'tags' => ['Featured', 'Popular']],
            ],
        ],

        'cta' => [
            'title' => 'See your own menu in Restano today.',
            'lead' => ':days days free. No credit card needed.',
            'primary' => 'Get started free',
            'secondary' => 'View pricing',
        ],
    ],

    'gallery' => [
        'eyebrow' => 'Menu designs',
        'title' => 'One platform. Every kind of venue.',
        'lead' => 'From a third-wave espresso bar to a white-tablecloth dining room — every menu carries its own identity, and every one is built for the phone in your guest’s hand.',
        'note' => 'Sample venues',
    ],

    'testimonials' => [
        'title' => 'Owners who stopped reprinting menus.',
    ],

    'pricing' => [
        'eyebrow' => 'Pricing',
        'title' => 'Simple plans. Cancel any time.',
        'lead' => 'Pay monthly, or pay yearly and save.',
        'per_month' => '/ month',
        'most_popular' => 'Most popular',
        'start_trial' => 'Start free trial',
        'yearly_note' => ':price / month billed yearly',
        'yearly_saving' => 'Save :percent%',
    ],

    'faq' => [
        'title' => 'Questions, answered.',
        'lead' => 'Something else on your mind? Start your free trial and we’ll help you get your first menu live.',
    ],

    'cta' => [
        'title' => 'Your next menu change takes ten seconds.',
        'lead' => 'Start your :days-day free trial. Your first QR code is ready today.',
        'primary' => 'Get started free',
    ],

    'footer' => [
        'tagline' => 'QR menus for cafés, restaurants and bakeries. Print once, update forever.',
        'product' => 'Product',
        'account' => 'Account',
        'register' => 'Create account',
        'login' => 'Log in',
        'reset' => 'Reset password',
        'copyright' => '© :year Restano. Made for people who feed people.',
    ],

    'themes' => [
        'cafe' => [
            'venue' => 'Kiln Coffee',
            'kind' => 'Specialty coffee',
            'section' => 'Espresso',
            'items' => [
                ['name' => 'Flat white', 'note' => 'Double ristretto, silky milk', 'price' => '₺95', 'dish' => 'latte'],
                ['name' => 'Croissant', 'note' => 'All-butter, baked daily', 'price' => '₺80', 'dish' => 'croissant'],
                ['name' => 'Cold brew', 'note' => 'Steeped for 18 hours', 'price' => '₺110', 'dish' => 'coldbrew'],
            ],
        ],
        'bistro' => [
            'venue' => 'Maison Ardoise',
            'kind' => 'Fine dining',
            'section' => 'Plats',
            'items' => [
                ['name' => 'Filet de bœuf', 'note' => 'Green peppercorn, Cognac jus', 'price' => '₺1,450', 'dish' => 'plate'],
                ['name' => 'Tartare', 'note' => 'Hand-cut beef', 'price' => '₺890', 'dish' => 'tartare'],
                ['name' => 'Fondant', 'note' => 'Dark chocolate', 'price' => '₺420', 'dish' => 'fondant'],
            ],
        ],
        'minimal' => [
            'venue' => 'noon.',
            'kind' => 'Minimalist café',
            'section' => 'Coffee',
            'items' => [
                ['name' => 'Latte', 'note' => 'Seasonal espresso', 'price' => '₺105', 'dish' => 'latte'],
                ['name' => 'Avocado toast', 'note' => 'Sourdough', 'price' => '₺240', 'dish' => 'toast'],
                ['name' => 'Salad', 'note' => 'Seasonal greens', 'price' => '₺260', 'dish' => 'salad'],
            ],
        ],
        'grid' => [
            'venue' => 'Petal Bakehouse',
            'kind' => 'Dessert & bakery',
            'section' => 'Sweets',
            'items' => [
                ['name' => 'Cupcake', 'note' => 'Strawberry frosting', 'price' => '₺120', 'dish' => 'cupcake'],
                ['name' => 'Macarons', 'note' => 'Box of three', 'price' => '₺150', 'dish' => 'macaron'],
                ['name' => 'Cheesecake', 'note' => 'With raspberry', 'price' => '₺190', 'dish' => 'cake'],
            ],
        ],
        'classic' => [
            'venue' => 'Kahve Konağı',
            'kind' => 'Turkish café',
            'section' => 'Hot drinks',
            'items' => [
                ['name' => 'Turkish coffee', 'note' => 'Brewed over embers', 'price' => '₺150', 'dish' => 'turkish'],
                ['name' => 'Brewed tea', 'note' => 'Tulip glass', 'price' => '₺45', 'dish' => 'tea'],
                ['name' => 'Simit', 'note' => 'From the stone oven', 'price' => '₺35', 'dish' => 'simit'],
            ],
        ],
    ],

];
