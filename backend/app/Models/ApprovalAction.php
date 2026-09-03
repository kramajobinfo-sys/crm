<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalAction extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id','approval_request_id','step','approver_id','action','comment','acted_at',
    ];
    protected function casts(): array { return ['step' => 'integer', 'acted_at' => 'datetime']; }

    public function request(): BelongsTo  { return $this->belongsTo(ApprovalRequest::class, 'approval_request_id'); }
    public function approver(): BelongsTo  { return $this->belongsTo(User::class, 'approver_id'); }
}
