<?php
namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'webhook_endpoint_id', 'event_id', 'status', 'payload', 'result', 'error'];
    protected $casts = ['payload' => 'array', 'result' => 'array'];
}
