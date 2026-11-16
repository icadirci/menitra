<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Plans\Pages;

use App\Filament\Admin\Resources\Plans\PlanResource;
use App\Models\Plan;
use App\Services\Subscription\PlanService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class CreatePlan extends CreateRecord
{
    protected static string $resource = PlanResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(PlanService::class)->save(
            new Plan,
            Arr::except($data, ['translations', 'prices', 'limits', 'extra_location_prices']),
            $data['translations'] ?? [],
            $data['prices'] ?? [],
            $data['limits'] ?? [],
            $data['extra_location_prices'] ?? [],
        );
    }
}
