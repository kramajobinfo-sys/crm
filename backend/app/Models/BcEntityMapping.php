<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BcEntityMapping extends Model
{
    use HasFactory, BelongsToCompany;

    public const DIRECTIONS = ['pull','push','bidirectional'];

    protected $fillable = [
        'company_id','connection_id','crm_entity','bc_entity','direction','is_enabled',
        'field_map','filter','sync_cursor','interval_minutes','last_run_at',
    ];
    protected function casts(): array
    {
        return [
            'field_map' => 'array', 'filter' => 'array', 'is_enabled' => 'boolean',
            'sync_cursor' => 'datetime', 'last_run_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo { return $this->belongsTo(BcConnection::class, 'connection_id'); }
    public function links(): HasMany        { return $this->hasMany(BcRecordLink::class, 'mapping_id'); }
    public function runs(): HasMany         { return $this->hasMany(BcSyncRun::class, 'mapping_id'); }

    public function isDue(): bool
    {
        return $this->is_enabled
            && ($this->last_run_at === null || $this->last_run_at->addMinutes($this->interval_minutes)->isPast());
    }
}
