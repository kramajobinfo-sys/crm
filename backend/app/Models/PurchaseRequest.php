<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseRequest extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    public const STATUSES = ['draft','submitted','approved','rejected','converted','cancelled'];

    protected $fillable = [
        'company_id','pr_no','requested_by','department_id','branch_id','status',
        'needed_by','estimated_total','notes','converted_po_id',
    ];
    protected function casts(): array
    {
        return ['needed_by' => 'date', 'estimated_total' => 'decimal:2'];
    }

    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function items(): HasMany       { return $this->hasMany(PurchaseRequestItem::class)->orderBy('sort_order'); }
    public function approval(): MorphOne   { return $this->morphOne(ApprovalRequest::class, 'approvable')->latestOfMany(); }
    public function approvals(): MorphMany { return $this->morphMany(ApprovalRequest::class, 'approvable'); }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        return $term ? $q->where('pr_no', 'like', '%'.$term.'%') : $q;
    }
}
