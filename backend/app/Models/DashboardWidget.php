<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardWidget extends Model
{
    protected $fillable = ['user_id','widget_type','position','config','is_visible'];
    protected function casts(): array { return ['config' => 'array', 'is_visible' => 'boolean']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
