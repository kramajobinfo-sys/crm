<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    public const EMPLOYMENT_TYPES = ['full_time', 'part_time', 'contract', 'intern'];
    public const STATUSES = ['active', 'on_leave', 'terminated'];

    protected $fillable = [
        'company_id','employee_no','user_id','first_name','last_name','email','phone',
        'department_id','branch_id','manager_id','job_title','employment_type','status',
        'hire_date','termination_date','date_of_birth','national_id','salary','currency',
        'address','emergency_contact',
    ];
    protected function casts(): array
    {
        return [
            'hire_date' => 'date', 'termination_date' => 'date', 'date_of_birth' => 'date',
            'salary' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo       { return $this->belongsTo(User::class); }
    public function department(): BelongsTo   { return $this->belongsTo(Department::class); }
    public function branch(): BelongsTo       { return $this->belongsTo(Branch::class); }
    public function manager(): BelongsTo      { return $this->belongsTo(self::class, 'manager_id'); }
    public function attendances(): HasMany    { return $this->hasMany(Attendance::class); }
    public function leaveRequests(): HasMany  { return $this->hasMany(LeaveRequest::class); }
    public function leaveBalances(): HasMany  { return $this->hasMany(LeaveBalance::class); }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (!$term) return $q;
        $like = '%'.$term.'%';
        return $q->where(fn ($w) => $w->where('first_name', 'like', $like)
            ->orWhere('last_name', 'like', $like)
            ->orWhere('employee_no', 'like', $like)
            ->orWhere('email', 'like', $like));
    }
}
