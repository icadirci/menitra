<?php

declare(strict_types=1);

namespace App\Services\Menus;

use App\Enums\ExtraSelectionType;
use App\Models\Extra;
use App\Models\ExtraGroup;
use App\Services\Translations\TranslationWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reusable extra groups and their extras (owner-only, ExtraGroupPolicy).
 */
final class ExtraGroupService
{
    public function __construct(private readonly TranslationWriter $translations) {}

    /**
     * @param  array<string, mixed>  $data  selection_type, min_select, max_select, is_required, translations
     *
     * @throws ValidationException
     */
    public function saveGroup(array $data, ?ExtraGroup $group = null): ExtraGroup
    {
        $type = $data['selection_type'] instanceof ExtraSelectionType
            ? $data['selection_type']
            : ExtraSelectionType::tryFrom((string) ($data['selection_type'] ?? ''));
        $min = max(0, (int) ($data['min_select'] ?? 0));
        $max = isset($data['max_select']) && $data['max_select'] !== '' ? (int) $data['max_select'] : null;

        if ($type === null) {
            throw ValidationException::withMessages(['selection_type' => __('validation.in', ['attribute' => 'selection_type'])]);
        }
        if ($type === ExtraSelectionType::Single) {
            $max = 1;
            $min = min($min, 1);
        }
        if ($max !== null && $max < max($min, 1)) {
            throw ValidationException::withMessages(['max_select' => __('panels.app.extras.errors.max_select')]);
        }

        return DB::transaction(function () use ($data, $group, $type, $min, $max): ExtraGroup {
            $group ??= new ExtraGroup(['sort_order' => (int) ExtraGroup::query()->max('sort_order') + 1]);
            $group->fill([
                'selection_type' => $type,
                'min_select' => $min,
                'max_select' => $max,
                'is_required' => (bool) ($data['is_required'] ?? false) || $min > 0,
            ])->save();

            $this->translations->sync($group, (array) ($data['translations'] ?? []), ['name']);

            return $group;
        });
    }

    public function deleteGroup(ExtraGroup $group): void
    {
        $group->delete();
    }

    /**
     * @param  array<string, mixed>  $data  price_delta (kuruş), is_available, translations
     *
     * @throws ValidationException
     */
    public function saveExtra(ExtraGroup $group, array $data, ?Extra $extra = null): Extra
    {
        return DB::transaction(function () use ($group, $data, $extra): Extra {
            $extra ??= $group->extras()->make(['sort_order' => (int) $group->extras()->max('sort_order') + 1]);
            $extra->fill([
                'price_delta' => (int) ($data['price_delta'] ?? 0),
                'is_available' => (bool) ($data['is_available'] ?? true),
            ])->save();

            $this->translations->sync($extra, (array) ($data['translations'] ?? []), ['name']);

            return $extra;
        });
    }

    public function deleteExtra(Extra $extra): void
    {
        $extra->delete();
    }
}
