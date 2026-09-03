<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory, BelongsToCompany;

    public const STATUSES = ['present', 'absent', 'late', 'half_day', 'leave', 'holiday', 'weekend'];

    protected $fillable = [
        'company_id','employee_id','date','check_in','check_out','status','hours_worked','notes',
    ];
    protected function casts(): array
    {
        return ['date' => 'date', 'hours_worked' => 'decimal:2'];
    }

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
}
