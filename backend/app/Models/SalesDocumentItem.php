<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Shared shape for quotation_items / sales_order_items / invoice_items. Concrete
 * subclasses set only `$table` and the parent relation. product_id is a soft link
 * (no FK-required load); name/unit_price are denormalised so a line survives a
 * product edit or delete.
 */
abstract class SalesDocumentItem extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id','product_id','name','description','quantity','unit_price',
        'discount_pct','tax_rate_id','tax_amount','line_total','sort_order',
        // the parent foreign key is appended per subclass below
    ];
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2', 'unit_price' => 'decimal:2', 'discount_pct' => 'decimal:2',
            'tax_amount' => 'decimal:2', 'line_total' => 'decimal:2', 'sort_order' => 'integer',
        ];
    }

    public function taxRate(): BelongsTo { return $this->belongsTo(TaxRate::class, 'tax_rate_id'); }

    /**
     * Compute net line total (excl. tax) and the tax amount for a line.
     * Returns [line_total, tax_amount]. A tax rate marked inclusive is treated as
     * already contained in unit_price, so it is backed out of the net rather than added.
     */
    public static function computeLine(float $qty, float $unitPrice, float $discountPct, float $taxRate, bool $inclusive): array
    {
        $gross = $qty * $unitPrice * (1 - $discountPct / 100);
        if ($taxRate <= 0) return [round($gross, 2), 0.0];

        if ($inclusive) {
            $net = $gross / (1 + $taxRate / 100);
            return [round($net, 2), round($gross - $net, 2)];
        }
        return [round($gross, 2), round($gross * $taxRate / 100, 2)];
    }
}
