<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalWorkflow extends Model
{
    use HasFactory, BelongsToCompany;

    public const DOCUMENT_TYPES = ['purchase_request', 'purchase_order', 'quotation'];

    protected $fillable = [
        'company_id','name','document_type','min_amount','approver_ids','is_active',
    ];
    protected function casts(): array
    {
        return ['min_amount' => 'decimal:2', 'approver_ids' => 'array', 'is_active' => 'boolean'];
    }

    public function steps(): int { return count($this->approver_ids ?? []); }
}
