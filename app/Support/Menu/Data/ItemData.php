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
}
