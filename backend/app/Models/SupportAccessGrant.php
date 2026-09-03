<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportAccessGrant extends Model
{
    protected $fillable = ['company_id', 'granted_by', 'reason', 'expires_at', 'revoked_at'];
    protected function casts(): array { return ['expires_at' => 'datetime', 'revoked_at' => 'datetime']; }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function grantedBy(): BelongsTo { return $this->belongsTo(User::class, 'granted_by'); }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }
}
