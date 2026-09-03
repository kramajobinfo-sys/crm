<?php
namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectTemplate extends Model
{
    use SoftDeletes, BelongsToCompany;
    protected $fillable = ['company_id','name','description','duration_days','default_priority','default_budget','currency','blueprint','is_active','created_by'];
    protected function casts(): array { return ['duration_days'=>'integer','default_budget'=>'decimal:2','blueprint'=>'array','is_active'=>'boolean']; }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
