<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Reminder extends Model
{
    use HasFactory, BelongsToCompany;

    public const CHANNELS = ['in_app', 'email'];

    protected $fillable = [
        'company_id','user_id','title','remind_at','channel','is_sent','sent_at',
        'related_type','related_id',
    ];
    protected function casts(): array
    {
        return ['remind_at' => 'datetime', 'sent_at' => 'datetime', 'is_sent' => 'boolean'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function related(): MorphTo { return $this->morphTo(); }

    public function scopeDue(Builder $q): Builder
    {
        return $q->where('is_sent', false)->where('remind_at', '<=', now());
    }
}
