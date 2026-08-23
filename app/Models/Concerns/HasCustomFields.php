<?php

namespace App\Models\Concerns;

use App\Models\CustomFieldValue;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Adds dynamic-field support to a future model (Product, Customer, ...) with
 * no schema change required — attach this trait, and it can read/write any
 * CustomField defined for its entity_type. Nothing in the platform uses this
 * yet (no Product/Customer model exists), but the mechanism is ready.
 */
trait HasCustomFields
{
    /**
     * @return MorphMany<CustomFieldValue, $this>
     */
    public function customFieldValues(): MorphMany
    {
        return $this->morphMany(CustomFieldValue::class, 'custom_fieldable');
    }

    public function getCustomFieldValue(string $key): mixed
    {
        return $this->customFieldValues()
            ->whereHas('customField', fn ($q) => $q->where('key', $key))
            ->value('value');
    }

    public function setCustomFieldValue(int $customFieldId, mixed $value): CustomFieldValue
    {
        return $this->customFieldValues()->updateOrCreate(
            ['custom_field_id' => $customFieldId],
            ['value' => $value]
        );
    }
}
