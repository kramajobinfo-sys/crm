<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LostReason extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = ['company_id','name','code','sort_order','is_active'];
    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    public function deals(): HasMany { return $this->hasMany(Deal::class); }
}
