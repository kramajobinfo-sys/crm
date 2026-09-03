<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id','purchase_order_id','product_id','name','description','quantity',
        'received_quantity','unit_price','discount_pct','tax_rate_id','tax_amount','line_total','sort_order',
    ];
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2', 'received_quantity' => 'decimal:2', 'unit_price' => 'decimal:2',
            'discount_pct' => 'decimal:2', 'tax_amount' => 'decimal:2', 'line_total' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function order(): BelongsTo   { return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id'); }
    public function taxRate(): BelongsTo { return $this->belongsTo(TaxRate::class, 'tax_rate_id'); }

    public function getOutstandingAttribute(): float
    {
        return max((float) $this->quantity - (float) $this->received_quantity, 0);
    }
}
