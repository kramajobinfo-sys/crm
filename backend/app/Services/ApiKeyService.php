<?php
namespace App\Services;

use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use RuntimeException;

class ApiKeyService
{
    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        return ApiKey::with(['user:id,name', 'creator:id,name'])->orderByDesc('id')->paginate($perPage);
    }

    public function find(int $id): ApiKey
    {
        return ApiKey::with(['user:id,name', 'creator:id,name'])->findOrFail($id);
    }

    /**
     * A key inherits the full permission set of the user it acts as (JwtAuthenticate does
     * `setUser($apiKey->user)`), so allowing an arbitrary `user_id` would let anyone holding
     * `api_keys.create` mint a key acting as the Owner and take over the tenant — sidestepping
     * UserManagementController::assignableRoles(), which exists precisely to stop that.
     *
     * The rule: you may only point a key at a user whose permissions you already hold.
     * Company scoping is handled by the FormRequest; this is about privilege, not tenancy.
     */
    private function assertMayActAs(?int $userId): void
    {
        $actor = auth()->user();
        if (!$userId || !$actor || $userId === $actor->id || $actor->isPlatformAdmin()) {
            return;
        }

        $target = User::find($userId); // company-scoped by BelongsToCompany
        if (!$target) {
            throw new RuntimeException('That user does not exist in this company.');
        }

        $extra = $target->getAllPermissions()->pluck('name')
            ->diff($actor->getAllPermissions()->pluck('name'));

        if ($extra->isNotEmpty()) {
            throw new RuntimeException(
                'You cannot create an API key acting as a user with permissions you do not hold ('
                .$extra->sort()->take(3)->implode(', ').($extra->count() > 3 ? ', …' : '').').'
            );
        }
    }

    /** Returns the plaintext key alongside the record — the only time it's ever available. */
    public function create(array $data): array
    {
        $this->assertMayActAs($data['user_id'] ?? null);

        $plain = 'krama_'.bin2hex(random_bytes(24));

        $apiKey = ApiKey::create([
            'user_id' => $data['user_id'] ?? auth()->id(),
            'name' => $data['name'],
            'key_prefix' => substr($plain, 0, 12),
            'key_hash' => hash('sha256', $plain),
            'expires_at' => $data['expires_at'] ?? null,
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        return ['api_key' => $this->find($apiKey->id), 'plain_key' => $plain];
    }

    public function update(ApiKey $apiKey, array $data): ApiKey
    {
        // Same escalation path as create(): re-pointing an existing key is equivalent.
        $this->assertMayActAs($data['user_id'] ?? null);
        $apiKey->update(array_intersect_key($data, array_flip(['name', 'user_id', 'expires_at', 'is_active'])));
        return $this->find($apiKey->id);
    }

    public function delete(ApiKey $apiKey): void
    {
        $apiKey->delete();
    }
}
