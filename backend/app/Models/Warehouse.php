<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id','name','code','branch_id','address','contact_name','contact_phone',
        'is_default','is_active',
    ];
    protected function casts(): array { return ['is_default' => 'boolean', 'is_active' => 'boolean']; }

    public function branch(): BelongsTo     { return $this->belongsTo(Branch::class); }
    public function stockItems(): HasMany   { return $this->hasMany(StockItem::class); }
}
