<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KbCategory extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = ['company_id','name','code','is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }

    public function articles(): HasMany { return $this->hasMany(KbArticle::class, 'category_id'); }
}
