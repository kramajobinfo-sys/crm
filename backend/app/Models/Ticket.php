<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    public const STATUSES    = ['new', 'open', 'pending', 'resolved', 'closed'];
    public const OPEN_STATUSES = ['new', 'open', 'pending'];
    public const PRIORITIES  = ['low', 'medium', 'high', 'urgent'];
    public const CHANNELS    = ['email', 'phone', 'web', 'chat', 'manual'];

    protected $fillable = [
        'company_id','ticket_no','subject','description','status','priority','category_id',
        'customer_id','requester_name','requester_email','channel','assigned_to','created_by',
        'sla_policy_id','first_response_due_at','due_at','first_response_at','resolved_at',
        'closed_at','reopened_count',
    ];
    protected function casts(): array
    {
        return [
            'first_response_due_at' => 'datetime', 'due_at' => 'datetime',
            'first_response_at' => 'datetime', 'resolved_at' => 'datetime', 'closed_at' => 'datetime',
            'reopened_count' => 'integer',
        ];
    }

    public function category(): BelongsTo  { return $this->belongsTo(TicketCategory::class, 'category_id'); }
    public function customer(): BelongsTo   { return $this->belongsTo(Customer::class); }
    public function assignee(): BelongsTo    { return $this->belongsTo(User::class, 'assigned_to'); }
    public function creator(): BelongsTo     { return $this->belongsTo(User::class, 'created_by'); }
    public function slaPolicy(): BelongsTo   { return $this->belongsTo(SlaPolicy::class, 'sla_policy_id'); }
    public function replies(): HasMany       { return $this->hasMany(TicketReply::class)->orderBy('id'); }
    public function escalations(): HasMany   { return $this->hasMany(Escalation::class)->orderByDesc('id'); }

    public function isOpen(): bool { return in_array($this->status, self::OPEN_STATUSES, true); }

    /** Past its resolution deadline while still open. */
    public function isBreaching(): bool
    {
        return $this->isOpen() && $this->due_at && $this->due_at->isPast();
    }

    public function scopeOpen(Builder $q): Builder { return $q->whereIn('status', self::OPEN_STATUSES); }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (!$term) return $q;
        $like = '%'.$term.'%';
        return $q->where(fn ($w) => $w->where('subject', 'like', $like)
            ->orWhere('ticket_no', 'like', $like)
            ->orWhere('requester_email', 'like', $like));
    }
}
