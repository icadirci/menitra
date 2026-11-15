<?php

declare(strict_types=1);

namespace App\Filament\App\Support;

use App\Exceptions\PlanLimitExceededException;
use Closure;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Validation\ValidationException;

/**
 * Runs a service call from a panel page or action:
 *
 * - validation errors raised by the service (keys relative to the submitted
 *   data) are re-thrown under the form's state path so they appear on the
 *   fields — or, with $statePath = null, shown as a notification;
 * - a plan limit becomes a notification.
 *
 * Both stop the page or action without saving (Filament rolls back the
 * panel's database transaction on Halt).
 */
final class ServiceCall
{
    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     *
     * @throws ValidationException
     * @throws Halt
     */
    public static function run(Closure $callback, ?string $statePath = 'data'): mixed
    {
        try {
            return $callback();
        } catch (ValidationException $exception) {
            if ($statePath === null) {
                Notification::make()->danger()->title((string) collect($exception->errors())->flatten()->first())->send();

                throw new Halt;
            }

            throw ValidationException::withMessages(collect($exception->errors())
                ->mapWithKeys(fn (array $messages, string $key): array => ["{$statePath}.{$key}" => $messages])
                ->all());
        } catch (PlanLimitExceededException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            throw new Halt;
        }
    }
}
