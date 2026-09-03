<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Shared header for quotations / sales_orders / invoices. Subclasses set `$table`,
 * add their own fillable columns, and implement items().
 */
abstract class SalesDocument extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $baseFillable = [
        'company_id','customer_id','owner_id','branch_id','currency',
        'subtotal','discount_total','tax_total','grand_total','notes','terms',
    ];
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2', 'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2', 'grand_total' => 'decimal:2',
        ];
    }

    abstract public function items(): HasMany;

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function owner(): BelongsTo    { return $this->belongsTo(User::class, 'owner_id'); }
    public function branch(): BelongsTo   { return $this->belongsTo(Branch::class); }

    /** Roll the loaded/fresh line items up into the header money columns and save. */
    public function recomputeTotals(): void
    {
        $items = $this->items()->get();
        $subtotal = 0.0; $discount = 0.0; $tax = 0.0;

        foreach ($items as $it) {
            $gross = (float) $it->quantity * (float) $it->unit_price;
            $subtotal += $gross;
            $discount += $gross - (float) $it->line_total;
            $tax += (float) $it->tax_amount;
        }

        $this->forceFill([
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discount, 2),
            'tax_total' => round($tax, 2),
            'grand_total' => round($subtotal - $discount + $tax, 2),
        ])->save();
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (!$term) return $q;
        $like = '%'.$term.'%';
        $noCol = $this->documentNumberColumn();
        return $q->where(fn ($w) => $w->where($noCol, 'like', $like));
    }

    /** e.g. quote_no / order_no / invoice_no — used by search. */
    abstract public function documentNumberColumn(): string;
}
