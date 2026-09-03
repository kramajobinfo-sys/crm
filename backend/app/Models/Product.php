<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    public const TYPES = ['goods', 'service'];

    protected $fillable = [
        'company_id','sku','name','description','category_id','type','unit',
        'cost_price','sale_price','tax_rate_id','barcode','track_inventory','reorder_level','is_active',
    ];
    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2', 'sale_price' => 'decimal:2', 'reorder_level' => 'decimal:2',
            'track_inventory' => 'boolean', 'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo { return $this->belongsTo(ProductCategory::class, 'category_id'); }
    public function taxRate(): BelongsTo  { return $this->belongsTo(TaxRate::class, 'tax_rate_id'); }
    public function suppliers(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(ProductSupplier::class); }
    public function preferredSupplier(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(ProductSupplier::class)->where('is_preferred', true); }
    /** This product's bill of materials (its component lines). Non-empty => manufacturable. */
    public function bomItems(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(BomItem::class, 'product_id'); }

    public function scopeActive(Builder $q): Builder { return $q->where('is_active', true); }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (!$term) return $q;
        $like = '%'.$term.'%';
        return $q->where(fn ($w) => $w->where('name', 'like', $like)
            ->orWhere('sku', 'like', $like)
            ->orWhere('barcode', 'like', $like));
    }
}
