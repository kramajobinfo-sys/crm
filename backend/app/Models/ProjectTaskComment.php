<?php
namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTaskComment extends Model
{
    use BelongsToCompany;
    protected $fillable=['company_id','project_task_id','user_id','body'];
    public function task(): BelongsTo { return $this->belongsTo(ProjectTask::class,'project_task_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
