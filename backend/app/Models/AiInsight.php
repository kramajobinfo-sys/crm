<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiInsight extends Model
{
    use HasFactory, BelongsToCompany;

    public const LEVELS = ['info', 'warning', 'critical'];

    protected $fillable = [
        'company_id','type','level','title','body','meta','is_dismissed','generated_at',
    ];
    protected function casts(): array { return ['meta' => 'array', 'is_dismissed' => 'boolean', 'generated_at' => 'datetime']; }
}
