<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BcSyncRun extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id','connection_id','mapping_id','direction','trigger','status',
        'created_count','updated_count','skipped_count','failed_count',
        'started_at','finished_at','error',
    ];
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime', 'finished_at' => 'datetime',
            'created_count' => 'integer', 'updated_count' => 'integer',
            'skipped_count' => 'integer', 'failed_count' => 'integer',
        ];
    }

    public function connection(): BelongsTo { return $this->belongsTo(BcConnection::class, 'connection_id'); }
    public function mapping(): BelongsTo    { return $this->belongsTo(BcEntityMapping::class, 'mapping_id'); }
    public function issues(): HasMany       { return $this->hasMany(BcSyncIssue::class, 'run_id'); }

    public function durationSeconds(): ?int
    {
        return $this->started_at && $this->finished_at
            ? $this->started_at->diffInSeconds($this->finished_at) : null;
    }
}
