<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CampaignRecipient extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id','campaign_id','recipient_type','recipient_id','name','email','phone',
        'status','sent_at','opened_at',
    ];
    protected function casts(): array { return ['sent_at' => 'datetime', 'opened_at' => 'datetime']; }

    public function campaign(): BelongsTo { return $this->belongsTo(Campaign::class); }
    public function recipient(): MorphTo  { return $this->morphTo(); }
}
