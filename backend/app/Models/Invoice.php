<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends SalesDocument
{
    protected $table = 'invoices';

    public const STATUSES = ['draft','issued','partially_paid','paid','void'];
    /** Statuses the dashboard counts as real revenue. */
    public const REVENUE_STATUSES = ['issued','partially_paid','paid'];

    public function __construct(array $attributes = [])
    {
        $this->fillable = array_merge($this->baseFillable, [
            'invoice_no','sales_order_id','created_by','status','issue_date','due_date',
            'amount_paid','balance',
        ]);
        parent::__construct($attributes);
    }
    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'issue_date' => 'date', 'due_date' => 'date',
            'amount_paid' => 'decimal:2', 'balance' => 'decimal:2',
        ]);
    }

    public function items(): HasMany   { return $this->hasMany(InvoiceItem::class)->orderBy('sort_order'); }
    public function order(): BelongsTo { return $this->belongsTo(SalesOrder::class, 'sales_order_id'); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
    /** Credits applied to this invoice — counted as settlement alongside payments. */
    public function creditApplications(): HasMany { return $this->hasMany(CreditApplication::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast() && !in_array($this->status, ['paid','void'], true);
    }

    public function scopeOutstanding(Builder $q): Builder
    {
        return $q->whereIn('status', ['issued','partially_paid']);
    }

    public function documentNumberColumn(): string { return 'invoice_no'; }
}
