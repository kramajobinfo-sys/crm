<?php
namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectMilestone extends Model
{
    use BelongsToCompany;
    public const STATUSES = ['pending', 'in_progress', 'completed'];
    protected $fillable = ['company_id','project_id','name','description','due_date','status','completed_at','sort_order'];
    protected function casts(): array { return ['due_date'=>'date','completed_at'=>'datetime','sort_order'=>'integer']; }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function tasks(): HasMany { return $this->hasMany(ProjectTask::class, 'milestone_id'); }
}
