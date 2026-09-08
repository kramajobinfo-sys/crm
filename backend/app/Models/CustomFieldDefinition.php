<?php
namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class CustomFieldDefinition extends Model
{
    use BelongsToCompany;

    public const ENTITIES = ['lead', 'customer', 'deal', 'contact'];
    public const TYPES = ['text', 'textarea', 'number', 'date', 'select', 'checkbox', 'url', 'email'];

    protected $fillable = [
        'company_id', 'entity', 'key', 'label', 'type', 'options', 'required', 'help', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return ['options' => 'array', 'required' => 'boolean', 'is_active' => 'boolean'];
    }
}
