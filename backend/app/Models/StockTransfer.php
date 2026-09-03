<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockTransfer extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    public const STATUSES = ['draft','in_transit','received','cancelled'];

    protected $fillable = [
        'company_id','transfer_no','from_warehouse_id','to_warehouse_id','status',
        'transfer_date','notes','requested_by','shipped_at','received_at',
    ];
    protected function casts(): array
    {
        return ['transfer_date' => 'date', 'shipped_at' => 'datetime', 'received_at' => 'datetime'];
    }

    public function fromWarehouse(): BelongsTo { return $this->belongsTo(Warehouse::class, 'from_warehouse_id'); }
    public function toWarehouse(): BelongsTo   { return $this->belongsTo(Warehouse::class, 'to_warehouse_id'); }
    public function requester(): BelongsTo     { return $this->belongsTo(User::class, 'requested_by'); }
    public function items(): HasMany           { return $this->hasMany(StockTransferItem::class)->orderBy('sort_order'); }
}
