<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToCompany;

class Setting extends Model
{
    use BelongsToCompany;
    public $timestamps = false;
    protected $fillable = ['company_id','key','value','updated_at'];
    protected function casts(): array { return ['value' => 'array', 'updated_at' => 'datetime']; }
}
