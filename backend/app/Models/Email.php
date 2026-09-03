<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Email extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    public const DIRECTIONS = ['inbound', 'outbound'];
    public const STATUSES   = ['draft', 'queued', 'sent', 'failed', 'received'];

    protected $fillable = [
        'company_id','email_account_id','direction','status','error','from_address','from_name',
        'to','cc','bcc','subject','body_html','template_id','message_id','in_reply_to',
        'related_type','related_id','opens','clicks','opened_at','sent_at','received_at','user_id',
    ];
    protected function casts(): array
    {
        return [
            'to' => 'array', 'cc' => 'array', 'bcc' => 'array',
            'opens' => 'integer', 'clicks' => 'integer',
            'opened_at' => 'datetime', 'sent_at' => 'datetime', 'received_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo   { return $this->belongsTo(EmailAccount::class, 'email_account_id'); }
    public function template(): BelongsTo   { return $this->belongsTo(EmailTemplate::class, 'template_id'); }
    public function user(): BelongsTo        { return $this->belongsTo(User::class); }
    public function attachments(): HasMany   { return $this->hasMany(EmailAttachment::class); }
    public function related(): MorphTo       { return $this->morphTo(); }

    public function scopeFolder(Builder $q, ?string $folder): Builder
    {
        return match ($folder) {
            'inbox' => $q->where('direction', 'inbound'),
            'sent'  => $q->where('direction', 'outbound')->whereIn('status', ['sent', 'queued']),
            'drafts'=> $q->where('status', 'draft'),
            default => $q,
        };
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (!$term) return $q;
        $like = '%'.$term.'%';
        return $q->where(fn ($w) => $w->where('subject', 'like', $like)->orWhere('from_address', 'like', $like));
    }
}
