<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    public const STATUSES = ['active', 'on_hold', 'blocked'];

    protected $fillable = [
        'company_id','vendor_no','name','legal_name','email','phone','mobile','website',
        'tax_id','currency','payment_terms_days','address','contact_name','status','notes',
    ];
    protected function casts(): array { return ['payment_terms_days' => 'integer']; }

    public function purchaseOrders(): HasMany { return $this->hasMany(PurchaseOrder::class); }

    public function scopeStatus(Builder $q, ?string $s): Builder
    {
        return $s && $s !== 'all' ? $q->where('status', $s) : $q;
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (!$term) return $q;
        $like = '%'.$term.'%';
        return $q->where(fn ($w) => $w->where('name', 'like', $like)
            ->orWhere('vendor_no', 'like', $like)
            ->orWhere('email', 'like', $like)
            ->orWhere('tax_id', 'like', $like));
    }
}
