<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends SalesDocumentItem
{
    protected $table = 'quotation_items';

    public function __construct(array $attributes = [])
    {
        $this->mergeFillable(['quotation_id']);
        parent::__construct($attributes);
    }

    public function quotation(): BelongsTo { return $this->belongsTo(Quotation::class); }
}
