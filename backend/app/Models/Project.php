<?php
namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    public const STATUSES = ['planned', 'active', 'on_hold', 'completed', 'cancelled'];
    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    protected $fillable = ['company_id','project_no','name','description','status','priority','customer_id','deal_id','owner_id','start_date','due_date','completed_at','budget','currency','progress'];
    protected function casts(): array { return ['start_date'=>'date','due_date'=>'date','completed_at'=>'datetime','budget'=>'decimal:2','progress'=>'integer']; }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function deal(): BelongsTo { return $this->belongsTo(Deal::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function members(): HasMany { return $this->hasMany(ProjectMember::class); }
    public function milestones(): HasMany { return $this->hasMany(ProjectMilestone::class)->orderBy('sort_order')->orderBy('id'); }
    public function tasks(): HasMany { return $this->hasMany(ProjectTask::class)->orderBy('sort_order')->orderBy('id'); }
    public function timeEntries(): HasMany { return $this->hasMany(ProjectTimeEntry::class); }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (!$term) return $query;
        $like = '%'.$term.'%';
        return $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('project_no', 'like', $like));
    }
}
