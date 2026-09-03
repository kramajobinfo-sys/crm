<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    public const METHODS = ['cash','card','bank_transfer','cheque','online'];

    protected $fillable = [
        'company_id','payment_no','invoice_id','customer_id','method','amount','applied_amount','currency',
        'received_at','reference','notes','created_by',
    ];
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'applied_amount' => 'decimal:2', 'received_at' => 'datetime'];
    }

    public function invoice(): BelongsTo  { return $this->belongsTo(Invoice::class); }
    public function customer(): BelongsTo  { return $this->belongsTo(Customer::class); }
    public function creator(): BelongsTo   { return $this->belongsTo(User::class, 'created_by'); }

    /**
     * The credit this payment's excess created, if it overpaid its invoice. Lets
     * SalesService::deletePayment reverse the credit alongside the cash rather than leaving
     * a credit with no money behind it.
     */
    public function credit(): HasOne { return $this->hasOne(CustomerCredit::class, 'source_payment_id'); }

    /** Cash received that no invoice took — 0 unless this payment overpaid. */
    public function getUnappliedAmountAttribute(): float
    {
        return round((float) $this->amount - (float) $this->applied_amount, 2);
    }
}
