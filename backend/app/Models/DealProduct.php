<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line on a deal. product_id is intentionally unconstrained until M7 (Sales)
 * creates `products`; name/unit_price are denormalised so a line survives a
 * later product deletion.
 */
class DealProduct extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id','deal_id','product_id','name','description',
        'quantity','unit_price','discount_pct','line_total','sort_order',
    ];
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'discount_pct' => 'decimal:2',
            'line_total' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function deal(): BelongsTo { return $this->belongsTo(Deal::class); }

    /** line_total = qty × unit_price × (1 - discount%). Single source of the maths. */
    public static function compute(float $qty, float $unitPrice, float $discountPct): float
    {
        return round($qty * $unitPrice * (1 - $discountPct / 100), 2);
    }
}
