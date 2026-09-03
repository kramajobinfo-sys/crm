<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SavedReport extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    public const CHART_TYPES = ['table', 'bar', 'line', 'pie'];

    protected $fillable = [
        'company_id','name','description','dataset','dimension','measures','filters',
        'chart_type','is_shared','created_by',
    ];
    protected function casts(): array
    {
        return ['measures' => 'array', 'filters' => 'array', 'is_shared' => 'boolean'];
    }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function exports(): HasMany   { return $this->hasMany(ReportExport::class); }
}
