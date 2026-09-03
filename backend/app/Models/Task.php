<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    public const STATUSES   = ['open', 'in_progress', 'done', 'cancelled'];
    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];
    public const OPEN_STATUSES = ['open', 'in_progress'];

    protected $fillable = [
        'company_id','title','description','status','priority','assigned_to','created_by',
        'due_at','completed_at','related_type','related_id',
    ];
    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function creator(): BelongsTo  { return $this->belongsTo(User::class, 'created_by'); }
    public function related(): MorphTo    { return $this->morphTo(); }

    public function isOpen(): bool     { return in_array($this->status, self::OPEN_STATUSES, true); }
    public function isOverdue(): bool  { return $this->isOpen() && $this->due_at && $this->due_at->isPast(); }

    public function scopeMine(Builder $q): Builder  { return $q->where('assigned_to', auth()->id()); }
    public function scopeOpen(Builder $q): Builder  { return $q->whereIn('status', self::OPEN_STATUSES); }
}
