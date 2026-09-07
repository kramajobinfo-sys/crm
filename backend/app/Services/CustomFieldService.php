<?php
namespace App\Services;

use App\Models\CustomFieldDefinition;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Admin-defined custom fields. Definitions live per company+entity; values are validated and coerced
 * here before they are written to the entity's `custom_fields` JSON column, so the stored data always
 * matches the current field definitions (type, required, select options).
 */
class CustomFieldService
{
    /** Active field definitions for an entity, ordered for display. */
    public function definitions(string $entity): Collection
    {
        return CustomFieldDefinition::where('entity', $entity)->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * Validate + coerce a submitted custom-field payload against the entity's definitions.
     * Unknown keys are dropped; typed values are normalized. Throws a 422 on invalid input.
     * @return array<string,mixed>
     */
    public function sanitize(string $entity, array $input): array
    {
        $out = []; $errors = [];
        foreach ($this->definitions($entity) as $d) {
            $val = $input[$d->key] ?? null;
            $empty = $val === null || $val === '' || $val === [];
            if ($d->required && $empty && $d->type !== 'checkbox') {
                $errors['custom_fields.'.$d->key] = "{$d->label} is required.";
                continue;
            }
            if ($empty && $d->type !== 'checkbox') continue;

            switch ($d->type) {
                case 'number':
                    if (!is_numeric($val)) $errors['custom_fields.'.$d->key] = "{$d->label} must be a number.";
                    else $out[$d->key] = 0 + $val;
                    break;
                case 'checkbox':
                    $out[$d->key] = (bool) $val;
                    break;
                case 'select':
                    if (!in_array($val, $d->options ?? [], true)) $errors['custom_fields.'.$d->key] = "{$d->label}: invalid option.";
                    else $out[$d->key] = $val;
                    break;
                case 'email':
                    if (!filter_var($val, FILTER_VALIDATE_EMAIL)) $errors['custom_fields.'.$d->key] = "{$d->label}: invalid email.";
                    else $out[$d->key] = (string) $val;
                    break;
                case 'date':
                    $out[$d->key] = (string) $val;   // ISO date string
                    break;
                default:                              // text, textarea, url
                    $out[$d->key] = mb_substr((string) $val, 0, 2000);
            }
        }
        if ($errors) throw ValidationException::withMessages($errors);
        return $out;
    }

    /** Turn a label into a safe, unique field key within an entity. */
    public function keyFor(string $entity, int $companyId, string $label, ?int $ignoreId = null): string
    {
        $base = Str::slug($label, '_') ?: 'field';
        $key = $base; $i = 2;
        while (CustomFieldDefinition::where('company_id', $companyId)->where('entity', $entity)->where('key', $key)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $key = $base.'_'.$i++;
        }
        return $key;
    }
}
