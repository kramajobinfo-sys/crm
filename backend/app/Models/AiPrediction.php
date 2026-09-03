<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AiPrediction extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id','type','subject_type','subject_id','title','value','generated_at',
    ];
    protected function casts(): array { return ['value' => 'array', 'generated_at' => 'datetime']; }

    public function subject(): MorphTo { return $this->morphTo(); }
}
