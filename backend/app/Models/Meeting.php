<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Meeting extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    public const STATUSES = ['scheduled', 'completed', 'cancelled'];

    protected $fillable = [
        'company_id','title','description','location','meeting_link','status','organizer_id',
        'start_at','end_at','related_type','related_id',
    ];
    protected function casts(): array
    {
        return ['start_at' => 'datetime', 'end_at' => 'datetime'];
    }

    public function organizer(): BelongsTo    { return $this->belongsTo(User::class, 'organizer_id'); }
    public function participants(): HasMany    { return $this->hasMany(MeetingParticipant::class); }
    public function related(): MorphTo         { return $this->morphTo(); }
}
