<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends SalesDocument
{
    protected $table = 'sales_orders';

    public const STATUSES = ['draft','confirmed','processing','fulfilled','cancelled'];

    public function __construct(array $attributes = [])
    {
        $this->fillable = array_merge($this->baseFillable, [
            'order_no','quotation_id','deal_id','status','order_date','expected_date','converted_invoice_id',
        ]);
        parent::__construct($attributes);
    }
    protected function casts(): array
    {
        return array_merge(parent::casts(), ['order_date' => 'date', 'expected_date' => 'date']);
    }

    public function items(): HasMany { return $this->hasMany(SalesOrderItem::class)->orderBy('sort_order'); }
    public function quotation(): BelongsTo { return $this->belongsTo(Quotation::class); }
    public function deal(): BelongsTo { return $this->belongsTo(Deal::class); }
    public function convertedInvoice(): BelongsTo { return $this->belongsTo(Invoice::class, 'converted_invoice_id'); }

    public function documentNumberColumn(): string { return 'order_no'; }
}
