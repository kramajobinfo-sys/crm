<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesTarget extends Model
{
    use HasFactory, BelongsToCompany;

    public const PERIOD_TYPES = ['month', 'quarter'];

    protected $fillable = ['company_id','user_id','period_type','period_start','target_amount'];
    protected function casts(): array
    {
        return ['period_start' => 'date', 'target_amount' => 'decimal:2'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
