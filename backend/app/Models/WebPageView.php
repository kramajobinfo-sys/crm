<?php
namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A single page view. Every string here came from a browser and is stored truncated. */
class WebPageView extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = ['company_id','visitor_id','url','path','title','referrer','occurred_at'];
    protected function casts(): array { return ['occurred_at' => 'datetime']; }

    public function visitor(): BelongsTo { return $this->belongsTo(WebVisitor::class, 'visitor_id'); }
}
