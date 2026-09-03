<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WorkflowRun extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id','workflow_id','trigger_type','status','subject_type','subject_id',
        'log','actions_run','started_at','finished_at','triggered_by',
    ];
    protected function casts(): array
    {
        return ['log' => 'array', 'actions_run' => 'integer', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function workflow(): BelongsTo { return $this->belongsTo(Workflow::class); }
    public function subject(): MorphTo    { return $this->morphTo(); }
    public function triggeredBy(): BelongsTo { return $this->belongsTo(User::class, 'triggered_by'); }
}
