<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatMessage extends Model
{
    use HasFactory, BelongsToCompany;

    public const DIRECTIONS = ['inbound','outbound','note'];

    protected $fillable = [
        'company_id','conversation_id','user_id','direction','content_type','body',
        'external_message_id','status','error','meta','sent_at',
    ];
    protected function casts(): array { return ['meta' => 'array', 'sent_at' => 'datetime']; }

    public function conversation(): BelongsTo { return $this->belongsTo(ChatConversation::class, 'conversation_id'); }
    public function sender(): BelongsTo       { return $this->belongsTo(User::class, 'user_id'); }
    public function attachments(): HasMany    { return $this->hasMany(ChatMessageAttachment::class, 'message_id'); }
}
