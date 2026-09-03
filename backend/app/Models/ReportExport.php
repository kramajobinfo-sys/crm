<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportExport extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id','saved_report_id','format','status','disk','path','row_count','requested_by','generated_at',
    ];
    protected function casts(): array { return ['row_count' => 'integer', 'generated_at' => 'datetime']; }

    public function report(): BelongsTo { return $this->belongsTo(SavedReport::class, 'saved_report_id'); }

    /**
     * Deliberately NO url accessor. Exports live on the private `local` disk, which has no
     * public URL, and the previous accessor returned a world-readable public-disk link —
     * the vulnerability this replaced. Access goes through
     * ReportController::downloadExport, which authorises via the company-scoped model.
     */
}
