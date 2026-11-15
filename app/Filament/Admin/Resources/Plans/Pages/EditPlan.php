<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Plans\Pages;

use App\Enums\BillingInterval;
use App\Enums\PlanFeature;
use App\Filament\Admin\Forms\PlatformTranslatableFields;
use App\Filament\Admin\Resources\Plans\PlanResource;
use App\Models\Plan;
use App\Services\Subscription\PlanService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * @property Plan $record
 */
class EditPlan extends EditRecord
{
    protected static string $resource = PlanResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $plan = $this->record->load(['prices', 'limits']);

        $data['translations'] = PlatformTranslatableFields::fillFrom($plan, ['name', 'description']);

        foreach (BillingInterval::cases() as $interval) {
            $data['prices'][$interval->value] = $plan->priceFor($interval)?->amount;
        }

        foreach (PlanFeature::cases() as $feature) {
            // A missing row means "not included" (FeatureGate: 0), null means unlimited.
            $row = $plan->limits->firstWhere('feature', $feature);
            $value = $row === null ? 0 : $row->limit_value;
            $data['limits'][$feature->value] = $feature->isBoolean() ? ($value === null || $value >= 1) : $value;
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(PlanService::class)->save(
            $record,
            Arr::except($data, ['code', 'translations', 'prices', 'limits']),
            $data['translations'] ?? [],
            $data['prices'] ?? [],
            $data['limits'] ?? [],
        );
    }
}
