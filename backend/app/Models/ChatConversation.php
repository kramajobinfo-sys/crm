<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatConversation extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    public const STATUSES  = ['open','pending','snoozed','resolved','closed'];
    public const PRIORITIES = ['low','normal','high','urgent'];

    protected $fillable = [
        'company_id','channel_id','contact_id','assigned_to','external_thread_id','subject',
        'status','priority','tags','last_message_preview','last_message_at','last_inbound_at','unread_count',
    ];
    protected function casts(): array
    {
        return [
            'tags' => 'array', 'last_message_at' => 'datetime',
            'last_inbound_at' => 'datetime', 'unread_count' => 'integer',
        ];
    }

    public function channel(): BelongsTo  { return $this->belongsTo(ChatChannel::class, 'channel_id'); }
    public function contact(): BelongsTo  { return $this->belongsTo(ChatContact::class, 'contact_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function messages(): HasMany   { return $this->hasMany(ChatMessage::class, 'conversation_id'); }

    public function scopeStatus(Builder $q, ?string $status): Builder
    {
        return $status && $status !== 'all' ? $q->where('status', $status) : $q;
    }
    public function scopeInbox(Builder $q): Builder
    {
        return $q->orderByRaw('last_message_at IS NULL, last_message_at DESC');
    }

    /**
     * Meta's 24h service window: outside it, only pre-approved templates may be sent.
     * Channels without the rule are always open.
     */
    public function serviceWindowOpen(): bool
    {
        if (!$this->relationLoaded('channel')) $this->load('channel');
        if (!$this->channel?->enforcesServiceWindow()) return true;
        return $this->last_inbound_at !== null && $this->last_inbound_at->gt(now()->subDay());
    }
}
