<?php
namespace App\Http\Controllers\Api\V1\System;

use App\Http\Controllers\Controller;
use App\Services\TenantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Public, unauthenticated branding lookup for the login/register screens. Returns only what's
 * already visible on a public login page (name, logo, accent color) — no user counts, no plan,
 * no id. See TenantResolver for why this must stay read-only/advisory.
 */
class TenantInfoController extends Controller
{
    public function show(Request $request, TenantResolver $resolver): JsonResponse
    {
        $company = $resolver->resolveFromHost($request->getHost());
        if (!$company) {
            return $this->error('No tenant for this host', 404);
        }
        return $this->success([
            'name' => $company->name,
            'logo_url' => $company->logo_path ? Storage::disk('public')->url($company->logo_path) : null,
            'primary_color' => $company->primary_color,
        ]);
    }
}
