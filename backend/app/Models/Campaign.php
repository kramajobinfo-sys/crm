<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    public const TYPES    = ['email', 'sms'];
    public const STATUSES = ['draft', 'scheduled', 'running', 'sent', 'paused', 'cancelled'];

    protected $fillable = [
        'company_id','name','type','status','subject','body','email_template_id','email_account_id',
        'sms_provider_id','audience','scheduled_at','sent_at','recipients_count','sent_count',
        'opened_count','clicked_count','failed_count','created_by',
    ];
    protected function casts(): array
    {
        return [
            'audience' => 'array', 'scheduled_at' => 'datetime', 'sent_at' => 'datetime',
            'recipients_count' => 'integer', 'sent_count' => 'integer', 'opened_count' => 'integer',
            'clicked_count' => 'integer', 'failed_count' => 'integer',
        ];
    }

    public function template(): BelongsTo   { return $this->belongsTo(EmailTemplate::class, 'email_template_id'); }
    public function emailAccount(): BelongsTo { return $this->belongsTo(EmailAccount::class, 'email_account_id'); }
    public function smsProvider(): BelongsTo  { return $this->belongsTo(SmsProvider::class, 'sms_provider_id'); }
    public function recipients(): HasMany     { return $this->hasMany(CampaignRecipient::class); }
    public function messages(): HasMany       { return $this->hasMany(CampaignMessage::class); }
    public function creator(): BelongsTo      { return $this->belongsTo(User::class, 'created_by'); }

    public function isEditable(): bool { return in_array($this->status, ['draft', 'scheduled'], true); }

    public function getOpenRateAttribute(): ?float
    {
        return $this->sent_count > 0 ? round($this->opened_count / $this->sent_count * 100, 1) : null;
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        return $term ? $q->where('name', 'like', '%'.$term.'%') : $q;
    }
}
