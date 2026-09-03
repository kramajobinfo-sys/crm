<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Build extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $fillable = [
        'company_id','build_no','product_id','warehouse_id','quantity',
        'unit_cost','total_cost','status','notes','built_by','built_at',
    ];
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2', 'unit_cost' => 'decimal:2', 'total_cost' => 'decimal:2',
            'built_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo   { return $this->belongsTo(Product::class, 'product_id'); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function builder(): BelongsTo   { return $this->belongsTo(User::class, 'built_by'); }
    public function items(): HasMany       { return $this->hasMany(BuildItem::class); }
}
