<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Barcode extends Model
{
    use HasFactory, BelongsToCompany;

    public const TYPES = ['EAN13','UPC','CODE128','QR','custom'];

    protected $fillable = ['company_id','product_id','barcode','type','is_primary'];
    protected function casts(): array { return ['is_primary' => 'boolean']; }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
