<?php
namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WebhookEndpoint extends Model
{
    use HasFactory, BelongsToCompany, SoftDeletes;

    /** Supported inbound handler types. */
    public const TYPES = ['web_to_lead', 'email_status', 'call_log'];

    protected $fillable = [
        'company_id', 'name', 'type', 'slug', 'secret', 'secret_prefix',
        'is_active', 'last_received_at', 'created_by',
    ];

    protected $casts = ['is_active' => 'boolean', 'last_received_at' => 'datetime'];
    protected $hidden = ['secret'];

    public function events(): HasMany { return $this->hasMany(WebhookEvent::class)->latest('id'); }
}
