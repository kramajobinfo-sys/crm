<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Lighter user shape for the admin user list — roles but not the full permission set. */
class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => (bool) $this->is_active,
            'two_factor_enabled' => (bool) $this->two_factor_enabled,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'last_login_human' => $this->last_login_at?->diffForHumans(),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch
                ? ['id' => $this->branch->id, 'name' => $this->branch->name] : null),
            'department' => $this->whenLoaded('department', fn () => $this->department
                ? ['id' => $this->department->id, 'name' => $this->department->name] : null),
            'roles' => $this->getRoleNames(),
        ];
    }
}
