<?php
namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class ImportRow extends Model
{
    use BelongsToCompany;

    protected $fillable = ['batch_id', 'company_id', 'row_number', 'data', 'errors'];

    protected function casts(): array
    {
        return ['data' => 'array', 'errors' => 'array'];
    }
}
