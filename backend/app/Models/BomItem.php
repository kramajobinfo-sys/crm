<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomItem extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = ['company_id','product_id','component_product_id','quantity'];
    protected function casts(): array { return ['quantity' => 'decimal:4']; }

    public function product(): BelongsTo   { return $this->belongsTo(Product::class, 'product_id'); }
    public function component(): BelongsTo { return $this->belongsTo(Product::class, 'component_product_id'); }
}
