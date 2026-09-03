<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dashboard extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = ['company_id','name','layout','is_default','created_by'];
    protected function casts(): array { return ['layout' => 'array', 'is_default' => 'boolean']; }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
