<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceBookEntry extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id','price_book_id','product_id','unit_price',
    ];
    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2'];
    }

    public function priceBook(): BelongsTo { return $this->belongsTo(PriceBook::class); }
    public function product(): BelongsTo   { return $this->belongsTo(Product::class); }
}
