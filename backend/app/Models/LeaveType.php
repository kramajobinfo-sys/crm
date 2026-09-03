<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = ['company_id','name','code','days_per_year','is_paid','color','is_active'];
    protected function casts(): array
    {
        return ['days_per_year' => 'decimal:1', 'is_paid' => 'boolean', 'is_active' => 'boolean'];
    }
}
