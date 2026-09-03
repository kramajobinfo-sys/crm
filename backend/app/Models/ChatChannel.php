<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatChannel extends Model
{
    use HasFactory, BelongsToCompany;

    public const TYPES = ['whatsapp','messenger','instagram','telegram','tiktok','sms','webchat'];

    protected $fillable = ['company_id','type','name','external_account_id','config','is_active'];
    protected function casts(): array { return ['config' => 'encrypted:array', 'is_active' => 'boolean']; }

    public function conversations(): HasMany { return $this->hasMany(ChatConversation::class, 'channel_id'); }
    public function contacts(): HasMany { return $this->hasMany(ChatContact::class, 'channel_id'); }

    /**
     * Meta channels only accept free-form replies inside a 24h service window.
     * TikTok is deliberately excluded: its messaging constraints differ from Meta's and
     * are unverified here, so it defaults to open rather than enforcing a rule we guessed.
     */
    public function enforcesServiceWindow(): bool
    {
        return in_array($this->type, ['whatsapp','messenger','instagram'], true);
    }
}
