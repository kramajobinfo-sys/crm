<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Escalation extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id','ticket_id','level','reason','escalated_to','escalated_by','note','escalated_at',
    ];
    protected function casts(): array { return ['level' => 'integer', 'escalated_at' => 'datetime']; }

    public function ticket(): BelongsTo   { return $this->belongsTo(Ticket::class); }
    public function assignee(): BelongsTo   { return $this->belongsTo(User::class, 'escalated_to'); }
    public function escalator(): BelongsTo  { return $this->belongsTo(User::class, 'escalated_by'); }
}
