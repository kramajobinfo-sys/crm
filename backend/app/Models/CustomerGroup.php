<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerGroup extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id','name','code','discount_percent','price_book_id','payment_terms_days','is_active',
    ];
    protected function casts(): array
    {
        return [
            'discount_percent' => 'decimal:2',
            'payment_terms_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function customers(): HasMany { return $this->hasMany(Customer::class, 'group_id'); }
    public function priceBook(): BelongsTo { return $this->belongsTo(PriceBook::class); }
}
