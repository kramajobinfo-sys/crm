<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledJob extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id','workflow_id','name','cron','is_active','next_run_at','last_run_at',
    ];
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'next_run_at' => 'datetime', 'last_run_at' => 'datetime'];
    }

    public function workflow(): BelongsTo { return $this->belongsTo(Workflow::class); }
}
