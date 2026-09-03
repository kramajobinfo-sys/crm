<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    use HasFactory, BelongsToCompany;

    public const TYPES = ['receipt','issue','adjustment','transfer_in','transfer_out','sale','purchase'];

    protected $fillable = [
        'company_id','product_id','warehouse_id','type','quantity','balance_after','unit_cost',
        'reference','note','related_type','related_id','user_id','occurred_at',
    ];
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2', 'balance_after' => 'decimal:2', 'unit_cost' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo   { return $this->belongsTo(Product::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function user(): BelongsTo      { return $this->belongsTo(User::class); }
    public function related(): MorphTo     { return $this->morphTo(); }
}
