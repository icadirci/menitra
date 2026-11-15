<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\PlanFeature;
use RuntimeException;

/**
 * Thrown by FeatureGate::ensureCanCreate() when the tenant's plan (or the lack
 * of a live subscription) does not allow creating another resource.
 */
class PlanLimitExceededException extends RuntimeException
{
    public function __construct(
        public readonly PlanFeature $feature,
        public readonly ?int $limit,
    ) {
        parent::__construct($limit === null || $limit === 0
            ? __('plans.errors.not_included', ['feature' => $feature->label()])
            : __('plans.errors.limit_reached', ['feature' => $feature->label(), 'limit' => $limit]));
    }
}
