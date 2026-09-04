<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactConsent extends Model
{
    use HasFactory, BelongsToCompany;

    /** Channels a contact can be reached on. `marketing` requires explicit opt-in. */
    public const CHANNELS = ['email', 'sms', 'phone', 'whatsapp', 'marketing'];

    /** Channels that require an explicit grant before we may contact (opt-in). */
    public const OPT_IN_CHANNELS = ['marketing'];

    protected $fillable = [
        'company_id', 'contact_id', 'channel', 'status', 'source', 'note', 'user_id', 'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function contact(): BelongsTo { return $this->belongsTo(Contact::class); }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
