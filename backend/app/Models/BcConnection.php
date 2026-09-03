<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BcConnection extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id','name','environment','tenant_id','client_id','client_secret',
        'bc_company_id','bc_company_name','base_url','api_version','is_active',
        'status','last_connected_at','last_error',
    ];
    // client_secret is encrypted at rest AND hidden, so an accidental ->toArray()
    // or a Resource that forgets to whitelist fields cannot leak it.
    protected $hidden = ['client_secret'];
    protected function casts(): array
    {
        return [
            'client_secret' => 'encrypted',
            'is_active' => 'boolean',
            'last_connected_at' => 'datetime',
        ];
    }

    public function mappings(): HasMany { return $this->hasMany(BcEntityMapping::class, 'connection_id'); }
    public function links(): HasMany    { return $this->hasMany(BcRecordLink::class, 'connection_id'); }
    public function runs(): HasMany     { return $this->hasMany(BcSyncRun::class, 'connection_id'); }

    /** Env-sourced credentials win; per-connection DB values are the multi-tenant fallback. */
    public function credentials(): array
    {
        return [
            'tenant_id'     => config('dynamics.tenant_id')     ?: $this->tenant_id,
            'client_id'     => config('dynamics.client_id')     ?: $this->client_id,
            'client_secret' => config('dynamics.client_secret') ?: $this->client_secret,
            'environment'   => $this->environment ?: config('dynamics.environment'),
        ];
    }

    public function isConfigured(): bool
    {
        $c = $this->credentials();
        return filled($c['tenant_id']) && filled($c['client_id']) && filled($c['client_secret']);
    }

    /** Where the credentials actually came from — surfaced in the UI so it is never a guess. */
    public function credentialSource(): string
    {
        if (filled(config('dynamics.client_secret'))) return 'env';
        if (filled($this->client_secret)) return 'database';
        return 'none';
    }
}
