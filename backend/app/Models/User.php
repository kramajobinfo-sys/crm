<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, SoftDeletes, HasRoles;
    protected $guard_name = 'api';

    protected $fillable = [
        'company_id','branch_id','department_id','name','email','password','phone','avatar_path',
        'language','timezone','is_active','is_platform_admin','two_factor_enabled','two_factor_secret','last_login_at',
    ];
    protected $hidden = ['password','remember_token','two_factor_secret'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime', 'password' => 'hashed',
            'is_active' => 'boolean', 'is_platform_admin' => 'boolean', 'two_factor_enabled' => 'boolean',
            'two_factor_secret' => 'encrypted', 'last_login_at' => 'datetime',
            // Cast is required: RequireTwoFactor compares it to the token's `iat`.
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }

    public function getJWTIdentifier() { return $this->getKey(); }
    public function getJWTCustomClaims(): array
    {
        return ['company_id' => $this->company_id, 'roles' => $this->getRoleNames()->toArray()];
    }
    /**
     * Krama staff who operate the platform: they bypass the per-company scope and, later, reach
     * the provider console. This is the master/tenant boundary — a tenant's own top role never
     * grants this. The scope-bypass rides on this flag, no longer on a role name.
     */
    public function isPlatformAdmin(): bool { return (bool) $this->is_platform_admin; }

    /** @deprecated Kept as an alias while call sites migrate to isPlatformAdmin(). */
    public function isSuperAdmin(): bool { return $this->isPlatformAdmin(); }
}
