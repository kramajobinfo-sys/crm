<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuildItem extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = ['company_id','build_id','component_product_id','name','quantity','unit_cost'];
    protected function casts(): array { return ['quantity' => 'decimal:4', 'unit_cost' => 'decimal:2']; }

    public function build(): BelongsTo     { return $this->belongsTo(Build::class); }
    public function component(): BelongsTo { return $this->belongsTo(Product::class, 'component_product_id'); }
}
