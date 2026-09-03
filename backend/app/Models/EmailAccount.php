<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailAccount extends Model
{
    use HasFactory, BelongsToCompany;

    public const PROVIDERS = ['smtp', 'gmail', 'outlook', 'ses'];

    protected $fillable = [
        'company_id','name','email_address','from_name','provider','user_id',
        'is_shared','is_default','is_active','signature','config','imap_last_uid',
    ];
    protected function casts(): array
    {
        return [
            'is_shared' => 'boolean', 'is_default' => 'boolean', 'is_active' => 'boolean',
            'config' => 'encrypted:array',
        ];
    }

    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
}
