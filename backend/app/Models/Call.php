<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Call extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    public const DIRECTIONS = ['inbound', 'outbound'];
    public const STATUSES   = ['scheduled', 'completed', 'missed', 'cancelled'];

    protected $fillable = [
        'company_id','subject','direction','status','phone','duration_seconds','user_id',
        'notes','scheduled_at','occurred_at','related_type','related_id',
    ];
    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'scheduled_at' => 'datetime',
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function related(): MorphTo    { return $this->morphTo(); }

    public function getDurationHumanAttribute(): ?string
    {
        if (!$this->duration_seconds) return null;
        $m = intdiv($this->duration_seconds, 60);
        $s = $this->duration_seconds % 60;
        return $m ? "{$m}m {$s}s" : "{$s}s";
    }
}
