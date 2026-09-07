<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'name','code','subdomain','legal_name','tax_id','base_currency','logo_path','primary_color',
        'default_language','address_line1','address_line2','city','country','phone','email','website',
        'is_active','plan_id',
    ];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function branches(): HasMany { return $this->hasMany(Branch::class); }
    public function users(): HasMany { return $this->hasMany(User::class); }
    public function departments(): HasMany { return $this->hasMany(Department::class); }
    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
    public function subscriptions(): HasMany { return $this->hasMany(Subscription::class); }

    /** The current (active, in-period) subscription, if any. */
    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('current_period_end')->orWhereDate('current_period_end', '>=', now()->toDateString()))
            ->latest('id')->first();
    }
}
