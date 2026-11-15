<?php

declare(strict_types=1);

namespace App\Filament\Admin\Concerns;

use App\Filament\Admin\Forms\PlatformTranslatableFields;
use App\Services\Translations\TranslationWriter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Create/Edit page glue for simple translatable platform records (marketing
 * FAQ, testimonials): base attributes through the model's fillable, the
 * `translations` state through TranslationWriter::syncPlatform(), in one
 * transaction. The using page defines translationFields() and requiredTranslationFields().
 */
trait SavesPlatformTranslations
{
    /**
     * @return list<string>
     */
    abstract protected function translationFields(): array;

    /**
     * @return list<string>
     */
    abstract protected function requiredTranslationFields(): array;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Model {
            $record = static::getModel()::create(Arr::except($data, ['translations']));
            app(TranslationWriter::class)->syncPlatform($record, $data['translations'] ?? [], $this->requiredTranslationFields());

            return $record;
        });
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data): Model {
            $record->update(Arr::except($data, ['translations']));
            app(TranslationWriter::class)->syncPlatform($record, $data['translations'] ?? [], $this->requiredTranslationFields());

            return $record;
        });
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['translations'] = PlatformTranslatableFields::fillFrom($this->getRecord(), $this->translationFields());

        return $data;
    }
}
