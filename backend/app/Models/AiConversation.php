<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiConversation extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $fillable = ['company_id','user_id','title'];

    public function user(): BelongsTo     { return $this->belongsTo(User::class); }
    public function messages(): HasMany   { return $this->hasMany(AiMessage::class)->orderBy('id'); }
}
