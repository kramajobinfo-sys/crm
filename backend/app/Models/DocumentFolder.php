<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentFolder extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = ['company_id','name','created_by'];

    public function documents(): HasMany { return $this->hasMany(Document::class, 'folder_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
