<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderItem extends SalesDocumentItem
{
    protected $table = 'sales_order_items';

    public function __construct(array $attributes = [])
    {
        $this->mergeFillable(['sales_order_id']);
        parent::__construct($attributes);
    }

    public function order(): BelongsTo { return $this->belongsTo(SalesOrder::class, 'sales_order_id'); }
}
