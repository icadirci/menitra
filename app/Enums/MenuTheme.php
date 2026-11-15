<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\ColorContrast;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\Rule;

/**
 * Theme registry (docs/architecture/themes.md). Themes are code: Blade views
 * in resources/views/themes/{key}/ and one stylesheet each.
 */
enum MenuTheme: string
{
    case Classic = 'classic';
    case Minimal = 'minimal';
    case Bistro = 'bistro';
    case Cafe = 'cafe';
    case Grid = 'grid';

    public function view(): string
    {
        return "themes.{$this->value}.menu";
    }

    public function locationView(): string
    {
        return "themes.{$this->value}.location";
    }

    public function stylesheet(): string
    {
        return "resources/css/themes/{$this->value}.css";
    }

    /**
     * Default settings. Tenant settings (theme_settings) are merged over
     * these by ThemeSettings::fromStored(). Colours become CSS custom
     * properties, the font pair --menu-font-heading/--menu-font-body, layout
     * options are read by the theme's views.
     *
     * @return array{colors: array<string, string>, font_pair: string, layout: array<string, bool|string>}
     */
    public function defaults(): array
    {
        return match ($this) {
            // Design reference: MenuKonak / DetailKonak.
            self::Classic => [
                'colors' => [
                    'primary' => '#23443A', 'accent' => '#9C4A2F', 'bg' => '#F4EBDD', 'surface' => '#FBF5EA',
                    'text' => '#2B1D16', 'muted' => '#6B5446', 'ornament' => '#C49A45',
                ],
                'font_pair' => MenuFontPair::YoungSerifFigtree->value,
                'layout' => ['show_images' => true, 'show_featured' => true, 'image_shape' => 'arch'],
            ],
            // Placeholders until the theme is built (Phase 7); like every
            // theme's defaults they must pass validator() (contrast included).
            default => [
                'colors' => [
                    'primary' => '#1F4D3B', 'accent' => '#9A5B13', 'bg' => '#FAF7F2', 'surface' => '#FFFFFF',
                    'text' => '#1C1917', 'muted' => '#57534E', 'ornament' => '#E7A33E',
                ],
                'font_pair' => MenuFontPair::BricolageInter->value,
                'layout' => [],
            ],
        };
    }

    /**
     * Font pairs this theme offers (its stylesheet declares their @font-face).
     *
     * @return list<MenuFontPair>
     */
    public function fontPairs(): array
    {
        return match ($this) {
            self::Classic => [MenuFontPair::YoungSerifFigtree, MenuFontPair::BricolageInter, MenuFontPair::System],
            default => [MenuFontPair::BricolageInter, MenuFontPair::System],
        };
    }

    /**
     * Allowed values of the non-boolean layout options. Boolean options are
     * the layout defaults that are booleans.
     *
     * @return array<string, list<string>>
     */
    public function layoutChoices(): array
    {
        return match ($this) {
            self::Classic => ['image_shape' => ['arch', 'rounded']],
            default => [],
        };
    }

    /**
     * Colour pairs [foreground, background] that carry text and must reach
     * WCAG AA (ColorContrast::AA). `ornament` is decorative only.
     *
     * @return list<array{string, string}>
     */
    public function contrastPairs(): array
    {
        return [
            ['text', 'bg'], ['text', 'surface'],
            ['muted', 'bg'], ['muted', 'surface'],
            ['primary', 'bg'], ['primary', 'surface'],
            ['accent', 'bg'],
            // Badges: surface-coloured text on an accent background.
            ['surface', 'accent'],
        ];
    }

    /**
     * Validation rules for theme_settings.settings.
     *
     * @return array<string, list<mixed>>
     */
    public function settingsRules(): array
    {
        $defaults = $this->defaults();
        $rules = [
            'colors' => ['required', 'array'],
            'font_pair' => ['required', 'string', Rule::in(array_map(fn (MenuFontPair $pair): string => $pair->value, $this->fontPairs()))],
            'layout' => [$defaults['layout'] === [] ? 'nullable' : 'required', 'array'],
        ];

        foreach (array_keys($defaults['colors']) as $name) {
            $rules["colors.{$name}"] = ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        }

        foreach ($defaults['layout'] as $name => $default) {
            $rules["layout.{$name}"] = is_bool($default)
                ? ['required', 'boolean']
                : ['required', 'string', Rule::in($this->layoutChoices()[$name] ?? [])];
        }

        return $rules;
    }

    /**
     * settingsRules() plus the contrast check. Error keys are relative to the
     * settings array (e.g. `colors.text`).
     *
     * @param  array<string, mixed>  $settings
     */
    public function validator(array $settings): ValidatorContract
    {
        $validator = Validator::make($settings, $this->settingsRules());

        $validator->after(function (ValidatorContract $validator) use ($settings): void {
            if ($validator->errors()->hasAny(array_map(fn (string $name): string => "colors.{$name}", array_keys($this->defaults()['colors'])))) {
                return;
            }

            foreach ($this->contrastPairs() as [$foreground, $background]) {
                $ratio = ColorContrast::ratio($settings['colors'][$foreground], $settings['colors'][$background]);

                if ($ratio < ColorContrast::AA) {
                    $validator->errors()->add("colors.{$foreground}", __('panels.app.theme.errors.contrast', [
                        'foreground' => __("panels.app.theme.colors.{$foreground}"),
                        'background' => __("panels.app.theme.colors.{$background}"),
                        'ratio' => number_format($ratio, 2),
                        'minimum' => ColorContrast::AA,
                    ]));
                }
            }
        });

        return $validator;
    }

    /**
     * Settings in canonical form: only known keys, upper-case colours,
     * real booleans. Expects input that passed validator().
     *
     * @param  array<string, mixed>  $settings
     * @return array{colors: array<string, string>, font_pair: string, layout: array<string, bool|string>}
     */
    public function normalize(array $settings): array
    {
        $defaults = $this->defaults();
        $normalized = ['colors' => [], 'font_pair' => (string) $settings['font_pair'], 'layout' => []];

        foreach (array_keys($defaults['colors']) as $name) {
            $normalized['colors'][$name] = strtoupper((string) $settings['colors'][$name]);
        }

        foreach ($defaults['layout'] as $name => $default) {
            $value = $settings['layout'][$name];
            $normalized['layout'][$name] = is_bool($default) ? filter_var($value, FILTER_VALIDATE_BOOLEAN) : (string) $value;
        }

        return $normalized;
    }

    /**
     * Built = both views exist. Themes without views can't be selected and
     * render as `classic` until Phase 7.
     */
    public function isBuilt(): bool
    {
        return View::exists($this->view()) && View::exists($this->locationView());
    }

    public function label(): string
    {
        return __("panels.app.theme.themes.{$this->value}.name");
    }

    public function requiredFeature(): ?PlanFeature
    {
        return $this === self::Classic ? null : PlanFeature::PremiumThemes;
    }
}
