<?php
namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectTask extends Model
{
    use SoftDeletes, BelongsToCompany;
    public const STATUSES = ['backlog', 'todo', 'in_progress', 'review', 'done', 'cancelled'];
    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];
    protected $fillable = ['company_id','project_id','milestone_id','parent_id','assigned_to','created_by','title','description','status','priority','start_date','due_date','estimated_hours','actual_hours','sort_order','completed_at','due_reminder_sent_at','overdue_reminder_sent_at','recurrence_frequency','recurrence_interval','recurrence_end_date','generated_from_id','recurrence_generated_at'];
    protected function casts(): array { return ['start_date'=>'date','due_date'=>'date','completed_at'=>'datetime','due_reminder_sent_at'=>'datetime','overdue_reminder_sent_at'=>'datetime','recurrence_end_date'=>'date','recurrence_generated_at'=>'datetime','recurrence_interval'=>'integer','estimated_hours'=>'decimal:2','actual_hours'=>'decimal:2','sort_order'=>'integer']; }
    public function timeEntries(): HasMany { return $this->hasMany(ProjectTimeEntry::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function milestone(): BelongsTo { return $this->belongsTo(ProjectMilestone::class); }
    public function parent(): BelongsTo { return $this->belongsTo(ProjectTask::class, 'parent_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function generatedFrom(): BelongsTo { return $this->belongsTo(ProjectTask::class, 'generated_from_id'); }
    public function children(): HasMany { return $this->hasMany(ProjectTask::class, 'parent_id'); }
    public function comments(): HasMany { return $this->hasMany(ProjectTaskComment::class)->latest(); }
    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(ProjectTask::class, 'project_task_dependencies', 'task_id', 'depends_on_task_id')
            ->withPivot('company_id')->withTimestamps();
    }
    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(ProjectTask::class, 'project_task_dependencies', 'depends_on_task_id', 'task_id')
            ->withPivot('company_id')->withTimestamps();
    }
    public function attachments(): MorphMany { return $this->morphMany(Attachment::class, 'attachable'); }
    public function timeline(): MorphMany { return $this->morphMany(TimelineActivity::class, 'subject'); }
    public function scopeOpen(Builder $query): Builder { return $query->whereNotIn('status', ['done','cancelled']); }
}
