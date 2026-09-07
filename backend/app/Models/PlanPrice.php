<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanPrice extends Model
{
    public const INTERVALS = ['monthly', 'yearly'];
    public const CURRENCIES = ['USD', 'KHR'];

    protected $fillable = ['plan_id', 'interval', 'currency', 'amount', 'is_active'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
