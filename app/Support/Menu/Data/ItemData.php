<?php

declare(strict_types=1);

namespace App\Support\Menu\Data;

/**
 * A visible item. Sold-out data are raw facts; themes ask $live->isSoldOut($item).
 */
final readonly class ItemData
{
    public function __construct(
        public string $publicId,
        public string $name,
        public ?string $description,
        public ?string $imageUrl,
        public ?PriceData $price,
        public ?PriceData $fromPrice,
        public bool $isFeatured,
        public bool $isPopular,
        public bool $isAvailable,
        public bool $soldOutIndefinitely,
        public ?int $soldOutUntil,
        public ?int $calories,
        public ?int $preparationMinutes,
        /** @var list<LabelData> */ public array $allergens,
        /** @var list<LabelData> */ public array $dietaryLabels,
        /** @var list<VariantData> */ public array $variants,
        /** @var list<ExtraGroupData> */ public array $extraGroups,
    ) {}

    /**
     * What the client-side order list needs (only rendered when the theme's
     * `cart` option is on): ids, names and prices in minor units. Allow-listed
     * snapshot data only.
     *
     * @return array{id: string, name: string, price: ?int, variants: list<array<string, mixed>>, groups: list<array<string, mixed>>}
     */
    public function cartPayload(): array
    {
        return [
            'id' => $this->publicId,
            'name' => $this->name,
            'price' => $this->price?->minor,
            'variants' => array_map(fn (VariantData $variant): array => [
                'id' => $variant->publicId,
                'name' => $variant->name,
                'price' => $variant->price->minor,
                'default' => $variant->isDefault,
                'available' => $variant->isAvailable,
            ], $this->variants),
            'groups' => array_map(fn (ExtraGroupData $group): array => [
                'name' => $group->name,
                'min' => $group->isRequired ? max(1, $group->minSelect) : $group->minSelect,
                'extras' => array_map(fn (ExtraData $extra): array => [
                    'id' => $extra->publicId,
                    'name' => $extra->name,
                    'delta' => $extra->priceDelta?->minor ?? 0,
                ], $group->extras),
            ], $this->extraGroups),
        ];
    }
}
