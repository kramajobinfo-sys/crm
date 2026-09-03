<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveRequest extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    public const STATUSES = ['pending', 'approved', 'rejected', 'cancelled'];

    protected $fillable = [
        'company_id','employee_id','leave_type_id','start_date','end_date','days','reason',
        'status','approved_by','approved_at','decision_note',
    ];
    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'days' => 'decimal:1', 'approved_at' => 'datetime'];
    }

    public function employee(): BelongsTo  { return $this->belongsTo(Employee::class); }
    public function leaveType(): BelongsTo  { return $this->belongsTo(LeaveType::class); }
    public function approver(): BelongsTo    { return $this->belongsTo(User::class, 'approved_by'); }
}
