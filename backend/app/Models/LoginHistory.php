<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginHistory extends Model
{
    public $timestamps = false;
    protected $table = 'login_history';
    protected $fillable = ['user_id','ip_address','user_agent','location_country',
        'location_city','status','failure_reason','created_at'];
    protected function casts(): array { return ['created_at' => 'datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
