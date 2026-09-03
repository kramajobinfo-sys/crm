<?php
namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Money a customer has paid us that no invoice is entitled to — an overpayment, an invoice
 * reduced after payment, or a staff-issued credit note. Applied to future invoices via
 * credit_applications.
 */
class CustomerCredit extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    public const SOURCES  = ['overpayment', 'invoice_adjustment', 'manual'];
    public const STATUSES = ['open', 'applied', 'void'];

    protected $fillable = [
        'company_id', 'credit_no', 'customer_id', 'source', 'source_invoice_id', 'source_payment_id',
        'currency', 'amount', 'applied_amount', 'status', 'reason', 'created_by', 'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'applied_amount' => 'decimal:2',
            'issued_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo      { return $this->belongsTo(Customer::class); }
    public function sourceInvoice(): BelongsTo { return $this->belongsTo(Invoice::class, 'source_invoice_id'); }
    public function sourcePayment(): BelongsTo { return $this->belongsTo(Payment::class, 'source_payment_id'); }
    public function creator(): BelongsTo       { return $this->belongsTo(User::class, 'created_by'); }
    public function applications(): HasMany    { return $this->hasMany(CreditApplication::class); }

    /** Unspent portion. `applied_amount` is maintained on write, so this is a plain subtraction. */
    public function getRemainingAttribute(): float
    {
        return round((float) $this->amount - (float) $this->applied_amount, 2);
    }

    public function isVoid(): bool { return $this->status === 'void'; }

    /** Usable credit only: open, not voided, with something left. */
    public function scopeAvailable(Builder $q): Builder
    {
        return $q->where('status', 'open')->whereColumn('applied_amount', '<', 'amount');
    }
}
