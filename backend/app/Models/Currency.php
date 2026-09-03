<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Currency extends Model
{
    protected $fillable = ['code','name','symbol','exchange_rate','is_base','last_updated_at'];
    protected function casts(): array {
        return ['exchange_rate' => 'decimal:8', 'is_base' => 'boolean', 'last_updated_at' => 'datetime'];
    }
}
