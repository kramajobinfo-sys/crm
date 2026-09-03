<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockItem extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id','product_id','warehouse_id','quantity','reserved_quantity',
        'average_cost','bin_location',
    ];
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2', 'reserved_quantity' => 'decimal:2', 'average_cost' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo   { return $this->belongsTo(Product::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }

    /** On-hand minus what's committed to orders. */
    public function getAvailableAttribute(): float
    {
        return (float) $this->quantity - (float) $this->reserved_quantity;
    }

    /** Low if at or below the product's reorder level (and the product tracks stock). */
    public function scopeLowStock(Builder $q): Builder
    {
        // Correlated EXISTS: compares stock_items.quantity to the product's reorder_level
        // without a join (so no row multiplication when chained with with()/paginate()).
        return $q->whereExists(function ($sub) {
            $sub->from('products')
                ->whereColumn('products.id', 'stock_items.product_id')
                ->where('products.track_inventory', true)
                ->where('products.reorder_level', '>', 0)
                ->whereColumn('stock_items.quantity', '<=', 'products.reorder_level');
        });
    }
}
