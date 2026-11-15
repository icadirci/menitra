<?php

declare(strict_types=1);

namespace App\Support\Menu\Data;

/**
 * Display-only in v1: rules and options of an extra group.
 */
final readonly class ExtraGroupData
{
    public function __construct(
        public string $name,
        public string $selectionType,
        public int $minSelect,
        public ?int $maxSelect,
        public bool $isRequired,
        /** @var list<ExtraData> */ public array $extras,
    ) {}
}
