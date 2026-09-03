<?php
namespace App\Traits;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::creating(function (Model $model) {
            if (!$model->company_id && auth()->check()) {
                $model->company_id = auth()->user()->company_id;
            }
        });
        static::addGlobalScope('company', function (Builder $query) {
            if (auth()->check() && !auth()->user()->isPlatformAdmin()) {
                $query->where($query->getModel()->getTable().'.company_id', auth()->user()->company_id);
            }
        });
    }
}
