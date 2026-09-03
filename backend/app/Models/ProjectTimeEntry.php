<?php
namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectTimeEntry extends Model
{
    use SoftDeletes, BelongsToCompany;
    public const STATUSES=['draft','submitted','approved','rejected'];
    protected $fillable=['company_id','project_id','project_task_id','user_id','work_date','hours','billable','cost_rate','bill_rate','notes','status','approved_by','approved_at'];
    protected function casts(): array { return ['work_date'=>'date','hours'=>'decimal:2','billable'=>'boolean','cost_rate'=>'decimal:2','bill_rate'=>'decimal:2','approved_at'=>'datetime']; }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function task(): BelongsTo { return $this->belongsTo(ProjectTask::class,'project_task_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class,'approved_by'); }
}
