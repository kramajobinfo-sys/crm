<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Workflow extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    public const ENTITIES      = ['leads', 'deals', 'tickets', 'customers', 'invoices', 'quotations'];
    public const TRIGGER_TYPES = ['manual', 'event', 'schedule'];

    /** entity → event names a workflow may listen for. Fired by the owning service on the real transition. */
    public const EVENTS = [
        'leads'      => ['lead.created', 'lead.status_changed', 'lead.converted'],
        'deals'      => ['deal.created', 'deal.stage_changed', 'deal.won', 'deal.lost'],
        'tickets'    => ['ticket.created', 'ticket.status_changed', 'ticket.escalated'],
        'customers'  => ['customer.created'],
        'invoices'   => ['invoice.created', 'invoice.paid'],
        'quotations' => ['quotation.sent', 'quotation.signed'],
    ];

    protected $fillable = [
        'company_id','name','description','entity','trigger_type','trigger_event','conditions',
        'schedule_cron','is_active','run_count','last_run_at','created_by',
    ];
    protected function casts(): array
    {
        return ['conditions' => 'array', 'is_active' => 'boolean', 'run_count' => 'integer', 'last_run_at' => 'datetime'];
    }

    public function actions(): HasMany { return $this->hasMany(WorkflowAction::class)->orderBy('order'); }
    public function runs(): HasMany    { return $this->hasMany(WorkflowRun::class)->latest(); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
