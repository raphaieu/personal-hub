<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Models\User;
use App\Services\Events\TurnstileVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class AuthController extends Controller
{
    /**
     * TTL do token Sanctum emitido para o front (cookie httpOnly mediado pelo Nitro).
     */
    private const TOKEN_TTL_DAYS = 30;

    public function __construct(
        private readonly TurnstileVerifier $turnstileVerifier,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        if (! $this->turnstileVerifier->verify($request->input('turnstile_token'))) {
            throw ValidationException::withMessages([
                'turnstile' => ['Validação anti-bot falhou. Atualize a página e tente novamente.'],
            ]);
        }

        $user = User::query()->create([
            'name' => $request->string('name')->trim()->value(),
            'email' => strtolower($request->string('email')->trim()->value()),
            'password' => $request->string('password')->value(),
        ]);

        $user->sendEmailVerificationNotification();

        return response()->json([
            'success' => true,
            'message' => 'Conta criada! Enviamos um link de verificação para '.$user->email.'.',
            'data' => [
                'user' => $this->userPayload($user),
                'token' => $this->issueToken($user),
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()
            ->where('email', strtolower($request->string('email')->trim()->value()))
            ->first();

        if ($user === null || ! Hash::check($request->string('password')->value(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $this->userPayload($user),
                'token' => $this->issueToken($user, $request->input('device_name')),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sessão encerrada.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['user' => $this->userPayload($request->user())],
        ]);
    }

    private function issueToken(User $user, ?string $deviceName = null): string
    {
        return $user->createToken(
            $deviceName ?: 'events-app',
            ['events:*'],
            now()->addDays(self::TOKEN_TTL_DAYS),
        )->plainTextToken;
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'emailVerified' => $user->hasVerifiedEmail(),
        ];
    }
}
