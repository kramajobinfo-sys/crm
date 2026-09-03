<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = ['company_id','employee_id','leave_type_id','year','entitled','used'];
    protected function casts(): array
    {
        return ['year' => 'integer', 'entitled' => 'decimal:1', 'used' => 'decimal:1'];
    }

    public function employee(): BelongsTo  { return $this->belongsTo(Employee::class); }
    public function leaveType(): BelongsTo  { return $this->belongsTo(LeaveType::class); }

    public function getRemainingAttribute(): float
    {
        return round((float) $this->entitled - (float) $this->used, 1);
    }
}
