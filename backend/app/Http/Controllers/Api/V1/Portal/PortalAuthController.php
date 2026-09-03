<?php
namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Http\Resources\Portal\PortalContactResource;
use App\Models\Contact;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email', 'password' => 'required|string']);

        $token = auth('portal')->attempt([
            'email' => $request->input('email'),
            'password' => $request->input('password'),
            'portal_enabled' => true,
        ]);
        if (!$token) return $this->error('Invalid credentials', 401);

        /** @var Contact $contact */
        $contact = auth('portal')->user();

        $customer = Customer::withoutGlobalScope('company')
            ->where('id', $contact->customer_id)->where('company_id', $contact->company_id)->first();
        if (!$customer || in_array($customer->status, ['blocked', 'archived'], true)) {
            auth('portal')->logout();
            return $this->error('Account is unavailable', 403);
        }

        $contact->forceFill(['last_login_at' => now()])->saveQuietly();

        return $this->success([
            'token_type' => 'bearer',
            'access_token' => $token,
            'expires_in' => auth('portal')->factory()->getTTL() * 60,
            'contact' => new PortalContactResource($contact),
        ], 'Login successful');
    }

    public function me(): JsonResponse
    {
        return $this->success(new PortalContactResource(auth('portal')->user()));
    }

    public function logout(): JsonResponse
    {
        auth('portal')->logout();
        return $this->success(null, 'Logged out');
    }
}
