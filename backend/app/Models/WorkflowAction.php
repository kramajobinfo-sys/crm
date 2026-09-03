<?php
namespace App\Models;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowAction extends Model
{
    use HasFactory, BelongsToCompany;

    public const TYPES = ['create_task', 'update_field', 'send_email', 'notify', 'webhook', 'log'];

    protected $fillable = ['company_id','workflow_id','order','type','config'];
    protected function casts(): array { return ['order' => 'integer', 'config' => 'array']; }

    public function workflow(): BelongsTo { return $this->belongsTo(Workflow::class); }
}
