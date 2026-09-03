<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsProvider extends Model
{
    use HasFactory, BelongsToCompany;

    public const PROVIDERS = ['twilio', 'nexmo', 'unifonic', 'generic'];

    protected $fillable = ['company_id','name','provider','sender_id','is_default','is_active'];
    protected function casts(): array { return ['is_default' => 'boolean', 'is_active' => 'boolean']; }
}
