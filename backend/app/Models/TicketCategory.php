<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketCategory extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = ['company_id','name','code','is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }

    public function tickets(): HasMany { return $this->hasMany(Ticket::class, 'category_id'); }
}
