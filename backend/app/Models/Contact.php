<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

/**
 * Also the login identity for the customer self-service portal (guard: `portal`).
 * Portal queries never rely on BelongsToCompany's scope — see PortalAuthenticate — because that
 * trait's global scope reads the default (`api`) guard via the auth() facade, which resolves to
 * nothing under a portal-only request and would silently skip filtering rather than throw.
 */
class Contact extends Authenticatable implements JWTSubject
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $fillable = [
        'company_id','customer_id','name','title','department','email','phone','mobile','is_primary','notes',
    ];
    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'is_primary'     => 'boolean',
            'portal_enabled' => 'boolean',
            'password'       => 'hashed',
            'last_login_at'  => 'datetime',
        ];
    }

    public function customer(): BelongsTo  { return $this->belongsTo(Customer::class); }
    public function deals(): BelongsToMany
    {
        return $this->belongsToMany(Deal::class, 'deal_contact')
            ->withPivot(['company_id', 'role', 'is_primary'])
            ->withTimestamps();
    }
    public function addresses(): MorphMany { return $this->morphMany(Address::class, 'addressable'); }

    /** Full consent/opt-out history, newest first. */
    public function consents(): HasMany
    {
        return $this->hasMany(ContactConsent::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }

    /** The most recent consent record for a channel, or null if none logged. */
    public function consentFor(string $channel): ?ContactConsent
    {
        return $this->consents->firstWhere('channel', $channel);
    }

    /** Whether we may currently contact on a channel (opt-in channels default to blocked). */
    public function canReceive(string $channel): bool
    {
        $latest = $this->consentFor($channel);
        if ($latest) return $latest->status === 'granted';
        return !in_array($channel, ContactConsent::OPT_IN_CHANNELS, true);
    }

    public function getJWTIdentifier() { return $this->getKey(); }
    public function getJWTCustomClaims(): array
    {
        return ['company_id' => $this->company_id, 'customer_id' => $this->customer_id];
    }
}
