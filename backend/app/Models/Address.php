<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Shared polymorphic address. Attaches to customers/contacts now, vendors and employees later. */
class Address extends Model
{
    use HasFactory, BelongsToCompany;

    public const TYPES = ['billing', 'shipping', 'other'];

    protected $fillable = [
        'company_id','addressable_type','addressable_id','type','label',
        'line1','line2','city','state','postal_code','country','is_default',
    ];
    protected function casts(): array { return ['is_default' => 'boolean']; }

    public function addressable(): MorphTo { return $this->morphTo(); }

    public function oneLine(): string
    {
        return implode(', ', array_filter([
            $this->line1, $this->line2, $this->city, $this->state, $this->postal_code, $this->country,
        ]));
    }
}
