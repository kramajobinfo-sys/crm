<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestItem extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id','purchase_request_id','product_id','name','quantity','estimated_price','note','sort_order',
    ];
    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'estimated_price' => 'decimal:2', 'sort_order' => 'integer'];
    }

    public function request(): BelongsTo { return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id'); }
}
