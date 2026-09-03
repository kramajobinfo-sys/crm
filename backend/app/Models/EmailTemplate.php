<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id','name','code','category','subject','body_html','is_active',
    ];
    protected function casts(): array { return ['is_active' => 'boolean']; }
}
