<?php
namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One consumption of a customer credit against an invoice. Deliberately NOT a Payment row:
 * `collected_mtd` and the dashboard cash series sum payments.amount, and applying a credit
 * moves no new cash — recording it as a payment would double-count the original overpayment.
 * SalesService::recalcInvoice adds these to the applied payment total instead.
 */
class CreditApplication extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id', 'customer_credit_id', 'invoice_id', 'amount', 'applied_at', 'created_by',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'applied_at' => 'datetime'];
    }

    public function credit(): BelongsTo  { return $this->belongsTo(CustomerCredit::class, 'customer_credit_id'); }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
