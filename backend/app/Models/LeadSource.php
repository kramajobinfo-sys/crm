<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadSource extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = ['company_id','name','code','is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }

    public function leads(): HasMany { return $this->hasMany(Lead::class, 'source_id'); }
}
