<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSupplier extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id','product_id','vendor_id','supplier_sku','cost','lead_time_days','currency','is_preferred',
    ];
    protected function casts(): array
    {
        return ['cost' => 'decimal:2', 'lead_time_days' => 'integer', 'is_preferred' => 'boolean'];
    }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function vendor(): BelongsTo  { return $this->belongsTo(Vendor::class); }
}
