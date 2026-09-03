<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PipelineStage extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id','pipeline_id','name','code','color','order_index','probability','is_won','is_lost',
        'required_fields','allowed_next_stage_ids',
    ];
    protected function casts(): array
    {
        return [
            'order_index' => 'integer', 'probability' => 'integer',
            'is_won' => 'boolean', 'is_lost' => 'boolean',
            'required_fields' => 'array', 'allowed_next_stage_ids' => 'array',
        ];
    }

    public function pipeline(): BelongsTo { return $this->belongsTo(Pipeline::class); }
    public function deals(): HasMany      { return $this->hasMany(Deal::class, 'stage_id'); }

    /** Which materialised deal status a stage implies. */
    public function impliedStatus(): string
    {
        return $this->is_won ? 'won' : ($this->is_lost ? 'lost' : 'open');
    }
}
