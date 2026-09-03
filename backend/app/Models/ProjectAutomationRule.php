<?php
namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectAutomationRule extends Model
{
    use SoftDeletes, BelongsToCompany;
    public const TRIGGERS = ['task_created','task_completed','task_overdue','project_completed'];
    public const ACTIONS = ['notify_user','create_follow_up','set_priority'];
    protected $fillable = ['company_id','project_id','name','trigger','conditions','action','action_config','is_active','last_run_at','created_by'];
    protected function casts(): array { return ['conditions'=>'array','action_config'=>'array','is_active'=>'boolean','last_run_at'=>'datetime']; }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function runs(): HasMany { return $this->hasMany(ProjectAutomationRun::class); }
}
