<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BcSyncIssue extends Model
{
    use HasFactory;

    // Scoped through its parent run; no company_id of its own.
    protected $fillable = [
        'run_id','record_link_id','stage','severity','crm_type','crm_id',
        'bc_id','message','context','resolved_at',
    ];
    protected function casts(): array { return ['context' => 'array', 'resolved_at' => 'datetime']; }

    public function run(): BelongsTo  { return $this->belongsTo(BcSyncRun::class, 'run_id'); }
    public function link(): BelongsTo { return $this->belongsTo(BcRecordLink::class, 'record_link_id'); }
}
