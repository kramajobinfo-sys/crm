<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SlaPolicy extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id','name','priority','first_response_minutes','resolution_minutes','is_active',
    ];
    protected function casts(): array
    {
        return [
            'first_response_minutes' => 'integer', 'resolution_minutes' => 'integer', 'is_active' => 'boolean',
        ];
    }
}
