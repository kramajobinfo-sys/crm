<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadStatus extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id','name','code','color','sort_order','is_default','is_won','is_lost',
    ];
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer', 'is_default' => 'boolean',
            'is_won' => 'boolean', 'is_lost' => 'boolean',
        ];
    }

    public function leads(): HasMany { return $this->hasMany(Lead::class, 'status_id'); }

    /** A status that ends the lead's life either way. */
    public function isTerminal(): bool { return $this->is_won || $this->is_lost; }
}
