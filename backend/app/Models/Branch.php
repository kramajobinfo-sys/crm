<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\BelongsToCompany;

class Branch extends Model
{
    use HasFactory, BelongsToCompany;
    protected $fillable = ['company_id','name','code','address','city','country','phone','manager_id','is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function manager(): BelongsTo { return $this->belongsTo(User::class, 'manager_id'); }
    public function departments(): HasMany { return $this->hasMany(Department::class); }
    public function users(): HasMany { return $this->hasMany(User::class); }
}
