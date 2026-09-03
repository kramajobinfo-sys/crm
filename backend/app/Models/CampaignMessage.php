<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignMessage extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id','campaign_id','campaign_recipient_id','channel','to_address','subject',
        'body','status','message_id','sent_at',
    ];
    protected function casts(): array { return ['sent_at' => 'datetime']; }

    public function campaign(): BelongsTo  { return $this->belongsTo(Campaign::class); }
    public function recipient(): BelongsTo   { return $this->belongsTo(CampaignRecipient::class, 'campaign_recipient_id'); }
}
