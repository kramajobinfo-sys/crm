<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalRequest extends Model
{
    use HasFactory, BelongsToCompany;

    public const STATUSES = ['pending', 'approved', 'rejected'];

    protected $fillable = [
        'company_id','workflow_id','approvable_type','approvable_id','status','current_step','requested_by',
    ];
    protected function casts(): array { return ['current_step' => 'integer']; }

    public function workflow(): BelongsTo  { return $this->belongsTo(ApprovalWorkflow::class); }
    public function approvable(): MorphTo   { return $this->morphTo(); }
    public function requester(): BelongsTo  { return $this->belongsTo(User::class, 'requested_by'); }
    public function actions(): HasMany      { return $this->hasMany(ApprovalAction::class)->orderBy('step'); }

    /** The user id whose turn it is, or null once resolved / past the last step. */
    public function currentApproverId(): ?int
    {
        $ids = $this->workflow?->approver_ids ?? [];
        return $ids[$this->current_step] ?? null;
    }
}
