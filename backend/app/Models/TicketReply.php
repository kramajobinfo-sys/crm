<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketReply extends Model
{
    use HasFactory, BelongsToCompany;

    public const AUTHOR_TYPES = ['agent', 'customer', 'system'];

    protected $fillable = [
        'company_id','ticket_id','user_id','author_type','is_internal','body',
    ];
    protected function casts(): array { return ['is_internal' => 'boolean']; }

    public function ticket(): BelongsTo { return $this->belongsTo(Ticket::class); }
    public function user(): BelongsTo   { return $this->belongsTo(User::class); }
}
