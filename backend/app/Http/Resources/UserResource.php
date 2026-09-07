<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'email' => $this->email, 'phone' => $this->phone,
            'avatar_url' => $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null,
            'language' => $this->language, 'timezone' => $this->timezone,
            'is_active' => (bool) $this->is_active, 'is_platform_admin' => (bool) $this->is_platform_admin,
            'two_factor_enabled' => (bool) $this->two_factor_enabled,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company->id, 'name' => $this->company->name, 'code' => $this->company->code,
                'logo_url' => $this->company->logo_path ? Storage::disk('public')->url($this->company->logo_path) : null,
                'primary_color' => $this->company->primary_color, 'base_currency' => $this->company->base_currency,
                'appearance' => $this->company->appearance,
                'plan' => $this->company->plan ? [
                    'code' => $this->company->plan->code,
                    'name' => $this->company->plan->name,
                    'modules' => $this->company->plan->features->pluck('module')->values(),
                ] : null,
            ]),
            'branch' => $this->whenLoaded('branch', fn () => ['id' => $this->branch->id, 'name' => $this->branch->name, 'code' => $this->branch->code]),
            'department' => $this->whenLoaded('department', fn () => ['id' => $this->department->id, 'name' => $this->department->name]),
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
        ];
    }
}
