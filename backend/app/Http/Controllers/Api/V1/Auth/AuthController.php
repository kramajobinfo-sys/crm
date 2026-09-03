<?php
namespace App\Http\Controllers\Api\V1\Auth;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private AuthService $authService) {}

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login($request->input('email'), $request->input('password'), $request);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 401);
        }
        return $this->success([
            'token_type' => $result['token_type'], 'access_token' => $result['access_token'],
            'expires_in' => $result['expires_in'], 'requires_2fa' => $result['requires_2fa'],
            'user' => new UserResource($result['user']),
        ], 'Login successful');
    }
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated(), $request);
        return $this->success([
            'token_type' => $result['token_type'], 'access_token' => $result['access_token'],
            'expires_in' => $result['expires_in'], 'requires_2fa' => $result['requires_2fa'],
            'user' => new UserResource($result['user']),
        ], 'Account created', 201);
    }
    public function refresh(): JsonResponse { return $this->success($this->authService->refresh(), 'Token refreshed'); }
    public function logout(): JsonResponse { $this->authService->logout(); return $this->success(null, 'Logged out'); }
    public function me(): JsonResponse { return $this->success(new UserResource($this->authService->me())); }
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->authService->updateProfile($request->user(), $request->validated());
        return $this->success(new UserResource($user), 'Profile updated');
    }
    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate(['avatar' => 'required|file|image|max:4096|mimes:jpg,jpeg,png,webp']);
        $path = $request->file('avatar')->store('avatars/'.date('Y/m'), 'public');
        $user = $this->authService->updateAvatar($request->user(), $path);
        return $this->success(new UserResource($user), 'Avatar updated');
    }
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->authService->changePassword($request->user(), $request->input('new_password'));
        return $this->success(null, 'Password updated');
    }
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);
        $status = $this->authService->sendPasswordResetLink($request->input('email'));
        return $this->success(['status' => $status], 'If that email exists, a reset link has been sent.');
    }
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate(['token' => 'required', 'email' => 'required|email', 'password' => 'required|confirmed|min:8']);
        $status = $this->authService->resetPassword($request->only('email','password','password_confirmation','token'));
        return $this->success(['status' => $status], 'Password reset');
    }
}
