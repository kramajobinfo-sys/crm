<?php
namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMember extends Model
{
    use BelongsToCompany;
    public const ROLES = ['owner', 'manager', 'member', 'viewer'];
    protected $fillable = ['company_id','project_id','user_id','role','allocation_percent','cost_rate','bill_rate'];
    protected function casts(): array { return ['allocation_percent'=>'integer','cost_rate'=>'decimal:2','bill_rate'=>'decimal:2']; }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
