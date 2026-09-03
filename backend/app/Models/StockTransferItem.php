<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferItem extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id','stock_transfer_id','product_id','name','quantity','sort_order',
    ];
    protected function casts(): array { return ['quantity' => 'decimal:2', 'sort_order' => 'integer']; }

    public function transfer(): BelongsTo { return $this->belongsTo(StockTransfer::class, 'stock_transfer_id'); }
    public function product(): BelongsTo  { return $this->belongsTo(Product::class); }
}
