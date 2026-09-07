<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends SalesDocument
{
    protected $table = 'quotations';

    public const STATUSES = ['draft','pending_approval','sent','accepted','rejected','expired','converted'];

    public function __construct(array $attributes = [])
    {
        $this->fillable = array_merge($this->baseFillable, [
            'quote_no','deal_id','status','issue_date','valid_until','converted_order_id',
            'signed_at','signed_name','signed_ip','signature_data',
        ]);
        parent::__construct($attributes);
    }
    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'issue_date' => 'date', 'valid_until' => 'date', 'signed_at' => 'datetime',
        ]);
    }

    public function items(): HasMany { return $this->hasMany(QuotationItem::class)->orderBy('sort_order'); }
    public function deal(): BelongsTo { return $this->belongsTo(Deal::class); }
    public function convertedOrder(): BelongsTo { return $this->belongsTo(SalesOrder::class, 'converted_order_id'); }

    public function documentNumberColumn(): string { return 'quote_no'; }
}
