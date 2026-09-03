<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    public const STATUSES = ['draft','submitted','confirmed','received','closed','cancelled'];

    protected $fillable = [
        'company_id','po_no','vendor_id','purchase_request_id','warehouse_id','created_by',
        'status','order_date','expected_date','currency','subtotal','discount_total',
        'tax_total','grand_total','received_at','notes','terms',
    ];
    protected function casts(): array
    {
        return [
            'order_date' => 'date', 'expected_date' => 'date', 'received_at' => 'datetime',
            'subtotal' => 'decimal:2', 'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2', 'grand_total' => 'decimal:2',
        ];
    }

    public function vendor(): BelongsTo    { return $this->belongsTo(Vendor::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function request(): BelongsTo   { return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id'); }
    public function creator(): BelongsTo   { return $this->belongsTo(User::class, 'created_by'); }
    public function items(): HasMany       { return $this->hasMany(PurchaseOrderItem::class)->orderBy('sort_order'); }
    public function approval(): MorphOne   { return $this->morphOne(ApprovalRequest::class, 'approvable')->latestOfMany(); }
    public function approvals(): MorphMany { return $this->morphMany(ApprovalRequest::class, 'approvable'); }

    /** Roll line items up into the money columns and save. */
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
            'subtotal' => round($subtotal, 2), 'discount_total' => round($discount, 2),
            'tax_total' => round($tax, 2), 'grand_total' => round($subtotal - $discount + $tax, 2),
        ])->save();
    }

    public function isFullyReceived(): bool
    {
        return $this->items->every(fn ($it) => (float) $it->received_quantity >= (float) $it->quantity);
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        return $term ? $q->where('po_no', 'like', '%'.$term.'%') : $q;
    }
}
