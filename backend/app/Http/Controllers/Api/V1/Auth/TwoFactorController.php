<?php
namespace App\Http\Controllers\Api\V1\Auth;
use App\Http\Controllers\Controller;
use App\Http\Middleware\RequireTwoFactor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function __construct(private Google2FA $google2fa) {}
    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();
        $secret = $this->google2fa->generateSecretKey();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_enabled' => false])->save();
        $qrUrl = $this->google2fa->getQRCodeUrl(config('app.name'), $user->email, $secret);
        return $this->success(['secret' => $secret, 'qr_url' => $qrUrl, 'issuer' => config('app.name')],
            'Scan the QR code with your authenticator app, then confirm with a code');
    }
    public function confirm(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string|size:6']);
        $user = $request->user();
        if (!$user->two_factor_secret) return $this->error('2FA not initialized', 400);
        if (!$this->google2fa->verifyKey($user->two_factor_secret, $request->input('code'))) return $this->error('Invalid code', 422);
        // two_factor_confirmed_at is what makes enabling 2FA apply to sessions that already
        // exist: RequireTwoFactor refuses any token issued before this moment, even though
        // such a token carries twofa=ok (it was minted while 2FA was off).
        $user->forceFill(['two_factor_enabled' => true, 'two_factor_confirmed_at' => now()])->save();

        // Hand back a verified token: the caller just proved possession of the secret, and
        // their current token predates two_factor_enabled becoming true — without this,
        // switching 2FA on would immediately lock them out of every gated route.
        return $this->success($this->verifiedToken($user), '2FA enabled');
    }

    public function verify(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string|size:6']);
        $user = $request->user();
        if (!$user->two_factor_secret) return $this->error('2FA not initialized', 400);
        if (!$this->google2fa->verifyKey($user->two_factor_secret, $request->input('code'))) return $this->error('Invalid code', 422);

        // Exchange the pending token for a verified one. The old token is NOT upgraded — it
        // keeps twofa=pending forever, so a token captured before verification stays useless.
        return $this->success($this->verifiedToken($user), '2FA verified');
    }

    /** Mint a token carrying the verified second-factor claim. */
    private function verifiedToken($user): array
    {
        $token = auth('api')->claims([
            RequireTwoFactor::CLAIM => RequireTwoFactor::VERIFIED,
        ])->login($user);

        return [
            'token_type'   => 'Bearer',
            'access_token' => $token,
            'expires_in'   => auth('api')->factory()->getTTL() * 60,
        ];
    }
    public function disable(Request $request): JsonResponse
    {
        $request->validate(['password' => 'required|current_password:api']);
        $request->user()->forceFill([
            'two_factor_enabled' => false, 'two_factor_secret' => null, 'two_factor_confirmed_at' => null,
        ])->save();
        return $this->success(null, '2FA disabled');
    }
}
