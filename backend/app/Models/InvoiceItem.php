<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends SalesDocumentItem
{
    protected $table = 'invoice_items';

    public function __construct(array $attributes = [])
    {
        $this->mergeFillable(['invoice_id']);
        parent::__construct($attributes);
    }

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
}
