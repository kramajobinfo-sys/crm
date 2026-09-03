<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ChatContact extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id','channel_id','external_user_id','display_name','avatar_url',
        'phone','email','linked_type','linked_id',
    ];

    public function channel(): BelongsTo { return $this->belongsTo(ChatChannel::class, 'channel_id'); }
    public function conversations(): HasMany { return $this->hasMany(ChatConversation::class, 'contact_id'); }

    /** Lead (Module 2) or Customer (Module 3) once those ship. Unconstrained by design. */
    public function linked(): MorphTo { return $this->morphTo(__FUNCTION__, 'linked_type', 'linked_id'); }
    public function timeline(): MorphMany { return $this->morphMany(TimelineActivity::class, 'subject'); }
}
