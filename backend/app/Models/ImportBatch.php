<?php
namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'user_id', 'entity', 'filename', 'path', 'status', 'dedupe', 'mapping',
        'total_rows', 'created_rows', 'updated_rows', 'skipped_rows', 'error_rows', 'error',
    ];

    protected function casts(): array
    {
        return ['mapping' => 'array'];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class, 'batch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
