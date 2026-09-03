<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pipeline extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = ['company_id','name','code','is_default','is_active','sort_order'];
    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function stages(): HasMany
    {
        return $this->hasMany(PipelineStage::class)->orderBy('order_index');
    }

    public function deals(): HasMany { return $this->hasMany(Deal::class); }
}
