<?php
namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectAutomationRun extends Model
{
    use BelongsToCompany;
    protected $fillable = ['company_id','project_automation_rule_id','project_id','project_task_id','event_key','status','details'];
    protected function casts(): array { return ['details'=>'array']; }
    public function rule(): BelongsTo { return $this->belongsTo(ProjectAutomationRule::class, 'project_automation_rule_id'); }
}
