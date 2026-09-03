<?php

namespace App\Services;

use App\Events\UserLoggedIn;
use App\Events\UserLoginFailed;
use App\Http\Middleware\RequireTwoFactor;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class AuthService
{
    public function login(string $email, string $password, Request $request): array
    {
        $token = auth('api')->attempt(['email' => $email, 'password' => $password]);
        if (! $token) {
            event(new UserLoginFailed($email, $request->ip(), 'Invalid credentials', $request->userAgent()));
            throw new \RuntimeException('Invalid email or password', 401);
        }
        $user = auth('api')->user();
        if (! $user->is_active) {
            auth('api')->logout();
            event(new UserLoginFailed($email, $request->ip(), 'Account disabled', $request->userAgent()));
            throw new \RuntimeException('Account is disabled', 403);
        }
        // Resolve the user's roles/permissions in their own tenant context for the login payload.
        // attempt() already read getRoleNames() (via the JWT claims) with no team set, caching an
        // empty roles relation — set the team, then drop the stale relations so the resource
        // re-queries them scoped to this tenant.
        if ($user->company_id) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($user->company_id);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }

        // Re-mint carrying an explicit second-factor state. A 2FA-enabled user gets a
        // `pending` token that RequireTwoFactor lets through only to the exempt auth routes
        // until POST /auth/2fa/verify swaps it for a verified one. The token attempt()
        // produced above is discarded — it only served to validate the credentials, and is
        // never returned to the caller.
        $token = auth('api')->claims([
            RequireTwoFactor::CLAIM => $user->two_factor_enabled
                ? RequireTwoFactor::PENDING
                : RequireTwoFactor::VERIFIED,
        ])->login($user);

        event(new UserLoggedIn($user, $request->ip(), $request->userAgent()));

        return [
            'token_type' => 'Bearer', 'access_token' => $token,
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'requires_2fa' => (bool) $user->two_factor_enabled,
            'user' => $user->load(['company.plan.features', 'branch', 'department']),
        ];
    }

    public function refresh(): array
    {
        return ['token_type' => 'Bearer', 'access_token' => auth('api')->refresh(),
            'expires_in' => auth('api')->factory()->getTTL() * 60];
    }

    public function logout(): void
    {
        auth('api')->logout();
    }

    public function me(): User
    {
        return auth('api')->user()->load(['company.plan.features', 'branch', 'department']);
    }

    public function changePassword(User $user, string $newPassword): void
    {
        $user->forceFill(['password' => Hash::make($newPassword)])->save();
    }

    public function sendPasswordResetLink(string $email): string
    {
        return (string) Password::sendResetLink(['email' => $email]);
    }

    public function resetPassword(array $credentials): string
    {
        return (string) Password::reset($credentials, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password)])->save();
        });
    }

    /**
     * Self-service sign-up: create a fresh company (tenant), provision its own default role set,
     * and make the first user the company Owner (full control of their org — never a platform
     * admin, which would reach across tenants). Wrapped in a transaction so a half-created tenant
     * never persists.
     */
    public function register(array $data, Request $request): array
    {
        $user = DB::transaction(function () use ($data) {
            $company = Company::create([
                'name' => $data['company_name'],
                'code' => $this->uniqueCompanyCode($data['company_name']),
                'subdomain' => $this->uniqueSubdomain($data['company_name']),
                'base_currency' => 'USD', 'default_language' => 'en', 'is_active' => true,
            ]);

            $user = User::create([
                'company_id' => $company->id,
                'name' => $data['name'], 'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'language' => 'en', 'is_active' => true, 'email_verified_at' => now(),
            ]);

            // Give the new tenant its own copy of the default roles, its starting edition, then
            // resolve roles in its team context and make this first user the Owner.
            $provisioner = app(TenantProvisioner::class);
            $provisioner->provisionRoles($company);
            $provisioner->provisionPlan($company, TenantProvisioner::DEFAULT_PLAN);
            app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
            $user->assignRole(TenantProvisioner::OWNER_ROLE);

            return $user;
        });

        $token = auth('api')->login($user);
        event(new UserLoggedIn($user, $request->ip(), $request->userAgent()));

        return [
            'token_type' => 'Bearer', 'access_token' => $token,
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'requires_2fa' => false,
            'user' => $user->load(['company.plan.features', 'branch', 'department']),
        ];
    }

    public function updateProfile(User $user, array $data): User
    {
        $user->update($data);

        return $user->load(['company.plan.features', 'branch', 'department']);
    }

    public function updateAvatar(User $user, string $path): User
    {
        $user->forceFill(['avatar_path' => $path])->save();

        return $user->load(['company.plan.features', 'branch', 'department']);
    }

    /** A URL-safe, per-company-unique code derived from the company name. */
    private function uniqueCompanyCode(string $name): string
    {
        $base = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', Str::ascii($name)), 0, 8)) ?: 'CO';
        do {
            $code = $base.strtoupper(Str::random(4));
        } while (Company::where('code', $code)->exists());

        return $code;
    }

    /** A URL-safe, unique subdomain slug derived from the company name (for TenantResolver). */
    private function uniqueSubdomain(string $name): string
    {
        $base = trim(preg_replace('/-+/', '-', preg_replace(
            '/[^a-z0-9]+/', '-', strtolower(Str::ascii($name))
        )), '-');
        $base = substr($base ?: 'tenant', 0, 40);
        $slug = $base;
        $i = 1;
        while (Company::where('subdomain', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
