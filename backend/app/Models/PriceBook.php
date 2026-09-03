<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PriceBook extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $fillable = [
        'company_id','name','currency','description','is_active','valid_from','valid_to',
    ];
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }

    public function entries(): HasMany { return $this->hasMany(PriceBookEntry::class); }

    public function scopeActive(Builder $q): Builder { return $q->where('is_active', true); }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (!$term) return $q;
        $like = '%'.$term.'%';
        return $q->where(fn ($w) => $w->where('name', 'like', $like)
            ->orWhere('currency', 'like', $like));
    }
}
