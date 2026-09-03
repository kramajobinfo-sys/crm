<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApiKey extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $fillable = [
        'company_id','user_id','name','key_prefix','key_hash','last_used_at',
        'expires_at','is_active','created_by',
    ];
    protected $hidden = ['key_hash'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime', 'expires_at' => 'datetime', 'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
