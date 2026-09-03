<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatCannedResponse extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = ['company_id','shortcut','title','body','is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
}
