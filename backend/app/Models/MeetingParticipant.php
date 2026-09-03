<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingParticipant extends Model
{
    use HasFactory, BelongsToCompany;

    public const RESPONSES = ['invited', 'accepted', 'declined', 'tentative'];

    protected $fillable = [
        'company_id','meeting_id','user_id','name','email','response',
    ];

    public function meeting(): BelongsTo { return $this->belongsTo(Meeting::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }

    /** External guests store their own name; internal ones borrow the user's. */
    public function getDisplayNameAttribute(): string
    {
        return $this->name ?: ($this->user?->name ?? $this->email ?? 'Guest');
    }
}
