<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\BelongsToCompany;

class Department extends Model
{
    use HasFactory, BelongsToCompany;
    protected $fillable = ['company_id','branch_id','name','code','head_id'];
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function head(): BelongsTo { return $this->belongsTo(User::class, 'head_id'); }
    public function users(): HasMany { return $this->hasMany(User::class); }
}
