<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = ['code', 'name', 'description', 'sort_order', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }

    public function features(): HasMany { return $this->hasMany(PlanFeature::class); }
    public function companies(): HasMany { return $this->hasMany(Company::class); }

    public function hasModule(string $module): bool
    {
        return $this->relationLoaded('features')
            ? $this->features->contains('module', $module)
            : $this->features()->where('module', $module)->exists();
    }
}
