<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class EmailAttachment extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = ['company_id','email_id','disk','path','name','mime','size'];
    protected function casts(): array { return ['size' => 'integer']; }

    public function email(): BelongsTo { return $this->belongsTo(Email::class); }

    public function getUrlAttribute(): ?string
    {
        return $this->path
            ? URL::temporarySignedRoute('attachments.show', now()->addHour(), ['type' => 'email', 'id' => $this->id], false)
            : null;
    }
}
